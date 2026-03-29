# API Endpoints

## Authentication
- POST /api/auth/register - Sign up
- POST /api/auth/login - Sign in
- POST /api/auth/google/token - Google OAuth
- GET /api/auth/me - Current user

## Chat
- POST /api/chat - Send message
- GET /api/chat/sessions - Get all sessions
- GET /api/chat/history - Get chat history

## Memory
- GET /api/memory - Get all memories
- POST /api/memory - Save memory
- DELETE /api/memory/{id} - Delete memory

## Contacts
- GET /api/contacts - Get all contacts
- POST /api/contacts - Add contact
- PUT /api/contacts/{id} - Update contact
- DELETE /api/contacts/{id} - Delete contact

## Voice
- POST /api/voice/transcribe - Transcribe audio (Whisper)
- GET /api/voice/packs - Get voice packs
- POST /api/voice/purchase - Buy voice pack
- GET /api/voice/balance - Check minutes

## SMS
- POST /api/sms/send - Send SMS
- GET /api/sms/history - Get SMS history

## Calls
- POST /api/call/make - Make call
- POST /api/admin/test-call - Test call (admin only)

## Subscription
- GET /api/subscription/status - Check status
- POST /api/trial/setup - Start trial
- GET /api/subscription/plans - Get plans

## Admin
- GET /api/admin/magic-links - Get magic links
- POST /api/admin/magic-link - Create magic link
