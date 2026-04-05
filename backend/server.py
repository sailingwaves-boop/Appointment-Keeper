from fastapi import FastAPI, APIRouter, HTTPException, Depends, status, Request, Response, UploadFile, File
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from fastapi.responses import PlainTextResponse
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
from pathlib import Path
from pydantic import BaseModel, Field, ConfigDict, EmailStr
from typing import List, Optional, Dict
import uuid
from datetime import datetime, timezone, timedelta
from passlib.context import CryptContext
from jose import JWTError, jwt
import httpx
from twilio.rest import Client as TwilioClient
from twilio.twiml.voice_response import VoiceResponse, Gather
from elevenlabs import ElevenLabs
import base64
import asyncio
import stripe
import tempfile
import anthropic
from openai import OpenAI

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

# Stripe Configuration
STRIPE_MODE = os.environ.get('STRIPE_MODE', 'live')
STRIPE_API_KEY = os.environ.get('STRIPE_API_KEY', '')

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
app = FastAPI(title="Chronicle API")

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
    trial_ends_at: Optional[str] = None
    trial_active: bool = False
    card_on_file: bool = False

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
    image_url: Optional[str] = None
    app_builder_mode: Optional[bool] = False

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

# ============== SUBSCRIPTION PLANS ==============

SUBSCRIPTION_PLANS = {
    "starter_monthly": {
        "id": "starter_monthly",
        "name": "Starter",
        "price": 14.00,
        "currency": "gbp",
        "interval": "month",
        "call_minutes": 0,
        "texts": 0,
        "features": ["Unlimited AI chat", "Persistent memory", "Contact management"]
    },
    "starter_annual": {
        "id": "starter_annual",
        "name": "Starter (Annual)",
        "price": 140.00,
        "currency": "gbp",
        "interval": "year",
        "call_minutes": 0,
        "texts": 0,
        "features": ["Unlimited AI chat", "Persistent memory", "Contact management", "2 months free"]
    },
    "pro_monthly": {
        "id": "pro_monthly",
        "name": "Pro",
        "price": 39.00,
        "currency": "gbp",
        "interval": "month",
        "call_minutes": 60,
        "texts": 100,
        "features": ["Everything in Starter", "60 call minutes/month", "100 texts/month", "Priority support"]
    },
    "pro_annual": {
        "id": "pro_annual",
        "name": "Pro (Annual)",
        "price": 390.00,
        "currency": "gbp",
        "interval": "year",
        "call_minutes": 60,
        "texts": 100,
        "features": ["Everything in Starter", "60 call minutes/month", "100 texts/month", "Priority support", "2 months free"]
    },
    "business_monthly": {
        "id": "business_monthly",
        "name": "Business",
        "price": 69.00,
        "currency": "gbp",
        "interval": "month",
        "call_minutes": 180,
        "texts": 300,
        "features": ["Everything in Pro", "180 call minutes/month", "300 texts/month", "API access", "Dedicated support"]
    },
    "business_annual": {
        "id": "business_annual",
        "name": "Business (Annual)",
        "price": 690.00,
        "currency": "gbp",
        "interval": "year",
        "call_minutes": 180,
        "texts": 300,
        "features": ["Everything in Pro", "180 call minutes/month", "300 texts/month", "API access", "Dedicated support", "2 months free"]
    }
}

# ============== VOICE ADD-ON PACKS ==============

VOICE_PACKS = {
    "voice_light": {
        "id": "voice_light",
        "name": "Voice Light",
        "price": 15.00,
        "currency": "gbp",
        "minutes": 30,
        "description": "30 AI voice call minutes"
    },
    "voice_medium": {
        "id": "voice_medium",
        "name": "Voice Medium",
        "price": 49.00,
        "currency": "gbp",
        "minutes": 120,
        "description": "120 AI voice call minutes"
    },
    "voice_heavy": {
        "id": "voice_heavy",
        "name": "Voice Heavy",
        "price": 99.00,
        "currency": "gbp",
        "minutes": 300,
        "description": "300 AI voice call minutes"
    }
}

class CheckoutRequest(BaseModel):
    plan_id: str
    origin_url: str

class TrialSetupRequest(BaseModel):
    plan_id: str
    origin_url: str

class SubscriptionStatusResponse(BaseModel):
    is_subscribed: bool
    plan: Optional[str] = None
    plan_name: Optional[str] = None
    interval: Optional[str] = None
    call_minutes_remaining: int = 0
    texts_remaining: int = 0
    subscription_started_at: Optional[str] = None
    subscription_ends_at: Optional[str] = None
    trial_ends_at: Optional[str] = None
    card_on_file: bool = False

class GoogleSessionRequest(BaseModel):
    session_id: str

# ============== SMS & CALL MODELS ==============

class SendSMSRequest(BaseModel):
    to_phone: str
    message: str
    contact_name: Optional[str] = None

class MakeCallRequest(BaseModel):
    to_phone: str
    message: str
    contact_name: Optional[str] = None
    voice_id: Optional[str] = "EXAVITQu4vr4xnSDxMaL"  # Default ElevenLabs voice

class SMSResponse(BaseModel):
    success: bool
    message_sid: Optional[str] = None
    error: Optional[str] = None

class CallResponse(BaseModel):
    success: bool
    call_sid: Optional[str] = None
    error: Optional[str] = None

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

def is_trial_active(user: dict) -> bool:
    """Check if user's trial is still active"""
    if user.get("is_subscribed"):
        return False  # Subscribed users don't need trial
    
    trial_ends_at = user.get("trial_ends_at")
    if not trial_ends_at:
        return False
    
    if isinstance(trial_ends_at, str):
        trial_ends_at = datetime.fromisoformat(trial_ends_at.replace('Z', '+00:00'))
    if trial_ends_at.tzinfo is None:
        trial_ends_at = trial_ends_at.replace(tzinfo=timezone.utc)
    
    return datetime.now(timezone.utc) < trial_ends_at

def get_trial_ends_at(user: dict) -> Optional[str]:
    """Get formatted trial end date"""
    return user.get("trial_ends_at")

async def get_current_user(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict:
    token = credentials.credentials
    
    # First try JWT token (email/password login)
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        user_id: str = payload.get("sub")
        if user_id:
            user = await db.users.find_one({"id": user_id}, {"_id": 0})
            if user:
                return user
    except JWTError:
        pass
    
    # Then try session token (Google OAuth)
    session = await db.user_sessions.find_one({"session_token": token}, {"_id": 0})
    if session:
        # Check if session is expired
        expires_at = session.get("expires_at")
        if isinstance(expires_at, str):
            expires_at = datetime.fromisoformat(expires_at.replace('Z', '+00:00'))
        if expires_at.tzinfo is None:
            expires_at = expires_at.replace(tzinfo=timezone.utc)
        
        if expires_at < datetime.now(timezone.utc):
            raise HTTPException(status_code=401, detail="Session expired")
        
        user = await db.users.find_one({"id": session["user_id"]}, {"_id": 0})
        if user:
            return user
    
    raise HTTPException(status_code=401, detail="Invalid token")

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
        "subscription_started_at": None,
        "trial_started_at": now,
        "trial_ends_at": (datetime.now(timezone.utc) + timedelta(days=10)).isoformat(),
        "trial_used": False,
        "card_on_file": False
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
        is_subscribed=False,
        trial_ends_at=user_doc["trial_ends_at"],
        trial_active=True,
        card_on_file=False
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
        is_subscribed=user.get("is_subscribed", False),
        trial_ends_at=get_trial_ends_at(user),
        trial_active=is_trial_active(user),
        card_on_file=user.get("card_on_file", False)
    )
    
    return TokenResponse(access_token=access_token, user=user_response)

class ForgotPasswordRequest(BaseModel):
    email: str

@api_router.post("/auth/forgot-password")
async def forgot_password(request: ForgotPasswordRequest):
    """Send password reset link"""
    user = await db.users.find_one({"email": request.email})
    if not user:
        # Don't reveal if email exists or not for security
        return {"message": "If that email exists, a reset link has been sent"}
    
    # Generate reset token
    reset_token = str(uuid.uuid4())
    expires = datetime.now(timezone.utc) + timedelta(hours=1)
    
    # Store reset token
    await db.password_resets.insert_one({
        "user_id": user["id"],
        "token": reset_token,
        "expires_at": expires.isoformat(),
        "used": False
    })
    
    # In production, send email here
    # For now, just log it
    logger.info(f"Password reset requested for {request.email}")
    
    return {"message": "If that email exists, a reset link has been sent"}

@api_router.get("/auth/me", response_model=UserResponse)
async def get_me(current_user: dict = Depends(get_current_user)):
    return UserResponse(
        id=current_user["id"],
        email=current_user["email"],
        name=current_user["name"],
        created_at=current_user["created_at"],
        disclosure_accepted=current_user.get("disclosure_accepted", False),
        is_subscribed=current_user.get("is_subscribed", False),
        trial_ends_at=get_trial_ends_at(current_user),
        trial_active=is_trial_active(current_user),
        card_on_file=current_user.get("card_on_file", False)
    )

# ============== GOOGLE OAUTH ROUTES ==============

