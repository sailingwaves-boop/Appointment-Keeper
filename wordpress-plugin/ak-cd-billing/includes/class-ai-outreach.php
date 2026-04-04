<?php
/**
 * AI Outreach System
 * Allows customers to send AI-powered pitches to their contacts
 * 
 * @package AK_Customer_Dashboard
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_AI_Outreach {
    
    private static $instance = null;
    private $twilio_service;
    private $elevenlabs_service;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_ajax_ak_send_outreach', array($this, 'ajax_send_outreach'));
        add_action('wp_ajax_ak_get_outreach_history', array($this, 'ajax_get_outreach_history'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'create_outreach_page'));
        add_action('init', array($this, 'handle_outreach_optout'));
        
        // Load services
        add_action('init', array($this, 'load_services'));
    }
    
    public function load_services() {
        if (class_exists('AK_Twilio_Service')) {
            $this->twilio_service = AK_Twilio_Service::get_instance();
        }
        if (class_exists('AK_ElevenLabs_Service')) {
            $this->elevenlabs_service = AK_ElevenLabs_Service::get_instance();
        }
    }
    
    /**
     * Check if AI Outreach is enabled
     */
    public function is_enabled() {
        return get_option('ak_ai_outreach_enabled', false);
    }
    
    /**
     * Get daily limit per user
     */
    public function get_daily_limit() {
        return intval(get_option('ak_ai_outreach_daily_limit', 5));
    }
    
    /**
     * Get credit cost per outreach
     */
    public function get_sms_cost() {
        return intval(get_option('ak_ai_outreach_sms_cost', 2));
    }
    
    public function get_call_cost() {
        return intval(get_option('ak_ai_outreach_call_cost', 2));
    }
    
    /**
     * Get customizable pitch templates
     */
    public function get_sms_template() {
        $default = "Hi {contact_name}, your friend {sender_name} thought you might like AppointmentKeeper - it helps businesses chase payments and manage appointments automatically. Get a free trial: {signup_link} Reply STOP to opt out.";
        return get_option('ak_ai_outreach_sms_template', $default);
    }
    
    public function get_call_script() {
        $default = "Hello {contact_name}, I'm calling on behalf of your friend {sender_name}. They've been using AppointmentKeeper to manage their appointments and chase payments automatically, and they thought you might find it useful too. You can try it free for 3 days at appointmentkeeper.co.uk. Have a great day!";
        return get_option('ak_ai_outreach_call_script', $default);
    }
    
    /**
     * Create the outreach page
     */
    public function create_outreach_page() {
        $page_slug = 'ai-outreach';
        $existing_page = get_page_by_path($page_slug);
        
        if (!$existing_page) {
            wp_insert_post(array(
                'post_title' => 'Invite Friends',
                'post_name' => $page_slug,
                'post_content' => '[ak_ai_outreach]',
                'post_status' => 'publish',
                'post_type' => 'page',
            ));
        }
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('ak_ai_outreach', array($this, 'render_outreach_page'));
        add_shortcode('ak_outreach_widget', array($this, 'render_outreach_widget'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (is_page('ai-outreach') || has_shortcode(get_post()->post_content ?? '', 'ak_ai_outreach')) {
            wp_enqueue_style('ak-outreach-style', plugins_url('../assets/outreach.css', __FILE__), array(), '1.0.0');
            wp_enqueue_script('ak-outreach-script', plugins_url('../assets/outreach.js', __FILE__), array('jquery'), '1.0.0', true);
            wp_localize_script('ak-outreach-script', 'akOutreach', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ak_outreach_nonce'),
                'sms_cost' => $this->get_sms_cost(),
                'call_cost' => $this->get_call_cost(),
                'daily_limit' => $this->get_daily_limit(),
            ));
        }
    }
    
    /**
     * Handle opt-out requests
     */
    public function handle_outreach_optout() {
        if (isset($_GET['ak_optout']) && isset($_GET['phone'])) {
            $phone = sanitize_text_field($_GET['phone']);
            $optouts = get_option('ak_outreach_optouts', array());
            
            if (!in_array($phone, $optouts)) {
                $optouts[] = $phone;
                update_option('ak_outreach_optouts', $optouts);
            }
            
            wp_die('You have been successfully opted out of future messages. You may close this page.', 'Opted Out', array('response' => 200));
        }
    }
    
    /**
     * Check if phone is opted out
     */
    public function is_opted_out($phone) {
        $optouts = get_option('ak_outreach_optouts', array());
        return in_array($phone, $optouts);
    }
    
    /**
     * Get user's outreach count for today
     */
    public function get_today_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_outreach_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        
        $today = date('Y-m-d');
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND DATE(created_at) = %s",
            $user_id, $today
        )));
    }
    
    /**
     * Check if user already contacted this phone
     */
    public function already_contacted($user_id, $phone) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_outreach_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND contact_phone = %s",
            $user_id, $phone
        )) > 0;
    }
    
    /**
     * Log outreach attempt
     */
    public function log_outreach($user_id, $contact_name, $contact_phone, $method, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_outreach_log';
        
        // Create table if not exists
        $this->create_outreach_table();
        
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'contact_name' => $contact_name,
            'contact_phone' => $contact_phone,
            'method' => $method,
            'status' => $status,
            'created_at' => current_time('mysql'),
        ), array('%d', '%s', '%s', '%s', '%s', '%s'));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create outreach log table
     */
    public function create_outreach_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_outreach_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            contact_name varchar(255) NOT NULL,
            contact_phone varchar(50) NOT NULL,
            method varchar(20) NOT NULL,
            status varchar(50) NOT NULL,
            converted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY contact_phone (contact_phone)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX: Send outreach message
     */
    public function ajax_send_outreach() {
        check_ajax_referer('ak_outreach_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in first.'));
        }
        
        // Check if feature is enabled
        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'This feature is currently disabled.'));
        }
        
        $user_id = get_current_user_id();
        $contact_name = sanitize_text_field($_POST['contact_name'] ?? '');
        $contact_phone = sanitize_text_field($_POST['contact_phone'] ?? '');
        $method = sanitize_text_field($_POST['method'] ?? 'sms');
        
        // Validation
        if (empty($contact_name) || empty($contact_phone)) {
            wp_send_json_error(array('message' => 'Please enter both name and phone number.'));
        }
        
        // Format phone number
        $contact_phone = $this->format_phone($contact_phone);
        
        // Check opt-out
        if ($this->is_opted_out($contact_phone)) {
            wp_send_json_error(array('message' => 'This contact has opted out of receiving messages.'));
        }
        
        // Check daily limit
        $today_count = $this->get_today_count($user_id);
        if ($today_count >= $this->get_daily_limit()) {
            wp_send_json_error(array('message' => 'You\'ve reached your daily limit of ' . $this->get_daily_limit() . ' outreach messages. Try again tomorrow!'));
        }
        
        // Check if already contacted
        if ($this->already_contacted($user_id, $contact_phone)) {
            wp_send_json_error(array('message' => 'You\'ve already contacted this person. Each contact can only be reached once.'));
        }
        
        // Check credits
        $credits = $this->get_user_credits($user_id);
        $cost = ($method === 'call') ? $this->get_call_cost() : $this->get_sms_cost();
        $credit_type = ($method === 'call') ? 'call_credits' : 'sms_credits';
        
        if ($credits[$credit_type] < $cost) {
            wp_send_json_error(array('message' => 'Insufficient credits. You need ' . $cost . ' ' . ($method === 'call' ? 'call minutes' : 'SMS credits') . ' for this action.'));
        }
        
        // Get sender info
        $user = get_userdata($user_id);
        $sender_name = get_user_meta($user_id, 'ak_business_name', true) ?: $user->display_name;
        
        // Get referral code
        $referral_code = get_user_meta($user_id, 'ak_referral_code', true);
        if (empty($referral_code)) {
            $referral_code = $this->generate_referral_code($user_id);
        }
        
        // Build signup link with referral
        $signup_link = home_url('/choose-plan/?ref=' . $referral_code);
        
        // Replace template variables
        $replacements = array(
            '{contact_name}' => $contact_name,
            '{sender_name}' => $sender_name,
            '{signup_link}' => $signup_link,
        );
        
        $success = false;
        $status = 'failed';
        
        if ($method === 'sms') {
            // Send SMS
            $message = str_replace(array_keys($replacements), array_values($replacements), $this->get_sms_template());
            
            if ($this->twilio_service) {
                $result = $this->send_outreach_sms($contact_phone, $message, $user_id);
                $success = $result['success'];
                $status = $success ? 'sent' : 'failed';
            } else {
                wp_send_json_error(array('message' => 'SMS service not available. Please contact support.'));
            }
        } else {
            // Make AI call
            $script = str_replace(array_keys($replacements), array_values($replacements), $this->get_call_script());
            
            if ($this->twilio_service && $this->elevenlabs_service) {
                $result = $this->send_outreach_call($contact_phone, $script, $user_id);
                $success = $result['success'];
                $status = $success ? 'called' : 'failed';
            } else {
                wp_send_json_error(array('message' => 'Voice service not available. Please contact support.'));
            }
        }
        
        if ($success) {
            // Deduct credits
            $this->deduct_credits($user_id, $credit_type, $cost);
            
            // Log the outreach
            $this->log_outreach($user_id, $contact_name, $contact_phone, $method, $status);
            
            wp_send_json_success(array(
                'message' => $method === 'sms' 
                    ? "SMS sent to {$contact_name}! You'll earn credits if they sign up." 
                    : "AI call initiated to {$contact_name}! You'll earn credits if they sign up.",
                'remaining_today' => $this->get_daily_limit() - $today_count - 1,
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to send. Please try again or contact support.'));
        }
    }
    
    /**
     * Send outreach SMS using Twilio
     */
    private function send_outreach_sms($phone, $message, $user_id) {
        $twilio_sid = get_option('ak_twilio_sid');
        $twilio_token = get_option('ak_twilio_token');
        $twilio_phone = get_option('ak_twilio_phone');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_phone)) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilio_sid}/Messages.json";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$twilio_sid}:{$twilio_token}"),
            ),
            'body' => array(
                'From' => $twilio_phone,
                'To' => $phone,
                'Body' => $message,
            ),
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['sid'])) {
            // Log usage
            $this->log_usage($user_id, 'outreach_sms', $phone);
            return array('success' => true, 'sid' => $body['sid']);
        }
        
        return array('success' => false, 'error' => $body['message'] ?? 'Unknown error');
    }
    
    /**
     * Send outreach AI call
     */
    private function send_outreach_call($phone, $script, $user_id) {
        // Generate AI voice audio
        $audio_url = null;
        
        if ($this->elevenlabs_service) {
            $audio_result = $this->elevenlabs_service->generate_speech($script);
            if ($audio_result && isset($audio_result['url'])) {
                $audio_url = $audio_result['url'];
            }
        }
        
        // Make call via Twilio
        $twilio_sid = get_option('ak_twilio_sid');
        $twilio_token = get_option('ak_twilio_token');
        $twilio_phone = get_option('ak_twilio_phone');
        
        if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_phone)) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilio_sid}/Calls.json";
        
        // Build TwiML
        if ($audio_url) {
            $twiml = "<Response><Play>{$audio_url}</Play></Response>";
        } else {
            // Fallback to text-to-speech
            $twiml = "<Response><Say voice=\"alice\" language=\"en-GB\">{$script}</Say></Response>";
        }
        
        $twiml_url = 'http://twimlets.com/echo?Twiml=' . urlencode($twiml);
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$twilio_sid}:{$twilio_token}"),
            ),
            'body' => array(
                'From' => $twilio_phone,
                'To' => $phone,
                'Url' => $twiml_url,
            ),
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['sid'])) {
            $this->log_usage($user_id, 'outreach_call', $phone);
            return array('success' => true, 'sid' => $body['sid']);
        }
        
        return array('success' => false, 'error' => $body['message'] ?? 'Unknown error');
    }
    
    /**
     * Get user credits
     */
    private function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT sms_credits, call_credits, email_credits FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        return $credits ?: array('sms_credits' => 0, 'call_credits' => 0, 'email_credits' => 0);
    }
    
    /**
     * Deduct credits
     */
    private function deduct_credits($user_id, $credit_type, $amount) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET {$credit_type} = {$credit_type} - %d WHERE user_id = %d",
            $amount, $user_id
        ));
    }
    
    /**
     * Log usage
     */
    private function log_usage($user_id, $type, $recipient) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'type' => $type,
            'recipient' => $recipient,
            'created_at' => current_time('mysql'),
        ), array('%d', '%s', '%s', '%s'));
    }
    
    /**
     * Format phone number to E.164
     */
    private function format_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (strpos($phone, '+') !== 0) {
            if (strpos($phone, '0') === 0) {
                $phone = '+44' . substr($phone, 1);
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Generate referral code
     */
    private function generate_referral_code($user_id) {
        $code = strtoupper(substr(md5($user_id . time()), 0, 8));
        update_user_meta($user_id, 'ak_referral_code', $code);
        return $code;
    }
    
    /**
     * AJAX: Get outreach history
     */
    public function ajax_get_outreach_history() {
        check_ajax_referer('ak_outreach_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in.'));
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'ak_outreach_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            wp_send_json_success(array('history' => array()));
            return;
        }
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT contact_name, contact_phone, method, status, converted, created_at 
             FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 50",
            $user_id
        ), ARRAY_A);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Render main outreach page
     */
    public function render_outreach_page() {
        if (!is_user_logged_in()) {
            return '<div class="ak-outreach-login"><p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to use the outreach feature.</p></div>';
        }
        
        if (!$this->is_enabled()) {
            return '<div class="ak-outreach-disabled"><p>This feature is currently unavailable.</p></div>';
        }
        
        $user_id = get_current_user_id();
        $credits = $this->get_user_credits($user_id);
        $today_count = $this->get_today_count($user_id);
        $remaining = $this->get_daily_limit() - $today_count;
        
        ob_start();
        ?>
        <div class="ak-outreach-container">
            <div class="ak-outreach-header">
                <h1>Invite Friends & Earn Credits</h1>
                <p class="ak-outreach-subtitle">Share AppointmentKeeper with your network. When they sign up, you both win!</p>
            </div>
            
            <div class="ak-outreach-stats">
                <div class="ak-stat-card">
                    <span class="ak-stat-icon">📱</span>
                    <span class="ak-stat-value"><?php echo esc_html($credits['sms_credits']); ?></span>
                    <span class="ak-stat-label">SMS Credits</span>
                </div>
                <div class="ak-stat-card">
                    <span class="ak-stat-icon">📞</span>
                    <span class="ak-stat-value"><?php echo esc_html($credits['call_credits']); ?></span>
                    <span class="ak-stat-label">Call Minutes</span>
                </div>
                <div class="ak-stat-card">
                    <span class="ak-stat-icon">🎯</span>
                    <span class="ak-stat-value"><?php echo esc_html($remaining); ?></span>
                    <span class="ak-stat-label">Invites Left Today</span>
                </div>
            </div>
            
            <div class="ak-outreach-form-card">
                <h2>Send an Invite</h2>
                <p>Enter your contact's details and choose how to reach them.</p>
                
                <form id="ak-outreach-form" class="ak-outreach-form">
                    <div class="ak-form-row">
                        <div class="ak-form-group">
                            <label for="contact_name">Contact Name</label>
                            <input type="text" id="contact_name" name="contact_name" placeholder="e.g. Sarah" required>
                        </div>
                        <div class="ak-form-group">
                            <label for="contact_phone">Phone Number</label>
                            <input type="tel" id="contact_phone" name="contact_phone" placeholder="e.g. 07700 900123" required>
                        </div>
                    </div>
                    
                    <div class="ak-method-selector">
                        <label class="ak-method-option">
                            <input type="radio" name="method" value="sms" checked>
                            <div class="ak-method-card">
                                <span class="ak-method-icon">💬</span>
                                <span class="ak-method-title">Send SMS</span>
                                <span class="ak-method-cost"><?php echo esc_html($this->get_sms_cost()); ?> credits</span>
                            </div>
                        </label>
                        <label class="ak-method-option">
                            <input type="radio" name="method" value="call">
                            <div class="ak-method-card">
                                <span class="ak-method-icon">🤖</span>
                                <span class="ak-method-title">AI Voice Call</span>
                                <span class="ak-method-cost"><?php echo esc_html($this->get_call_cost()); ?> minutes</span>
                            </div>
                        </label>
                    </div>
                    
                    <button type="submit" class="ak-outreach-submit" id="ak-outreach-submit">
                        <span class="ak-btn-text">Send Invitation</span>
                        <span class="ak-btn-loading" style="display:none;">Sending...</span>
                    </button>
                </form>
                
                <div id="ak-outreach-result" class="ak-outreach-result" style="display:none;"></div>
            </div>
            
            <div class="ak-outreach-rewards">
                <h2>Rewards</h2>
                <div class="ak-reward-info">
                    <div class="ak-reward-item">
                        <span class="ak-reward-icon">🎁</span>
                        <div class="ak-reward-text">
                            <strong>You Get:</strong> 10 bonus SMS credits for each signup
                        </div>
                    </div>
                    <div class="ak-reward-item">
                        <span class="ak-reward-icon">🎉</span>
                        <div class="ak-reward-text">
                            <strong>They Get:</strong> Extended 7-day trial (instead of 3)
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="ak-outreach-history">
                <h2>Your Outreach History</h2>
                <div id="ak-history-container">
                    <p class="ak-loading">Loading history...</p>
                </div>
            </div>
            
            <div class="ak-outreach-tips">
                <h3>Tips for Success</h3>
                <ul>
                    <li>Only contact people you actually know - cold outreach doesn't convert</li>
                    <li>Let them know YOU sent it (mention it in conversation)</li>
                    <li>Business owners and freelancers are your best targets</li>
                    <li>SMS has 5x better response rate than calls for first contact</li>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render compact widget version
     */
    public function render_outreach_widget() {
        if (!is_user_logged_in() || !$this->is_enabled()) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ak-outreach-widget">
            <a href="<?php echo esc_url(home_url('/ai-outreach')); ?>" class="ak-widget-link">
                <span class="ak-widget-icon">📨</span>
                <span class="ak-widget-text">Invite Friends & Earn Credits</span>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
AK_AI_Outreach::get_instance();
