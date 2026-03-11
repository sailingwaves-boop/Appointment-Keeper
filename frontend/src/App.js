import React, { useState, useEffect, createContext, useContext } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
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
  CheckCircle
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
    <AuthContext.Provider value={{ user, token, login, register, logout, loading, updateUser }}>
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

  return (
    <div className="auth-page">
      <div className="auth-container">
        <div className="auth-header">
          <Brain className="auth-logo" />
          <h1>AI Helper</h1>
          <p>Your personal AI assistant with unlimited memory</p>
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
          <p>Please read and accept before using AI Helper</p>
        </div>

        <div className="disclosure-content">
          <h2>Data Storage & Privacy</h2>
          <ul>
            <li>AI Helper stores your conversations and personal information to provide persistent memory features.</li>
            <li>Your data is stored securely and is only accessible by you.</li>
            <li>We do not sell or share your personal information with third parties.</li>
            <li>You can delete your data at any time from the settings page.</li>
          </ul>

          <h2>AI Limitations</h2>
          <ul>
            <li>AI Helper is an artificial intelligence and may make mistakes.</li>
            <li>Always verify important information independently.</li>
            <li>Do not rely on AI Helper for medical, legal, or financial advice.</li>
          </ul>

          <h2>Phone & Messaging Features</h2>
          <ul>
            <li>If you use phone/SMS features, standard carrier rates may apply.</li>
            <li>You are responsible for ensuring you have consent to contact others.</li>
            <li>AI Helper will send messages and make calls on your behalf as instructed.</li>
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
        <h2>Chat with AI Helper</h2>
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
            <p>I'm your AI Helper with permanent memory. I can help you with:</p>
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
          <h1>AI Helper</h1>
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
        <Routes>
          <Route path="/auth" element={<AuthPage />} />
          <Route path="/disclosure" element={<DisclosurePage />} />
          <Route
            path="/*"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />
        </Routes>
      </AuthProvider>
    </Router>
  );
}

export default App;
