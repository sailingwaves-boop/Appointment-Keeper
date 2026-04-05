# Chronicle - AI Assistant SaaS

## Overview
Chronicle is a personal AI assistant SaaS application with persistent memory across conversations. It's built as a React PWA frontend served statically via FastAPI backend.

## Tech Stack
- **Frontend:** React PWA
- **Backend:** FastAPI (Python)
- **Database:** MongoDB
- **LLM:** Anthropic Claude Sonnet 4.6 (direct API integration)
- **Voice:** OpenAI Whisper (via Emergent), ElevenLabs TTS
- **Payments:** Stripe (subscriptions with 10-day trial)
- **SMS/Voice Calls:** Twilio

## Core Features

### Implemented
- [x] User authentication (Email/Password + Google OAuth)
- [x] 10-day free trial with Stripe subscription
- [x] Chat with Claude Sonnet 4.6 (direct Anthropic SDK)
- [x] Persistent memory across conversations
- [x] Voice input (Whisper transcription)
- [x] Image upload and analysis in chat
- [x] Contacts management
- [x] Owner Admin Panel (manage users, partners, credits)
- [x] User Settings Panel (AI rules, voice preferences, Home Assistant config)
- [x] Code block copy button
- [x] Message copy and TTS playback
- [x] Microphone with 2-second silence auto-stop
- [x] **App Builder Mode toggle** (switches AI context to coding/development focus)
- [x] **Native Phone SMS** (admin only - opens native SMS app)
- [x] **Native Phone Call** (admin only - opens native dialer)

### Future/Backlog
- [ ] Google Home / Alexa smart home integration (cloud-based, OAuth)
- [ ] Network Printing via PrintNode API
- [ ] ElevenLabs voice for Twilio calls (enhanced TTS)

## Key Files
- `/app/backend/server.py` - FastAPI backend with all endpoints
- `/app/frontend/src/App.js` - Monolithic React frontend (~3000 lines)
- `/app/frontend/src/App.css` - Styling

## API Endpoints
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/google/token` - Google OAuth
- `POST /api/chat` - Chat with AI (supports `app_builder_mode` flag)
- `POST /api/sms/send` - Send SMS (Twilio or native)
- `POST /api/call/place` - Place call (Twilio or native)
- `GET /api/memory` - Get user memories
- `GET /api/contacts` - Get contacts
- `POST /api/trial/setup` - Start trial with card
- `GET /api/admin/dashboard` - Admin stats
- `GET /api/user/settings` - User settings

## Admin-Only Features
The following features are only available to the admin (sailingwaves@gmail.com) or partners added via the admin panel:
- Native SMS (opens phone's SMS app with pre-filled message)
- Native Phone Call (opens phone's dialer)
- Admin Dashboard (user management, credits, partners)

## Environment Variables (Backend)
- `MONGO_URL` - MongoDB connection
- `DB_NAME` - Database name
- `ANTHROPIC_API_KEY` - Anthropic API key
- `STRIPE_API_KEY` - Stripe API key
- `TWILIO_ACCOUNT_SID` - Twilio SID
- `TWILIO_AUTH_TOKEN` - Twilio auth token
- `TWILIO_PHONE_NUMBER` - Twilio phone number
- `ELEVENLABS_API_KEY` - ElevenLabs API key
- `EMERGENT_LLM_KEY` - For Whisper transcription

## Deployment
User deploys to VPS with this command:
```bash
cd /app && git pull && cd frontend && npm run build && cp -r build/* /app/backend/static/ && fuser -k 8000/tcp && cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > nohup.out 2>&1 &
```

## Last Updated
2026-04-05 - Added App Builder Mode toggle, Native Phone SMS & Call (admin only)
