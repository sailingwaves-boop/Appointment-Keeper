# Chronicle - AI Assistant SaaS

## Overview
Chronicle is a personal AI assistant SaaS application with persistent memory across conversations. It's built as a React PWA frontend served statically via FastAPI backend.

## Tech Stack
- **Frontend:** React PWA
- **Backend:** FastAPI (Python)
- **Database:** MongoDB
- **LLM:** Anthropic Claude Sonnet 4.6 (direct API integration)
- **Voice TTS:** ElevenLabs (direct API, using `eleven_flash_v2_5` model)
- **Voice STT:** OpenAI Whisper (direct API)
- **Payments:** Stripe (direct SDK)
- **SMS/Voice Calls:** Twilio

## Core Features

### Implemented
- [x] User authentication (Email/Password + Google OAuth)
- [x] 10-day free trial with Stripe subscription
- [x] Chat with Claude Sonnet 4.6 (direct Anthropic SDK)
- [x] Persistent memory across conversations
- [x] Voice input (Direct OpenAI Whisper)
- [x] Image upload and analysis in chat
- [x] Contacts management
- [x] Owner Admin Panel (manage users, partners, credits)
- [x] User Settings Panel (AI rules, voice preferences, Home Assistant config)
- [x] Code block copy button
- [x] Message copy and TTS playback
- [x] Microphone with 2-second silence auto-stop
- [x] App Builder Mode toggle (switches AI context to coding/development focus)
- [x] Native Phone SMS (admin only - opens native SMS app)
- [x] Native Phone Call (admin only - opens native dialer)
- [x] **ElevenLabs TTS for chat playback** (replaces browser voice)
- [x] **Voice cloning** (users can clone their voice via ElevenLabs)
- [x] **4 preset ElevenLabs voices** (Rachel, Domi, Sarah, Antoni)
- [x] **Twilio calls with ElevenLabs voice** (AI-generated voice plays on call)

### Emergent Dependencies REMOVED
- ❌ emergentintegrations library - COMPLETELY REMOVED
- ✅ Stripe - now uses direct `stripe` SDK
- ✅ Whisper - now uses direct `openai` SDK
- ✅ All LLM calls - use direct provider SDKs

### Future/Backlog
- [ ] Google Home / Alexa smart home integration (cloud-based, OAuth)
- [ ] Network Printing via PrintNode API

## Key Files
- `/app/backend/server.py` - FastAPI backend with all endpoints
- `/app/frontend/src/App.js` - Monolithic React frontend (~3000 lines)
- `/app/frontend/src/App.css` - Styling

## API Endpoints

### Voice/TTS
- `POST /api/voice/tts` - Generate speech with ElevenLabs
- `POST /api/voice/clone` - Clone user's voice from audio sample
- `GET /api/voice/clone/sample-text` - Get sample text for voice cloning
- `GET /api/voices/available` - Get preset + user's cloned voices
- `POST /api/voice/transcribe` - Speech-to-text (Direct OpenAI Whisper)

### Calls
- `POST /api/call/with-voice` - Make call with ElevenLabs voice via Twilio
- `GET /api/call/audio/{audio_id}` - Serve audio for Twilio playback
- `POST /api/call/place` - Place call (Twilio or native)
- `POST /api/sms/send` - Send SMS (Twilio or native)

### Auth & User
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/google/token` - Google OAuth
- `GET /api/user/settings` - User settings
- `POST /api/user/settings` - Update settings

### Chat
- `POST /api/chat` - Chat with AI (supports `app_builder_mode` flag)
- `GET /api/memory` - Get user memories

### Payments (Direct Stripe SDK)
- `POST /api/checkout/create` - Create Stripe checkout session
- `GET /api/checkout/status/{session_id}` - Check payment status
- `POST /api/trial/setup` - Start trial with card
- `GET /api/trial/status/{session_id}` - Check trial status
- `POST /api/webhook/stripe` - Stripe webhook handler

## Environment Variables (Backend)
- `MONGO_URL` - MongoDB connection
- `DB_NAME` - Database name
- `ANTHROPIC_API_KEY` - Anthropic API key
- `OPENAI_API_KEY` - OpenAI API key (for Whisper)
- `STRIPE_API_KEY` - Stripe API key
- `ELEVENLABS_API_KEY` - ElevenLabs API key
- `TWILIO_ACCOUNT_SID` - Twilio SID
- `TWILIO_AUTH_TOKEN` - Twilio auth token
- `TWILIO_PHONE_NUMBER` - Twilio phone number
- `APP_BASE_URL` - Base URL for audio serving (e.g., https://chroniclehelper.com)

## Deployment
User deploys to VPS with this command:
```bash
cd /app && git pull && cd frontend && npm run build && cp -r build/* /app/backend/static/ && fuser -k 8000/tcp && cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > nohup.out 2>&1 &
```

## Last Updated
2026-04-05 - Removed all Emergent dependencies, added ElevenLabs TTS/voice cloning, Twilio calls with AI voice
