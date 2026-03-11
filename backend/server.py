from fastapi import FastAPI, APIRouter, HTTPException, Depends, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
from pathlib import Path
from pydantic import BaseModel, Field, ConfigDict, EmailStr
from typing import List, Optional
import uuid
from datetime import datetime, timezone, timedelta
from passlib.context import CryptContext
from jose import JWTError, jwt
from emergentintegrations.llm.chat import LlmChat, UserMessage

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

# MongoDB connection
mongo_url = os.environ['MONGO_URL']
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ['DB_NAME']]

# Security
SECRET_KEY = os.environ.get('SECRET_KEY', 'ai-helper-secret-key-change-in-production')
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_HOURS = 24 * 7  # 7 days

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
security = HTTPBearer()

# Create the main app
app = FastAPI(title="AI Helper API")

# Create a router with the /api prefix
api_router = APIRouter(prefix="/api")

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# ============== MODELS ==============

class UserCreate(BaseModel):
    email: EmailStr
    password: str
    name: str

class UserLogin(BaseModel):
    email: EmailStr
    password: str

class UserResponse(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str
    email: str
    name: str
    created_at: str
    disclosure_accepted: bool = False
    is_subscribed: bool = False

class TokenResponse(BaseModel):
    access_token: str
    token_type: str = "bearer"
    user: UserResponse

class DisclosureAccept(BaseModel):
    accepted: bool

class ChatMessage(BaseModel):
    role: str  # "user" or "assistant"
    content: str
    timestamp: str

class ChatRequest(BaseModel):
    message: str
    session_id: Optional[str] = None

class ChatResponse(BaseModel):
    response: str
    session_id: str

class ContactCreate(BaseModel):
    name: str
    phone: str
    email: Optional[str] = None
    notes: Optional[str] = None

class ContactResponse(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str
    user_id: str
    name: str
    phone: str
    email: Optional[str] = None
    notes: Optional[str] = None
    created_at: str

class MemoryItem(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str
    user_id: str
    key: str
    value: str
    category: str
    created_at: str
    updated_at: str

# ============== AUTH HELPERS ==============

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)

def get_password_hash(password: str) -> str:
    return pwd_context.hash(password)

def create_access_token(data: dict) -> str:
    to_encode = data.copy()
    expire = datetime.now(timezone.utc) + timedelta(hours=ACCESS_TOKEN_EXPIRE_HOURS)
    to_encode.update({"exp": expire})
    return jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)

async def get_current_user(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict:
    token = credentials.credentials
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        user_id: str = payload.get("sub")
        if user_id is None:
            raise HTTPException(status_code=401, detail="Invalid token")
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
    
    user = await db.users.find_one({"id": user_id}, {"_id": 0})
    if user is None:
        raise HTTPException(status_code=401, detail="User not found")
    return user

# ============== AUTH ROUTES ==============

@api_router.post("/auth/register", response_model=TokenResponse)
async def register(user_data: UserCreate):
    # Check if user exists
    existing = await db.users.find_one({"email": user_data.email})
    if existing:
        raise HTTPException(status_code=400, detail="Email already registered")
    
    # Create user
    user_id = str(uuid.uuid4())
    now = datetime.now(timezone.utc).isoformat()
    
    user_doc = {
        "id": user_id,
        "email": user_data.email,
        "name": user_data.name,
        "password_hash": get_password_hash(user_data.password),
        "created_at": now,
        "disclosure_accepted": False,
        "disclosure_accepted_at": None,
        "is_subscribed": False,
        "subscription_tier": None,
        "subscription_started_at": None
    }
    
    await db.users.insert_one(user_doc)
    
    # Create token
    access_token = create_access_token({"sub": user_id})
    
    user_response = UserResponse(
        id=user_id,
        email=user_data.email,
        name=user_data.name,
        created_at=now,
        disclosure_accepted=False,
        is_subscribed=False
    )
    
    return TokenResponse(access_token=access_token, user=user_response)

@api_router.post("/auth/login", response_model=TokenResponse)
async def login(credentials: UserLogin):
    user = await db.users.find_one({"email": credentials.email}, {"_id": 0})
    if not user:
        raise HTTPException(status_code=401, detail="Invalid email or password")
    
    if not verify_password(credentials.password, user["password_hash"]):
        raise HTTPException(status_code=401, detail="Invalid email or password")
    
    access_token = create_access_token({"sub": user["id"]})
    
    user_response = UserResponse(
        id=user["id"],
        email=user["email"],
        name=user["name"],
        created_at=user["created_at"],
        disclosure_accepted=user.get("disclosure_accepted", False),
        is_subscribed=user.get("is_subscribed", False)
    )
    
    return TokenResponse(access_token=access_token, user=user_response)

@api_router.get("/auth/me", response_model=UserResponse)
async def get_me(current_user: dict = Depends(get_current_user)):
    return UserResponse(
        id=current_user["id"],
        email=current_user["email"],
        name=current_user["name"],
        created_at=current_user["created_at"],
        disclosure_accepted=current_user.get("disclosure_accepted", False),
        is_subscribed=current_user.get("is_subscribed", False)
    )

# ============== DISCLOSURE ROUTES ==============

@api_router.post("/disclosure/accept")
async def accept_disclosure(data: DisclosureAccept, current_user: dict = Depends(get_current_user)):
    if not data.accepted:
        raise HTTPException(status_code=400, detail="You must accept the disclosure to continue")
    
    now = datetime.now(timezone.utc).isoformat()
    await db.users.update_one(
        {"id": current_user["id"]},
        {"$set": {"disclosure_accepted": True, "disclosure_accepted_at": now}}
    )
    
    return {"message": "Disclosure accepted", "accepted_at": now}

@api_router.get("/disclosure/status")
async def get_disclosure_status(current_user: dict = Depends(get_current_user)):
    return {
        "accepted": current_user.get("disclosure_accepted", False),
        "accepted_at": current_user.get("disclosure_accepted_at")
    }

# ============== CHAT ROUTES ==============

# Store active chat sessions in memory (for multi-turn conversations)
chat_sessions: dict = {}

@api_router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest, current_user: dict = Depends(get_current_user)):
    # Check disclosure
    if not current_user.get("disclosure_accepted", False):
        raise HTTPException(status_code=403, detail="You must accept the disclosure before using the AI Helper")
    
    user_id = current_user["id"]
    session_id = request.session_id or str(uuid.uuid4())
    session_key = f"{user_id}_{session_id}"
    
    # Get or create chat instance
    if session_key not in chat_sessions:
        # Load user's memory for context
        memories = await db.memories.find({"user_id": user_id}, {"_id": 0}).to_list(100)
        memory_context = ""
        if memories:
            memory_context = "\n\nUser's stored information:\n"
            for mem in memories:
                memory_context += f"- {mem['key']}: {mem['value']}\n"
        
        # Load user's contacts
        contacts = await db.contacts.find({"user_id": user_id}, {"_id": 0}).to_list(100)
        contacts_context = ""
        if contacts:
            contacts_context = "\n\nUser's contacts:\n"
            for contact in contacts:
                contacts_context += f"- {contact['name']}: {contact['phone']}"
                if contact.get('email'):
                    contacts_context += f" ({contact['email']})"
                contacts_context += "\n"
        
        system_message = f"""You are AI Helper, a highly capable personal assistant with persistent memory. You help the user with anything they need - coding, planning, problem-solving, managing their business, and more.

You remember everything the user tells you. If they share personal information, preferences, or important details, acknowledge that you'll remember it.

You are technical and capable - you can help with coding in any language, building applications, debugging, and solving complex problems.

You are also connected to the user's phone system and can help them:
- Send text messages to their contacts
- Make phone calls
- Set reminders and appointments

When the user asks you to text or call someone, confirm you'll do it and ask for any missing details (like the message content or phone number if not in contacts).

Be direct, helpful, and efficient. Don't be overly formal or use unnecessary pleasantries.
{memory_context}{contacts_context}
User's name: {current_user['name']}"""

        chat_sessions[session_key] = LlmChat(
            api_key=os.environ.get('EMERGENT_LLM_KEY'),
            session_id=session_key,
            system_message=system_message
        ).with_model("openai", "gpt-5.2")
    
    chat_instance = chat_sessions[session_key]
    
    # Send message and get response
    try:
        user_message = UserMessage(text=request.message)
        response_text = await chat_instance.send_message(user_message)
        
        # Save conversation to database
        now = datetime.now(timezone.utc).isoformat()
        conversation_doc = {
            "id": str(uuid.uuid4()),
            "user_id": user_id,
            "session_id": session_id,
            "user_message": request.message,
            "assistant_response": response_text,
            "timestamp": now
        }
        await db.conversations.insert_one(conversation_doc)
        
        # Check if user shared something to remember
        message_lower = request.message.lower()
        if any(phrase in message_lower for phrase in ["remember", "my name is", "i am", "i'm", "my email", "my phone", "my address", "i like", "i prefer", "i work"]):
            # Extract and store memory (simplified - in production, use NLP)
            memory_doc = {
                "id": str(uuid.uuid4()),
                "user_id": user_id,
                "key": "user_shared_info",
                "value": request.message,
                "category": "conversation",
                "created_at": now,
                "updated_at": now
            }
            await db.memories.insert_one(memory_doc)
        
        return ChatResponse(response=response_text, session_id=session_id)
        
    except Exception as e:
        logger.error(f"Chat error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"AI Helper error: {str(e)}")

@api_router.get("/chat/history")
async def get_chat_history(session_id: Optional[str] = None, current_user: dict = Depends(get_current_user)):
    query = {"user_id": current_user["id"]}
    if session_id:
        query["session_id"] = session_id
    
    conversations = await db.conversations.find(query, {"_id": 0}).sort("timestamp", -1).to_list(50)
    return {"conversations": conversations}

@api_router.get("/chat/sessions")
async def get_chat_sessions(current_user: dict = Depends(get_current_user)):
    pipeline = [
        {"$match": {"user_id": current_user["id"]}},
        {"$group": {
            "_id": "$session_id",
            "last_message": {"$last": "$timestamp"},
            "message_count": {"$sum": 1},
            "first_message": {"$first": "$user_message"}
        }},
        {"$sort": {"last_message": -1}},
        {"$limit": 20}
    ]
    sessions = await db.conversations.aggregate(pipeline).to_list(20)
    return {"sessions": sessions}

# ============== MEMORY ROUTES ==============

@api_router.get("/memory", response_model=List[MemoryItem])
async def get_memories(current_user: dict = Depends(get_current_user)):
    memories = await db.memories.find({"user_id": current_user["id"]}, {"_id": 0}).to_list(100)
    return memories

@api_router.post("/memory", response_model=MemoryItem)
async def add_memory(key: str, value: str, category: str = "general", current_user: dict = Depends(get_current_user)):
    now = datetime.now(timezone.utc).isoformat()
    memory_doc = {
        "id": str(uuid.uuid4()),
        "user_id": current_user["id"],
        "key": key,
        "value": value,
        "category": category,
        "created_at": now,
        "updated_at": now
    }
    await db.memories.insert_one(memory_doc)
    return MemoryItem(**memory_doc)

@api_router.delete("/memory/{memory_id}")
async def delete_memory(memory_id: str, current_user: dict = Depends(get_current_user)):
    result = await db.memories.delete_one({"id": memory_id, "user_id": current_user["id"]})
    if result.deleted_count == 0:
        raise HTTPException(status_code=404, detail="Memory not found")
    return {"message": "Memory deleted"}

# ============== CONTACTS ROUTES ==============

@api_router.get("/contacts", response_model=List[ContactResponse])
async def get_contacts(current_user: dict = Depends(get_current_user)):
    contacts = await db.contacts.find({"user_id": current_user["id"]}, {"_id": 0}).to_list(100)
    return contacts

@api_router.post("/contacts", response_model=ContactResponse)
async def add_contact(contact: ContactCreate, current_user: dict = Depends(get_current_user)):
    now = datetime.now(timezone.utc).isoformat()
    contact_doc = {
        "id": str(uuid.uuid4()),
        "user_id": current_user["id"],
        "name": contact.name,
        "phone": contact.phone,
        "email": contact.email,
        "notes": contact.notes,
        "created_at": now
    }
    await db.contacts.insert_one(contact_doc)
    return ContactResponse(**contact_doc)

@api_router.delete("/contacts/{contact_id}")
async def delete_contact(contact_id: str, current_user: dict = Depends(get_current_user)):
    result = await db.contacts.delete_one({"id": contact_id, "user_id": current_user["id"]})
    if result.deleted_count == 0:
        raise HTTPException(status_code=404, detail="Contact not found")
    return {"message": "Contact deleted"}

# ============== HEALTH CHECK ==============

@api_router.get("/health")
async def health_check():
    return {"status": "healthy", "service": "AI Helper API"}

# Include the router in the main app
app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=os.environ.get('CORS_ORIGINS', '*').split(','),
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
