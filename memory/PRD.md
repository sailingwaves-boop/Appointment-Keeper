# Chronicle - AI Personal Assistant

## Original Problem Statement
Build a multi-tenant AI assistant SaaS application called "Chronicle" with:
- Persistent memory for users
- Voice calls and SMS via Twilio
- Stripe subscriptions with 10-day free trial
- PWA installable app
- Admin magic links for free accounts
- Voice-to-text (Whisper) input

## Tech Stack
- Frontend: React (PWA)
- Backend: FastAPI
- Database: MongoDB
- Auth: Google OAuth 2.0
- Payments: Stripe
- Voice/SMS: Twilio
- AI: OpenAI GPT-5.2, Whisper

## What's Been Implemented

### Core Features (DONE)
- User authentication (Google OAuth + email/password)
- 10-day free trial with Stripe subscription
- AI chat with persistent memory
- Contact management
- Voice-to-text input (Whisper)
- PWA installation support
- Admin magic links page
- Voice call add-on packs (Light/Medium/Heavy)

### UI Updates (2025-03-28)
- Wider chat interface (max-width 1200px)
- New color scheme: user messages light blue, AI responses white
- Code block rendering in messages
- Camera button for direct photo capture
- File upload button
- Listen/TTS button on AI messages
- Up arrow send button
- Microphone on same line as input
- File preview before sending

### Backend Updates (2025-03-28)
- File upload endpoint (/api/upload)
- File retrieval endpoint (/api/files/{id})
- Voice packs purchase system
- Voice minutes balance tracking

## Pricing Structure
### Subscriptions
- Starter: £14/month (£140/year)
- Pro: £39/month (£390/year) - includes 60 call mins, 100 texts
- Business: £69/month (£690/year) - includes 180 call mins, 300 texts

### Voice Add-ons
- Light: £15 for 30 minutes
- Medium: £49 for 120 minutes
- Heavy: £99 for 300 minutes

## Deployment
- User's VPS: 45.32.176.250
- Domain: chroniclehelper.com (www.chroniclehelper.com)
- Workflow: Emergent -> GitHub -> VPS pull

## Pending/Future Tasks

### P0 - Critical
- [ ] Fix microphone if still not working on VPS (test after deployment)

### P1 - High Priority
- [ ] Convert to React Native for native phone calls/SMS
- [ ] Voice commands ("call John Ross")
- [ ] Fix "run out of credit" text limit issue

### P2 - Medium Priority
- [ ] Add to Google Play Store (Android)
- [ ] Apple App Store (iOS) - requires $99/year developer account
- [ ] APK download from website
- [ ] ElevenLabs voice integration for natural AI voice

### P3 - Future
- [ ] Keeper Assistant WordPress widget
- [ ] Full conversational voice AI

## Key Files
- `/app/frontend/src/App.js` - Main React app
- `/app/frontend/src/App.css` - Styles
- `/app/backend/server.py` - FastAPI backend
- `/app/frontend/public/manifest.json` - PWA config
- `/app/frontend/public/service-worker.js` - PWA cache

## Environment Variables Required on VPS
```
STRIPE_API_KEY=sk_live_xxx
TWILIO_ACCOUNT_SID=xxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_PHONE_NUMBER=+44xxx
GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
EMERGENT_LLM_KEY=xxx
```

## Deploy Command
```bash
cd /app && git pull && cd /app/frontend && npm run build && fuser -k 8000/tcp; cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > /app/backend/nohup.out 2>&1 &
```
