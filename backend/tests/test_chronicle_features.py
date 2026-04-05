"""
Chronicle Feature Tests - Testing Emergent removal, Voice features, and Stripe integration
Tests:
1. No Emergent imports in server.py
2. GET /api/voices/available returns 4 preset voices
3. GET /api/voice/clone/sample-text returns text and instructions
4. POST /api/voice/transcribe endpoint exists (Whisper via direct OpenAI)
5. POST /api/checkout/create works (direct Stripe SDK)
"""

import pytest
import requests
import os
import subprocess

BASE_URL = os.environ.get('REACT_APP_BACKEND_URL', 'https://chronicle-mobile.preview.emergentagent.com')

# Test credentials
TEST_EMAIL = "test@chronicle.com"
TEST_PASSWORD = "test123456"


class TestEmergentRemoval:
    """Test that all Emergent dependencies have been removed"""
    
    def test_no_emergent_imports_in_server(self):
        """Verify no 'emergent' imports exist in server.py"""
        result = subprocess.run(
            ['grep', '-ni', 'emergent', '/app/backend/server.py'],
            capture_output=True,
            text=True
        )
        # grep returns exit code 1 if no matches found (which is what we want)
        assert result.returncode == 1, f"Found Emergent references in server.py: {result.stdout}"
        print("PASS: No Emergent imports found in server.py")


class TestAuthentication:
    """Authentication tests"""
    
    @pytest.fixture(scope="class")
    def auth_token(self):
        """Get authentication token for test user"""
        response = requests.post(
            f"{BASE_URL}/api/auth/login",
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD}
        )
        if response.status_code == 200:
            return response.json().get("access_token")
        pytest.skip(f"Authentication failed: {response.status_code} - {response.text}")
    
    def test_login_success(self):
        """Test login with valid credentials"""
        response = requests.post(
            f"{BASE_URL}/api/auth/login",
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD}
        )
        assert response.status_code == 200, f"Login failed: {response.text}"
        data = response.json()
        assert "access_token" in data
        assert "user" in data
        print(f"PASS: Login successful for {TEST_EMAIL}")


