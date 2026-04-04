<?php
/**
 * Shareable Marketing Card
 * Beautiful landing card for social media sharing (TikTok, Instagram, etc.)
 * 
 * @package AK_Customer_Dashboard
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Marketing_Card {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('init', array($this, 'create_marketing_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Create the marketing page
     */
    public function create_marketing_page() {
        $page_slug = 'get-started';
        $existing_page = get_page_by_path($page_slug);
        
        if (!$existing_page) {
            wp_insert_post(array(
                'post_title' => 'Get Started with AppointmentKeeper',
                'post_name' => $page_slug,
                'post_content' => '[ak_marketing_card]',
                'post_status' => 'publish',
                'post_type' => 'page',
            ));
        }
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('ak_marketing_card', array($this, 'render_marketing_card'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (is_page('get-started') || has_shortcode(get_post()->post_content ?? '', 'ak_marketing_card')) {
            wp_enqueue_style('ak-marketing-style', plugins_url('../assets/marketing-card.css', __FILE__), array(), '1.0.0');
        }
    }
    
    /**
     * Render the marketing card
     */
    public function render_marketing_card() {
        // Get referral code if present
        $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
        $signup_url = home_url('/choose-plan/');
        if ($ref) {
            $signup_url .= '?ref=' . $ref;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta property="og:title" content="AppointmentKeeper - Your AI Business Assistant">
            <meta property="og:description" content="Never miss a payment again. AI-powered appointment reminders, debt chasing, and business automation.">
            <meta property="og:image" content="<?php echo plugins_url('../assets/og-image.png', __FILE__); ?>">
            <meta property="og:type" content="website">
        </head>
        <body class="ak-marketing-body">
        
        <div class="ak-marketing-wrapper">
            <!-- Animated Background -->
            <div class="ak-bg-animation">
                <div class="ak-bg-circle ak-circle-1"></div>
                <div class="ak-bg-circle ak-circle-2"></div>
                <div class="ak-bg-circle ak-circle-3"></div>
            </div>
            
            <!-- Main Card -->
            <div class="ak-marketing-card">
                
                <!-- Logo/Brand -->
                <div class="ak-brand">
                    <div class="ak-logo-icon">
                        <svg viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="25" cy="25" r="23" stroke="url(#gradient1)" stroke-width="4"/>
                            <path d="M15 25L22 32L35 18" stroke="url(#gradient1)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                            <defs>
                                <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#0066cc"/>
                                    <stop offset="100%" stop-color="#00c853"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <span class="ak-brand-name">AppointmentKeeper</span>
                </div>
                
                <!-- Hero Section -->
                <div class="ak-hero">
                    <h1 class="ak-headline">
                        <span class="ak-headline-small">Meet Your</span>
                        <span class="ak-headline-big">AI Business Assistant</span>
                    </h1>
                    <p class="ak-tagline">Never miss a payment. Never forget an appointment. Let AI do the chasing.</p>
                </div>
                
                <!-- Features Grid -->
                <div class="ak-features">
                    <div class="ak-feature-item">
                        <div class="ak-feature-icon">
                            <span>🤖</span>
                        </div>
                        <div class="ak-feature-text">
                            <strong>AI Voice Calls</strong>
                            <span>Robot assistant makes calls for you</span>
                        </div>
                    </div>
                    
                    <div class="ak-feature-item">
                        <div class="ak-feature-icon">
                            <span>💬</span>
                        </div>
                        <div class="ak-feature-text">
                            <strong>Smart SMS Reminders</strong>
                            <span>Auto-sends at the perfect time</span>
                        </div>
                    </div>
                    
                    <div class="ak-feature-item">
                        <div class="ak-feature-icon">
                            <span>💰</span>
                        </div>
                        <div class="ak-feature-text">
                            <strong>Debt Chasing</strong>
                            <span>Get paid without awkward convos</span>
                        </div>
                    </div>
                    
                    <div class="ak-feature-item">
                        <div class="ak-feature-icon">
                            <span>📍</span>
                        </div>
                        <div class="ak-feature-text">
                            <strong>GPS Directions</strong>
                            <span>Clients never get lost again</span>
                        </div>
                    </div>
                </div>
                
                <!-- Social Proof -->
                <div class="ak-social-proof">
                    <div class="ak-proof-avatars">
                        <div class="ak-avatar" style="background: #ff6b6b;">J</div>
                        <div class="ak-avatar" style="background: #4ecdc4;">S</div>
                        <div class="ak-avatar" style="background: #45b7d1;">M</div>
                        <div class="ak-avatar" style="background: #96c93d;">K</div>
                        <div class="ak-avatar" style="background: #f9ca24;">+</div>
                    </div>
                    <p class="ak-proof-text">Join <strong>500+</strong> UK businesses saving 10+ hours/week</p>
                </div>
                
                <!-- CTA Button -->
                <a href="<?php echo esc_url($signup_url); ?>" class="ak-cta-button">
                    <span class="ak-cta-text">Start Free Trial</span>
                    <span class="ak-cta-subtext">No card required • 3 days free</span>
                    <svg class="ak-cta-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
                
                <!-- Trust Badges -->
                <div class="ak-trust-badges">
                    <div class="ak-badge">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                        </svg>
                        <span>GDPR Compliant</span>
                    </div>
                    <div class="ak-badge">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>UK Based</span>
                    </div>
                    <div class="ak-badge">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                        </svg>
                        <span>From £9.99/mo</span>
                    </div>
                </div>
                
                <!-- Quote -->
                <div class="ak-quote">
                    <p>"This thing is brilliant. Saved me chasing debtors myself - the AI does it all!"</p>
                    <span class="ak-quote-author">— Sarah, Mobile Beautician</span>
                </div>
                
            </div>
            
            <!-- Floating Elements -->
            <div class="ak-float ak-float-1">📱</div>
            <div class="ak-float ak-float-2">💵</div>
            <div class="ak-float ak-float-3">✅</div>
            
        </div>
        
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize
AK_Marketing_Card::get_instance();
