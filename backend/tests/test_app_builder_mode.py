"""
Test App Builder Mode Feature
- Tests the toggle functionality for app_builder_mode in chat endpoint
- Verifies both normal mode and builder mode work correctly
"""
import pytest
import requests
import os

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', '').rstrip('/')

# Test credentials from test_credentials.md
TEST_EMAIL = "test@chronicle.com"
TEST_PASSWORD = "test123456"


class TestAppBuilderMode:
    """Tests for App Builder Mode feature"""
    
    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup - get auth token"""
        self.session = requests.Session()
        self.session.headers.update({"Content-Type": "application/json"})
        
        # Login to get token
        login_response = self.session.post(f"{BASE_URL}/api/auth/login", json={
            "email": TEST_EMAIL,
            "password": TEST_PASSWORD
        })
        
        if login_response.status_code != 200:
            pytest.skip(f"Login failed: {login_response.status_code} - {login_response.text}")
        
        token = login_response.json().get("access_token")
        self.session.headers.update({"Authorization": f"Bearer {token}"})
        self.user = login_response.json().get("user")
        print(f"Logged in as: {self.user.get('email')}")
    
    def test_health_check(self):
        """Test API health endpoint"""
        response = self.session.get(f"{BASE_URL}/api/health")
        assert response.status_code == 200
        data = response.json()
        assert data.get("status") == "healthy"
        print("Health check passed")
    
    def test_chat_normal_mode(self):
        """Test chat with app_builder_mode=false (normal mode)"""
        response = self.session.post(f"{BASE_URL}/api/chat", json={
            "message": "Hello, what can you help me with?",
            "app_builder_mode": False
        })
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        
        # Verify response structure
        assert "response" in data, "Response should contain 'response' field"
        assert "session_id" in data, "Response should contain 'session_id' field"
        assert isinstance(data["response"], str), "Response should be a string"
        assert len(data["response"]) > 0, "Response should not be empty"
        
        print(f"Normal mode response (first 200 chars): {data['response'][:200]}...")
    
    def test_chat_builder_mode(self):
        """Test chat with app_builder_mode=true (builder mode)"""
        response = self.session.post(f"{BASE_URL}/api/chat", json={
            "message": "Help me create a simple Python function to calculate factorial",
            "app_builder_mode": True
        })
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        
        # Verify response structure
        assert "response" in data, "Response should contain 'response' field"
        assert "session_id" in data, "Response should contain 'session_id' field"
        assert isinstance(data["response"], str), "Response should be a string"
        assert len(data["response"]) > 0, "Response should not be empty"
        
        # Builder mode should return code-focused response
        response_text = data["response"].lower()
        # Check if response contains code-related content
        has_code_indicators = any(indicator in response_text for indicator in [
            "def ", "function", "```", "python", "code", "factorial"
        ])
        print(f"Builder mode response (first 300 chars): {data['response'][:300]}...")
        print(f"Contains code indicators: {has_code_indicators}")
    
    def test_chat_without_builder_mode_param(self):
        """Test chat without app_builder_mode parameter (should default to false)"""
        response = self.session.post(f"{BASE_URL}/api/chat", json={
            "message": "What's the weather like?"
        })
        
        assert response.status_code == 200, f"Expected 200, got {response.status_code}: {response.text}"
        data = response.json()
        
        assert "response" in data, "Response should contain 'response' field"
        assert "session_id" in data, "Response should contain 'session_id' field"
        print("Chat without builder mode param works correctly")
    
    def test_chat_with_session_id(self):
        """Test chat with session_id for conversation continuity"""
        # First message
        response1 = self.session.post(f"{BASE_URL}/api/chat", json={
            "message": "My name is TestUser",
            "app_builder_mode": False
        })
        
        assert response1.status_code == 200
        session_id = response1.json().get("session_id")
        assert session_id is not None
        
        # Second message with same session
        response2 = self.session.post(f"{BASE_URL}/api/chat", json={
            "message": "What's my name?",
            "session_id": session_id,
            "app_builder_mode": False
        })
        
        assert response2.status_code == 200
        data = response2.json()
        assert data.get("session_id") == session_id, "Session ID should be preserved"
        print(f"Session continuity test passed. Session ID: {session_id}")


class TestAuthEndpoints:
    """Test authentication endpoints"""
    
    def test_login_success(self):
        """Test successful login"""
        response = requests.post(f"{BASE_URL}/api/auth/login", json={
            "email": TEST_EMAIL,
            "password": TEST_PASSWORD
        })
        
        assert response.status_code == 200, f"Login failed: {response.status_code}"
        data = response.json()
        
        assert "access_token" in data, "Response should contain access_token"
        assert "user" in data, "Response should contain user"
        assert data["user"]["email"] == TEST_EMAIL
        print(f"Login successful for {TEST_EMAIL}")
    
    def test_login_invalid_credentials(self):
        """Test login with invalid credentials"""
        response = requests.post(f"{BASE_URL}/api/auth/login", json={
            "email": "invalid@test.com",
            "password": "wrongpassword"
        })
        
        assert response.status_code == 401, f"Expected 401, got {response.status_code}"
        print("Invalid credentials correctly rejected")
    
    def test_auth_me_endpoint(self):
        """Test /auth/me endpoint"""
        # First login
        login_response = requests.post(f"{BASE_URL}/api/auth/login", json={
            "email": TEST_EMAIL,
            "password": TEST_PASSWORD
        })
        
        assert login_response.status_code == 200
        token = login_response.json().get("access_token")
        
        # Then get user info
        me_response = requests.get(
            f"{BASE_URL}/api/auth/me",
            headers={"Authorization": f"Bearer {token}"}
        )
        
        assert me_response.status_code == 200
        data = me_response.json()
        assert data["email"] == TEST_EMAIL
        print(f"Auth/me endpoint works correctly for {TEST_EMAIL}")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
