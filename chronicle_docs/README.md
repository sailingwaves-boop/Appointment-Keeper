# Chronicle App - Complete Documentation

## Server Details
- VPS: Vultr (45.32.176.250)
- Domain: chroniclehelper.com / www.chroniclehelper.com
- OS: Ubuntu

## Tech Stack
- Backend: FastAPI (Python) on port 8000
- Frontend: React (built to static files)
- Database: MongoDB
- Process: uvicorn via nohup

## Directory Structure
```
/app/
├── backend/
│   ├── server.py (main FastAPI app)
│   ├── .env (environment variables)
│   ├── static/ (frontend build files go here)
│   └── nohup.out (server logs)
├── frontend/
│   ├── src/
│   │   ├── App.js (main React app)
│   │   └── App.css (styles)
│   ├── build/ (compiled frontend)
│   └── public/
│       ├── manifest.json (PWA config)
│       └── service-worker.js
```

## Environment Variables (backend/.env)
```
MONGO_URL=your_mongodb_connection_string
DB_NAME=chronicle
STRIPE_API_KEY=sk_live_xxx
TWILIO_ACCOUNT_SID=xxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_PHONE_NUMBER=+44xxx
GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
EMERGENT_LLM_KEY=xxx
```

## Deploy Command
After making changes:
```bash
cd /app && git pull && cd /app/frontend && npm run build && cp -r /app/frontend/build/* /app/backend/static/ && fuser -k 8000/tcp; cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > nohup.out 2>&1 &
```

## Check Server Logs
```bash
tail -50 /app/backend/nohup.out
```

## Check Server Status
```bash
ps aux | grep uvicorn
```

## Restart Server Only
```bash
fuser -k 8000/tcp; cd /app/backend && nohup python3 -m uvicorn server:app --host 0.0.0.0 --port 8000 > nohup.out 2>&1 &
```