# Google OAuth Configuration - from environment variables
# Google OAuth Configuration
GOOGLE_CLIENT_ID = '336164855084-jse1r3a4o1t45kv7c4813h2hhqn6b2mk.apps.googleusercontent.com'
GOOGLE_CLIENT_SECRET = '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'

class GoogleTokenRequest(BaseModel):
    code: str
    redirect_uri: str

@api_router.post("/auth/google/token")
async def google_token_exchange(request: GoogleTokenRequest):
    """Exchange Google authorization code for user data and create session"""
    
    # Exchange authorization code for tokens
    async with httpx.AsyncClient() as client:
        try:
            # Exchange code for tokens
            token_response = await client.post(
                "https://oauth2.googleapis.com/token",
                data={
                    "code": request.code,
                    "client_id": GOOGLE_CLIENT_ID,
                    "client_secret": GOOGLE_CLIENT_SECRET,
                    "redirect_uri": request.redirect_uri,
                    "grant_type": "authorization_code"
                }
            )
            
            if token_response.status_code != 200:
                logger.error(f"Google token error: {token_response.text}")
                raise HTTPException(status_code=401, detail="Failed to exchange authorization code")
            
            tokens = token_response.json()
            access_token = tokens.get("access_token")
            
            # Get user info from Google
            userinfo_response = await client.get(
                "https://www.googleapis.com/oauth2/v2/userinfo",
                headers={"Authorization": f"Bearer {access_token}"}
            )
            
            if userinfo_response.status_code != 200:
                raise HTTPException(status_code=401, detail="Failed to get user info from Google")
            
            data = userinfo_response.json()
            
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Google auth error: {str(e)}")
            raise HTTPException(status_code=500, detail="Failed to verify Google authorization")
    
    # Check if user exists
    email = data.get("email")
    if not email:
        raise HTTPException(status_code=400, detail="No email provided by Google")
    
    existing_user = await db.users.find_one({"email": email}, {"_id": 0})
    
    now = datetime.now(timezone.utc).isoformat()
    
    if existing_user:
        # Update existing user
        user_id = existing_user["id"]
        await db.users.update_one(
            {"id": user_id},
            {"$set": {
                "name": data.get("name", existing_user.get("name")),
                "picture": data.get("picture"),
                "last_login": now
            }}
        )
    else:
        # Create new user with 10-day trial
        user_id = str(uuid.uuid4())
        trial_ends = (datetime.now(timezone.utc) + timedelta(days=10)).isoformat()
        user_doc = {
            "id": user_id,
            "email": email,
            "name": data.get("name", ""),
            "picture": data.get("picture"),
            "password_hash": None,  # No password for Google users
            "created_at": now,
            "disclosure_accepted": False,
            "disclosure_accepted_at": None,
            "is_subscribed": False,
            "auth_provider": "google",
            "trial_started_at": now,
            "trial_ends_at": trial_ends,
            "trial_used": False,
            "card_on_file": False
        }
        await db.users.insert_one(user_doc)
    
    # Create session token
    session_token = str(uuid.uuid4())
    expires_at = (datetime.now(timezone.utc) + timedelta(days=7)).isoformat()
    
    session_doc = {
        "user_id": user_id,
        "session_token": session_token,
        "expires_at": expires_at,
        "created_at": now
    }
    
    # Remove old sessions for this user
    await db.user_sessions.delete_many({"user_id": user_id})
    await db.user_sessions.insert_one(session_doc)
    
    # Get updated user
    user = await db.users.find_one({"id": user_id}, {"_id": 0})
    
    return {
        "session_token": session_token,
        "user": UserResponse(
            id=user["id"],
            email=user["email"],
            name=user["name"],
            created_at=user["created_at"],
            disclosure_accepted=user.get("disclosure_accepted", False),
            is_subscribed=user.get("is_subscribed", False),
            trial_ends_at=get_trial_ends_at(user),
            trial_active=is_trial_active(user),
            card_on_file=user.get("card_on_file", False)
        )
    }

# Keep old endpoint for backwards compatibility but mark as deprecated
@api_router.post("/auth/google/session")
async def google_session(request: GoogleSessionRequest):
    """DEPRECATED - Use /auth/google/token instead"""
    raise HTTPException(status_code=410, detail="This endpoint is deprecated. Please update your app.")

@api_router.post("/auth/logout")
async def logout(current_user: dict = Depends(get_current_user)):
    """Logout user and clear session"""
    await db.user_sessions.delete_many({"user_id": current_user["id"]})
    return {"message": "Logged out successfully"}

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
        raise HTTPException(status_code=403, detail="You must accept the disclosure before using Chronicle")
    
    # Skip subscription check for admin
    is_admin = current_user["email"] == ADMIN_EMAIL
    partner = await db.admin_partners.find_one({"email": current_user["email"]})
    
    if not is_admin and not partner:
        # Check if user has active subscription or trial
        if not current_user.get("is_subscribed", False) and not is_trial_active(current_user):
            raise HTTPException(
                status_code=403, 
                detail="Your free trial has ended. Please subscribe to continue using Chronicle. Your memories and data are preserved."
            )
    
    user_id = current_user["id"]
    session_id = request.session_id or str(uuid.uuid4())
    
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
    
    # Load user's custom rules
    user_settings = await db.user_settings.find_one({"user_id": user_id}, {"_id": 0})
    rules_context = ""
    if user_settings and user_settings.get("rules"):
        rules_context = "\n\nUser's rules for you:\n"
        for rule in user_settings["rules"]:
            rules_context += f"- {rule}\n"
    
    system_message = f"""You are Chronicle, a personal assistant. You are Claude Sonnet 4.6.

IMPORTANT RULES:
- NEVER give unprompted commands, system commands, or tell the user to "run" anything unless they specifically ask for technical help
- NEVER suggest taking screenshots or running terminal commands unless the user asks
- Just have a normal conversation - be helpful, friendly, and conversational
- If you don't know something, say so - don't make up commands or instructions

You can help with:
- Answering questions
- Having conversations
- Helping with planning and ideas
- Coding help ONLY when asked
- Managing contacts and phone features ONLY when asked

You have persistent memory - you remember what the user tells you across conversations.
{rules_context}{memory_context}{contacts_context}
User's name: {current_user['name']}"""

    # If App Builder Mode is enabled, switch to coding-focused context
    if request.app_builder_mode:
        system_message = f"""You are Chronicle in APP BUILDER MODE. You are Claude Sonnet 4.6, an expert software engineer.

In this mode, you are focused on helping the user build apps, plugins, scripts, and automation tools.

YOUR CAPABILITIES:
- Write complete, working code in any language
- Build web apps, mobile apps, browser extensions, Discord bots, Slack apps, etc.
- Create automation scripts and integrations
- Debug and fix code issues
- Explain technical concepts clearly
- Suggest architecture and best practices

CODING GUIDELINES:
- Write clean, production-ready code
- Include comments for complex logic
- Provide complete files, not snippets (unless asked otherwise)
- Use modern best practices
- Consider error handling and edge cases
- If the user's request is vague, ask clarifying questions

You still have access to the user's memory and context:
{rules_context}{memory_context}
User's name: {current_user['name']}

You are now in coding mode. Help the user build whatever they need."""

    # Get conversation history for this session
    history = await db.conversations.find(
        {"user_id": user_id, "session_id": session_id},
        {"_id": 0}
    ).sort("timestamp", 1).limit(20).to_list(20)
    
    # Build messages array for Anthropic
    messages = []
    for h in history:
        messages.append({"role": "user", "content": h["user_message"]})
        messages.append({"role": "assistant", "content": h["assistant_response"]})
    
    # Add current message with optional image
    if request.image_url:
        logger.info(f"Processing image_url: {request.image_url}")
        try:
            file_id = request.image_url.split('/')[-1]
            file_doc = await db.uploads.find_one({"id": file_id}, {"_id": 0})
            if file_doc and file_doc.get("data"):
                logger.info(f"Found image, sending to Claude")
                messages.append({
                    "role": "user",
                    "content": [
                        {
                            "type": "image",
                            "source": {
                                "type": "base64",
                                "media_type": file_doc.get("content_type", "image/jpeg"),
                                "data": file_doc["data"]
                            }
                        },
                        {
                            "type": "text",
                            "text": request.message
                        }
                    ]
                })
            else:
                messages.append({"role": "user", "content": request.message})
        except Exception as img_err:
            logger.error(f"Image fetch error: {img_err}")
            messages.append({"role": "user", "content": request.message})
    else:
        logger.info("No image_url provided")
        messages.append({"role": "user", "content": request.message})
    
    # Call Anthropic API directly with user's key
    try:
        client = anthropic.Anthropic(api_key=os.environ.get('ANTHROPIC_API_KEY'))
        
        response = client.messages.create(
            model="claude-sonnet-4-6",
            max_tokens=8192,
            system=system_message,
            messages=messages
        )
        
        response_text = response.content[0].text
        
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
        
    except anthropic.APIError as e:
        logger.error(f"Anthropic API error: {str(e)}")
        raise HTTPException(status_code=500, detail="Chronicle is having trouble connecting. Please try again.")
    except Exception as e:
        logger.error(f"Chat error: {str(e)}")
        raise HTTPException(status_code=500, detail="Chronicle is having trouble right now. Please try again.")

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

