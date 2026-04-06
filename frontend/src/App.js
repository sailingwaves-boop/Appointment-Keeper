import React, { useState, useEffect, createContext, useContext, useRef, useCallback } from 'react';
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
  Copy,
  Globe,
  FolderOpen,
  ChevronDown
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
  const silenceTimerRef = useRef(null);
  const streamRef = useRef(null);

  const stopRecording = useCallback(() => {
    setAudioLevel(0);
    if (silenceTimerRef.current) {
      clearInterval(silenceTimerRef.current);
      silenceTimerRef.current = null;
    }
    if (mediaRecorderRef.current && mediaRecorderRef.current.state === 'recording') {
      mediaRecorderRef.current.stop();
    }
    setIsRecording(false);
  }, []);

  const startRecording = async () => {
    if (isRecording || isProcessing) return;
    
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      streamRef.current = stream;
      
      // Find supported format
      let mimeType = '';
      if (MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm';
      } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4';
      }
      
      const options = mimeType ? { mimeType } : {};
      mediaRecorderRef.current = new MediaRecorder(stream, options);
      chunksRef.current = [];

      // Audio analysis
      audioContextRef.current = new (window.AudioContext || window.webkitAudioContext)();
      analyserRef.current = audioContextRef.current.createAnalyser();
      const source = audioContextRef.current.createMediaStreamSource(stream);
      source.connect(analyserRef.current);
      analyserRef.current.fftSize = 256;
      
      let lastSoundTime = Date.now();

      silenceTimerRef.current = setInterval(() => {
        if (!analyserRef.current) return;
        
        const dataArray = new Uint8Array(analyserRef.current.frequencyBinCount);
        analyserRef.current.getByteFrequencyData(dataArray);
        const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
        
        setAudioLevel(Math.min(100, average * 2));
        
        if (average > 8) {
          lastSoundTime = Date.now();
        } else if (Date.now() - lastSoundTime > 2000) {
          stopRecording();
        }
      }, 50);

      mediaRecorderRef.current.ondataavailable = (e) => {
        if (e.data.size > 0) chunksRef.current.push(e.data);
      };

      mediaRecorderRef.current.onstop = async () => {
        if (silenceTimerRef.current) clearInterval(silenceTimerRef.current);
        if (audioContextRef.current) audioContextRef.current.close();
        
        setIsProcessing(true);
        const blob = new Blob(chunksRef.current, { type: mimeType || 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', blob, 'recording.webm');

        try {
          const token = localStorage.getItem('token');
          const response = await axios.post(`${API}/api/voice/transcribe`, formData, {
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'multipart/form-data' }
          });
          if (response.data.text) onTranscription(response.data.text);
        } catch (err) {
          toast.error('Failed to transcribe');
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

  const handleClick = () => {
    if (isProcessing) return;
    if (isRecording) {
      stopRecording();
    } else {
      startRecording();
    }
  };

  const glowStyle = isRecording ? {
    boxShadow: `0 0 ${10 + audioLevel / 4}px ${5 + audioLevel / 8}px rgba(239, 68, 68, ${0.4 + audioLevel / 150})`
  } : {};

  return (
    <button
      type="button"
      className={`voice-input-btn ${isRecording ? 'recording' : ''} ${isProcessing ? 'processing' : ''}`}
      onClick={handleClick}
      disabled={disabled || isProcessing}
      style={glowStyle}
      data-testid="voice-input-btn"
    >
      {isProcessing ? <div className="voice-spinner" /> : <Mic size={20} />}
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
  const [appBuilderMode, setAppBuilderMode] = useState(false);
  const [webSearchEnabled, setWebSearchEnabled] = useState(false);
  const [webSearchAvailable, setWebSearchAvailable] = useState(false);
  const [showChatSwitcher, setShowChatSwitcher] = useState(false);
  const [chatSessions, setChatSessions] = useState([]);
  const { user } = useAuth();
  const messagesEndRef = React.useRef(null);
  const fileInputRef = React.useRef(null);
  const cameraInputRef = React.useRef(null);
  const abortControllerRef = React.useRef(null);

  // Force scroll to top on mount
  useEffect(() => {
    window.scrollTo(0, 0);
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
    // Also try after a short delay for any async rendering
    setTimeout(() => {
      window.scrollTo(0, 0);
      document.body.scrollTop = 0;
      document.documentElement.scrollTop = 0;
    }, 100);
  }, []);

  // Fetch chat sessions for switcher
  const fetchChatSessions = async () => {
    try {
      const token = localStorage.getItem('token');
      const res = await axios.get(`${API_URL}/api/chat/sessions`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setChatSessions(res.data.sessions || []);
    } catch (err) {
      console.error('Failed to load chat sessions');
    }
  };

  // Load a specific chat session
  const loadChatSession = async (targetSessionId) => {
    try {
      const token = localStorage.getItem('token');
      const historyRes = await axios.get(`${API_URL}/api/chat/history?session_id=${targetSessionId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const conversations = historyRes.data.conversations;
      
      if (conversations && conversations.length > 0) {
        const loadedMessages = [];
        conversations.reverse().forEach(conv => {
          loadedMessages.push({ role: 'user', content: conv.user_message });
          loadedMessages.push({ role: 'assistant', content: conv.assistant_response });
        });
        setMessages(loadedMessages);
        setSessionId(targetSessionId);
      } else {
        setMessages([]);
        setSessionId(targetSessionId);
      }
      setShowChatSwitcher(false);
    } catch (err) {
      toast.error('Failed to load chat');
    }
  };

  // Start new chat
  const startNewChat = () => {
    setMessages([]);
    setSessionId(null);
    setShowChatSwitcher(false);
  };

  // Check if web search is available (admin enabled)
  useEffect(() => {
    const checkWebSearch = async () => {
      try {
        const token = localStorage.getItem('token');
        const res = await axios.get(`${API_URL}/api/admin/settings/web-search`, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        setWebSearchAvailable(res.data.enabled);
      } catch (err) {
        // Not admin or not available
        setWebSearchAvailable(false);
      }
    };
    checkWebSearch();
  }, []);

  const [showFileCommands, setShowFileCommands] = useState(false);

  const fileCommands = [
    { label: 'Show my files', command: 'show my files' },
    { label: 'Save this as...', command: 'save this as ', prompt: true },
    { label: 'Open file...', command: 'open ', prompt: true },
    { label: 'Delete file...', command: 'delete file ', prompt: true },
    { label: 'Hold (paste + hold)', command: '', info: 'Add "hold" at the end of your message to store it' },
  ];

  const handleFileCommand = (cmd) => {
    if (cmd.info) {
      // Just show info, don't insert command
      toast.info(cmd.info);
      setShowFileCommands(false);
      return;
    }
    setInput(cmd.command);
    setShowFileCommands(false);
    if (!cmd.prompt) {
      // Auto-send if no additional input needed
      setTimeout(() => {
        document.querySelector('[data-testid="send-button"]')?.click();
      }, 100);
    }
  };

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Load last session on mount
  useEffect(() => {
    const loadLastSession = async () => {
      try {
        // Always fetch latest session from server (enables multi-device sync)
        const sessionsRes = await axios.get(`${API_URL}/api/chat/sessions`);
        const sessions = sessionsRes.data.sessions;
        
        if (sessions && sessions.length > 0) {
          // Always use the most recent session from server
          const targetSession = sessions[0]._id;
          
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
      toast.success('Photo captured');
    }
  };

  // Clear selected file
  const clearFile = () => {
    setSelectedFile(null);
    setPreviewUrl(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
    if (cameraInputRef.current) cameraInputRef.current.value = '';
  };

  // Execute actions from Chronicle's response (SMS/Call)
  const executeActionsFromResponse = async (response) => {
    const token = localStorage.getItem('token');
    const lines = response.trim().split('\n');
    const lastLine = lines[lines.length - 1].trim();
    
    // Check for SMS action: SEND_SMS_TWILIO|phone|message or SEND_SMS_NATIVE|phone|message
    if (lastLine.startsWith('SEND_SMS_TWILIO|') || lastLine.startsWith('SEND_SMS_NATIVE|')) {
      const parts = lastLine.split('|');
      if (parts.length >= 3) {
        const useNative = lastLine.startsWith('SEND_SMS_NATIVE');
        const phone = parts[1];
        const message = parts.slice(2).join('|');
        try {
          const res = await axios.post(`${API_URL}/api/sms/send`, {
            to: phone,
            message: message,
            use_native: useNative
          }, { headers: { 'Authorization': `Bearer ${token}` }});
          
          if (res.data.native && res.data.link) {
            window.location.href = res.data.link;
          } else if (res.data.success) {
            toast.success('SMS sent via Twilio!');
          }
        } catch (err) {
          toast.error('Failed to send SMS');
        }
      }
    }
    
    // Check for Call action: MAKE_CALL_TWILIO|phone|message or MAKE_CALL_NATIVE|phone
    if (lastLine.startsWith('MAKE_CALL_TWILIO|') || lastLine.startsWith('MAKE_CALL_NATIVE|')) {
      const parts = lastLine.split('|');
      if (parts.length >= 2) {
        const useNative = lastLine.startsWith('MAKE_CALL_NATIVE');
        const phone = parts[1];
        const message = parts[2] || '';
        try {
          const res = await axios.post(`${API_URL}/api/call/place`, {
            to: phone,
            message: message,
            use_native: useNative
          }, { headers: { 'Authorization': `Bearer ${token}` }});
          
          if (res.data.native && res.data.link) {
            window.location.href = res.data.link;
          } else if (res.data.success) {
            toast.success('Call initiated via Twilio!');
          }
        } catch (err) {
          toast.error('Failed to place call');
        }
      }
    }
  };

  // Text-to-speech for AI responses using ElevenLabs
  const [isSpeaking, setIsSpeaking] = useState(false);
  const audioRef = useRef(null);

  const speakMessage = async (text) => {
    if (isSpeaking) {
      // Stop current audio
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current = null;
      }
      setIsSpeaking(false);
      return;
    }

    setIsSpeaking(true);
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_URL}/api/voice/tts`, null, {
        params: { text: text.substring(0, 5000) }, // Limit text length
        headers: { 'Authorization': `Bearer ${token}` }
      });

      if (response.data.audio) {
        const audioBlob = new Blob(
          [Uint8Array.from(atob(response.data.audio), c => c.charCodeAt(0))],
          { type: 'audio/mpeg' }
        );
        const audioUrl = URL.createObjectURL(audioBlob);
        const audio = new Audio(audioUrl);
        audioRef.current = audio;
        
        audio.onended = () => {
          setIsSpeaking(false);
          URL.revokeObjectURL(audioUrl);
        };
        
        audio.onerror = () => {
          setIsSpeaking(false);
          toast.error('Failed to play audio');
        };
        
        audio.play();
      }
    } catch (err) {
      console.error('TTS error:', err);
      setIsSpeaking(false);
      // Fallback to browser TTS
      if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-GB';
        utterance.rate = 1;
        window.speechSynthesis.speak(utterance);
      } else {
        toast.error('Text-to-speech not available');
      }
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
    
    toast.info(selectedFile ? 'Sending with image...' : 'Sending...');

    // Get the message text - use default only if sending just an image
    const userMessage = input.trim() ? input.trim() : (selectedFile ? "" : "");
    setInput(''); // Clear input immediately
    
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
          const token = localStorage.getItem('token');
          const uploadRes = await axios.post(`${API_URL}/api/upload`, formData, {
            headers: { 
              'Content-Type': 'multipart/form-data',
              'Authorization': `Bearer ${token}`
            }
          });
          imageUrl = uploadRes.data.url;
          toast.success('Image uploaded');
        } catch (uploadErr) {
          toast.error('Image upload failed');
          console.log('File upload failed:', uploadErr);
        }
      }

      const token = localStorage.getItem('token');
      
      // Create abort controller for this request
      abortControllerRef.current = new AbortController();
      
      const res = await axios.post(`${API_URL}/api/chat`, {
        message: userMessage,
        session_id: sessionId,
        image_url: imageUrl,
        app_builder_mode: appBuilderMode,
        web_search: webSearchEnabled
      }, {
        headers: { 'Authorization': `Bearer ${token}` },
        signal: abortControllerRef.current.signal
      });
      
      if (!sessionId) {
        setSessionId(res.data.session_id);
      }
      
      const aiResponse = res.data.response;
      setMessages(prev => [...prev, { role: 'assistant', content: aiResponse }]);
      
      // Check for action tags and execute them
      await executeActionsFromResponse(aiResponse);
      
      clearFile();
    } catch (err) {
      if (err.name === 'CanceledError' || err.message === 'canceled') {
        setMessages(prev => [...prev, { role: 'assistant', content: '[Stopped]' }]);
      } else {
        toast.error(err.response?.data?.detail || 'Failed to send message');
        setMessages(prev => prev.slice(0, -1));
      }
    } finally {
      setSending(false);
      abortControllerRef.current = null;
    }
  };

  // Stop the current response
  const stopResponse = () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      toast.info('Response stopped');
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
            onClick={() => setAppBuilderMode(!appBuilderMode)}
            className={`mode-toggle-btn ${appBuilderMode ? 'active' : ''}`}
            title={appBuilderMode ? 'App Builder Mode ON' : 'App Builder Mode OFF'}
            data-testid="app-builder-toggle"
          >
            <Code size={18} />
            {appBuilderMode ? 'Builder' : 'Chat'}
          </button>
          {webSearchAvailable && (
            <button
              onClick={() => setWebSearchEnabled(!webSearchEnabled)}
              className={`mode-toggle-btn ${webSearchEnabled ? 'active' : ''}`}
              title={webSearchEnabled ? 'Web Search ON' : 'Web Search OFF'}
              data-testid="web-search-toggle"
            >
              <Globe size={18} />
              {webSearchEnabled ? 'Web' : 'Web'}
            </button>
          )}
          <button 
            onClick={toggleMemories} 
            className={`memory-toggle-btn ${showMemories ? 'active' : ''}`}
            title="View memories"
          >
            <Brain size={18} />
          </button>
          <div className="chat-switcher-wrapper">
            <button 
              onClick={() => { setShowChatSwitcher(!showChatSwitcher); fetchChatSessions(); }}
              className={`mode-toggle-btn ${showChatSwitcher ? 'active' : ''}`}
              title="Switch chats"
              data-testid="chat-switcher-btn"
            >
              <MessageSquare size={18} />
              Chats
            </button>
            {showChatSwitcher && (
              <div className="chat-switcher-dropdown">
                <div className="chat-switcher-header">
                  <span>Your Chats</span>
                  <button onClick={startNewChat} className="new-chat-small-btn">
                    <Plus size={14} /> New
                  </button>
                </div>
                {chatSessions.length === 0 ? (
                  <div className="no-chats">No previous chats</div>
                ) : (
                  chatSessions.slice(0, 10).map((session, idx) => (
                    <button
                      key={session._id || idx}
                      onClick={() => loadChatSession(session._id)}
                      className={`chat-session-item ${sessionId === session._id ? 'active' : ''}`}
                    >
                      <MessageSquare size={14} />
                      <span>{session.preview || `Chat ${idx + 1}`}</span>
                    </button>
                  ))
                )}
              </div>
            )}
          </div>
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
        
        {/* File Commands Dropdown */}
        <div className="file-commands-wrapper">
          <button 
            type="button" 
            className={`input-action-btn ${showFileCommands ? 'active' : ''}`}
            onClick={() => setShowFileCommands(!showFileCommands)}
            title="File commands"
          >
            <FolderOpen size={20} />
          </button>
          {showFileCommands && (
            <div className="file-commands-dropdown">
              {fileCommands.map((cmd, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => handleFileCommand(cmd)}
                  className="file-command-item"
                >
                  {cmd.label}
                </button>
              ))}
            </div>
          )}
        </div>
        
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
          disabled={false}
          data-testid="chat-input"
        />
        
        {/* Stop or Send button */}
        {sending ? (
          <button type="button" onClick={stopResponse} className="stop-btn" data-testid="stop-btn">
            <X size={20} />
          </button>
        ) : (
          <button type="submit" disabled={!input.trim() && !selectedFile} data-testid="send-button">
            <ArrowUp size={20} />
          </button>
        )}
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
  const { user } = useContext(AuthContext);
  const [activeTab, setActiveTab] = useState('send');
  const [contacts, setContacts] = useState([]);
  const [subscription, setSubscription] = useState(null);
  const [callHistory, setCallHistory] = useState([]);
  const [smsHistory, setSmsHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [isAdminOrPartner, setIsAdminOrPartner] = useState(false);
  
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
      const token = localStorage.getItem('token');
      const headers = { Authorization: `Bearer ${token}` };
      
      const [contactsRes, subRes, callsRes, smsRes, adminRes] = await Promise.all([
        axios.get(`${API_URL}/api/contacts`, { headers }),
        axios.get(`${API_URL}/api/subscription/status`, { headers }),
        axios.get(`${API_URL}/api/call/history`, { headers }).catch(() => ({ data: { calls: [] } })),
        axios.get(`${API_URL}/api/sms/history`, { headers }).catch(() => ({ data: { messages: [] } })),
        axios.get(`${API_URL}/api/user/is-admin`, { headers }).catch(() => ({ data: { is_admin: false } }))
      ]);
      setContacts(contactsRes.data || []);
      setSubscription(subRes.data || {});
      setCallHistory(callsRes.data?.calls || []);
      setSmsHistory(smsRes.data?.messages || []);
      setIsAdminOrPartner(adminRes.data?.is_admin || adminRes.data?.is_partner || false);
    } catch (err) {
      console.error('Failed to load phone data:', err);
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

  // Admin/partner can always use phone features
  const canUsePhoneFeatures = isAdminOrPartner || (subscription?.plan && !subscription?.plan.includes('starter'));

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

// Voice Clone Section Component
const VoiceCloneSection = ({ onVoiceCloned }) => {
  const [isRecording, setIsRecording] = useState(false);
  const [isCloning, setIsCloning] = useState(false);
  const [sampleText, setSampleText] = useState('');
  const [instructions, setInstructions] = useState('');
  const mediaRecorderRef = useRef(null);
  const audioChunksRef = useRef([]);

  useEffect(() => {
    fetchSampleText();
  }, []);

  const fetchSampleText = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/voice/clone/sample-text`);
      setSampleText(res.data.text);
      setInstructions(res.data.instructions);
    } catch (err) {
      console.error('Failed to fetch sample text');
    }
  };

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        audioChunksRef.current.push(event.data);
      };

      mediaRecorder.onstop = async () => {
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
        await uploadVoiceSample(audioBlob);
        stream.getTracks().forEach(track => track.stop());
      };

      mediaRecorder.start();
      setIsRecording(true);
    } catch (err) {
      toast.error('Could not access microphone');
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
    }
  };

  const uploadVoiceSample = async (audioBlob) => {
    setIsCloning(true);
    try {
      const formData = new FormData();
      formData.append('audio', audioBlob, 'voice_sample.webm');

      const token = localStorage.getItem('token');
      const res = await axios.post(`${API_URL}/api/voice/clone`, formData, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'multipart/form-data'
        }
      });

      toast.success(res.data.message || 'Voice cloned successfully!');
      if (onVoiceCloned) onVoiceCloned();
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to clone voice');
    } finally {
      setIsCloning(false);
    }
  };

  return (
    <div className="settings-section voice-clone-section">
      <h3><Mic size={20} /> Clone Your Voice</h3>
      <p className="section-desc">Record your voice to create a personalized AI voice clone</p>
      
      {sampleText && (
        <div className="sample-text-box">
          <p className="sample-instructions">{instructions}</p>
          <p className="sample-text">"{sampleText}"</p>
        </div>
      )}

      <div className="voice-clone-controls">
        {isCloning ? (
          <div className="cloning-status">
            <div className="spinner"></div>
            <span>Creating your voice clone...</span>
          </div>
        ) : isRecording ? (
          <button onClick={stopRecording} className="record-btn recording" data-testid="stop-recording-btn">
            <div className="recording-indicator"></div>
            Stop Recording
          </button>
        ) : (
          <button onClick={startRecording} className="record-btn" data-testid="start-recording-btn">
            <Mic size={20} />
            Start Recording
          </button>
        )}
      </div>
    </div>
  );
};

// User Settings View
const UserSettingsView = () => {
  const { user } = useContext(AuthContext);
  const [settings, setSettings] = useState({
    rules: [],
    custom_sms_templates: [],
    timezone: '',
    language: 'en',
    notification_email: true,
    notification_sms: true,
    custom_greeting: '',
    selected_voice: ''
  });
  const [credits, setCredits] = useState(0);
  const [newRule, setNewRule] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [homeAssistantUrl, setHomeAssistantUrl] = useState('');
  const [homeAssistantToken, setHomeAssistantToken] = useState('');
  const [haConnected, setHaConnected] = useState(false);
  const [voices, setVoices] = useState([]);

  useEffect(() => {
    fetchSettings();
    fetchVoices();
  }, []);

  const fetchVoices = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/voices`);
      setVoices(res.data.voices || []);
    } catch (err) {
      console.error('Failed to load voices');
    }
  };

  const fetchSettings = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/user/settings`);
      setSettings(res.data.settings || {});
      setCredits(res.data.credits || 0);
      setHaConnected(res.data.settings?.home_assistant_connected || false);
    } catch (err) {
      console.error('Failed to load settings');
    } finally {
      setLoading(false);
    }
  };

  const saveSettings = async () => {
    setSaving(true);
    try {
      await axios.post(`${API_URL}/api/user/settings`, settings);
      toast.success('Settings saved');
    } catch (err) {
      toast.error('Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  const addRule = async () => {
    if (newRule.trim()) {
      const updatedRules = [...(settings.rules || []), newRule.trim()];
      setSettings({ ...settings, rules: updatedRules });
      setNewRule('');
      // Auto-save rules
      try {
        await axios.post(`${API_URL}/api/user/settings/rules`, { rules: updatedRules });
        toast.success('Rule added');
      } catch (err) {
        toast.error('Failed to save rule');
      }
    }
  };

  const removeRule = async (index) => {
    const updatedRules = settings.rules.filter((_, i) => i !== index);
    setSettings({ ...settings, rules: updatedRules });
    // Auto-save rules
    try {
      await axios.post(`${API_URL}/api/user/settings/rules`, { rules: updatedRules });
      toast.success('Rule removed');
    } catch (err) {
      toast.error('Failed to save');
    }
  };

  const connectHomeAssistant = async () => {
    if (!homeAssistantUrl || !homeAssistantToken) {
      toast.error('Please enter URL and token');
      return;
    }
    try {
      await axios.post(`${API_URL}/api/user/settings/home-assistant`, {
        url: homeAssistantUrl,
        access_token: homeAssistantToken
      });
      toast.success('Home Assistant connected');
      setHaConnected(true);
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to connect');
    }
  };

  const exportData = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/user/data/export`);
      const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'chronicle-data.json';
      a.click();
      toast.success('Data exported');
    } catch (err) {
      toast.error('Failed to export data');
    }
  };

  if (loading) return <div className="loading">Loading...</div>;

  return (
    <div className="settings-view" data-testid="settings-view">
      <div className="view-header">
        <h2><Settings size={24} /> Settings</h2>
      </div>

      <div className="settings-section">
        <h3>Credits</h3>
        <div className="credits-display">
          <span className="credits-amount">{credits}</span>
          <span className="credits-label">credits remaining</span>
        </div>
      </div>

      <div className="settings-section">
        <h3>AI Rules</h3>
        <p className="section-desc">Set rules for how Chronicle responds to you</p>
        <div className="rules-list">
          {(settings.rules || []).map((rule, index) => (
            <div key={index} className="rule-item">
              <span>{index + 1}. {rule}</span>
              <button onClick={() => removeRule(index)} className="remove-btn">
                <X size={16} />
              </button>
            </div>
          ))}
        </div>
        <div className="add-rule">
          <input
            type="text"
            value={newRule}
            onChange={(e) => setNewRule(e.target.value)}
            placeholder="e.g., Keep replies short unless told otherwise"
            onKeyDown={(e) => e.key === 'Enter' && addRule()}
          />
          <button onClick={addRule} className="add-btn">
            <Plus size={20} />
          </button>
        </div>
      </div>

      <div className="settings-section">
        <h3>Custom Greeting</h3>
        <input
          type="text"
          value={settings.custom_greeting || ''}
          onChange={(e) => setSettings({ ...settings, custom_greeting: e.target.value })}
          placeholder="e.g., Hey boss!"
        />
      </div>

      <div className="settings-section">
        <h3>Notifications</h3>
        <label className="toggle-setting">
          <input
            type="checkbox"
            checked={settings.notification_email}
            onChange={(e) => setSettings({ ...settings, notification_email: e.target.checked })}
          />
          Email notifications
        </label>
        <label className="toggle-setting">
          <input
            type="checkbox"
            checked={settings.notification_sms}
            onChange={(e) => setSettings({ ...settings, notification_sms: e.target.checked })}
          />
          SMS notifications
        </label>
      </div>

      <div className="settings-section">
        <h3>Voice for Calls</h3>
        <p className="section-desc">Select a voice for when Chronicle makes calls on your behalf</p>
        <select
          value={settings.selected_voice || ''}
          onChange={(e) => setSettings({ ...settings, selected_voice: e.target.value })}
          className="voice-select"
        >
          <option value="">Default voice</option>
          {voices.map((voice) => (
            <option key={voice.id} value={voice.id}>{voice.name}</option>
          ))}
        </select>
      </div>

      <VoiceCloneSection onVoiceCloned={fetchVoices} />

      <div className="settings-section">
        <h3>Smart Home (Home Assistant)</h3>
        {haConnected ? (
          <div className="ha-connected">
            <CheckCircle size={20} /> Connected
          </div>
        ) : (
          <div className="ha-connect">
            <input
              type="text"
              value={homeAssistantUrl}
              onChange={(e) => setHomeAssistantUrl(e.target.value)}
              placeholder="http://homeassistant.local:8123"
            />
            <input
              type="password"
              value={homeAssistantToken}
              onChange={(e) => setHomeAssistantToken(e.target.value)}
              placeholder="Long-lived access token"
            />
            <button onClick={connectHomeAssistant}>Connect</button>
          </div>
        )}
      </div>

      <div className="settings-section">
        <h3>Data</h3>
        <button onClick={exportData} className="export-btn">
          <Download size={20} /> Export My Data
        </button>
        <button onClick={async () => {
          if (window.confirm('Delete all your chat history? This cannot be undone.')) {
            try {
              await axios.delete(`${API_URL}/api/user/chats`);
              toast.success('Chat history deleted');
            } catch (err) {
              toast.error('Failed to delete chats');
            }
          }
        }} className="delete-data-btn">
          <Trash2 size={20} /> Delete All Chats
        </button>
        <button onClick={async () => {
          if (window.confirm('Delete all your memories? Chronicle will forget everything about you. This cannot be undone.')) {
            try {
              await axios.delete(`${API_URL}/api/user/memories`);
              toast.success('Memories deleted');
            } catch (err) {
              toast.error('Failed to delete memories');
            }
          }
        }} className="delete-data-btn">
          <Trash2 size={20} /> Delete All Memories
        </button>
      </div>

      <div className="settings-actions">
        <button onClick={saveSettings} disabled={saving} className="save-btn">
          {saving ? 'Saving...' : 'Save Settings'}
        </button>
      </div>
    </div>
  );
};

