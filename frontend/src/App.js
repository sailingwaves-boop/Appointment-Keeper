import React, { useState, useEffect, createContext, useContext, useRef } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import { Toaster, toast } from 'sonner';
import { 
  MessageSquare, 
  User, 
  LogOut, 
  Send, 
  Brain, 
  Phone, 
  Users, 
  Settings,
  Plus,
  Trash2,
  Menu,
  X,
  Shield,
  CheckCircle,
  CreditCard,
  Zap,
  Crown,
  Check,
  Download,
  Mic,
  MicOff,
  Link,
  UserX,
  UserCheck,
  ArrowUp,
  Camera,
  Paperclip,
  Volume2,
  Code,
  Copy
} from 'lucide-react';
import './App.css';

const API_URL = process.env.REACT_APP_BACKEND_URL;
const API = API_URL;

// Voice Input Component
const VoiceInput = ({ onTranscription, disabled }) => {
  const [isRecording, setIsRecording] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [audioLevel, setAudioLevel] = useState(0);
  const mediaRecorderRef = useRef(null);
  const chunksRef = useRef([]);
  const audioContextRef = useRef(null);
  const analyserRef = useRef(null);
  const silenceIntervalRef = useRef(null);
  const lastSoundTimeRef = useRef(null);
  const streamRef = useRef(null);
  const isRecordingRef = useRef(false);
  const lastTapRef = useRef(0);

  const stopRecording = () => {
    isRecordingRef.current = false;
    setAudioLevel(0);
    if (silenceIntervalRef.current) {
      clearInterval(silenceIntervalRef.current);
      silenceIntervalRef.current = null;
    }
    if (mediaRecorderRef.current && mediaRecorderRef.current.state === 'recording') {
      mediaRecorderRef.current.stop();
    }
    setIsRecording(false);
  };

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      streamRef.current = stream;
      
      // Check supported mime types for mobile compatibility
      let mimeType = 'audio/webm';
      if (!MediaRecorder.isTypeSupported('audio/webm')) {
        if (MediaRecorder.isTypeSupported('audio/mp4')) {
          mimeType = 'audio/mp4';
        } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
          mimeType = 'audio/ogg';
        } else {
          mimeType = '';
        }
      }
      
      const options = mimeType ? { mimeType } : {};
      mediaRecorderRef.current = new MediaRecorder(stream, options);
      chunksRef.current = [];
      isRecordingRef.current = true;

      // Set up audio analysis for silence detection and visual feedback
      audioContextRef.current = new (window.AudioContext || window.webkitAudioContext)();
      analyserRef.current = audioContextRef.current.createAnalyser();
      const source = audioContextRef.current.createMediaStreamSource(stream);
      source.connect(analyserRef.current);
      analyserRef.current.fftSize = 256;
      
      lastSoundTimeRef.current = Date.now();

      // Check for silence every 100ms
      silenceIntervalRef.current = setInterval(() => {
        if (!isRecordingRef.current || !analyserRef.current) {
          clearInterval(silenceIntervalRef.current);
          return;
        }
        
        const dataArray = new Uint8Array(analyserRef.current.frequencyBinCount);
        analyserRef.current.getByteFrequencyData(dataArray);
        
        // Calculate average volume
        const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
        
        // Update visual audio level (0-100)
        setAudioLevel(Math.min(100, average * 2));
        
        if (average > 10) {
          // Sound detected
          lastSoundTimeRef.current = Date.now();
        } else {
          // Silence - check if 1.2 seconds passed
          if (Date.now() - lastSoundTimeRef.current > 1200) {
            stopRecording();
          }
        }
      }, 100);

      mediaRecorderRef.current.ondataavailable = (e) => {
        if (e.data.size > 0) {
          chunksRef.current.push(e.data);
        }
      };

      mediaRecorderRef.current.onstop = async () => {
        // Clear silence detection
        if (silenceIntervalRef.current) {
          clearInterval(silenceIntervalRef.current);
        }
        if (audioContextRef.current) {
          audioContextRef.current.close();
        }
        
        setIsProcessing(true);
        const blob = new Blob(chunksRef.current, { type: mimeType || 'audio/webm' });
        
        const formData = new FormData();
        formData.append('audio', blob, 'recording.webm');

        try {
          const token = localStorage.getItem('token');
          const response = await axios.post(`${API}/api/voice/transcribe`, formData, {
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'multipart/form-data'
            }
          });
          if (response.data.text) {
            onTranscription(response.data.text);
          }
        } catch (err) {
          toast.error('Failed to transcribe audio');
        } finally {
          setIsProcessing(false);
        }

        if (streamRef.current) {
          streamRef.current.getTracks().forEach(track => track.stop());
        }
      };

      mediaRecorderRef.current.start(100);
      setIsRecording(true);
    } catch (err) {
      toast.error('Microphone access denied');
    }
  };

  const handleTap = (e) => {
    e.preventDefault();
    
    // Debounce - ignore taps within 300ms of each other
    const now = Date.now();
    if (now - lastTapRef.current < 300) {
      return;
    }
    lastTapRef.current = now;
    
    if (isRecording) {
      stopRecording();
    } else {
      startRecording();
    }
  };

  // Calculate glow intensity based on audio level
  const glowStyle = isRecording ? {
    boxShadow: `0 0 ${10 + audioLevel / 5}px ${audioLevel / 10}px rgba(239, 68, 68, ${0.3 + audioLevel / 200})`
  } : {};

  return (
    <button
      type="button"
      className={`voice-input-btn ${isRecording ? 'recording' : ''} ${isProcessing ? 'processing' : ''}`}
      onTouchEnd={handleTap}
      onClick={handleTap}
      disabled={disabled || isProcessing}
      style={glowStyle}
      data-testid="voice-input-btn"
    >
      {isProcessing ? (
        <div className="voice-spinner" />
      ) : (
        <Mic size={20} />
      )}
    </button>
  );
};

// Install Button Component - only shows in browser, not when installed as app
const InstallButton = () => {
  const [installPrompt, setInstallPrompt] = useState(null);
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
      setIsInstalled(true);
      return;
    }

    // Listen for install prompt
    const handleBeforeInstall = (e) => {
      e.preventDefault();
      setInstallPrompt(e);
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstall);
    return () => window.removeEventListener('beforeinstallprompt', handleBeforeInstall);
  }, []);

  const handleInstall = async () => {
    if (!installPrompt) return;
    installPrompt.prompt();
    const result = await installPrompt.userChoice;
    if (result.outcome === 'accepted') {
      setIsInstalled(true);
    }
    setInstallPrompt(null);
  };

  if (isInstalled) return null;

  return (
    <button 
      className="install-app-btn"
      onClick={handleInstall}
      data-testid="install-app-btn"
    >
      <Download size={18} />
      Install Chronicle App
    </button>
  );
};

// Auth Context
const AuthContext = createContext(null);

const useAuth = () => useContext(AuthContext);