# ============== VOICE INPUT (WHISPER) ==============

@api_router.post("/voice/transcribe")
async def transcribe_voice(
    audio: UploadFile = File(...),
    current_user: dict = Depends(get_current_user)
):
    """Transcribe voice audio to text using Whisper (Direct OpenAI)"""
    try:
        # Read audio file
        audio_content = await audio.read()
        
        # Save to temp file
        with tempfile.NamedTemporaryFile(suffix=".webm", delete=False) as temp_file:
            temp_file.write(audio_content)
            temp_path = temp_file.name
        
        # Initialize OpenAI directly
        openai_client = OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))
        
        # Transcribe
        with open(temp_path, "rb") as audio_file:
            response = openai_client.audio.transcriptions.create(
                model="whisper-1",
                file=audio_file,
                response_format="json",
                language="en"
            )
        
        # Clean up temp file
        os.unlink(temp_path)
        
        return {"text": response.text}
    except Exception as e:
        logger.error(f"Transcription error: {e}")
        raise HTTPException(status_code=500, detail="Failed to transcribe audio")

# ============== FILE UPLOAD ==============

@api_router.post("/upload")
async def upload_file(
    file: UploadFile = File(...),
    current_user: dict = Depends(get_current_user)
):
    """Upload a file (image, document) for chat"""
    try:
        # Read file content
        content = await file.read()
        
        # Generate unique filename
        file_id = str(uuid.uuid4())
        ext = file.filename.split('.')[-1] if '.' in file.filename else 'bin'
        filename = f"{file_id}.{ext}"
        
        # Store file metadata in database
        now = datetime.now(timezone.utc).isoformat()
        file_doc = {
            "id": file_id,
            "user_id": current_user["id"],
            "filename": filename,
            "original_name": file.filename,
            "content_type": file.content_type,
            "size": len(content),
            "data": base64.b64encode(content).decode(),
            "created_at": now
        }
        await db.uploads.insert_one(file_doc)
        
        return {"url": f"/api/files/{file_id}", "file_id": file_id}
    except Exception as e:
        logger.error(f"Upload error: {e}")
        raise HTTPException(status_code=500, detail="Failed to upload file")

@api_router.get("/files/{file_id}")
async def get_file(file_id: str):
    """Retrieve uploaded file"""
    file_doc = await db.uploads.find_one({"id": file_id}, {"_id": 0})
    if not file_doc:
        raise HTTPException(status_code=404, detail="File not found")
    
    content = base64.b64decode(file_doc["data"])
    return Response(
        content=content,
        media_type=file_doc["content_type"],
        headers={"Content-Disposition": f"inline; filename={file_doc['original_name']}"}
    )

# ============== ADMIN - MAGIC LINKS ==============

ADMIN_EMAIL = "sailingwaves@gmail.com"  # Your admin email

async def require_admin(current_user: dict = Depends(get_current_user)):
    """Check if user is admin or partner"""
    if current_user["email"] == ADMIN_EMAIL:
        return current_user
    # Check if user is a partner
    partner = await db.admin_partners.find_one({"email": current_user["email"]})
    if partner:
        return current_user
    raise HTTPException(status_code=403, detail="Admin access required")

@api_router.post("/admin/magic-link")
async def create_magic_link(admin: dict = Depends(require_admin)):
    """Generate a one-time magic link for free account"""
    link_id = str(uuid.uuid4())
    now = datetime.now(timezone.utc).isoformat()
    
    magic_link = {
        "id": link_id,
        "created_at": now,
        "created_by": admin["id"],
        "used": False,
        "used_by": None,
        "used_at": None,
        "revoked": False
    }
    
    await db.magic_links.insert_one(magic_link)
    
    return {
        "link_id": link_id,
        "url": f"https://chroniclehelper.com/signup?invite={link_id}"
    }

@api_router.get("/admin/magic-links")
async def get_magic_links(admin: dict = Depends(require_admin)):
    """Get all magic links"""
    links = await db.magic_links.find({}, {"_id": 0}).sort("created_at", -1).to_list(100)
    
    # Get user info for used links
    for link in links:
        if link.get("used_by"):
            user = await db.users.find_one({"id": link["used_by"]}, {"_id": 0, "email": 1, "name": 1})
            if user:
                link["user_info"] = user
    
    return {"links": links}

@api_router.post("/admin/revoke-access/{user_id}")
async def revoke_user_access(user_id: str, admin: dict = Depends(require_admin)):
    """Revoke a user's free access"""
    result = await db.users.update_one(
        {"id": user_id},
        {"$set": {"access_revoked": True, "revoked_at": datetime.now(timezone.utc).isoformat()}}
    )
    if result.modified_count == 0:
        raise HTTPException(status_code=404, detail="User not found")
    return {"message": "Access revoked"}

@api_router.post("/admin/restore-access/{user_id}")
async def restore_user_access(user_id: str, admin: dict = Depends(require_admin)):
    """Restore a user's access"""
    result = await db.users.update_one(
        {"id": user_id},
        {"$set": {"access_revoked": False, "revoked_at": None}}
    )
    if result.modified_count == 0:
        raise HTTPException(status_code=404, detail="User not found")
    return {"message": "Access restored"}

# ============== OWNER ADMIN PANEL ==============

class AdminPartner(BaseModel):
    email: str

class UserCreditsUpdate(BaseModel):
    credits: int

class UserRulesUpdate(BaseModel):
    rules: List[str]

class CreditWarningThreshold(BaseModel):
    threshold: int

@api_router.get("/admin/dashboard")
async def admin_dashboard(admin: dict = Depends(require_admin)):
    """Get admin dashboard stats"""
    total_users = await db.users.count_documents({})
    active_users = await db.users.count_documents({"access_revoked": {"$ne": True}})
    total_conversations = await db.conversations.count_documents({})
    
    # Get recent users
    recent_users = await db.users.find(
        {}, 
        {"_id": 0, "id": 1, "email": 1, "name": 1, "created_at": 1, "credits": 1, "access_revoked": 1}
    ).sort("created_at", -1).limit(20).to_list(20)
    
    return {
        "total_users": total_users,
        "active_users": active_users,
        "total_conversations": total_conversations,
        "recent_users": recent_users
    }

@api_router.get("/admin/users")
async def admin_get_all_users(admin: dict = Depends(require_admin), search: Optional[str] = None):
    """Get all users with optional search"""
    query = {}
    if search:
        query = {"$or": [
            {"name": {"$regex": search, "$options": "i"}},
            {"email": {"$regex": search, "$options": "i"}}
        ]}
    
    users = await db.users.find(
        query,
        {"_id": 0, "id": 1, "email": 1, "name": 1, "created_at": 1, "credits": 1, 
         "access_revoked": 1, "is_subscribed": 1, "plan": 1, "rules": 1}
    ).sort("created_at", -1).to_list(500)
    
    return {"users": users}

@api_router.get("/admin/user/{user_id}")
async def admin_get_user(user_id: str, admin: dict = Depends(require_admin)):
    """Get single user details"""
    user = await db.users.find_one({"id": user_id}, {"_id": 0})
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    # Get user stats
    conversations = await db.conversations.count_documents({"user_id": user_id})
    contacts = await db.contacts.count_documents({"user_id": user_id})
    memories = await db.memories.count_documents({"user_id": user_id})
    
    return {
        "user": user,
        "stats": {
            "conversations": conversations,
            "contacts": contacts,
            "memories": memories
        }
    }

@api_router.post("/admin/user/{user_id}/credits")
async def admin_update_credits(user_id: str, data: UserCreditsUpdate, admin: dict = Depends(require_admin)):
    """Add or remove credits from user"""
    result = await db.users.update_one(
        {"id": user_id},
        {"$inc": {"credits": data.credits}}
    )
    if result.modified_count == 0:
        raise HTTPException(status_code=404, detail="User not found")
    return {"message": f"Credits updated by {data.credits}"}

@api_router.delete("/admin/user/{user_id}")
async def admin_delete_user(user_id: str, admin: dict = Depends(require_admin)):
    """Delete a user completely"""
    # Delete user data
    await db.contacts.delete_many({"user_id": user_id})
    await db.memories.delete_many({"user_id": user_id})
    await db.conversations.delete_many({"user_id": user_id})
    await db.uploads.delete_many({"user_id": user_id})
    result = await db.users.delete_one({"id": user_id})
    
    if result.deleted_count == 0:
        raise HTTPException(status_code=404, detail="User not found")
    return {"message": "User deleted"}

@api_router.post("/admin/partner")
async def admin_add_partner(data: AdminPartner, admin: dict = Depends(require_admin)):
    """Add a partner with admin access"""
    await db.admin_partners.update_one(
        {"email": data.email},
        {"$set": {"email": data.email, "added_by": admin["id"], "added_at": datetime.now(timezone.utc).isoformat()}},
        upsert=True
    )
    return {"message": f"Partner {data.email} added"}

