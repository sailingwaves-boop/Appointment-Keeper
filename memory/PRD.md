# Chronicle - AI Assistant SaaS

## Overview
Chronicle is a personal AI assistant SaaS application with persistent memory across conversations.

## Tech Stack
- **Frontend:** React PWA
- **Backend:** FastAPI (Python)
- **Database:** MongoDB
- **LLM:** Anthropic Claude Sonnet 4.6 (direct API)
- **Voice TTS:** ElevenLabs (direct API)
- **Voice STT:** OpenAI Whisper (direct API)
- **Payments:** Stripe (direct SDK)
- **SMS/Voice Calls:** Twilio

## Core Features - ALL WORKING

### Authentication & UI
- [x] Brain icon visible on login page
- [x] Google OAuth login
- [x] Email/password login

### Chat Features
- [x] App Builder Mode toggle (Chat ↔ Builder in header)
- [x] Memory panel toggle
- [x] New Chat button
- [x] Voice input with Whisper
- [x] Image upload and analysis
- [x] ElevenLabs TTS for chat playback

### Phone & SMS (Admin Only)
- [x] Chronicle asks "Twilio or your phone?" when you request SMS/call
- [x] Looks up contact from contacts list
- [x] Executes action when you respond with choice
- [x] Action format: SEND_SMS_NATIVE|phone|message or MAKE_CALL_NATIVE|phone

### Voice Features
- [x] Voice cloning via ElevenLabs
- [x] 4 preset voices (Rachel, Domi, Sarah, Antoni)
- [x] Voice selector in Settings

### Admin Features
- [x] Admin/Partner bypass for SMS/call credits
- [x] Admin panel for user management

## Emergent Dependencies - REMOVED
- ✅ No emergentintegrations library
- ✅ Stripe - direct SDK
- ✅ Whisper - direct OpenAI SDK

## Key API Endpoints
- POST /api/chat - Chat with AI
- POST /api/sms/send - Send SMS
- POST /api/call/place - Place call
- GET /api/user/is-admin - Check admin/partner status
- POST /api/voice/tts - Text to speech
- POST /api/voice/clone - Clone voice

## Deployment Command
```bash
cd /app && git pull && cd frontend && npm run build && cp -r build/* /app/backend/static/ && fuser -k 8000/tcp && cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > nohup.out 2>&1 &
```

## Last Updated
2026-04-05 - Fixed all issues: brain icon, App Builder toggle, phone/SMS flow