const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(localStorage.getItem('token'));
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // If returning from OAuth callback with code param, skip the /me check.
    // AuthCallback will exchange the code and establish the session first.
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('code')) {
      setLoading(false);
      return;
    }
    
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      fetchUser();
    } else {
      setLoading(false);
    }
  }, [token]);

  const fetchUser = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/auth/me`);
      setUser(res.data);
    } catch (err) {
      logout();
    } finally {
      setLoading(false);
    }
  };

  const login = async (email, password) => {
    const res = await axios.post(`${API_URL}/api/auth/login`, { email, password });
    localStorage.setItem('token', res.data.access_token);
    setToken(res.data.access_token);
    setUser(res.data.user);
    return res.data;
  };

  const register = async (email, password, name) => {
    const res = await axios.post(`${API_URL}/api/auth/register`, { email, password, name });
    localStorage.setItem('token', res.data.access_token);
    setToken(res.data.access_token);
    setUser(res.data.user);
    return res.data;
  };

  const loginWithGoogle = async (code, redirectUri) => {
    const res = await axios.post(`${API_URL}/api/auth/google/token`, { 
      code: code,
      redirect_uri: redirectUri 
    });
    localStorage.setItem('token', res.data.session_token);
    setToken(res.data.session_token);
    setUser(res.data.user);
    return res.data;
  };

  const logout = () => {
    localStorage.removeItem('token');
    setToken(null);
    setUser(null);
    delete axios.defaults.headers.common['Authorization'];
  };

  const updateUser = (updates) => {
    setUser(prev => ({ ...prev, ...updates }));
  };

  return (
    <AuthContext.Provider value={{ user, token, login, register, loginWithGoogle, logout, loading, updateUser }}>
      {children}
    </AuthContext.Provider>
  );
};

// Login/Register Page
const AuthPage = () => {
  const [isLogin, setIsLogin] = useState(true);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showForgotPassword, setShowForgotPassword] = useState(false);
  const [resetEmail, setResetEmail] = useState('');
  const [resetSending, setResetSending] = useState(false);
  const { login, register } = useAuth();
  const navigate = useNavigate();

  const handleForgotPassword = async (e) => {
    e.preventDefault();
    setResetSending(true);
    try {
      await axios.post(`${API}/api/auth/forgot-password`, { email: resetEmail });
      toast.success('Password reset link sent to your email');
      setShowForgotPassword(false);
      setResetEmail('');
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to send reset link');
    } finally {
      setResetSending(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      if (isLogin) {
        await login(email, password);
      } else {
        await register(email, password, name);
      }
      toast.success(isLogin ? 'Welcome back!' : 'Account created!');
      navigate('/');
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Something went wrong');
    } finally {
      setSubmitting(false);
    }
  };

  const handleGoogleLogin = () => {
    // Direct Google OAuth - YOUR credentials, YOUR branding
    const clientId = '336164855084-jse1r3a4o1t45kv7c4813h2hhqn6b2mk.apps.googleusercontent.com';
    const redirectUri = window.location.origin + '/auth/google/callback';
    const scope = 'email profile';
    
    const googleAuthUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
      `client_id=${clientId}` +
      `&redirect_uri=${encodeURIComponent(redirectUri)}` +
      `&response_type=code` +
      `&scope=${encodeURIComponent(scope)}` +
      `&access_type=offline` +
      `&prompt=consent`;
    
    window.location.href = googleAuthUrl;
  };

  return (
    <div className="auth-page">
      <div className="auth-container">
        <div className="auth-header">
          <Brain className="auth-logo" />
          <h1>Chronicle</h1>
          <p>The AI that never forgets</p>
        </div>

        <button 
          onClick={handleGoogleLogin}
          className="google-btn"
          type="button"
          data-testid="google-login-btn"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
            <path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18l-2.909-2.26c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/>
            <path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
            <path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.002 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/>
          </svg>
          Continue with Google
        </button>

        <p className="trial-text" data-testid="trial-text">
          <Zap size={14} /> 10-day free trial
        </p>

        <div className="auth-divider">
          <span>or</span>
        </div>

        <form onSubmit={handleSubmit} className="auth-form">
          {!isLogin && (
            <div className="form-group">
              <label>Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Your name"
                required={!isLogin}
                data-testid="register-name-input"
              />
            </div>
          )}
          
          <div className="form-group">
            <label>Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              required
              data-testid="auth-email-input"
            />
          </div>

          <div className="form-group">
            <label>Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              required
              minLength={6}
              data-testid="auth-password-input"
            />
          </div>

          <button 
            type="submit" 
            className="auth-button"
            disabled={submitting}
            data-testid="auth-submit-btn"
          >
            {submitting ? 'Please wait...' : (isLogin ? 'Sign In' : 'Create Account')}
          </button>
        </form>

        {isLogin && (
          <button 
            className="forgot-password-btn"
            onClick={() => setShowForgotPassword(true)}
            data-testid="forgot-password-btn"
          >
            Forgot your password?
          </button>
        )}

        <p className="auth-switch">
          {isLogin ? "Don't have an account? " : "Already have an account? "}
          <button 
            onClick={() => setIsLogin(!isLogin)}
            data-testid="auth-switch-btn"
          >
            {isLogin ? 'Sign up' : 'Sign in'}
          </button>
        </p>

        <InstallButton />
      </div>

      {showForgotPassword && (
        <div className="modal-overlay" onClick={() => setShowForgotPassword(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h2>Reset Password</h2>
            <p>Enter your email and we'll send you a reset link</p>
            <form onSubmit={handleForgotPassword}>
              <div className="form-group">
                <input
                  type="email"
                  value={resetEmail}
                  onChange={(e) => setResetEmail(e.target.value)}
                  placeholder="Your email address"
                  required
                  data-testid="reset-email-input"
                />
              </div>
              <button 
                type="submit" 
                className="auth-button"
                disabled={resetSending}
              >
                {resetSending ? 'Sending...' : 'Send Reset Link'}
              </button>
            </form>
            <button 
              className="modal-close"
              onClick={() => setShowForgotPassword(false)}
            >
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

// Google OAuth Callback
const AuthCallback = () => {
  const { loginWithGoogle } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const hasProcessed = React.useRef(false);

  useEffect(() => {
    // Prevent double processing in StrictMode
    if (hasProcessed.current) return;
    hasProcessed.current = true;

    const processCallback = async () => {
      // Get authorization code from URL query params (Google's response)
      const code = searchParams.get('code');
      const error = searchParams.get('error');
      
      if (error) {
        toast.error('Google sign-in was cancelled');
        navigate('/auth');
        return;
      }
      
      if (!code) {
        toast.error('Authentication failed - no authorization code');
        navigate('/auth');
        return;
      }

      const redirectUri = window.location.origin + '/auth/google/callback';

      try {
        const result = await loginWithGoogle(code, redirectUri);
        toast.success('Welcome!');
        
        // Check if user needs to accept disclosure
        if (!result.user.disclosure_accepted) {
          navigate('/disclosure');
        } else {
          navigate('/');
        }
      } catch (err) {
        console.error('Google auth error:', err);
        toast.error(err.response?.data?.detail || 'Google sign-in failed');
        navigate('/auth');
      }
    };

    processCallback();
  }, [loginWithGoogle, navigate, searchParams]);

  return (
    <div className="loading-screen">
      <Brain size={48} className="spin" />
      <p>Signing you in...</p>
    </div>
  );
};

// Disclosure Page
const DisclosurePage = () => {
  const [accepted, setAccepted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const { user, updateUser } = useAuth();
  const navigate = useNavigate();

  const handleAccept = async () => {
    if (!accepted) {
      toast.error('Please check the box to accept the terms');
      return;
    }
    
    setSubmitting(true);
    try {
      await axios.post(`${API_URL}/api/disclosure/accept`, { accepted: true });
      updateUser({ disclosure_accepted: true });
      toast.success('Terms accepted!');
      navigate('/');
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to accept terms');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="disclosure-page">
      <div className="disclosure-container">
        <div className="disclosure-header">
          <Shield className="disclosure-icon" />
          <h1>Terms & Disclosure</h1>
          <p>Please read and accept before using Chronicle</p>
        </div>

        <div className="disclosure-content">
          <h2>Data Storage & Privacy</h2>
          <ul>
            <li>Chronicle stores your conversations and personal information to provide persistent memory features.</li>
            <li>Your data is stored securely and is only accessible by you.</li>
            <li>We do not sell or share your personal information with third parties.</li>
            <li>You can delete your data at any time from the settings page.</li>
          </ul>

          <h2>AI Limitations</h2>
          <ul>
            <li>Chronicle is an artificial intelligence and may make mistakes.</li>
            <li>Always verify important information independently.</li>
            <li>Do not rely on Chronicle for medical, legal, or financial advice.</li>
          </ul>

          <h2>Phone & Messaging Features</h2>
          <ul>
            <li>If you use phone/SMS features, standard carrier rates may apply.</li>
            <li>You are responsible for ensuring you have consent to contact others.</li>
            <li>Chronicle will send messages and make calls on your behalf as instructed.</li>
          </ul>

          <h2>Subscription & Billing</h2>
          <ul>
            <li>Some features require a paid subscription.</li>
            <li>You can cancel your subscription at any time.</li>
            <li>Refunds are handled according to our refund policy.</li>
          </ul>
        </div>

        <div className="disclosure-accept">
          <label className="checkbox-label">
            <input
              type="checkbox"
              checked={accepted}
              onChange={(e) => setAccepted(e.target.checked)}
              data-testid="disclosure-checkbox"
            />
            <span>I have read and agree to the terms above</span>
          </label>

          <button
            onClick={handleAccept}
            disabled={!accepted || submitting}
            className="accept-button"
            data-testid="disclosure-accept-btn"
          >
            {submitting ? 'Processing...' : 'Accept & Continue'}
          </button>
        </div>
      </div>
    </div>
  );
};

// Trial Setup Page - Collect card details before using the app
const TrialSetupPage = () => {
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [processingPlan, setProcessingPlan] = useState(null);
  const { user } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // If user already has card on file or is subscribed, redirect to dashboard
    if (user?.card_on_file || user?.is_subscribed) {
      navigate('/');
      return;
    }
    fetchPlans();
  }, [user, navigate]);

  const fetchPlans = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/plans`);
      // Only show monthly plans for trial setup
      const monthlyPlans = res.data.plans.filter(p => p.interval === 'month');
      setPlans(monthlyPlans);
    } catch (err) {
      toast.error('Failed to load plans');
    } finally {
      setLoading(false);
    }
  };

  const handleStartTrial = async (planId) => {
    setProcessingPlan(planId);
    try {
      const res = await axios.post(`${API_URL}/api/trial/setup`, {
        plan_id: planId,
        origin_url: window.location.origin
      });
      
      // Redirect to Stripe to collect card
      window.location.href = res.data.checkout_url;
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to start trial setup');
      setProcessingPlan(null);
    }
  };

  const getPlanIcon = (planId) => {
    if (planId.includes('business')) return <Crown size={24} />;
    if (planId.includes('pro')) return <Zap size={24} />;
    return <Brain size={24} />;
  };

  if (loading) {
    return (
      <div className="loading-screen">
        <Brain size={48} className="spin" />
        <p>Loading...</p>
      </div>
    );
  }

  return (
    <div className="trial-setup-page">
      <div className="trial-setup-container">
        <div className="trial-setup-header">
          <Brain size={48} />
          <h1>Start Your 10-Day Free Trial</h1>
          <p>Choose a plan - you won't be charged until day 11</p>
          <p className="trial-note">Card required to start trial. Cancel anytime.</p>
          <div className="trial-features-note">
            <p><Check size={14} /> Full AI assistant with memory</p>
            <p><Check size={14} /> Phone & SMS unlocks after trial</p>
          </div>
        </div>

        <div className="trial-plans-grid">
          {plans.map(plan => (
            <div key={plan.id} className={`trial-plan-card ${plan.id.includes('pro') ? 'featured' : ''}`}>
              {plan.id.includes('pro') && <div className="popular-badge">Most Popular</div>}
              <div className="plan-icon">{getPlanIcon(plan.id)}</div>
              <h3>Chronicle {plan.name}</h3>
              <div className="plan-price">
                <span className="amount">£{plan.price}</span>
                <span className="period">/month</span>
              </div>
              <p className="after-trial">Billed monthly after 10-day trial</p>
              <ul className="plan-features">
                {plan.features.map((feature, idx) => (
                  <li key={idx}><Check size={16} /> {feature}</li>
                ))}
              </ul>
              <button
                onClick={() => handleStartTrial(plan.id)}
                disabled={processingPlan !== null}
                className="trial-start-btn"
                data-testid={`trial-start-${plan.id}`}
              >
                {processingPlan === plan.id ? 'Processing...' : 'Start Free Trial'}
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

// Trial Success Page
const TrialSuccess = () => {
  const [checking, setChecking] = useState(true);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { updateUser } = useAuth();

  useEffect(() => {
    const sessionId = searchParams.get('session_id');
    if (sessionId) {
      checkTrialStatus(sessionId);
    } else {
      navigate('/trial-setup');
    }
  }, [searchParams, navigate]);

  const checkTrialStatus = async (sessionId) => {
    try {
      const res = await axios.get(`${API_URL}/api/trial/status/${sessionId}`);
      
      if (res.data.trial_active) {
        updateUser({ 
          card_on_file: true, 
          is_subscribed: true,
          trial_active: false 
        });
        toast.success('Subscription activated! Welcome to Chronicle.');
        setTimeout(() => navigate('/'), 2000);
      } else {
        // Still processing
        setTimeout(() => checkTrialStatus(sessionId), 2000);
      }
    } catch (err) {
      toast.error('Failed to verify subscription status');
      navigate('/trial-setup');
    } finally {
      setChecking(false);
    }
  };

  return (
    <div className="success-page">
      <div className="success-container">
        <CheckCircle size={64} className="success-icon" />
        <h1>Activating Your Subscription...</h1>
        <p>Please wait while we set up your account</p>
        {checking && <Brain size={32} className="spin" />}
      </div>
    </div>
  );
};

// Chat Component
const ChatView = () => {
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [sessionId, setSessionId] = useState(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [loadingHistory, setLoadingHistory] = useState(true);
  const { user } = useAuth();
  const messagesEndRef = React.useRef(null);
  const fileInputRef = React.useRef(null);
  const cameraInputRef = React.useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Load last session on mount
  useEffect(() => {
    const loadLastSession = async () => {
      try {
        // Get last session from localStorage or API
        const savedSessionId = localStorage.getItem('chronicle_session_id');
        
        // Fetch chat sessions
        const sessionsRes = await axios.get(`${API_URL}/api/chat/sessions`);
        const sessions = sessionsRes.data.sessions;
        
        if (sessions && sessions.length > 0) {
          // Use saved session if exists, otherwise use most recent
          const targetSession = savedSessionId || sessions[0]._id;
          
          // Fetch messages for this session
          const historyRes = await axios.get(`${API_URL}/api/chat/history?session_id=${targetSession}`);
          const conversations = historyRes.data.conversations;
          
          if (conversations && conversations.length > 0) {
            // Convert to message format
            const loadedMessages = [];
            conversations.reverse().forEach(conv => {
              loadedMessages.push({ role: 'user', content: conv.user_message });
              loadedMessages.push({ role: 'assistant', content: conv.assistant_response });
            });
            
            setMessages(loadedMessages);
            setSessionId(targetSession);
            localStorage.setItem('chronicle_session_id', targetSession);
          }
        }
      } catch (err) {
        console.log('No previous session found');
      } finally {
        setLoadingHistory(false);
      }
    };
    
    loadLastSession();
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Save session ID to localStorage when it changes
  useEffect(() => {
    if (sessionId) {
      localStorage.setItem('chronicle_session_id', sessionId);
    }
  }, [sessionId]);

  // Handle file selection
  const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
      setSelectedFile(file);
      if (file.type.startsWith('image/')) {
        setPreviewUrl(URL.createObjectURL(file));
      }
    }
  };

  // Handle camera capture
  const handleCameraCapture = (e) => {
    const file = e.target.files[0];
    if (file) {
      setSelectedFile(file);
      setPreviewUrl(URL.createObjectURL(file));
    }
  };

  // Clear selected file
  const clearFile = () => {
    setSelectedFile(null);
    setPreviewUrl(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
    if (cameraInputRef.current) cameraInputRef.current.value = '';
  };

  // Text-to-speech for AI responses
  const speakMessage = (text) => {
    if ('speechSynthesis' in window) {
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = 'en-GB';
      utterance.rate = 1;
      window.speechSynthesis.speak(utterance);
    } else {
      toast.error('Text-to-speech not supported');
    }
  };

  // Copy text to clipboard
  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
      toast.success('Copied to clipboard');
    }).catch(() => {
      toast.error('Failed to copy');
    });
  };

  // Parse code blocks in messages
  const parseMessage = (content) => {
    const codeBlockRegex = /```(\w+)?\n?([\s\S]*?)```/g;
    const parts = [];
    let lastIndex = 0;
    let match;

    while ((match = codeBlockRegex.exec(content)) !== null) {
      if (match.index > lastIndex) {
        parts.push({ type: 'text', content: content.slice(lastIndex, match.index) });
      }
      parts.push({ type: 'code', language: match[1] || 'text', content: match[2].trim() });
      lastIndex = match.index + match[0].length;
    }

    if (lastIndex < content.length) {
      parts.push({ type: 'text', content: content.slice(lastIndex) });
    }

    return parts.length > 0 ? parts : [{ type: 'text', content }];
  };

  const sendMessage = async (e) => {
    e.preventDefault();
    if ((!input.trim() && !selectedFile) || sending) return;

    const userMessage = input.trim();
    setInput('');
    
    // Create message with optional image
    const newMessage = { 
      role: 'user', 
      content: userMessage,
      image: previewUrl
    };
    setMessages(prev => [...prev, newMessage]);
    setSending(true);

    try {
      // If there's a file, upload it first
      let imageUrl = null;
      if (selectedFile) {
        const formData = new FormData();
        formData.append('file', selectedFile);
        try {
          const uploadRes = await axios.post(`${API_URL}/api/upload`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
          });
          imageUrl = uploadRes.data.url;
        } catch (uploadErr) {
          console.log('File upload skipped');
        }
      }

      const res = await axios.post(`${API_URL}/api/chat`, {
        message: userMessage,
        session_id: sessionId,
        image_url: imageUrl
      });
      
      if (!sessionId) {
        setSessionId(res.data.session_id);
      }
      
      setMessages(prev => [...prev, { role: 'assistant', content: res.data.response }]);
      clearFile();
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to send message');
      setMessages(prev => prev.slice(0, -1));
    } finally {
      setSending(false);
    }
  };

  // Handle Enter key to send
  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage(e);
    }
  };

  const startNewChat = () => {
    setMessages([]);
    setSessionId(null);
    localStorage.removeItem('chronicle_session_id');
    clearFile();
  };

  // Memories panel state
  const [showMemories, setShowMemories] = useState(false);
  const [memories, setMemories] = useState([]);
  const [loadingMemories, setLoadingMemories] = useState(false);

  const fetchMemories = async () => {
    setLoadingMemories(true);
    try {
      const res = await axios.get(`${API_URL}/api/memory`);
      setMemories(res.data);
    } catch (err) {
      console.log('Failed to load memories');
    } finally {
      setLoadingMemories(false);
    }
  };

  const toggleMemories = () => {
    if (!showMemories) {
      fetchMemories();
    }
    setShowMemories(!showMemories);
  };

  const insertMemory = (memory) => {
    setInput(prev => prev + (prev ? '\n' : '') + `${memory.key}: ${memory.value}`);
    setShowMemories(false);
  };

  return (
    <div className="chat-view">
      <div className="chat-header">
        <h2>Chat with Chronicle</h2>
        <div className="chat-header-actions">
          <button 
            onClick={toggleMemories} 
            className={`memory-toggle-btn ${showMemories ? 'active' : ''}`}
            title="View memories"
          >
            <Brain size={18} />
          </button>
          <button onClick={startNewChat} className="new-chat-btn" data-testid="new-chat-btn">
            <Plus size={18} />
            New Chat
          </button>
        </div>
      </div>

      {/* Memories Panel */}
      {showMemories && (
        <div className="memories-panel">
          <div className="memories-panel-header">
            <h3>Memories</h3>
            <button onClick={() => setShowMemories(false)}><X size={18} /></button>
          </div>
          {loadingMemories ? (
            <div className="loading">Loading...</div>
          ) : memories.length === 0 ? (
            <div className="empty-memories">No memories yet</div>
          ) : (
            <div className="memories-list-panel">
              {memories.map(memory => (
                <div 
                  key={memory.id} 
                  className="memory-item-panel"
                  onClick={() => insertMemory(memory)}
                  title="Click to insert into chat"
                >
                  <span className="memory-key">{memory.key}</span>
                  <span className="memory-value">{memory.value}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      <div className="messages-container">
        {messages.length === 0 ? (
          <div className="empty-chat">
            <Brain size={48} />
            <h3>Hello, {user?.name}!</h3>
            <p>I'm Chronicle, your AI with permanent memory. I can help you with:</p>
            <ul>
              <li>Coding and technical problems</li>
              <li>Planning and organization</li>
              <li>Sending texts and making calls</li>
              <li>Remembering important information</li>
            </ul>
            <p>What would you like help with?</p>
          </div>
        ) : (
          messages.map((msg, idx) => (
            <div 
              key={idx} 
              className={`message ${msg.role}`}
              data-testid={`message-${msg.role}-${idx}`}
            >
              <div className="message-avatar">
                {msg.role === 'user' ? <User size={20} /> : <Brain size={20} />}
              </div>
              <div className="message-content">
                {msg.image && (
                  <img src={msg.image} alt="Uploaded" className="message-image" />
                )}
                {parseMessage(msg.content).map((part, i) => (
                  part.type === 'code' ? (
                    <div key={i} className="code-block">
                      <div className="code-header">
                        <Code size={14} />
                        <span>{part.language}</span>
                        <button 
                          className="copy-code-btn"
                          onClick={() => copyToClipboard(part.content)}
                          title="Copy code"
                        >
                          <Copy size={14} />
                        </button>
                      </div>
                      <pre><code>{part.content}</code></pre>
                    </div>
                  ) : (
                    <pre key={i}>{part.content}</pre>
                  )
                ))}
                {msg.role === 'assistant' && (
                  <div className="message-actions">
                    <button 
                      className="copy-btn" 
                      onClick={() => copyToClipboard(msg.content)}
                      title="Copy message"
                    >
                      <Copy size={16} />
                    </button>
                    <button 
                      className="listen-btn" 
                      onClick={() => speakMessage(msg.content)}
                      title="Listen to response"
                    >
                      <Volume2 size={16} />
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))
        )}
        {sending && (
          <div className="message assistant">
            <div className="message-avatar">
              <Brain size={20} />
            </div>
            <div className="message-content typing">
              <span></span><span></span><span></span>
            </div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* File Preview */}
      {previewUrl && (
        <div className="file-preview">
          <img src={previewUrl} alt="Preview" />
          <button onClick={clearFile} className="clear-preview">
            <X size={16} />
          </button>
        </div>
      )}

      {/* Agent Status */}
      <div className={`agent-status ${sending ? 'working' : 'waiting'}`}>
        <span className="status-dot"></span>
        <span className="status-text">{sending ? 'Agent is working' : 'Agent is waiting'}</span>
      </div>

      <form onSubmit={sendMessage} className="chat-input-form">
        {/* Hidden file inputs */}
        <input
          type="file"
          ref={fileInputRef}
          onChange={handleFileSelect}
          accept="image/*,.pdf,.txt,.doc,.docx"
          style={{ display: 'none' }}
        />
        <input
          type="file"
          ref={cameraInputRef}
          onChange={handleCameraCapture}
          accept="image/*"
          capture="environment"
          style={{ display: 'none' }}
        />
        
        {/* Camera button */}
        <button 
          type="button" 
          className="input-action-btn"
          onClick={() => cameraInputRef.current?.click()}
          title="Take photo"
        >
          <Camera size={20} />
        </button>
        
        {/* File upload button */}
        <button 
          type="button" 
          className="input-action-btn"
          onClick={() => fileInputRef.current?.click()}
          title="Upload file"
        >
          <Paperclip size={20} />
        </button>
        
        {/* Voice input */}
        <VoiceInput 
          onTranscription={(text) => setInput(prev => prev + text)} 
          disabled={sending}
        />
        
        {/* Text input */}
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Type or speak..."
          disabled={sending}
          data-testid="chat-input"
        />
        
        {/* Send button with up arrow */}
        <button type="submit" disabled={sending || (!input.trim() && !selectedFile)} data-testid="send-message-btn">
          <ArrowUp size={20} />
        </button>
      </form>
    </div>
  );
};

// Contacts Component
const ContactsView = () => {
  const [contacts, setContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [newContact, setNewContact] = useState({ name: '', phone: '', email: '', notes: '' });

  useEffect(() => {
    fetchContacts();
  }, []);

  const fetchContacts = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/contacts`);
      setContacts(res.data);
    } catch (err) {
      toast.error('Failed to load contacts');
    } finally {
      setLoading(false);
    }
  };

  const addContact = async (e) => {
    e.preventDefault();
    try {
      const res = await axios.post(`${API_URL}/api/contacts`, newContact);
      setContacts(prev => [...prev, res.data]);
      setNewContact({ name: '', phone: '', email: '', notes: '' });
      setShowForm(false);
      toast.success('Contact added!');
    } catch (err) {
      toast.error('Failed to add contact');
    }
  };

  const deleteContact = async (id) => {
    if (!window.confirm('Delete this contact?')) return;
    try {
      await axios.delete(`${API_URL}/api/contacts/${id}`);
      setContacts(prev => prev.filter(c => c.id !== id));
      toast.success('Contact deleted');
    } catch (err) {
      toast.error('Failed to delete contact');
    }
  };

  return (
    <div className="contacts-view">
      <div className="view-header">
        <h2><Users size={24} /> Contacts</h2>
        <button onClick={() => setShowForm(!showForm)} className="add-btn" data-testid="add-contact-btn">
          <Plus size={18} />
          Add Contact
        </button>
      </div>

      {showForm && (
        <form onSubmit={addContact} className="contact-form">
          <input
            type="text"
            placeholder="Name"
            value={newContact.name}
            onChange={(e) => setNewContact(prev => ({ ...prev, name: e.target.value }))}
            required
            data-testid="contact-name-input"
          />
          <input
            type="tel"
            placeholder="Phone"
            value={newContact.phone}
            onChange={(e) => setNewContact(prev => ({ ...prev, phone: e.target.value }))}
            required
            data-testid="contact-phone-input"
          />
          <input
            type="email"
            placeholder="Email (optional)"
            value={newContact.email}
            onChange={(e) => setNewContact(prev => ({ ...prev, email: e.target.value }))}
            data-testid="contact-email-input"
          />
          <textarea
            placeholder="Notes (optional)"
            value={newContact.notes}
            onChange={(e) => setNewContact(prev => ({ ...prev, notes: e.target.value }))}
            data-testid="contact-notes-input"
          />
          <div className="form-buttons">
            <button type="button" onClick={() => setShowForm(false)} className="cancel-btn">Cancel</button>
            <button type="submit" data-testid="save-contact-btn">Save Contact</button>
          </div>
        </form>
      )}

      {loading ? (
        <div className="loading">Loading contacts...</div>
      ) : contacts.length === 0 ? (
        <div className="empty-state">
          <Users size={48} />
          <p>No contacts yet. Add your first contact!</p>
        </div>
      ) : (
        <div className="contacts-list">
          {contacts.map(contact => (
            <div key={contact.id} className="contact-card" data-testid={`contact-${contact.id}`}>
              <div className="contact-info">
                <h3>{contact.name}</h3>
                <p><Phone size={14} /> {contact.phone}</p>
                {contact.email && <p>{contact.email}</p>}
                {contact.notes && <p className="notes">{contact.notes}</p>}
              </div>
              <button 
                onClick={() => deleteContact(contact.id)} 
                className="delete-btn"
                data-testid={`delete-contact-${contact.id}`}
              >
                <Trash2 size={18} />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// Memory View Component
const MemoryView = () => {
  const [memories, setMemories] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchMemories();
  }, []);

  const fetchMemories = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/memory`);
      setMemories(res.data);
    } catch (err) {
      toast.error('Failed to load memories');
    } finally {
      setLoading(false);
    }
  };

  const deleteMemory = async (id) => {
    try {
      await axios.delete(`${API_URL}/api/memory/${id}`);
      setMemories(prev => prev.filter(m => m.id !== id));
      toast.success('Memory deleted');
    } catch (err) {
      toast.error('Failed to delete memory');
    }
  };

  return (
    <div className="memory-view">
      <div className="view-header">
        <h2><Brain size={24} /> What I Remember</h2>
      </div>

      {loading ? (
        <div className="loading">Loading memories...</div>
      ) : memories.length === 0 ? (
        <div className="empty-state">
          <Brain size={48} />
          <p>I don't have any stored memories yet.</p>
          <p>As we chat, I'll remember important things you tell me!</p>
        </div>
      ) : (
        <div className="memories-list">
          {memories.map(memory => (
            <div key={memory.id} className="memory-card" data-testid={`memory-${memory.id}`}>
              <div className="memory-content">
                <span className="memory-category">{memory.category}</span>
                <p><strong>{memory.key}</strong></p>
                <p>{memory.value}</p>
                <span className="memory-date">{new Date(memory.created_at).toLocaleDateString()}</span>
              </div>
              <button 
                onClick={() => deleteMemory(memory.id)} 
                className="delete-btn"
                data-testid={`delete-memory-${memory.id}`}
              >
                <Trash2 size={18} />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// Phone & SMS View
const PhoneView = () => {
  const [activeTab, setActiveTab] = useState('send');
  const [contacts, setContacts] = useState([]);
  const [subscription, setSubscription] = useState(null);
  const [callHistory, setCallHistory] = useState([]);
  const [smsHistory, setSmsHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  
  // SMS form
  const [smsPhone, setSmsPhone] = useState('');
  const [smsMessage, setSmsMessage] = useState('');
  const [smsContactName, setSmsContactName] = useState('');
  
  // Call form
  const [callPhone, setCallPhone] = useState('');
  const [callMessage, setCallMessage] = useState('');
  const [callContactName, setCallContactName] = useState('');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [contactsRes, subRes, callsRes, smsRes] = await Promise.all([
        axios.get(`${API_URL}/api/contacts`),
        axios.get(`${API_URL}/api/subscription/status`),
        axios.get(`${API_URL}/api/call/history`).catch(() => ({ data: { calls: [] } })),
        axios.get(`${API_URL}/api/sms/history`).catch(() => ({ data: { messages: [] } }))
      ]);
      setContacts(contactsRes.data);
      setSubscription(subRes.data);
      setCallHistory(callsRes.data.calls || []);
      setSmsHistory(smsRes.data.messages || []);
    } catch (err) {
      console.error('Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const selectContact = (contact, type) => {
    if (type === 'sms') {
      setSmsPhone(contact.phone);
      setSmsContactName(contact.name);
    } else {
      setCallPhone(contact.phone);
      setCallContactName(contact.name);
    }
  };

  const sendSMS = async (e) => {
    e.preventDefault();
    if (!smsPhone || !smsMessage) return;
    
    setSending(true);
    try {
      const res = await axios.post(`${API_URL}/api/sms/send`, {
        to_phone: smsPhone,
        message: smsMessage,
        contact_name: smsContactName || null
      });
      
      if (res.data.success) {
        toast.success('SMS sent successfully!');
        setSmsMessage('');
        fetchData(); // Refresh history
      } else {
        toast.error(res.data.error || 'Failed to send SMS');
      }
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to send SMS');
    } finally {
      setSending(false);
    }
  };

  const makeCall = async (e) => {
    e.preventDefault();
    if (!callPhone || !callMessage) return;
    
    setSending(true);
    try {
      const res = await axios.post(`${API_URL}/api/call/make`, {
        to_phone: callPhone,
        message: callMessage,
        contact_name: callContactName || null
      });
      
      if (res.data.success) {
        toast.success('Call initiated!');
        setCallMessage('');
        fetchData(); // Refresh history
      } else {
        toast.error(res.data.error || 'Failed to make call');
      }
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to make call');
    } finally {
      setSending(false);
    }
  };

  const canUsePhoneFeatures = subscription?.plan && !subscription?.plan.includes('starter');

  if (loading) {
    return <div className="loading">Loading...</div>;
  }

  return (
    <div className="phone-view">
      <div className="view-header">
        <h2><Phone size={24} /> Phone & SMS</h2>
        {subscription?.is_subscribed && (
          <div className="usage-badges">
            <span className="usage-badge">
              <Phone size={14} /> {subscription.call_minutes_remaining} mins
            </span>
            <span className="usage-badge">
              <MessageSquare size={14} /> {subscription.texts_remaining} texts
            </span>
          </div>
        )}
      </div>

      {!canUsePhoneFeatures ? (
        <div className="upgrade-prompt">
          <Phone size={48} />
          <h3>{user?.is_subscribed ? 'Upgrade to Pro or Business' : 'Phone & SMS Available After Trial'}</h3>
          <p>{user?.is_subscribed 
            ? 'Phone calls and SMS require a Pro or Business subscription.' 
            : 'Complete your 10-day trial to unlock Phone & SMS features.'}</p>
          <p>Pro includes 60 minutes and 100 texts per month.</p>
        </div>
      ) : (
        <>
          <div className="phone-tabs">
            <button 
              className={activeTab === 'send' ? 'active' : ''}
              onClick={() => setActiveTab('send')}
              data-testid="tab-send"
            >
              Send Message
            </button>
            <button 
              className={activeTab === 'call' ? 'active' : ''}
              onClick={() => setActiveTab('call')}
              data-testid="tab-call"
            >
              Make Call
            </button>
            <button 
              className={activeTab === 'history' ? 'active' : ''}
              onClick={() => setActiveTab('history')}
              data-testid="tab-history"
            >
              History
            </button>
          </div>

          {activeTab === 'send' && (
            <div className="phone-section">
              <div className="contacts-quick-select">
                <h4>Quick Select Contact</h4>
                <div className="contact-chips">
                  {contacts.slice(0, 5).map(contact => (
                    <button 
                      key={contact.id}
                      onClick={() => selectContact(contact, 'sms')}
                      className={smsPhone === contact.phone ? 'selected' : ''}
                    >
                      {contact.name}
                    </button>
                  ))}
                </div>
              </div>
              
              <form onSubmit={sendSMS} className="phone-form">
                <input
                  type="tel"
                  placeholder="Phone number (e.g., +44...)"
                  value={smsPhone}
                  onChange={(e) => setSmsPhone(e.target.value)}
                  required
                  data-testid="sms-phone-input"
                />
                <textarea
                  placeholder="Your message..."
                  value={smsMessage}
                  onChange={(e) => setSmsMessage(e.target.value)}
                  required
                  rows={3}
                  data-testid="sms-message-input"
                />
                <button type="submit" disabled={sending} data-testid="send-sms-btn">
                  {sending ? 'Sending...' : 'Send SMS'}
                </button>
              </form>
            </div>
          )}

          {activeTab === 'call' && (
            <div className="phone-section">
              <div className="contacts-quick-select">
                <h4>Quick Select Contact</h4>
                <div className="contact-chips">
                  {contacts.slice(0, 5).map(contact => (
                    <button 
                      key={contact.id}
                      onClick={() => selectContact(contact, 'call')}
                      className={callPhone === contact.phone ? 'selected' : ''}
                    >
                      {contact.name}
                    </button>
                  ))}
                </div>
              </div>
              
              <form onSubmit={makeCall} className="phone-form">
                <input
                  type="tel"
                  placeholder="Phone number (e.g., +44...)"
                  value={callPhone}
                  onChange={(e) => setCallPhone(e.target.value)}
                  required
                  data-testid="call-phone-input"
                />
                <textarea
                  placeholder="What should the AI say? (e.g., 'Hi John, this is a reminder about your appointment at 3pm today.')"
                  value={callMessage}
                  onChange={(e) => setCallMessage(e.target.value)}
                  required
                  rows={3}
                  data-testid="call-message-input"
                />
                <button type="submit" disabled={sending} data-testid="make-call-btn">
                  {sending ? 'Calling...' : 'Make Call'}
                </button>
              </form>
            </div>
          )}

          {activeTab === 'history' && (
            <div className="phone-section history-section">
              <h4>SMS History</h4>
              {smsHistory.length === 0 ? (
                <p className="no-history">No messages sent yet.</p>
              ) : (
                <div className="history-list">
                  {smsHistory.slice(0, 10).map(msg => (
                    <div key={msg.id} className="history-item">
                      <span className="history-icon"><MessageSquare size={16} /></span>
                      <div className="history-details">
                        <strong>{msg.contact_name || msg.to_phone}</strong>
                        <p>{msg.message}</p>
                        <span className="history-date">{new Date(msg.created_at).toLocaleString()}</span>
                      </div>
                      <span className={`status-badge ${msg.status}`}>{msg.status}</span>
                    </div>
                  ))}
                </div>
              )}

              <h4>Call History</h4>
              {callHistory.length === 0 ? (
                <p className="no-history">No calls made yet.</p>
              ) : (
                <div className="history-list">
                  {callHistory.slice(0, 10).map(call => (
                    <div key={call.id} className="history-item">
                      <span className="history-icon"><Phone size={16} /></span>
                      <div className="history-details">
                        <strong>{call.contact_name || call.to_phone}</strong>
                        <p>{call.message?.substring(0, 50)}...</p>
                        <span className="history-date">
                          {new Date(call.created_at).toLocaleString()}
                          {call.duration && ` • ${call.duration}s`}
                        </span>
                      </div>
                      <span className={`status-badge ${call.status}`}>{call.status}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
};

// Subscription/Pricing View
const SubscriptionView = () => {
  const [plans, setPlans] = useState([]);
  const [subscription, setSubscription] = useState(null);
  const [loading, setLoading] = useState(true);
  const [processingPlan, setProcessingPlan] = useState(null);
  const [billingCycle, setBillingCycle] = useState('monthly');
  const { user } = useAuth();

  // Calculate trial days remaining
  const getTrialDaysRemaining = () => {
    if (!user?.trial_ends_at) return 0;
    const trialEnd = new Date(user.trial_ends_at);
    const now = new Date();
    const diffTime = trialEnd - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
  };

  const trialDaysRemaining = getTrialDaysRemaining();

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [plansRes, subRes] = await Promise.all([
        axios.get(`${API_URL}/api/plans`),
        axios.get(`${API_URL}/api/subscription/status`)
      ]);
      setPlans(plansRes.data.plans);
      setSubscription(subRes.data);
    } catch (err) {
      toast.error('Failed to load subscription data');
    } finally {
      setLoading(false);
    }
  };

  const handleSubscribe = async (planId) => {
    setProcessingPlan(planId);
    try {
      const res = await axios.post(`${API_URL}/api/trial/setup`, {
        plan_id: planId,
        origin_url: window.location.origin
      });
      
      // Redirect to Stripe checkout
      window.location.href = res.data.checkout_url;
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to start checkout');
      setProcessingPlan(null);
    }
  };

  const getFilteredPlans = () => {
    return plans.filter(plan => 
      billingCycle === 'monthly' ? plan.interval === 'month' : plan.interval === 'year'
    );
  };

  const getPlanIcon = (planId) => {
    if (planId.includes('business')) return <Crown size={24} />;
    if (planId.includes('pro')) return <Zap size={24} />;
    return <Brain size={24} />;
  };

  if (loading) {
    return <div className="loading">Loading plans...</div>;
  }

  return (
    <div className="subscription-view">
      <div className="subscription-header">
        <h2><CreditCard size={24} /> Subscription</h2>
        {subscription?.is_subscribed && (
          <div className="current-plan-badge">
            <CheckCircle size={16} />
            {subscription.plan_name} Plan Active
          </div>
        )}
      </div>

      {/* Trial Status Card */}
      {user?.trial_active && !user?.is_subscribed && (
        <div className="trial-status-card" data-testid="trial-status-card">
          <div className="trial-status-header">
            <Zap size={24} />
            <h3>Free Trial Active</h3>
          </div>
          <p className="trial-days-left">
            <strong>{trialDaysRemaining}</strong> day{trialDaysRemaining !== 1 ? 's' : ''} remaining
          </p>
          <p className="trial-info">
            Subscribe now to keep your memories and continue using Chronicle after your trial ends.
          </p>
        </div>
      )}

      {/* Trial Expired Card */}
      {!user?.trial_active && !user?.is_subscribed && (
        <div className="trial-expired-card" data-testid="trial-expired-card">
          <div className="trial-status-header">
            <Shield size={24} />
            <h3>Trial Ended</h3>
          </div>
          <p className="trial-info">
            Your free trial has ended, but don't worry - all your memories and data are preserved. 
            Subscribe to a plan below to continue using Chronicle.
          </p>
        </div>
      )}

      {subscription?.is_subscribed && (
        <div className="subscription-status-card">
          <h3>Your Current Plan: {subscription.plan_name}</h3>
          <div className="usage-stats">
            <div className="stat">
              <Phone size={18} />
              <span>{subscription.call_minutes_remaining} minutes remaining</span>
            </div>
            <div className="stat">
              <MessageSquare size={18} />
              <span>{subscription.texts_remaining} texts remaining</span>
            </div>
          </div>
          <p className="renewal-date">
            Renews: {new Date(subscription.subscription_ends_at).toLocaleDateString()}
          </p>
        </div>
      )}

      <div className="billing-toggle">
        <button 
          className={billingCycle === 'monthly' ? 'active' : ''}
          onClick={() => setBillingCycle('monthly')}
          data-testid="billing-monthly"
        >
          Monthly
        </button>
        <button 
          className={billingCycle === 'annual' ? 'active' : ''}
          onClick={() => setBillingCycle('annual')}
          data-testid="billing-annual"
        >
          Annual <span className="save-badge">Save 17%</span>
        </button>
      </div>

      <div className="plans-grid">
        {getFilteredPlans().map(plan => (
          <div 
            key={plan.id} 
            className={`plan-card ${plan.id.includes('pro') ? 'popular' : ''}`}
            data-testid={`plan-${plan.id}`}
          >
            {plan.id.includes('pro') && <div className="popular-badge">Most Popular</div>}
            
            <div className="plan-icon">
              {getPlanIcon(plan.id)}
            </div>
            
            <h3>{plan.name.replace(' (Annual)', '')}</h3>
            
            <div className="plan-price">
              <span className="currency">£</span>
              <span className="amount">{plan.price.toFixed(0)}</span>
              <span className="period">/{plan.interval === 'year' ? 'year' : 'mo'}</span>
            </div>

            <ul className="plan-features">
              {plan.features.map((feature, idx) => (
                <li key={idx}>
                  <Check size={16} />
                  {feature}
                </li>
              ))}
            </ul>

            <button
              onClick={() => handleSubscribe(plan.id)}
              disabled={processingPlan === plan.id || (subscription?.plan === plan.id)}
              className="subscribe-btn"
              data-testid={`subscribe-${plan.id}`}
            >
              {processingPlan === plan.id ? 'Processing...' : 
               subscription?.plan === plan.id ? 'Current Plan' : 'Subscribe'}
            </button>
          </div>
        ))}
      </div>
    </div>
  );
};

// Subscription Success Page
const SubscriptionSuccess = () => {
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState('checking');
  const [attempts, setAttempts] = useState(0);
  const navigate = useNavigate();
  const sessionId = searchParams.get('session_id');

  useEffect(() => {
    if (sessionId) {
      pollPaymentStatus();
    }
  }, [sessionId]);

  const pollPaymentStatus = async () => {
    if (attempts >= 5) {
      setStatus('timeout');
      return;
    }

    try {
      const res = await axios.get(`${API_URL}/api/checkout/status/${sessionId}`);
      
      if (res.data.payment_status === 'paid') {
        setStatus('success');
        toast.success('Payment successful! Welcome to Chronicle Pro!');
        setTimeout(() => navigate('/'), 3000);
      } else {
        setAttempts(prev => prev + 1);
        setTimeout(pollPaymentStatus, 2000);
      }
    } catch (err) {
      setStatus('error');
    }
  };

  return (
    <div className="subscription-success">
      <div className="success-container">
        {status === 'checking' && (
          <>
            <Brain size={64} className="spin" />
            <h2>Processing your payment...</h2>
            <p>Please wait while we confirm your subscription.</p>
          </>
        )}
        
        {status === 'success' && (
          <>
            <CheckCircle size={64} className="success-icon" />
            <h2>Payment Successful!</h2>
            <p>Welcome to Chronicle! Redirecting you to your dashboard...</p>
          </>
        )}
        
        {status === 'error' && (
          <>
            <X size={64} className="error-icon" />
            <h2>Something went wrong</h2>
            <p>Please contact support if you were charged.</p>
            <button onClick={() => navigate('/')} className="back-btn">
              Go to Dashboard
            </button>
          </>
        )}
        
        {status === 'timeout' && (
          <>
            <Brain size={64} />
            <h2>Taking longer than expected</h2>
            <p>Your payment may still be processing. Check your email for confirmation.</p>
            <button onClick={() => navigate('/')} className="back-btn">
              Go to Dashboard
            </button>
          </>
        )}
      </div>
    </div>
  );
};

// Main Dashboard
const Dashboard = () => {
  const [activeView, setActiveView] = useState('chat');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/auth');
  };

  // Calculate days remaining in trial
  const getTrialDaysRemaining = () => {
    if (!user?.trial_ends_at) return 0;
    const trialEnd = new Date(user.trial_ends_at);
    const now = new Date();
    const diffTime = trialEnd - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
  };

  const trialDaysRemaining = getTrialDaysRemaining();
  const showTrialBanner = user?.trial_active && !user?.is_subscribed;
  const showTrialExpiredBanner = !user?.trial_active && !user?.is_subscribed;

  const renderView = () => {
    switch (activeView) {
      case 'chat':
        return <ChatView />;
      case 'contacts':
        return <ContactsView />;
      case 'memory':
        return <MemoryView />;
      case 'phone':
        return <PhoneView />;
      case 'subscription':
        return <SubscriptionView />;
      default:
        return <ChatView />;
    }
  };

  return (
    <div className="dashboard">
      <button 
        className="mobile-menu-btn"
        onClick={() => setSidebarOpen(!sidebarOpen)}
        data-testid="mobile-menu-btn"
      >
        {sidebarOpen ? <X size={24} /> : <Menu size={24} />}
      </button>

      <aside className={`sidebar ${sidebarOpen ? 'open' : ''}`}>
        <div className="sidebar-header">
          <Brain size={32} />
          <h1>Chronicle</h1>
        </div>

        <nav className="sidebar-nav">
          <button
            className={activeView === 'chat' ? 'active' : ''}
            onClick={() => { setActiveView('chat'); setSidebarOpen(false); }}
            data-testid="nav-chat"
          >
            <MessageSquare size={20} />
            Chat
          </button>
          <button
            className={activeView === 'phone' ? 'active' : ''}
            onClick={() => { setActiveView('phone'); setSidebarOpen(false); }}
            data-testid="nav-phone"
          >
            <Phone size={20} />
            Phone & SMS
          </button>
          <button
            className={activeView === 'contacts' ? 'active' : ''}
            onClick={() => { setActiveView('contacts'); setSidebarOpen(false); }}
            data-testid="nav-contacts"
          >
            <Users size={20} />
            Contacts
          </button>
          <button
            className={activeView === 'memory' ? 'active' : ''}
            onClick={() => { setActiveView('memory'); setSidebarOpen(false); }}
            data-testid="nav-memory"
          >
            <Brain size={20} />
            Memory
          </button>
          <button
            className={activeView === 'subscription' ? 'active' : ''}
            onClick={() => { setActiveView('subscription'); setSidebarOpen(false); }}
            data-testid="nav-subscription"
          >
            <CreditCard size={20} />
            Subscription
          </button>
        </nav>

        <div className="sidebar-footer">
          <div className="user-info">
            <User size={20} />
            <span>{user?.name}</span>
          </div>
          <button onClick={handleLogout} className="logout-btn" data-testid="logout-btn">
            <LogOut size={20} />
          </button>
        </div>
        <div className="app-footer">
          Chronicle - The AI that never forgets © 2026 Useful Gadgets Ltd
        </div>
      </aside>

      <main className="main-content">
        {showTrialExpiredBanner && (
          <div className="trial-banner trial-expired" data-testid="trial-expired-banner">
            <div className="trial-banner-content">
              <span>Your free trial has ended. Subscribe to continue using Chronicle - your memories are preserved!</span>
              <button 
                onClick={() => setActiveView('subscription')}
                className="trial-subscribe-btn"
                data-testid="trial-subscribe-btn"
              >
                Subscribe Now
              </button>
            </div>
          </div>
        )}
        {showTrialBanner && (
          <div className="trial-banner trial-active" data-testid="trial-active-banner">
            <div className="trial-banner-content">
              <span>{trialDaysRemaining} day{trialDaysRemaining !== 1 ? 's' : ''} left in your free trial</span>
              <button 
                onClick={() => setActiveView('subscription')}
                className="trial-upgrade-btn"
                data-testid="trial-upgrade-btn"
              >
                Upgrade Now
              </button>
            </div>
          </div>
        )}
        {renderView()}
      </main>
    </div>
  );
};

// Protected Route
const ProtectedRoute = ({ children }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="loading-screen">
        <Brain size={48} className="spin" />
        <p>Loading...</p>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/auth" replace />;
  }

  if (!user.disclosure_accepted) {
    return <Navigate to="/disclosure" replace />;
  }

  // Require card on file to access the app (unless already subscribed)
  if (!user.card_on_file && !user.is_subscribed) {
    return <Navigate to="/trial-setup" replace />;
  }

  return children;
};

// Admin Page Component
const AdminPage = () => {
  const { user } = useAuth();
  const [links, setLinks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const navigate = useNavigate();

  const ADMIN_EMAIL = 'sailingwaves@gmail.com';

  useEffect(() => {
    if (user?.email !== ADMIN_EMAIL) {
      toast.error('Admin access required');
      navigate('/');
      return;
    }
    fetchLinks();
  }, [user, navigate]);

  const fetchLinks = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API}/api/admin/magic-links`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      setLinks(response.data.links || []);
    } catch (err) {
      toast.error('Failed to load magic links');
    } finally {
      setLoading(false);
    }
  };

  const createLink = async () => {
    setCreating(true);
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API}/api/admin/magic-link`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      });
      toast.success('Magic link created');
      navigator.clipboard.writeText(response.data.url);
      toast.success('Link copied to clipboard');
      fetchLinks();
    } catch (err) {
      toast.error('Failed to create link');
    } finally {
      setCreating(false);
    }
  };

  const revokeAccess = async (userId) => {
    try {
      const token = localStorage.getItem('token');
      await axios.post(`${API}/api/admin/revoke-access/${userId}`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      });
      toast.success('Access revoked');
      fetchLinks();
    } catch (err) {
      toast.error('Failed to revoke access');
    }
  };

  const restoreAccess = async (userId) => {
    try {
      const token = localStorage.getItem('token');
      await axios.post(`${API}/api/admin/restore-access/${userId}`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      });
      toast.success('Access restored');
      fetchLinks();
    } catch (err) {
      toast.error('Failed to restore access');
    }
  };

  if (user?.email !== ADMIN_EMAIL) {
    return null;
  }

  return (
    <div className="admin-page" data-testid="admin-page">
      <div className="admin-container">
        <div className="admin-header">
          <h1>Admin Panel</h1>
          <button onClick={() => navigate('/')} className="back-btn">Back to App</button>
        </div>

        <div className="admin-section">
          <h2>Magic Links</h2>
          <p>Create one-time invite links for free accounts</p>
          
          <button 
            onClick={createLink} 
            disabled={creating}
            className="create-link-btn"
            data-testid="create-magic-link-btn"
          >
            <Link size={18} />
            {creating ? 'Creating...' : 'Create Magic Link'}
          </button>
        </div>

        <div className="admin-section">
          <h2>Invited Users</h2>
          {loading ? (
            <p>Loading...</p>
          ) : links.length === 0 ? (
            <p>No magic links created yet</p>
          ) : (
            <div className="links-list">
              {links.map(link => (
                <div key={link.id} className="link-item">
                  <div className="link-info">
                    <span className={`link-status ${link.used ? 'used' : link.revoked ? 'revoked' : 'available'}`}>
                      {link.used ? 'Used' : link.revoked ? 'Revoked' : 'Available'}
                    </span>
                    {link.user_info && (
                      <span className="user-email">{link.user_info.email}</span>
                    )}
                    <span className="link-date">{new Date(link.created_at).toLocaleDateString()}</span>
                  </div>
                  {link.used && link.used_by && (
                    <button
                      onClick={() => link.access_revoked ? restoreAccess(link.used_by) : revokeAccess(link.used_by)}
                      className={link.access_revoked ? 'restore-btn' : 'revoke-btn'}
                    >
                      {link.access_revoked ? <UserCheck size={16} /> : <UserX size={16} />}
                      {link.access_revoked ? 'Restore' : 'Revoke'}
                    </button>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

// App Component
function App() {
  return (
    <Router>
      <AuthProvider>
        <Toaster position="top-right" richColors />
        <AppRoutes />
      </AuthProvider>
    </Router>
  );
}

// Separate component to handle routing
function AppRoutes() {
  return (
    <Routes>
      <Route path="/auth" element={<AuthPage />} />
      <Route path="/auth/google/callback" element={<AuthCallback />} />
      <Route path="/disclosure" element={<DisclosurePage />} />
      <Route path="/trial-setup" element={<TrialSetupPage />} />
      <Route path="/trial/success" element={<TrialSuccess />} />
      <Route path="/subscription/success" element={
        <ProtectedRoute>
          <SubscriptionSuccess />
        </ProtectedRoute>
      } />
      <Route path="/admin" element={
        <ProtectedRoute>
          <AdminPage />
        </ProtectedRoute>
      } />
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        }
      />
    </Routes>
  );
}

export default App;