@api_router.delete("/admin/partner/{email}")
async def admin_remove_partner(email: str, admin: dict = Depends(require_admin)):
    """Remove a partner"""
    await db.admin_partners.delete_one({"email": email})
    return {"message": "Partner removed"}

@api_router.get("/admin/partners")
async def admin_get_partners(admin: dict = Depends(require_admin)):
    """Get all admin partners"""
    partners = await db.admin_partners.find({}, {"_id": 0}).to_list(100)
    return {"partners": partners}

@api_router.post("/admin/settings/credit-warning")
async def admin_set_credit_warning(data: CreditWarningThreshold, admin: dict = Depends(require_admin)):
    """Set global credit warning threshold"""
    await db.settings.update_one(
        {"key": "credit_warning_threshold"},
        {"$set": {"key": "credit_warning_threshold", "value": data.threshold}},
        upsert=True
    )
    return {"message": f"Credit warning threshold set to {data.threshold}"}

# ============== USER SETTINGS/ADMIN PANEL ==============

class UserSettings(BaseModel):
    rules: Optional[List[str]] = None
    custom_sms_templates: Optional[List[str]] = None
    timezone: Optional[str] = None
    language: Optional[str] = None
    notification_email: Optional[bool] = None
    notification_sms: Optional[bool] = None
    custom_greeting: Optional[str] = None

class HomeAssistantConfig(BaseModel):
    url: str
    access_token: str

@api_router.get("/user/settings")
async def get_user_settings(current_user: dict = Depends(get_current_user)):
    """Get user settings"""
    settings = await db.user_settings.find_one({"user_id": current_user["id"]}, {"_id": 0})
    user = await db.users.find_one({"id": current_user["id"]}, {"_id": 0, "credits": 1, "plan": 1, "is_subscribed": 1})
    
    return {
        "settings": settings or {},
        "credits": user.get("credits", 0),
        "plan": user.get("plan"),
        "is_subscribed": user.get("is_subscribed", False)
    }

@api_router.post("/user/settings")
async def update_user_settings(settings: UserSettings, current_user: dict = Depends(get_current_user)):
    """Update user settings"""
    update_data = {k: v for k, v in settings.model_dump().items() if v is not None}
    update_data["user_id"] = current_user["id"]
    update_data["updated_at"] = datetime.now(timezone.utc).isoformat()
    
    await db.user_settings.update_one(
        {"user_id": current_user["id"]},
        {"$set": update_data},
        upsert=True
    )
    return {"message": "Settings updated"}

@api_router.post("/user/settings/rules")
async def update_user_rules(data: UserRulesUpdate, current_user: dict = Depends(get_current_user)):
    """Update user AI rules"""
    await db.user_settings.update_one(
        {"user_id": current_user["id"]},
        {"$set": {"rules": data.rules, "updated_at": datetime.now(timezone.utc).isoformat()}},
        upsert=True
    )
    return {"message": "Rules updated"}

@api_router.post("/user/settings/home-assistant")
async def connect_home_assistant(config: HomeAssistantConfig, current_user: dict = Depends(get_current_user)):
    """Connect Home Assistant for smart home control"""
    # Test connection first
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get(
                f"{config.url}/api/",
                headers={"Authorization": f"Bearer {config.access_token}"},
                timeout=10
            )
            if response.status_code != 200:
                raise HTTPException(status_code=400, detail="Could not connect to Home Assistant")
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Connection failed: {str(e)}")
    
    # Save config
    await db.user_settings.update_one(
        {"user_id": current_user["id"]},
        {"$set": {
            "home_assistant_url": config.url,
            "home_assistant_token": config.access_token,
            "home_assistant_connected": True,
            "updated_at": datetime.now(timezone.utc).isoformat()
        }},
        upsert=True
    )
    return {"message": "Home Assistant connected"}

@api_router.get("/user/settings/home-assistant/devices")
async def get_home_assistant_devices(current_user: dict = Depends(get_current_user)):
    """Get available Home Assistant devices"""
    settings = await db.user_settings.find_one({"user_id": current_user["id"]}, {"_id": 0})
    if not settings or not settings.get("home_assistant_connected"):
        raise HTTPException(status_code=400, detail="Home Assistant not connected")
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.get(
                f"{settings['home_assistant_url']}/api/states",
                headers={"Authorization": f"Bearer {settings['home_assistant_token']}"},
                timeout=10
            )
            devices = response.json()
            # Filter to useful devices
            filtered = [d for d in devices if d["entity_id"].startswith(("light.", "switch.", "media_player.", "climate."))]
            return {"devices": filtered}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to get devices: {str(e)}")

@api_router.post("/user/credits/topup")
async def request_credit_topup(current_user: dict = Depends(get_current_user)):
    """Request credit top-up (redirects to payment)"""
    # This would integrate with Stripe for credit purchases
    return {"message": "Redirect to payment", "redirect": "/subscription"}

@api_router.get("/user/data/export")
async def export_user_data(current_user: dict = Depends(get_current_user)):
    """Export all user data"""
    contacts = await db.contacts.find({"user_id": current_user["id"]}, {"_id": 0}).to_list(1000)
    memories = await db.memories.find({"user_id": current_user["id"]}, {"_id": 0}).to_list(1000)
    conversations = await db.conversations.find({"user_id": current_user["id"]}, {"_id": 0}).to_list(1000)
    settings = await db.user_settings.find_one({"user_id": current_user["id"]}, {"_id": 0})
    
    return {
        "contacts": contacts,
        "memories": memories,
        "conversations": conversations,
        "settings": settings
    }

@api_router.delete("/user/account")
async def delete_user_account(current_user: dict = Depends(get_current_user)):
    """Delete user account and all data"""
    user_id = current_user["id"]
    await db.contacts.delete_many({"user_id": user_id})
    await db.memories.delete_many({"user_id": user_id})
    await db.conversations.delete_many({"user_id": user_id})
    await db.uploads.delete_many({"user_id": user_id})
    await db.user_settings.delete_many({"user_id": user_id})
    await db.users.delete_one({"id": user_id})
    
    return {"message": "Account deleted"}

@api_router.delete("/user/chats")
async def delete_user_chats(current_user: dict = Depends(get_current_user)):
    """Delete all user's chat history"""
    await db.conversations.delete_many({"user_id": current_user["id"]})
    return {"message": "Chat history deleted"}

@api_router.delete("/user/memories")
async def delete_user_memories(current_user: dict = Depends(get_current_user)):
    """Delete all user's memories"""
    await db.memories.delete_many({"user_id": current_user["id"]})
    return {"message": "Memories deleted"}

# ============== PHONE & SMS ROUTES ==============

class SendSMSRequest(BaseModel):
    to: str
    message: str
    use_native: Optional[bool] = False

class PlaceCallRequest(BaseModel):
    to: str
    message: Optional[str] = None
    voice_id: Optional[str] = None
    use_native: Optional[bool] = False

@api_router.post("/sms/send")
async def send_sms(data: SendSMSRequest, current_user: dict = Depends(get_current_user)):
    """Send SMS via Twilio or return native SMS link"""
    is_admin = current_user["email"] == ADMIN_EMAIL
    partner = await db.admin_partners.find_one({"email": current_user["email"]})
    
    if data.use_native and (is_admin or partner):
        # Return deep link for native SMS
        encoded_message = data.message.replace(' ', '%20')
        return {
            "native": True,
            "link": f"sms:{data.to}?body={encoded_message}"
        }
    
    # Send via Twilio
    try:
        twilio_client = TwilioClient(
            os.environ.get('TWILIO_ACCOUNT_SID'),
            os.environ.get('TWILIO_AUTH_TOKEN')
        )
        message = twilio_client.messages.create(
            body=data.message,
            from_=os.environ.get('TWILIO_PHONE_NUMBER'),
            to=data.to
        )
        return {"success": True, "sid": message.sid}
    except Exception as e:
        logger.error(f"SMS error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to send SMS")

@api_router.post("/call/place")
async def place_call(data: PlaceCallRequest, current_user: dict = Depends(get_current_user)):
    """Place a call via Twilio with ElevenLabs voice or return native call link"""
    is_admin = current_user["email"] == ADMIN_EMAIL
    partner = await db.admin_partners.find_one({"email": current_user["email"]})
    
    if data.use_native and (is_admin or partner):
        # Return deep link for native phone call
        return {
            "native": True,
            "link": f"tel:{data.to}"
        }
    
    # Place call via Twilio
    try:
        twilio_client = TwilioClient(
            os.environ.get('TWILIO_ACCOUNT_SID'),
            os.environ.get('TWILIO_AUTH_TOKEN')
        )
        
        # If message provided, use TwiML to speak it
        if data.message:
            twiml = f'<Response><Say voice="alice">{data.message}</Say></Response>'
            call = twilio_client.calls.create(
                twiml=twiml,
                from_=os.environ.get('TWILIO_PHONE_NUMBER'),
                to=data.to
            )
        else:
            # Just connect the call
            call = twilio_client.calls.create(
                url="http://demo.twilio.com/docs/voice.xml",
                from_=os.environ.get('TWILIO_PHONE_NUMBER'),
                to=data.to
            )
        return {"success": True, "sid": call.sid}
    except Exception as e:
        logger.error(f"Call error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to place call")

