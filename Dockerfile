FROM python:3.11-slim

WORKDIR /app

# Install Node.js for frontend build
RUN apt-get update && apt-get install -y curl && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g yarn && \
    rm -rf /var/lib/apt/lists/*

# Copy backend and install dependencies
COPY backend/requirements.txt backend/requirements.txt
RUN pip install --no-cache-dir -r backend/requirements.txt

# Copy frontend and build
COPY frontend frontend
WORKDIR /app/frontend
RUN yarn install && yarn build

# Copy backend code
WORKDIR /app
COPY backend backend

# Serve frontend static files from backend
RUN cp -r frontend/build backend/static

WORKDIR /app/backend

EXPOSE 8080

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8080"]
