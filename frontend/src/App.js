import React, { useState, useEffect, createContext, useContext } from 'react';
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
  Check
} from 'lucide-react';
import './App.css';

const API_URL = process.env.REACT_APP_BACKEND_URL;

// Auth Context
const AuthContext = createContext(null);

const useAuth = () => useContext(AuthContext);

const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(localStorage.getItem('token'));
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // CRITICAL: If returning from OAuth callback, skip the /me check.
    // AuthCallback will exchange the session_id and establish the session first.
    if (window.location.hash?.includes('session_id=')) {
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

  const loginWithGoogle = async (sessionId) => {
    const res = await axios.post(`${API_URL}/api/auth/google/session`, { session_id: sessionId });
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
  const { login, register } = useAuth();
  const navigate = useNavigate();

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
    // REMINDER: DO NOT HARDCODE THE URL, OR ADD ANY FALLBACKS OR REDIRECT URLS, THIS BREAKS THE AUTH
    const redirectUrl = window.location.origin + '/auth/callback';
    window.location.href = `https://auth.emergentagent.com/?redirect=${encodeURIComponent(redirectUrl)}`;
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

        <p className="auth-switch">
          {isLogin ? "Don't have an account? " : "Already have an account? "}
          <button 
            onClick={() => setIsLogin(!isLogin)}
            data-testid="auth-switch-btn"
          >
            {isLogin ? 'Sign up' : 'Sign in'}
          </button>
        </p>
      </div>
    </div>
  );
};

// Google OAuth Callback
const AuthCallback = () => {
  const { loginWithGoogle } = useAuth();
  const navigate = useNavigate();
  const hasProcessed = React.useRef(false);

  useEffect(() => {
    // Prevent double processing in StrictMode
    if (hasProcessed.current) return;
    hasProcessed.current = true;

    const processCallback = async () => {
      // Get session_id from URL hash
      const hash = window.location.hash;
      const sessionIdMatch = hash.match(/session_id=([^&]+)/);
      
      if (!sessionIdMatch) {
        toast.error('Authentication failed - no session ID');
        navigate('/auth');
        return;
      }

      const sessionId = sessionIdMatch[1];

      try {
        await loginWithGoogle(sessionId);
        toast.success('Welcome!');
        // Clear the hash and navigate to home
        window.history.replaceState(null, '', window.location.pathname);
        navigate('/');
      } catch (err) {
        toast.error(err.response?.data?.detail || 'Google sign-in failed');
        navigate('/auth');
      }
    };

    processCallback();
  }, [loginWithGoogle, navigate]);

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

// Chat Component
const ChatView = () => {
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [sessionId, setSessionId] = useState(null);
  const { user } = useAuth();
  const messagesEndRef = React.useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const sendMessage = async (e) => {
    e.preventDefault();
    if (!input.trim() || sending) return;

    const userMessage = input.trim();
    setInput('');
    setMessages(prev => [...prev, { role: 'user', content: userMessage }]);
    setSending(true);

    try {
      const res = await axios.post(`${API_URL}/api/chat`, {
        message: userMessage,
        session_id: sessionId
      });
      
      if (!sessionId) {
        setSessionId(res.data.session_id);
      }
      
      setMessages(prev => [...prev, { role: 'assistant', content: res.data.response }]);
    } catch (err) {
      toast.error(err.response?.data?.detail || 'Failed to send message');
      setMessages(prev => prev.slice(0, -1)); // Remove user message on error
    } finally {
      setSending(false);
    }
  };

  const startNewChat = () => {
    setMessages([]);
    setSessionId(null);
  };

  return (
    <div className="chat-view">
      <div className="chat-header">
        <h2>Chat with Chronicle</h2>
        <button onClick={startNewChat} className="new-chat-btn" data-testid="new-chat-btn">
          <Plus size={18} />
          New Chat
        </button>
      </div>

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
                <pre>{msg.content}</pre>
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

      <form onSubmit={sendMessage} className="chat-input-form">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Type your message..."
          disabled={sending}
          data-testid="chat-input"
        />
        <button type="submit" disabled={sending || !input.trim()} data-testid="send-message-btn">
          <Send size={20} />
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
          <h3>Upgrade to Pro or Business</h3>
          <p>Phone calls and SMS require a Pro or Business subscription.</p>
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
      const res = await axios.post(`${API_URL}/api/checkout/create`, {
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

  return children;
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

// Separate component to handle routing with hash detection
function AppRoutes() {
  const location = window.location;
  
  // Check URL hash for session_id (Google OAuth callback)
  if (location.hash?.includes('session_id=')) {
    return <AuthCallback />;
  }
  
  return (
    <Routes>
      <Route path="/auth" element={<AuthPage />} />
      <Route path="/auth/callback" element={<AuthCallback />} />
      <Route path="/disclosure" element={<DisclosurePage />} />
      <Route path="/subscription/success" element={
        <ProtectedRoute>
          <SubscriptionSuccess />
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