@api_router.get("/voices")
async def get_available_voices(current_user: dict = Depends(get_current_user)):
    """Get available ElevenLabs voices"""
    try:
        eleven_client = ElevenLabs(api_key=os.environ.get('ELEVENLABS_API_KEY'))
        voices = eleven_client.voices.get_all()
        voice_list = [{"id": v.voice_id, "name": v.name} for v in voices.voices]
        return {"voices": voice_list}
    except Exception as e:
        logger.error(f"Voices error: {str(e)}")
        return {"voices": []}

# ============== ELEVENLABS TTS & VOICE CLONING ==============

# Default ElevenLabs voices available for all users
DEFAULT_VOICES = [
    {"id": "21m00Tcm4TlvDq8ikWAM", "name": "Rachel", "description": "Calm, friendly female voice"},
    {"id": "AZnzlk1XvdvUeBnXmlld", "name": "Domi", "description": "Strong, confident female voice"},
    {"id": "EXAVITQu4vr4xnSDxMaL", "name": "Sarah", "description": "Soft, warm female voice"},
    {"id": "ErXwobaYiN019PkySvjV", "name": "Antoni", "description": "Well-rounded male voice"},
]

@api_router.get("/voices/available")
async def get_available_voices(current_user: dict = Depends(get_current_user)):
    """Get available voices (presets + user's cloned voice if exists)"""
    voices = DEFAULT_VOICES.copy()
    
    # Check if user has a cloned voice
    user_voice = await db.user_voices.find_one({"user_id": current_user["id"]}, {"_id": 0})
    if user_voice:
        voices.insert(0, {
            "id": user_voice["voice_id"],
            "name": "My Voice",
            "description": "Your cloned voice",
            "is_clone": True
        })
    
    return {"voices": voices}

@api_router.post("/voice/tts")
async def text_to_speech(
    text: str,
    voice_id: Optional[str] = None,
    current_user: dict = Depends(get_current_user)
):
    """Convert text to speech using ElevenLabs"""
    try:
        eleven_client = ElevenLabs(api_key=os.environ.get('ELEVENLABS_API_KEY'))
        
        # Get user's preferred voice or use default
        if not voice_id:
            user_settings = await db.user_settings.find_one({"user_id": current_user["id"]}, {"_id": 0})
            voice_id = user_settings.get("selected_voice") if user_settings else None
            if not voice_id:
                voice_id = DEFAULT_VOICES[0]["id"]  # Default to Rachel
        
        # Generate audio using the new API
        audio = eleven_client.text_to_speech.convert(
            text=text,
            voice_id=voice_id,
            model_id="eleven_flash_v2_5"
        )
        
        # Collect audio bytes (it returns an iterator)
        audio_bytes = b""
        for chunk in audio:
            audio_bytes += chunk
        
        # Return as base64
        audio_base64 = base64.b64encode(audio_bytes).decode()
        
        return {"audio": audio_base64, "content_type": "audio/mpeg"}
        
    except Exception as e:
        logger.error(f"TTS error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to generate speech")

class VoiceCloneRequest(BaseModel):
    name: str = "My Voice"

@api_router.post("/voice/clone")
async def clone_voice(
    audio: UploadFile = File(...),
    current_user: dict = Depends(get_current_user)
):
    """Clone user's voice from audio sample"""
    try:
        eleven_client = ElevenLabs(api_key=os.environ.get('ELEVENLABS_API_KEY'))
        
        # Read audio file
        audio_content = await audio.read()
        
        # Save to temp file
        with tempfile.NamedTemporaryFile(suffix=".mp3", delete=False) as temp_file:
            temp_file.write(audio_content)
            temp_path = temp_file.name
        
        # Check if user already has a cloned voice
        existing_voice = await db.user_voices.find_one({"user_id": current_user["id"]}, {"_id": 0})
        
        if existing_voice:
            # Delete old voice from ElevenLabs
            try:
                eleven_client.voices.delete(existing_voice["voice_id"])
            except:
                pass  # Ignore if voice doesn't exist anymore
        
        # Clone voice using the new API
        voice_name = f"Chronicle_{current_user['id'][:8]}"
        
        from io import BytesIO
        with open(temp_path, "rb") as f:
            audio_bytes = f.read()
        
        voice = eleven_client.voices.ivc.create(
            name=voice_name,
            description=f"Cloned voice for Chronicle user",
            files=[BytesIO(audio_bytes)]
        )
        
        # Clean up temp file
        os.unlink(temp_path)
        
        # Save voice ID to database
        await db.user_voices.update_one(
            {"user_id": current_user["id"]},
            {"$set": {
                "user_id": current_user["id"],
                "voice_id": voice.voice_id,
                "name": voice_name,
                "created_at": datetime.now(timezone.utc).isoformat()
            }},
            upsert=True
        )
        
        # Update user settings to use their cloned voice
        await db.user_settings.update_one(
            {"user_id": current_user["id"]},
            {"$set": {"selected_voice": voice.voice_id}},
            upsert=True
        )
        
        return {
            "success": True,
            "voice_id": voice.voice_id,
            "message": "Voice cloned successfully! It's now set as your default voice."
        }
        
    except Exception as e:
        logger.error(f"Voice clone error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to clone voice: {str(e)}")

@api_router.get("/voice/clone/sample-text")
async def get_clone_sample_text():
    """Get sample text for voice cloning"""
    return {
        "text": "Hello, I'm recording my voice for Chronicle. This sample will help create a clone of my voice that sounds natural and clear. I'll speak at my normal pace and volume, making sure to enunciate each word properly.",
        "instructions": "Read this text clearly in a quiet environment. Speak naturally for about 30 seconds to 1 minute."
    }

class TwilioCallWithVoiceRequest(BaseModel):
    to_phone: str
    message: str
    voice_id: Optional[str] = None

@api_router.post("/call/with-voice")
async def make_call_with_elevenlabs(
    request: TwilioCallWithVoiceRequest,
    current_user: dict = Depends(get_current_user)
):
    """Make a call using ElevenLabs voice via Twilio"""
    # Check if admin or partner
    is_admin = current_user["email"] == ADMIN_EMAIL
    partner = await db.admin_partners.find_one({"email": current_user["email"]})
    
    if not is_admin and not partner:
        # Check subscription for regular users
        if not current_user.get("is_subscribed"):
            raise HTTPException(status_code=403, detail="Subscription required for phone calls")
        if current_user.get("call_minutes_remaining", 0) <= 0:
            raise HTTPException(status_code=403, detail="No call minutes remaining")
    
    try:
        eleven_client = ElevenLabs(api_key=os.environ.get('ELEVENLABS_API_KEY'))
        
        # Get voice ID
        voice_id = request.voice_id
        if not voice_id:
            user_settings = await db.user_settings.find_one({"user_id": current_user["id"]}, {"_id": 0})
            voice_id = user_settings.get("selected_voice") if user_settings else None
            if not voice_id:
                voice_id = DEFAULT_VOICES[0]["id"]
        
        # Generate audio with ElevenLabs using new API
        audio = eleven_client.text_to_speech.convert(
            text=request.message,
            voice_id=voice_id,
            model_id="eleven_flash_v2_5"
        )
        
        # Collect audio bytes
        audio_bytes = b""
        for chunk in audio:
            audio_bytes += chunk
        
        # Store audio in database temporarily
        audio_id = str(uuid.uuid4())
        await db.call_audio.insert_one({
            "id": audio_id,
            "audio": base64.b64encode(audio_bytes).decode(),
            "created_at": datetime.now(timezone.utc).isoformat(),
            "expires_at": (datetime.now(timezone.utc) + timedelta(minutes=10)).isoformat()
        })
        
        # Get the base URL for the audio endpoint
        # This will be served via the /api/call/audio/{audio_id} endpoint
        base_url = os.environ.get('APP_BASE_URL', 'https://chroniclehelper.com')
        audio_url = f"{base_url}/api/call/audio/{audio_id}"
        
        # Make Twilio call with audio URL
        twilio_client = TwilioClient(
            os.environ.get('TWILIO_ACCOUNT_SID'),
            os.environ.get('TWILIO_AUTH_TOKEN')
        )
        
        # Create TwiML to play the audio
        twiml = f'<Response><Play>{audio_url}</Play></Response>'
        
        call = twilio_client.calls.create(
            twiml=twiml,
            to=request.to_phone,
            from_=os.environ.get('TWILIO_PHONE_NUMBER')
        )
        
        # Log the call
        await db.call_logs.insert_one({
            "id": str(uuid.uuid4()),
            "user_id": current_user["id"],
            "to_phone": request.to_phone,
            "message": request.message,
            "voice_id": voice_id,
            "call_sid": call.sid,
            "status": "initiated",
            "created_at": datetime.now(timezone.utc).isoformat()
        })
        
        # Deduct minutes for non-admin users
        if not is_admin and not partner:
            await db.users.update_one(
                {"id": current_user["id"]},
                {"$inc": {"call_minutes_remaining": -1}}
            )
        
        return {"success": True, "call_sid": call.sid}
        
    except Exception as e:
        logger.error(f"Call with voice error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to make call: {str(e)}")

