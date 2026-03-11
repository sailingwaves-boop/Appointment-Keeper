# AI Helper - Product Requirements Document

## Project Overview
AI Helper is a multi-tenant SaaS application providing an AI assistant with persistent memory for each user. It will be offered both as a standalone product and as an add-on service for Appointment Keeper customers.

## Original Problem Statement
Build an AI helper with:
- Persistent/unlimited memory per user
- Multi-tenant architecture (isolated user instances)
- Subscription payments via Stripe
- Phone call capabilities (make/receive calls, send texts)
- Admin dashboard for customer/revenue management
- Integration with Appointment Keeper business
- Can be sold standalone AND as add-on for Appointment Keeper

## Tech Stack
- **Frontend**: React with Tailwind CSS
- **Backend**: Python FastAPI
- **Database**: MongoDB
- **AI**: GPT-5.2 (via Emergent LLM Key)
- **Voice**: ElevenLabs (realistic AI voice)
- **Phone/SMS**: Twilio
- **Payments**: Stripe

## User Personas
1. **Standalone Users**: People who want an AI assistant with permanent memory
2. **Appointment Keeper Customers**: Business customers who want AI-powered features as an add-on

---

## Phase 1 - Core MVP (COMPLETED ✅)
**Status**: Implemented and Tested
**Date**: March 11, 2025

### Implemented Features:
- ✅ User registration and login (JWT auth)
- ✅ Disclosure/terms acceptance flow
- ✅ AI Chat with GPT-5.2 integration
- ✅ Persistent memory storage per user
- ✅ Session-based multi-turn conversations
- ✅ Contacts management (add/delete)
- ✅ Memory viewing and deletion
- ✅ Mobile-responsive design
- ✅ Dark theme UI

### API Endpoints:
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Get current user
- `POST /api/disclosure/accept` - Accept terms
- `GET /api/disclosure/status` - Check disclosure status
- `POST /api/chat` - Send message to AI
- `GET /api/chat/history` - Get conversation history
- `GET /api/chat/sessions` - List chat sessions
- `GET /api/memory` - List user memories
- `POST /api/memory` - Add memory
- `DELETE /api/memory/{id}` - Delete memory
- `GET /api/contacts` - List contacts
- `POST /api/contacts` - Add contact
- `DELETE /api/contacts/{id}` - Delete contact

---

## Phase 2 - Stripe Subscriptions (PENDING)
### Features to Build:
- [ ] Stripe checkout integration
- [ ] Subscription plans (Basic, Pro, etc.)
- [ ] Billing management page
- [ ] Admin: View subscribers and revenue
- [ ] Free tier with limited usage
- [ ] Upgrade prompts

---

## Phase 3 - Phone System (PENDING)
### Features to Build:
- [ ] Twilio integration for SMS
- [ ] Send texts via AI command ("Text John...")
- [ ] Make outbound calls with ElevenLabs voice
- [ ] Appointment reminder calls
- [ ] Two-way phone conversations
- [ ] Birthday/special occasion calls
- [ ] Receive and process inbound calls

---

## Phase 4 - Admin Panel (PENDING)
### Features to Build:
- [ ] Admin dashboard for owner
- [ ] Customer list with subscription status
- [ ] Revenue analytics
- [ ] Usage statistics per user
- [ ] Appointment Keeper integration controls

---

## Phase 5 - Appointment Keeper Integration (PENDING)
### Features to Build:
- [ ] Embeddable widget for WordPress/Amelia
- [ ] Zapier integration for automations
- [ ] Upsell flow for Appointment Keeper customers
- [ ] Separate pricing for add-on tier

---

## Environment Variables
### Backend (.env)
```
MONGO_URL=mongodb://localhost:27017
DB_NAME=ai_helper_db
EMERGENT_LLM_KEY=sk-emergent-xxx
TWILIO_ACCOUNT_SID=ACxxx
TWILIO_AUTH_TOKEN=xxx
ELEVENLABS_API_KEY=sk_xxx
```

### Frontend (.env)
```
REACT_APP_BACKEND_URL=https://xxx.preview.emergentagent.com
```

---

## Database Collections
- `users` - User accounts and subscription status
- `conversations` - Chat history
- `memories` - Stored user information
- `contacts` - User's contact list

---

## Known Issues
- None currently

## Notes
- All keys are configured and working
- GPT-5.2 responding correctly with memory context
- Mobile responsive design implemented