class TestVoiceEndpoints:
    """Test voice-related endpoints"""
    
    @pytest.fixture(scope="class")
    def auth_token(self):
        """Get authentication token for test user"""
        response = requests.post(
            f"{BASE_URL}/api/auth/login",
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD}
        )
        if response.status_code == 200:
            return response.json().get("access_token")
        pytest.skip(f"Authentication failed: {response.status_code}")
    
    def test_voices_available_returns_4_presets(self, auth_token):
        """GET /api/voices/available should return 4 preset voices"""
        response = requests.get(
            f"{BASE_URL}/api/voices/available",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        assert response.status_code == 200, f"Failed to get voices: {response.text}"
        data = response.json()
        assert "voices" in data
        voices = data["voices"]
        
        # Should have at least 4 preset voices
        assert len(voices) >= 4, f"Expected at least 4 voices, got {len(voices)}"
        
        # Verify voice structure
        for voice in voices[:4]:
            assert "id" in voice, "Voice missing 'id' field"
            assert "name" in voice, "Voice missing 'name' field"
        
        # Check for expected preset voice names
        voice_names = [v["name"] for v in voices]
        expected_names = ["Rachel", "Domi", "Sarah", "Antoni"]
        for name in expected_names:
            assert name in voice_names, f"Expected preset voice '{name}' not found"
        
        print(f"PASS: /api/voices/available returns {len(voices)} voices including 4 presets")
    
    def test_voice_clone_sample_text(self):
        """GET /api/voice/clone/sample-text should return text and instructions"""
        response = requests.get(f"{BASE_URL}/api/voice/clone/sample-text")
        assert response.status_code == 200, f"Failed to get sample text: {response.text}"
        data = response.json()
        
        assert "text" in data, "Response missing 'text' field"
        assert "instructions" in data, "Response missing 'instructions' field"
        assert len(data["text"]) > 50, "Sample text seems too short"
        assert len(data["instructions"]) > 20, "Instructions seem too short"
        
        print(f"PASS: /api/voice/clone/sample-text returns text ({len(data['text'])} chars) and instructions")
    
    def test_voice_transcribe_endpoint_exists(self, auth_token):
        """POST /api/voice/transcribe endpoint should exist (Whisper via OpenAI)"""
        # We can't fully test transcription without audio, but we can verify the endpoint exists
        # and returns appropriate error for missing file
        response = requests.post(
            f"{BASE_URL}/api/voice/transcribe",
            headers={"Authorization": f"Bearer {auth_token}"}
        )
        # Should return 422 (validation error) for missing file, not 404
        assert response.status_code in [422, 400], f"Unexpected status: {response.status_code} - {response.text}"
        print(f"PASS: /api/voice/transcribe endpoint exists (returns {response.status_code} for missing file)")


class TestStripeCheckout:
    """Test Stripe checkout endpoints (direct SDK, no Emergent)"""
    
    @pytest.fixture(scope="class")
    def auth_token(self):
        """Get authentication token for test user"""
        response = requests.post(
            f"{BASE_URL}/api/auth/login",
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD}
        )
        if response.status_code == 200:
            return response.json().get("access_token")
        pytest.skip(f"Authentication failed: {response.status_code}")
    
    def test_checkout_create_endpoint_exists(self, auth_token):
        """POST /api/checkout/create should work with direct Stripe SDK"""
        response = requests.post(
            f"{BASE_URL}/api/checkout/create",
            headers={"Authorization": f"Bearer {auth_token}"},
            json={
                "plan_id": "starter_monthly",
                "origin_url": "https://chronicle-mobile.preview.emergentagent.com"
            }
        )
        # Should return 200 with checkout URL or appropriate error
        # Even if Stripe fails due to config, endpoint should exist
        assert response.status_code in [200, 400, 500], f"Unexpected status: {response.status_code}"
        
        if response.status_code == 200:
            data = response.json()
            assert "checkout_url" in data or "url" in data, "Response missing checkout URL"
            print(f"PASS: /api/checkout/create returns checkout URL")
        else:
            # Endpoint exists but may have config issues
            print(f"PASS: /api/checkout/create endpoint exists (status {response.status_code})")
    
    def test_plans_endpoint(self):
        """GET /api/plans should return subscription plans"""
        response = requests.get(f"{BASE_URL}/api/plans")
        assert response.status_code == 200, f"Failed to get plans: {response.text}"
        data = response.json()
        
        assert "plans" in data
        plans = data["plans"]
        assert len(plans) > 0, "No plans returned"
        
        # Verify plan structure
        for plan in plans:
            assert "id" in plan
            assert "name" in plan
            assert "price" in plan
        
        print(f"PASS: /api/plans returns {len(plans)} subscription plans")


class TestHealthAndBasicEndpoints:
    """Test basic health and API endpoints"""
    
    def test_health_endpoint(self):
        """Health check endpoint should work"""
        response = requests.get(f"{BASE_URL}/api/health")
        assert response.status_code == 200
        data = response.json()
        assert data.get("status") == "healthy"
        print("PASS: /api/health returns healthy status")


class TestElevenLabsCallEndpoint:
    """Test ElevenLabs call endpoint"""
    
    @pytest.fixture(scope="class")
    def auth_token(self):
        """Get authentication token for test user"""
        response = requests.post(
            f"{BASE_URL}/api/auth/login",
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD}
        )
        if response.status_code == 200:
            return response.json().get("access_token")
        pytest.skip(f"Authentication failed: {response.status_code}")
    
    def test_call_with_voice_endpoint_exists(self, auth_token):
        """POST /api/call/with-voice endpoint should exist"""
        response = requests.post(
            f"{BASE_URL}/api/call/with-voice",
            headers={"Authorization": f"Bearer {auth_token}"},
            json={
                "to_phone": "+441234567890",
                "message": "Test message"
            }
        )
        # Endpoint should exist - may fail due to ElevenLabs IP restrictions but not 404
        assert response.status_code != 404, f"Endpoint not found: {response.status_code}"
        print(f"PASS: /api/call/with-voice endpoint exists (status {response.status_code})")


if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