@api_router.get("/call/audio/{audio_id}")
async def get_call_audio(audio_id: str):
    """Serve audio for Twilio calls"""
    audio_doc = await db.call_audio.find_one({"id": audio_id}, {"_id": 0})
    if not audio_doc:
        raise HTTPException(status_code=404, detail="Audio not found")
    
    audio_bytes = base64.b64decode(audio_doc["audio"])
    
    return Response(
        content=audio_bytes,
        media_type="audio/mpeg",
        headers={"Content-Disposition": f"inline; filename=call_audio.mp3"}
    )

@api_router.get("/invite/{link_id}")
async def validate_magic_link(link_id: str):
    """Validate a magic link"""
    link = await db.magic_links.find_one({"id": link_id}, {"_id": 0})
    if not link:
        raise HTTPException(status_code=404, detail="Invalid invite link")
    if link.get("used"):
        raise HTTPException(status_code=400, detail="This invite has already been used")
    if link.get("revoked"):
        raise HTTPException(status_code=400, detail="This invite has been revoked")
    return {"valid": True}

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
    return {"status": "healthy", "service": "Chronicle API"}

# ============== SUBSCRIPTION ROUTES ==============

@api_router.get("/plans")
async def get_plans():
    """Get all available subscription plans"""
    return {"plans": list(SUBSCRIPTION_PLANS.values())}

@api_router.post("/checkout/create")
async def create_checkout(request: CheckoutRequest, http_request: Request, current_user: dict = Depends(get_current_user)):
    """Create a Stripe checkout session for subscription (Direct Stripe SDK)"""
    
    # Validate plan exists
    if request.plan_id not in SUBSCRIPTION_PLANS:
        raise HTTPException(status_code=400, detail="Invalid plan selected")
    
    plan = SUBSCRIPTION_PLANS[request.plan_id]
    
    # Set Stripe API key directly
    stripe.api_key = STRIPE_API_KEY
    
    # Create URLs
    success_url = f"{request.origin_url}/subscription/success?session_id={{CHECKOUT_SESSION_ID}}"
    cancel_url = f"{request.origin_url}/subscription"
    
    # Create checkout session directly with Stripe SDK
    try:
        session = stripe.checkout.Session.create(
            mode="subscription",
            payment_method_types=["card"],
            line_items=[{
                "price_data": {
                    "currency": plan["currency"],
                    "product_data": {
                        "name": f"Chronicle {plan['name']}",
                    },
                    "unit_amount": int(plan["price"] * 100),
                    "recurring": {
                        "interval": plan["interval"]
                    }
                },
                "quantity": 1
            }],
            customer_email=current_user["email"],
            success_url=success_url,
            cancel_url=cancel_url,
            metadata={
                "user_id": current_user["id"],
                "user_email": current_user["email"],
                "plan_id": request.plan_id,
                "plan_name": plan["name"]
            }
        )
        
        # Store transaction in database
        now = datetime.now(timezone.utc).isoformat()
        transaction_doc = {
            "id": str(uuid.uuid4()),
            "session_id": session.id,
            "user_id": current_user["id"],
            "user_email": current_user["email"],
            "plan_id": request.plan_id,
            "amount": plan["price"],
            "currency": plan["currency"],
            "payment_status": "pending",
            "created_at": now,
            "updated_at": now
        }
        await db.payment_transactions.insert_one(transaction_doc)
        
        return {"checkout_url": session.url, "session_id": session.id}
        
    except Exception as e:
        logger.error(f"Checkout error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to create checkout: {str(e)}")

@api_router.post("/trial/setup")
async def setup_trial_with_card(request: TrialSetupRequest, current_user: dict = Depends(get_current_user)):
    """Create a Stripe checkout session with 10-day free trial"""
    
    # Validate plan exists
    if request.plan_id not in SUBSCRIPTION_PLANS:
        raise HTTPException(status_code=400, detail="Invalid plan selected")
    
    plan = SUBSCRIPTION_PLANS[request.plan_id]
    
    # Set Stripe API key
    stripe.api_key = STRIPE_API_KEY
    
    # Create URLs
    success_url = f"{request.origin_url}/trial/success?session_id={{CHECKOUT_SESSION_ID}}"
    cancel_url = f"{request.origin_url}/trial-setup"
    
    try:
        # Create Stripe checkout session with subscription and trial
        session = stripe.checkout.Session.create(
            mode="subscription",
            payment_method_types=["card"],
            line_items=[{
                "price_data": {
                    "currency": plan["currency"],
                    "product_data": {
                        "name": f"Chronicle {plan['name']}",
                    },
                    "unit_amount": int(plan["price"] * 100),
                    "recurring": {
                        "interval": plan["interval"]
                    }
                },
                "quantity": 1
            }],
            subscription_data={
                "trial_period_days": 10,
                "metadata": {
                    "user_id": current_user["id"],
                    "plan_id": request.plan_id
                }
            },
            customer_email=current_user["email"],
            success_url=success_url,
            cancel_url=cancel_url,
            metadata={
                "user_id": current_user["id"],
                "user_email": current_user["email"],
                "plan_id": request.plan_id,
                "plan_name": plan["name"],
                "is_trial_setup": "true"
            }
        )
        
        # Store transaction in database
        now = datetime.now(timezone.utc).isoformat()
        transaction_doc = {
            "id": str(uuid.uuid4()),
            "session_id": session.id,
            "user_id": current_user["id"],
            "user_email": current_user["email"],
            "plan_id": request.plan_id,
            "amount": plan["price"],
            "currency": plan["currency"],
            "payment_status": "trial_pending",
            "is_trial": True,
            "created_at": now,
            "updated_at": now
        }
        await db.payment_transactions.insert_one(transaction_doc)
        
        return {"checkout_url": session.url, "session_id": session.id}
        
    except Exception as e:
        logger.error(f"Trial setup error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to setup trial: {str(e)}")

