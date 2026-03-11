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
- **Frontend**: React with CSS
- **Backend**: Python FastAPI
- **Database**: MongoDB
- **AI**: GPT-5.2 (via Emergent LLM Key)
- **Voice**: ElevenLabs (realistic AI voice)
- **Phone/SMS**: Twilio
- **Payments**: Stripe
- **Auth**: Email/Password + Google OAuth

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

---

## Phase 2 - Stripe Subscriptions (COMPLETED ✅)
**Status**: Implemented and Tested
**Date**: March 11, 2025

### Implemented Features:
- ✅ Subscription plans page with pricing
- ✅ Three tiers: Starter (£19), Pro (£39), Business (£69)
- ✅ Monthly and Annual billing options (17% discount for annual)
- ✅ Stripe checkout integration
- ✅ Payment status polling
- ✅ Subscription status tracking
- ✅ Usage limits (call minutes, texts) per tier

### Subscription Tiers:
| Plan | Monthly | Annual | Call Minutes | Texts |
|------|---------|--------|--------------|-------|
| Starter | £19 | £190 | 0 | 0 |
| Pro | £39 | £390 | 60/month | 100/month |
| Business | £69 | £690 | 180/month | 300/month |

---

## Google Sign-In (COMPLETED ✅)
**Status**: Implemented
**Date**: March 11, 2025

### Implemented Features:
- ✅ Google OAuth via Emergent Auth
- ✅ "Continue with Google" button on login page
- ✅ OAuth callback handling
- ✅ Session token management for Google users
- ✅ Works alongside email/password login

---

## Phase 3 - Phone System (COMPLETED ✅)
**Status**: Implemented
**Date**: March 11, 2025

### Implemented Features:
- ✅ Phone & SMS page in dashboard
- ✅ Send SMS via Twilio API
- ✅ Make outbound calls with AI voice (ElevenLabs + Twilio)
- ✅ Quick contact selection
- ✅ Call and SMS history
- ✅ Usage tracking (minutes/texts remaining)
- ✅ Feature gated behind Pro/Business subscription

### API Endpoints Added:
- `POST /api/sms/send` - Send SMS message
- `POST /api/call/make` - Initiate AI voice call
- `GET /api/call/twiml/{id}` - Twilio TwiML webhook
- `POST /api/call/status/{id}` - Twilio status callback
- `GET /api/call/history` - Get call history
- `GET /api/sms/history` - Get SMS history

---

## Phase 4 - Admin Panel (PENDING)
### Features to Build:
- [ ] Admin dashboard for owner
- [ ] Customer list with subscription status
- [ ] Revenue analytics
- [ ] Usage statistics per user
- [ ] System settings management

---

## Phase 5 - Keeper Assistant (WordPress Widget) (PENDING)
### Features to Build:
- [ ] Separate branding ("Keeper Assistant")
- [ ] Embeddable widget for WordPress/Amelia
- [ ] Limited feature set (appointment-focused only)
- [ ] Different styling to match Appointment Keeper
- [ ] Upsell flow for Appointment Keeper customers

---

## API Endpoints Summary

### Auth
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login with email/password
- `POST /api/auth/google/session` - Google OAuth session exchange
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get current user

### Disclosure
- `POST /api/disclosure/accept` - Accept terms
- `GET /api/disclosure/status` - Check disclosure status

### Chat
- `POST /api/chat` - Send message to AI
- `GET /api/chat/history` - Get conversation history
- `GET /api/chat/sessions` - List chat sessions

### Memory & Contacts
- `GET /api/memory` - List user memories
- `POST /api/memory` - Add memory
- `DELETE /api/memory/{id}` - Delete memory
- `GET /api/contacts` - List contacts
- `POST /api/contacts` - Add contact
- `DELETE /api/contacts/{id}` - Delete contact

### Subscriptions
- `GET /api/plans` - Get all subscription plans
- `POST /api/checkout/create` - Create Stripe checkout session
- `GET /api/checkout/status/{session_id}` - Check payment status
- `GET /api/subscription/status` - Get user's subscription status
- `POST /api/webhook/stripe` - Stripe webhook handler

### Phone & SMS
- `POST /api/sms/send` - Send SMS
- `POST /api/call/make` - Make AI voice call
- `GET /api/call/history` - Call history
- `GET /api/sms/history` - SMS history

---

## Environment Variables
### Backend (.env)
```
MONGO_URL=mongodb://localhost:27017
DB_NAME=ai_helper_db
EMERGENT_LLM_KEY=sk-emergent-xxx
TWILIO_ACCOUNT_SID=ACxxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_PHONE_NUMBER=+44xxx
ELEVENLABS_API_KEY=sk_xxx
STRIPE_API_KEY=sk_test_xxx
```

---

## Database Collections
- `users` - User accounts and subscription status
- `user_sessions` - Google OAuth sessions
- `conversations` - Chat history
- `memories` - Stored user information
- `contacts` - User's contact list
- `payment_transactions` - Stripe payment records
- `call_logs` - Phone call history
- `sms_logs` - SMS history

---

## Known Issues
- Twilio phone number needs to be configured in production
- ElevenLabs audio streaming to Twilio requires audio file hosting (currently using Twilio TTS fallback)

## Notes
- All integrations configured and working
- GPT-5.2 responding correctly with memory context
- Stripe checkout returns valid URLs
- Google OAuth working
- Phone/SMS features gated behind subscription