// Owner Admin View
const OwnerAdminView = () => {
  const [stats, setStats] = useState({ total_users: 0, active_users: 0, total_conversations: 0 });
  const [users, setUsers] = useState([]);
  const [partners, setPartners] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedUser, setSelectedUser] = useState(null);
  const [newPartnerEmail, setNewPartnerEmail] = useState('');
  const [creditAmount, setCreditAmount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [phoneNumber, setPhoneNumber] = useState('');
  const [smsMessage, setSmsMessage] = useState('');
  const [showPhonePanel, setShowPhonePanel] = useState(false);
  const [webSearchEnabled, setWebSearchEnabled] = useState(false);

  useEffect(() => {
    fetchDashboard();
    fetchPartners();
    fetchWebSearchStatus();
  }, []);

  const fetchWebSearchStatus = async () => {
    try {
      const token = localStorage.getItem('token');
      const res = await axios.get(`${API_URL}/api/admin/settings/web-search`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setWebSearchEnabled(res.data.enabled);
    } catch (err) {
      console.error('Failed to load web search status');
    }
  };

  const toggleWebSearch = async () => {
    try {
      const token = localStorage.getItem('token');
      await axios.post(`${API_URL}/api/admin/settings/web-search`, 
        { enabled: !webSearchEnabled },
        { headers: { 'Authorization': `Bearer ${token}` }}
      );
      setWebSearchEnabled(!webSearchEnabled);
      toast.success(`Web search ${!webSearchEnabled ? 'enabled' : 'disabled'}`);
    } catch (err) {
      toast.error('Failed to update web search setting');
    }
  };

  const fetchDashboard = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/admin/dashboard`);
      setStats(res.data);
      setUsers(res.data.recent_users || []);
    } catch (err) {
      toast.error('Failed to load admin data');
    } finally {
      setLoading(false);
    }
  };

  const searchUsers = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/admin/users?search=${searchQuery}`);
      setUsers(res.data.users || []);
    } catch (err) {
      toast.error('Search failed');
    }
  };

  const fetchPartners = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/admin/partners`);
      setPartners(res.data.partners || []);
    } catch (err) {
      console.error('Failed to load partners');
    }
  };

  const viewUser = async (userId) => {
    try {
      const res = await axios.get(`${API_URL}/api/admin/user/${userId}`);
      setSelectedUser(res.data);
    } catch (err) {
      toast.error('Failed to load user');
    }
  };

  const updateCredits = async (userId) => {
    try {
      await axios.post(`${API_URL}/api/admin/user/${userId}/credits`, { credits: creditAmount });
      toast.success('Credits updated');
      fetchDashboard();
      setCreditAmount(0);
    } catch (err) {
      toast.error('Failed to update credits');
    }
  };

  const revokeAccess = async (userId) => {
    try {
      await axios.post(`${API_URL}/api/admin/revoke-access/${userId}`);
      toast.success('Access revoked');
      fetchDashboard();
    } catch (err) {
      toast.error('Failed to revoke access');
    }
  };

  const restoreAccess = async (userId) => {
    try {
      await axios.post(`${API_URL}/api/admin/restore-access/${userId}`);
      toast.success('Access restored');
      fetchDashboard();
    } catch (err) {
      toast.error('Failed to restore access');
    }
  };

  const deleteUser = async (userId) => {
    if (!window.confirm('Delete this user and all their data? This cannot be undone.')) return;
    try {
      await axios.delete(`${API_URL}/api/admin/user/${userId}`);
      toast.success('User deleted');
      setSelectedUser(null);
      fetchDashboard();
    } catch (err) {
      toast.error('Failed to delete user');
    }
  };

  const addPartner = async () => {
    if (!newPartnerEmail) return;
    try {
      await axios.post(`${API_URL}/api/admin/partner`, { email: newPartnerEmail });
      toast.success('Partner added');
      setNewPartnerEmail('');
      fetchPartners();
    } catch (err) {
      toast.error('Failed to add partner');
    }
  };

  const removePartner = async (email) => {
    try {
      await axios.delete(`${API_URL}/api/admin/partner/${email}`);
      toast.success('Partner removed');
      fetchPartners();
    } catch (err) {
      toast.error('Failed to remove partner');
    }
  };

  if (loading) return <div className="loading">Loading...</div>;

  return (
    <div className="admin-view" data-testid="admin-view">
      <div className="view-header">
        <h2><Shield size={24} /> Admin Panel</h2>
      </div>

      <div className="admin-stats">
        <div className="stat-card">
          <span className="stat-number">{stats.total_users}</span>
          <span className="stat-label">Total Users</span>
        </div>
        <div className="stat-card">
          <span className="stat-number">{stats.active_users}</span>
          <span className="stat-label">Active Users</span>
        </div>
        <div className="stat-card">
          <span className="stat-number">{stats.total_conversations}</span>
          <span className="stat-label">Conversations</span>
        </div>
      </div>

      <div className="admin-section">
        <h3>Feature Toggles</h3>
        <div className="feature-toggles">
          <div className="feature-toggle-item">
            <div className="feature-info">
              <Globe size={20} />
              <div>
                <span className="feature-name">Web Search</span>
                <span className="feature-desc">Allow users to search the internet</span>
              </div>
            </div>
            <button 
              onClick={toggleWebSearch}
              className={`toggle-btn ${webSearchEnabled ? 'active' : ''}`}
              data-testid="web-search-admin-toggle"
            >
              {webSearchEnabled ? 'ON' : 'OFF'}
            </button>
          </div>
        </div>
      </div>

      <div className="admin-section">
        <h3>Search Users</h3>
        <div className="search-bar">
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by name or email..."
            onKeyDown={(e) => e.key === 'Enter' && searchUsers()}
          />
          <button onClick={searchUsers}>Search</button>
        </div>
      </div>

      <div className="admin-section">
        <h3>Users</h3>
        <div className="users-list">
          {users.map((u) => (
            <div key={u.id} className={`user-item ${u.access_revoked ? 'revoked' : ''}`} onClick={() => viewUser(u.id)}>
              <div className="user-info-admin">
                <span className="user-name">{u.name}</span>
                <span className="user-email">{u.email}</span>
              </div>
              <div className="user-credits">
                <span>{u.credits || 0} credits</span>
              </div>
              {u.access_revoked && <span className="revoked-badge">Revoked</span>}
            </div>
          ))}
        </div>
      </div>

      {selectedUser && (
        <div className="user-detail-modal">
          <div className="modal-content">
            <button className="close-modal" onClick={() => setSelectedUser(null)}>
              <X size={24} />
            </button>
            <h3>{selectedUser.user.name}</h3>
            <p>{selectedUser.user.email}</p>
            <div className="user-stats-detail">
              <span>Conversations: {selectedUser.stats.conversations}</span>
              <span>Contacts: {selectedUser.stats.contacts}</span>
              <span>Memories: {selectedUser.stats.memories}</span>
              <span>Credits: {selectedUser.user.credits || 0}</span>
            </div>
            <div className="credit-adjustment">
              <input
                type="number"
                value={creditAmount}
                onChange={(e) => setCreditAmount(parseInt(e.target.value) || 0)}
                placeholder="Credits (+/-)"
              />
              <button onClick={() => updateCredits(selectedUser.user.id)}>Update Credits</button>
            </div>
            <div className="user-actions">
              {selectedUser.user.access_revoked ? (
                <button onClick={() => restoreAccess(selectedUser.user.id)} className="restore-btn">
                  <UserCheck size={20} /> Restore Access
                </button>
              ) : (
                <button onClick={() => revokeAccess(selectedUser.user.id)} className="revoke-btn">
                  <UserX size={20} /> Revoke Access
                </button>
              )}
              <button onClick={() => deleteUser(selectedUser.user.id)} className="delete-btn">
                <Trash2 size={20} /> Delete User
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="admin-section">
        <h3>Admin Partners</h3>
        <div className="partners-list">
          {partners.map((p) => (
            <div key={p.email} className="partner-item">
              <span>{p.email}</span>
              <button onClick={() => removePartner(p.email)} className="remove-btn">
                <X size={16} />
              </button>
            </div>
          ))}
        </div>
        <div className="add-partner">
          <input
            type="email"
            value={newPartnerEmail}
            onChange={(e) => setNewPartnerEmail(e.target.value)}
            placeholder="Partner email address"
          />
          <button onClick={addPartner}>Add Partner</button>
        </div>
      </div>

      <div className="admin-section">
        <h3>Phone & SMS</h3>
        <p className="section-desc">Send SMS or place calls (choose Twilio or your phone)</p>
        <div className="phone-controls">
          <input
            type="text"
            value={phoneNumber}
            onChange={(e) => setPhoneNumber(e.target.value)}
            placeholder="Phone number (+44...)"
          />
          <input
            type="text"
            value={smsMessage}
            onChange={(e) => setSmsMessage(e.target.value)}
            placeholder="Message (for SMS)"
          />
          <div className="phone-buttons">
            <button onClick={async () => {
              if (!phoneNumber) { toast.error('Enter phone number'); return; }
              if (!smsMessage) { toast.error('Enter message'); return; }
              try {
                const res = await axios.post(`${API_URL}/api/sms/send`, { to: phoneNumber, message: smsMessage, use_native: false });
                toast.success('SMS sent via Twilio');
              } catch (err) { toast.error('Failed to send SMS'); }
            }} className="twilio-btn">
              SMS via Twilio
            </button>
            <button onClick={async () => {
              if (!phoneNumber) { toast.error('Enter phone number'); return; }
              if (!smsMessage) { toast.error('Enter message'); return; }
              const res = await axios.post(`${API_URL}/api/sms/send`, { to: phoneNumber, message: smsMessage, use_native: true });
              if (res.data.native) {
                window.location.href = res.data.link;
              }
            }} className="native-btn">
              SMS via My Phone
            </button>
            <button onClick={async () => {
              if (!phoneNumber) { toast.error('Enter phone number'); return; }
              try {
                const res = await axios.post(`${API_URL}/api/call/place`, { to: phoneNumber, use_native: false });
                toast.success('Call placed via Twilio');
              } catch (err) { toast.error('Failed to place call'); }
            }} className="twilio-btn">
              Call via Twilio
            </button>
            <button onClick={async () => {
              if (!phoneNumber) { toast.error('Enter phone number'); return; }
              const res = await axios.post(`${API_URL}/api/call/place`, { to: phoneNumber, use_native: true });
              if (res.data.native) {
                window.location.href = res.data.link;
              }
            }} className="native-btn">
              Call via My Phone
            </button>
          </div>
        </div>
      </div>

      <ElevenLabsCallSection phoneNumber={phoneNumber} />
    </div>
  );
};

// ElevenLabs Call Section Component  
const ElevenLabsCallSection = ({ phoneNumber }) => {
  const [callMessage, setCallMessage] = useState('');
  const [selectedVoice, setSelectedVoice] = useState('');
  const [voices, setVoices] = useState([]);
  const [calling, setCalling] = useState(false);

  useEffect(() => {
    fetchVoices();
  }, []);

  const fetchVoices = async () => {
    try {
      const res = await axios.get(`${API_URL}/api/voices/available`);
      setVoices(res.data.voices || []);
    } catch (err) {
      console.error('Failed to load voices');
    }
  };

  const makeCallWithVoice = async () => {
    if (!phoneNumber) {
      toast.error('Enter phone number above');
      return;
    }
    if (!callMessage.trim()) {
      toast.error('Enter message to speak');
      return;
    }

    setCalling(true);
    try {
      const res = await axios.post(`${API_URL}/api/call/with-voice`, {
        to_phone: phoneNumber,
        message: callMessage,
        voice_id: selectedVoice || null
      });
      toast.success('Call initiated with ElevenLabs voice!');
      setCallMessage('');
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to make call');
    } finally {
      setCalling(false);
    }
  };

  return (
    <div className="admin-section call-voice-section">
      <h3><Volume2 size={20} /> Call with AI Voice</h3>
      <p className="section-desc">Make a call using ElevenLabs voices (your clone or presets)</p>
      <div className="call-voice-form">
        <textarea
          value={callMessage}
          onChange={(e) => setCallMessage(e.target.value)}
          placeholder="What should Chronicle say on the call?"
          data-testid="call-message-input"
        />
        <div className="voice-select-row">
          <select
            value={selectedVoice}
            onChange={(e) => setSelectedVoice(e.target.value)}
            data-testid="voice-select"
          >
            <option value="">Default Voice</option>
            {voices.map((voice) => (
              <option key={voice.id} value={voice.id}>
                {voice.name} {voice.is_clone ? '(Your Clone)' : ''}
              </option>
            ))}
          </select>
          <button
            onClick={makeCallWithVoice}
            disabled={calling}
            className="call-elevenlabs-btn"
            data-testid="call-with-voice-btn"
          >
            {calling ? (
              <>Calling...</>
            ) : (
              <>
                <Phone size={18} />
                Call with Voice
              </>
            )}
          </button>
        </div>
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

  // Scroll to top on mount
  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

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
      case 'settings':
        return <UserSettingsView />;
      case 'admin':
        return <OwnerAdminView />;
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
          <button
            className={activeView === 'settings' ? 'active' : ''}
            onClick={() => { setActiveView('settings'); setSidebarOpen(false); }}
            data-testid="nav-settings"
          >
            <Settings size={20} />
            Settings
          </button>
          {user?.email === 'sailingwaves@gmail.com' && (
            <button
              className={activeView === 'admin' ? 'active' : ''}
              onClick={() => { setActiveView('admin'); setSidebarOpen(false); }}
              data-testid="nav-admin"
            >
              <Shield size={20} />
              Admin
            </button>
          )}
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
        <Toaster position="top-center" richColors toastOptions={{ style: { marginTop: '50px' } }} />
        <AppRoutes />
      </AuthProvider>
    </Router>
  );
}

// Privacy Policy Page
const PrivacyPage = () => {
  return (
    <div className="legal-page">
      <div className="legal-container">
        <h1>Privacy Policy</h1>
        <p className="last-updated">Last updated: April 2026</p>
        
        <section>
          <h2>1. Information We Collect</h2>
          <p>Chronicle collects information you provide directly, including:</p>
          <ul>
            <li>Account information (name, email address)</li>
            <li>Messages and conversations with our AI assistant</li>
            <li>Contacts you add to the app</li>
            <li>Voice recordings for transcription</li>
            <li>Payment information (processed securely via Stripe)</li>
          </ul>
        </section>

        <section>
          <h2>2. How We Use Your Information</h2>
          <p>We use your information to:</p>
          <ul>
            <li>Provide and improve our AI assistant services</li>
            <li>Maintain conversation memory across sessions</li>
            <li>Process payments and manage subscriptions</li>
            <li>Send SMS and make calls on your behalf (when requested)</li>
            <li>Communicate with you about your account</li>
          </ul>
        </section>

        <section>
          <h2>3. Data Storage and Security</h2>
          <p>Your data is stored securely and encrypted. We use industry-standard security measures to protect your information. Conversation data and memories are stored to provide continuity in your AI assistant experience.</p>
        </section>

        <section>
          <h2>4. Third-Party Services</h2>
          <p>We use the following third-party services:</p>
          <ul>
            <li>Google OAuth for authentication</li>
            <li>Stripe for payment processing</li>
            <li>Twilio for SMS and voice calls</li>
            <li>ElevenLabs for voice synthesis</li>
            <li>OpenAI for voice transcription</li>
            <li>Anthropic for AI conversations</li>
          </ul>
        </section>

        <section>
          <h2>5. Your Rights</h2>
          <p>You can request to delete your account and all associated data at any time through the Settings page or by contacting us.</p>
        </section>

        <section>
          <h2>6. Contact Us</h2>
          <p>For privacy-related questions, contact us at: ceo@usefulgadgets.ltd</p>
        </section>

        <a href="/" className="back-link">← Back to Chronicle</a>
      </div>
    </div>
  );
};

// Terms of Service Page
const TermsPage = () => {
  return (
    <div className="legal-page">
      <div className="legal-container">
        <h1>Terms of Service</h1>
        <p className="last-updated">Last updated: April 2026</p>
        
        <section>
          <h2>1. Acceptance of Terms</h2>
          <p>By accessing and using Chronicle, you agree to be bound by these Terms of Service. If you do not agree, please do not use the service.</p>
        </section>

        <section>
          <h2>2. Description of Service</h2>
          <p>Chronicle is an AI-powered personal assistant that provides:</p>
          <ul>
            <li>Conversational AI with persistent memory</li>
            <li>SMS and voice call capabilities</li>
            <li>Contact management</li>
            <li>Voice transcription and synthesis</li>
          </ul>
        </section>

        <section>
          <h2>3. Account Registration</h2>
          <p>You must provide accurate information when creating an account. You are responsible for maintaining the security of your account credentials.</p>
        </section>

        <section>
          <h2>4. Subscription and Payment</h2>
          <p>Chronicle offers a 10-day free trial. After the trial, continued use requires a paid subscription. Payments are processed securely through Stripe. You may cancel your subscription at any time.</p>
        </section>

        <section>
          <h2>5. Acceptable Use</h2>
          <p>You agree not to use Chronicle for:</p>
          <ul>
            <li>Any illegal or unauthorized purpose</li>
            <li>Harassment, spam, or abuse</li>
            <li>Attempting to gain unauthorized access</li>
            <li>Interfering with the service's operation</li>
          </ul>
        </section>

        <section>
          <h2>6. Limitation of Liability</h2>
          <p>Chronicle is provided "as is" without warranties. We are not liable for any indirect, incidental, or consequential damages arising from your use of the service.</p>
        </section>

        <section>
          <h2>7. Changes to Terms</h2>
          <p>We may update these terms from time to time. Continued use of the service after changes constitutes acceptance of the new terms.</p>
        </section>

        <section>
          <h2>8. Contact</h2>
          <p>For questions about these terms, contact us at: ceo@usefulgadgets.ltd</p>
        </section>

        <a href="/" className="back-link">← Back to Chronicle</a>
      </div>
    </div>
  );
};

// Separate component to handle routing
function AppRoutes() {
  return (
    <Routes>
      <Route path="/privacy" element={<PrivacyPage />} />
      <Route path="/terms" element={<TermsPage />} />
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