@api_router.get("/trial/status/{session_id}")
async def get_trial_status(session_id: str, current_user: dict = Depends(get_current_user)):
    """Check trial setup status and activate subscription if paid (Direct Stripe SDK)"""
    
    stripe.api_key = STRIPE_API_KEY
    
    try:
        # Get checkout session status directly from Stripe
        session = stripe.checkout.Session.retrieve(session_id)
        
        # Find the transaction
        transaction = await db.payment_transactions.find_one({"session_id": session_id}, {"_id": 0})
        
        if not transaction:
            raise HTTPException(status_code=404, detail="Transaction not found")
        
        # Check if already processed
        if transaction.get("payment_status") == "paid":
            return {
                "status": "complete",
                "trial_active": True,
                "already_processed": True
            }
        
        # If payment successful, activate subscription
        if session.payment_status == "paid":
            now = datetime.now(timezone.utc).isoformat()
            plan_id = transaction.get("plan_id")
            plan = SUBSCRIPTION_PLANS.get(plan_id, {})
            
            # Calculate subscription end date
            if "annual" in plan_id:
                end_date = (datetime.now(timezone.utc) + timedelta(days=365)).isoformat()
            else:
                end_date = (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
            
            # Update user with subscription and card info
            await db.users.update_one(
                {"id": current_user["id"]},
                {"$set": {
                    "is_subscribed": True,
                    "subscription_tier": plan_id,
                    "subscription_started_at": now,
                    "subscription_ends_at": end_date,
                    "card_on_file": True,
                    "call_minutes_remaining": plan.get("call_minutes", 0),
                    "texts_remaining": plan.get("texts", 0),
                    "usage_reset_date": (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
                }}
            )
            
            # Update transaction status
            await db.payment_transactions.update_one(
                {"session_id": session_id},
                {"$set": {"payment_status": "paid", "updated_at": now}}
            )
            
            return {
                "status": "complete",
                "trial_active": True,
                "subscription_ends_at": end_date
            }
        
        return {
            "status": session.status,
            "trial_active": False
        }
        
    except Exception as e:
        logger.error(f"Trial status error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to check trial status: {str(e)}")

@api_router.get("/checkout/status/{session_id}")
async def get_checkout_status(session_id: str, current_user: dict = Depends(get_current_user)):
    """Check the status of a checkout session and update subscription if paid (Direct Stripe SDK)"""
    
    stripe.api_key = STRIPE_API_KEY
    
    try:
        # Get checkout session directly from Stripe
        session = stripe.checkout.Session.retrieve(session_id)
        
        # Find the transaction
        transaction = await db.payment_transactions.find_one({"session_id": session_id}, {"_id": 0})
        
        if not transaction:
            raise HTTPException(status_code=404, detail="Transaction not found")
        
        # Check if already processed
        if transaction.get("payment_status") == "paid":
            return {
                "status": session.status,
                "payment_status": session.payment_status,
                "already_processed": True
            }
        
        # If payment successful, update user subscription
        if session.payment_status == "paid":
            now = datetime.now(timezone.utc).isoformat()
            plan_id = transaction.get("plan_id")
            plan = SUBSCRIPTION_PLANS.get(plan_id, {})
            
            # Calculate subscription end date
            if "annual" in plan_id:
                end_date = (datetime.now(timezone.utc) + timedelta(days=365)).isoformat()
            else:
                end_date = (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
            
            # Update user subscription
            await db.users.update_one(
                {"id": current_user["id"]},
                {"$set": {
                    "is_subscribed": True,
                    "subscription_tier": plan_id,
                    "subscription_started_at": now,
                    "subscription_ends_at": end_date,
                    "call_minutes_remaining": plan.get("call_minutes", 0),
                    "texts_remaining": plan.get("texts", 0),
                    "usage_reset_date": (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
                }}
            )
            
            # Update transaction status
            await db.payment_transactions.update_one(
                {"session_id": session_id},
                {"$set": {"payment_status": "paid", "updated_at": now}}
            )
        
        return {
            "status": session.status,
            "payment_status": session.payment_status,
            "amount_total": session.amount_total,
            "currency": session.currency
        }
        
    except Exception as e:
        logger.error(f"Status check error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to check status: {str(e)}")

@api_router.get("/subscription/status", response_model=SubscriptionStatusResponse)
async def get_subscription_status(current_user: dict = Depends(get_current_user)):
    """Get current user's subscription status"""
    
    is_subscribed = current_user.get("is_subscribed", False)
    plan_id = current_user.get("subscription_tier")
    plan = SUBSCRIPTION_PLANS.get(plan_id, {}) if plan_id else {}
    
    return SubscriptionStatusResponse(
        is_subscribed=is_subscribed,
        plan=plan_id,
        plan_name=plan.get("name"),
        interval=plan.get("interval"),
        call_minutes_remaining=current_user.get("call_minutes_remaining", 0),
        texts_remaining=current_user.get("texts_remaining", 0),
        subscription_started_at=current_user.get("subscription_started_at"),
        subscription_ends_at=current_user.get("subscription_ends_at"),
        trial_ends_at=current_user.get("trial_ends_at"),
        card_on_file=current_user.get("card_on_file", False)
    )

@api_router.post("/webhook/stripe")
async def stripe_webhook(request: Request):
    """Handle Stripe webhook events (Direct Stripe SDK)"""
    try:
        body = await request.body()
        signature = request.headers.get("Stripe-Signature", "")
        
        stripe.api_key = STRIPE_API_KEY
        webhook_secret = os.environ.get("STRIPE_WEBHOOK_SECRET", "")
        
        # Verify webhook signature if secret is configured
        if webhook_secret:
            try:
                event = stripe.Webhook.construct_event(body, signature, webhook_secret)
            except stripe.error.SignatureVerificationError:
                raise HTTPException(status_code=400, detail="Invalid signature")
        else:
            # Parse event without verification (development mode)
            import json
            event = json.loads(body)
        
        event_type = event.get("type") if isinstance(event, dict) else event.type
        logger.info(f"Webhook received: {event_type}")
        
        # Handle checkout.session.completed event
        if event_type == "checkout.session.completed":
            session_data = event.get("data", {}).get("object", {}) if isinstance(event, dict) else event.data.object
            session_id = session_data.get("id") if isinstance(session_data, dict) else session_data.id
            metadata = session_data.get("metadata", {}) if isinstance(session_data, dict) else session_data.metadata
            
            if metadata and metadata.get("user_id"):
                user_id = metadata["user_id"]
                plan_id = metadata.get("plan_id")
                plan = SUBSCRIPTION_PLANS.get(plan_id, {})
                now = datetime.now(timezone.utc).isoformat()
                
                # Calculate subscription end date
                if plan_id and "annual" in plan_id:
                    end_date = (datetime.now(timezone.utc) + timedelta(days=365)).isoformat()
                else:
                    end_date = (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
                
                # Update user subscription
                await db.users.update_one(
                    {"id": user_id},
                    {"$set": {
                        "is_subscribed": True,
                        "subscription_tier": plan_id,
                        "subscription_started_at": now,
                        "subscription_ends_at": end_date,
                        "call_minutes_remaining": plan.get("call_minutes", 0),
                        "texts_remaining": plan.get("texts", 0),
                        "usage_reset_date": (datetime.now(timezone.utc) + timedelta(days=30)).isoformat()
                    }}
                )
                
                # Update transaction
                await db.payment_transactions.update_one(
                    {"session_id": session_id},
                    {"$set": {"payment_status": "paid", "updated_at": now}}
                )
        
        return {"status": "received"}
        
    except Exception as e:
        logger.error(f"Webhook error: {str(e)}")
        return {"status": "error", "message": str(e)}

# ============== SMS ROUTES ==============

@api_router.post("/sms/send", response_model=SMSResponse)
async def send_sms(request: SendSMSRequest, current_user: dict = Depends(get_current_user)):
    """Send an SMS message via Twilio"""
    
    # Check if user has SMS quota (Pro or Business plan)
    texts_remaining = current_user.get("texts_remaining", 0)
    subscription_tier = current_user.get("subscription_tier", "")
    
    if not subscription_tier or "starter" in subscription_tier:
        raise HTTPException(status_code=403, detail="SMS feature requires Pro or Business subscription")
    
    if texts_remaining <= 0:
        raise HTTPException(status_code=403, detail="You have no SMS credits remaining. Please upgrade your plan.")
    
    try:
        # Initialize Twilio client
        account_sid = os.environ.get('TWILIO_ACCOUNT_SID')
        auth_token = os.environ.get('TWILIO_AUTH_TOKEN')
        
        if not account_sid or not auth_token:
            raise HTTPException(status_code=500, detail="Twilio not configured")
        
        twilio_client = TwilioClient(account_sid, auth_token)
        
        # Get Twilio phone number (you'd typically have this in env)
        from_number = os.environ.get('TWILIO_PHONE_NUMBER', '+15005550006')  # Test number
        
        # Send SMS
        message = twilio_client.messages.create(
            body=request.message,
            from_=from_number,
            to=request.to_phone
        )
        
        # Decrement SMS quota
        await db.users.update_one(
            {"id": current_user["id"]},
            {"$inc": {"texts_remaining": -1}}
        )
        
        # Log the SMS
        now = datetime.now(timezone.utc).isoformat()
        sms_log = {
            "id": str(uuid.uuid4()),
            "user_id": current_user["id"],
            "to_phone": request.to_phone,
            "contact_name": request.contact_name,
            "message": request.message,
            "message_sid": message.sid,
            "status": message.status,
            "created_at": now
        }
        await db.sms_logs.insert_one(sms_log)
        
        return SMSResponse(success=True, message_sid=message.sid)
        
    except Exception as e:
        logger.error(f"SMS error: {str(e)}")
        return SMSResponse(success=False, error=str(e))

# ============== VOICE CALL ROUTES ==============

@api_router.post("/call/make", response_model=CallResponse)
async def make_call(request: MakeCallRequest, http_request: Request, current_user: dict = Depends(get_current_user)):
    """Initiate an outbound call with AI voice"""
    
    # Check if user has call minutes (Pro or Business plan)
    call_minutes_remaining = current_user.get("call_minutes_remaining", 0)
    subscription_tier = current_user.get("subscription_tier", "")
    
    if not subscription_tier or "starter" in subscription_tier:
        raise HTTPException(status_code=403, detail="Voice call feature requires Pro or Business subscription")
    
    if call_minutes_remaining <= 0:
        raise HTTPException(status_code=403, detail="You have no call minutes remaining. Please upgrade your plan.")
    
    try:
        # Initialize Twilio client
        account_sid = os.environ.get('TWILIO_ACCOUNT_SID')
        auth_token = os.environ.get('TWILIO_AUTH_TOKEN')
        
        if not account_sid or not auth_token:
            raise HTTPException(status_code=500, detail="Twilio not configured")
        
        twilio_client = TwilioClient(account_sid, auth_token)
        
        # Get Twilio phone number
        from_number = os.environ.get('TWILIO_PHONE_NUMBER', '+15005550006')
        
        # Store the call data for the webhook
        call_id = str(uuid.uuid4())
        now = datetime.now(timezone.utc).isoformat()
        
        call_data = {
            "id": call_id,
            "user_id": current_user["id"],
            "to_phone": request.to_phone,
            "contact_name": request.contact_name,
            "message": request.message,
            "voice_id": request.voice_id,
            "status": "initiating",
            "created_at": now
        }
        await db.call_logs.insert_one(call_data)
        
        # Get base URL for webhooks
        base_url = str(http_request.base_url).rstrip('/')
        if 'localhost' in base_url or '0.0.0.0' in base_url:
            # Use the frontend URL for webhooks in production
            base_url = os.environ.get('WEBHOOK_BASE_URL', base_url)
        
        # Initiate the call
        call = twilio_client.calls.create(
            url=f"{base_url}/api/call/twiml/{call_id}",
            to=request.to_phone,
            from_=from_number,
            status_callback=f"{base_url}/api/call/status/{call_id}",
            status_callback_event=['completed', 'failed']
        )
        
        # Update call log with SID
        await db.call_logs.update_one(
            {"id": call_id},
            {"$set": {"call_sid": call.sid, "status": "initiated"}}
        )
        
        # Decrement call minutes (1 minute minimum)
        await db.users.update_one(
            {"id": current_user["id"]},
            {"$inc": {"call_minutes_remaining": -1}}
        )
        
        return CallResponse(success=True, call_sid=call.sid)
        
    except Exception as e:
        logger.error(f"Call error: {str(e)}")
        return CallResponse(success=False, error=str(e))

@api_router.get("/call/twiml/{call_id}", response_class=PlainTextResponse)
async def call_twiml(call_id: str):
    """Generate TwiML for the call (Twilio webhook)"""
    
    # Get call data
    call_data = await db.call_logs.find_one({"id": call_id}, {"_id": 0})
    
    if not call_data:
        response = VoiceResponse()
        response.say("Sorry, there was an error with this call.")
        return PlainTextResponse(content=str(response), media_type="application/xml")
    
    message = call_data.get("message", "Hello, this is a call from Chronicle.")
    
    # Generate speech using ElevenLabs
    try:
        elevenlabs_key = os.environ.get('ELEVENLABS_API_KEY')
        voice_id = call_data.get("voice_id", "EXAVITQu4vr4xnSDxMaL")
        
        if elevenlabs_key:
            eleven_client = ElevenLabs(api_key=elevenlabs_key)
            
            # Generate audio
            audio_generator = eleven_client.text_to_speech.convert(
                voice_id=voice_id,
                text=message,
                model_id="eleven_multilingual_v2"
            )
            
            # Collect audio bytes
            audio_bytes = b""
            for chunk in audio_generator:
                audio_bytes += chunk
            
            # Store audio temporarily (in production, use S3 or similar)
            audio_id = str(uuid.uuid4())
            await db.temp_audio.insert_one({
                "id": audio_id,
                "audio": base64.b64encode(audio_bytes).decode(),
                "created_at": datetime.now(timezone.utc).isoformat()
            })
            
            # Create TwiML that plays the audio
            response = VoiceResponse()
            # For now, use Twilio's built-in TTS as fallback
            # In production, you'd host the audio file and use <Play>
            response.say(message, voice="Polly.Amy", language="en-GB")
            
            return PlainTextResponse(content=str(response), media_type="application/xml")
    
    except Exception as e:
        logger.error(f"ElevenLabs error: {str(e)}")
    
    # Fallback to Twilio's built-in TTS
    response = VoiceResponse()
    response.say(message, voice="Polly.Amy", language="en-GB")
    
    return PlainTextResponse(content=str(response), media_type="application/xml")

@api_router.post("/call/status/{call_id}")
async def call_status_callback(call_id: str, request: Request):
    """Handle call status updates from Twilio"""
    
    form_data = await request.form()
    call_status = form_data.get("CallStatus", "unknown")
    call_duration = form_data.get("CallDuration", "0")
    
    now = datetime.now(timezone.utc).isoformat()
    
    await db.call_logs.update_one(
        {"id": call_id},
        {"$set": {
            "status": call_status,
            "duration": int(call_duration),
            "completed_at": now
        }}
    )
    
    # If call was longer than 1 minute, deduct additional minutes
    duration_minutes = int(call_duration) // 60
    if duration_minutes > 1:
        call_data = await db.call_logs.find_one({"id": call_id}, {"_id": 0})
        if call_data:
            additional_minutes = duration_minutes - 1  # Already deducted 1 minute
            await db.users.update_one(
                {"id": call_data["user_id"]},
                {"$inc": {"call_minutes_remaining": -additional_minutes}}
            )
    
    return {"status": "received"}

@api_router.get("/call/history")
async def get_call_history(current_user: dict = Depends(get_current_user)):
    """Get user's call history"""
    calls = await db.call_logs.find(
        {"user_id": current_user["id"]},
        {"_id": 0}
    ).sort("created_at", -1).to_list(50)
    return {"calls": calls}

@api_router.get("/sms/history")
async def get_sms_history(current_user: dict = Depends(get_current_user)):
    """Get user's SMS history"""
    messages = await db.sms_logs.find(
        {"user_id": current_user["id"]},
        {"_id": 0}
    ).sort("created_at", -1).to_list(50)
    return {"messages": messages}

# ============== VOICE PACKS ==============

@api_router.get("/voice/packs")
async def get_voice_packs():
    """Get available voice minute packs"""
    return {"packs": list(VOICE_PACKS.values())}

@api_router.get("/voice/balance")
async def get_voice_balance(current_user: dict = Depends(get_current_user)):
    """Get user's voice minutes balance"""
    return {
        "minutes_remaining": current_user.get("voice_minutes", 0),
        "has_voice_feature": current_user.get("voice_minutes", 0) > 0
    }

class VoicePackPurchaseRequest(BaseModel):
    pack_id: str
    origin_url: str

@api_router.post("/voice/purchase")
async def purchase_voice_pack(request: VoicePackPurchaseRequest, current_user: dict = Depends(get_current_user)):
    """Purchase a voice minute pack via Stripe"""
    if request.pack_id not in VOICE_PACKS:
        raise HTTPException(status_code=400, detail="Invalid pack selected")
    
    pack = VOICE_PACKS[request.pack_id]
    
    stripe.api_key = STRIPE_API_KEY
    
    success_url = f"{request.origin_url}/voice/success?session_id={{CHECKOUT_SESSION_ID}}"
    cancel_url = f"{request.origin_url}/settings"
    
    try:
        session = stripe.checkout.Session.create(
            mode="payment",
            payment_method_types=["card"],
            line_items=[{
                "price_data": {
                    "currency": pack["currency"],
                    "product_data": {
                        "name": f"Chronicle {pack['name']}",
                        "description": pack["description"]
                    },
                    "unit_amount": int(pack["price"] * 100)
                },
                "quantity": 1
            }],
            customer_email=current_user["email"],
            success_url=success_url,
            cancel_url=cancel_url,
            metadata={
                "user_id": current_user["id"],
                "pack_id": request.pack_id,
                "minutes": str(pack["minutes"]),
                "type": "voice_pack"
            }
        )
        
        return {"checkout_url": session.url, "session_id": session.id}
    except Exception as e:
        logger.error(f"Voice pack purchase error: {e}")
        raise HTTPException(status_code=500, detail="Failed to create checkout")

@api_router.get("/voice/verify/{session_id}")
async def verify_voice_purchase(session_id: str, current_user: dict = Depends(get_current_user)):
    """Verify voice pack purchase and add minutes"""
    stripe.api_key = STRIPE_API_KEY
    
    try:
        session = stripe.checkout.Session.retrieve(session_id)
        
        if session.payment_status == "paid" and session.metadata.get("type") == "voice_pack":
            minutes = int(session.metadata.get("minutes", 0))
            
            # Add minutes to user
            await db.users.update_one(
                {"id": current_user["id"]},
                {"$inc": {"voice_minutes": minutes}}
            )
            
            # Log purchase
            await db.voice_purchases.insert_one({
                "id": str(uuid.uuid4()),
                "user_id": current_user["id"],
                "pack_id": session.metadata.get("pack_id"),
                "minutes": minutes,
                "session_id": session_id,
                "created_at": datetime.now(timezone.utc).isoformat()
            })
            
            return {"success": True, "minutes_added": minutes}
        
        return {"success": False, "message": "Payment not completed"}
    except Exception as e:
        logger.error(f"Voice verification error: {e}")
        raise HTTPException(status_code=500, detail="Failed to verify purchase")

# ============== TEST CALL (ADMIN ONLY) ==============

class TestCallRequest(BaseModel):
    to_phone: str
    message: str = "Hello, this is a test call from Chronicle. Your phone integration is working correctly. Goodbye!"

@api_router.post("/admin/test-call")
async def admin_test_call(request: TestCallRequest, admin: dict = Depends(require_admin)):
    """Make a test call (admin only, no subscription required)"""
    try:
        account_sid = os.environ.get('TWILIO_ACCOUNT_SID')
        auth_token = os.environ.get('TWILIO_AUTH_TOKEN')
        from_number = os.environ.get('TWILIO_PHONE_NUMBER')
        
        if not account_sid or not auth_token or not from_number:
            raise HTTPException(status_code=500, detail="Twilio not configured")
        
        twilio_client = TwilioClient(account_sid, auth_token)
        
        # Create TwiML for the test message
        twiml = f'<Response><Say voice="Polly.Amy" language="en-GB">{request.message}</Say></Response>'
        
        # Make the call
        call = twilio_client.calls.create(
            twiml=twiml,
            to=request.to_phone,
            from_=from_number
        )
        
        return {"success": True, "call_sid": call.sid, "status": call.status}
        
    except Exception as e:
        logger.error(f"Test call error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# Include the router in the main app
app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=os.environ.get('CORS_ORIGINS', '*').split(','),
    allow_methods=["*"],
    allow_headers=["*"],
)

# Serve static frontend files (for Railway deployment)
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse

static_path = Path(__file__).parent / "static"
if static_path.exists():
    app.mount("/static", StaticFiles(directory=static_path / "static"), name="static")
    
    @app.get("/{full_path:path}")
    async def serve_spa(full_path: str):
        # Don't intercept API routes
        if full_path.startswith("api/"):
            raise HTTPException(status_code=404)
        
        file_path = static_path / full_path
        if file_path.exists() and file_path.is_file():
            return FileResponse(file_path)
        return FileResponse(static_path / "index.html")

@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
