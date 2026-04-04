<?php
/**
 * Admin Settings for AK Customer Dashboard
 * Includes: Stripe, Twilio, ElevenLabs API keys with connection test buttons
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Dashboard_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ak_test_twilio', array($this, 'test_twilio_connection'));
        add_action('wp_ajax_ak_test_elevenlabs', array($this, 'test_elevenlabs_connection'));
        add_action('wp_ajax_ak_test_stripe', array($this, 'test_stripe_connection'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_settings_page() {
        add_options_page(
            'AppointmentKeeper Settings',
            'AppointmentKeeper',
            'manage_options',
            'ak-dashboard-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        // Stripe settings
        register_setting('ak_dashboard_settings', 'ak_stripe_secret_key');
        register_setting('ak_dashboard_settings', 'ak_stripe_publishable_key');
        
        // Twilio settings
        register_setting('ak_dashboard_settings', 'ak_twilio_account_sid');
        register_setting('ak_dashboard_settings', 'ak_twilio_auth_token');
        register_setting('ak_dashboard_settings', 'ak_twilio_phone_number');
        
        // ElevenLabs settings
        register_setting('ak_dashboard_settings', 'ak_elevenlabs_api_key');
        register_setting('ak_dashboard_settings', 'ak_elevenlabs_voice_id');
        
        // Notification settings
        register_setting('ak_dashboard_settings', 'ak_low_credit_threshold', array(
            'default' => 10,
            'sanitize_callback' => 'absint'
        ));
        register_setting('ak_dashboard_settings', 'ak_enable_low_credit_emails', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('ak_dashboard_settings', 'ak_enable_auto_topup', array(
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Referral settings
        register_setting('ak_dashboard_settings', 'ak_referral_reward_credits', array(
            'default' => 10,
            'sanitize_callback' => 'absint'
        ));
        
        // Legal pages
        register_setting('ak_dashboard_settings', 'ak_terms_page_id');
        register_setting('ak_dashboard_settings', 'ak_privacy_page_id');
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ak-dashboard-settings') {
            return;
        }
        
        wp_enqueue_style('ak-admin-settings', AK_DASHBOARD_PLUGIN_URL . 'assets/admin-settings.css', array(), AK_DASHBOARD_VERSION);
        wp_enqueue_script('ak-admin-settings', AK_DASHBOARD_PLUGIN_URL . 'assets/admin-settings.js', array('jquery'), AK_DASHBOARD_VERSION, true);
        
        wp_localize_script('ak-admin-settings', 'akAdminData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_admin_nonce')
        ));
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap ak-settings-wrap">
            <h1>AppointmentKeeper Settings</h1>
            
            <div class="ak-settings-tabs">
                <button class="ak-tab-btn active" data-tab="stripe">Stripe</button>
                <button class="ak-tab-btn" data-tab="twilio">Twilio</button>
                <button class="ak-tab-btn" data-tab="elevenlabs">ElevenLabs</button>
                <button class="ak-tab-btn" data-tab="notifications">Notifications</button>
                <button class="ak-tab-btn" data-tab="referrals">Referrals</button>
                <button class="ak-tab-btn" data-tab="legal">Legal Pages</button>
                <button class="ak-tab-btn" data-tab="webhooks">Webhooks</button>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('ak_dashboard_settings'); ?>
                
                <!-- Stripe Tab -->
                <div class="ak-tab-content active" id="ak-tab-stripe">
                    <div class="ak-settings-section">
                        <h2>Stripe API Keys</h2>
                        <p class="description">Enter your Stripe API keys to enable billing. Get them from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Publishable Key</th>
                                <td>
                                    <input type="text" name="ak_stripe_publishable_key" 
                                           value="<?php echo esc_attr(get_option('ak_stripe_publishable_key')); ?>" 
                                           class="regular-text" placeholder="pk_live_...">
                                    <p class="description">Starts with pk_live_ or pk_test_</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Secret Key</th>
                                <td>
                                    <div class="ak-key-field">
                                        <input type="password" name="ak_stripe_secret_key" id="ak_stripe_secret_key"
                                               value="<?php echo esc_attr(get_option('ak_stripe_secret_key')); ?>" 
                                               class="regular-text" placeholder="sk_live_...">
                                        <button type="button" class="button ak-toggle-key" data-target="ak_stripe_secret_key">Show</button>
                                    </div>
                                    <p class="description">Starts with sk_live_ or sk_test_</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Connection</th>
                                <td>
                                    <button type="button" class="button button-secondary ak-test-connection" data-service="stripe">
                                        Test Stripe Connection
                                    </button>
                                    <span class="ak-test-result" id="ak-stripe-result"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Twilio Tab -->
                <div class="ak-tab-content" id="ak-tab-twilio">
                    <div class="ak-settings-section">
                        <h2>Twilio Settings</h2>
                        <p class="description">Configure Twilio for SMS and voice calls. Get your credentials from <a href="https://console.twilio.com" target="_blank">Twilio Console</a>.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Account SID</th>
                                <td>
                                    <input type="text" name="ak_twilio_account_sid" 
                                           value="<?php echo esc_attr(get_option('ak_twilio_account_sid')); ?>" 
                                           class="regular-text" placeholder="AC...">
                                    <p class="description">Found on your Twilio Console dashboard</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Auth Token</th>
                                <td>
                                    <div class="ak-key-field">
                                        <input type="password" name="ak_twilio_auth_token" id="ak_twilio_auth_token"
                                               value="<?php echo esc_attr(get_option('ak_twilio_auth_token')); ?>" 
                                               class="regular-text" placeholder="Your auth token">
                                        <button type="button" class="button ak-toggle-key" data-target="ak_twilio_auth_token">Show</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Phone Number</th>
                                <td>
                                    <input type="text" name="ak_twilio_phone_number" 
                                           value="<?php echo esc_attr(get_option('ak_twilio_phone_number')); ?>" 
                                           class="regular-text" placeholder="+447...">
                                    <p class="description">Your Twilio phone number (with country code)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Connection</th>
                                <td>
                                    <button type="button" class="button button-secondary ak-test-connection" data-service="twilio">
                                        Test Twilio Connection
                                    </button>
                                    <span class="ak-test-result" id="ak-twilio-result"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- ElevenLabs Tab -->
                <div class="ak-tab-content" id="ak-tab-elevenlabs">
                    <div class="ak-settings-section">
                        <h2>ElevenLabs Settings</h2>
                        <p class="description">Configure ElevenLabs for AI voice generation. Get your API key from <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank">ElevenLabs</a>.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">API Key</th>
                                <td>
                                    <div class="ak-key-field">
                                        <input type="password" name="ak_elevenlabs_api_key" id="ak_elevenlabs_api_key"
                                               value="<?php echo esc_attr(get_option('ak_elevenlabs_api_key')); ?>" 
                                               class="regular-text" placeholder="Your ElevenLabs API key">
                                        <button type="button" class="button ak-toggle-key" data-target="ak_elevenlabs_api_key">Show</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Voice ID (Optional)</th>
                                <td>
                                    <input type="text" name="ak_elevenlabs_voice_id" 
                                           value="<?php echo esc_attr(get_option('ak_elevenlabs_voice_id')); ?>" 
                                           class="regular-text" placeholder="Default voice ID">
                                    <p class="description">Leave blank to use the default voice. Find voice IDs in ElevenLabs Voice Lab.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Connection</th>
                                <td>
                                    <button type="button" class="button button-secondary ak-test-connection" data-service="elevenlabs">
                                        Test ElevenLabs Connection
                                    </button>
                                    <span class="ak-test-result" id="ak-elevenlabs-result"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Notifications Tab -->
                <div class="ak-tab-content" id="ak-tab-notifications">
                    <div class="ak-settings-section">
                        <h2>Credit Notifications</h2>
                        <p class="description">Configure automatic credit warnings and top-up settings.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Low Credit Threshold</th>
                                <td>
                                    <input type="number" name="ak_low_credit_threshold" 
                                           value="<?php echo esc_attr(get_option('ak_low_credit_threshold', 10)); ?>" 
                                           class="small-text" min="1" max="100">
                                    <p class="description">Send warning when any credit type falls below this number</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Low Credit Emails</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_enable_low_credit_emails" value="yes"
                                               <?php checked(get_option('ak_enable_low_credit_emails', 'yes'), 'yes'); ?>>
                                        Send email alerts when credits are running low
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Auto Top-Up</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_enable_auto_topup" value="yes"
                                               <?php checked(get_option('ak_enable_auto_topup', 'no'), 'yes'); ?>>
                                        Allow customers to enable auto top-up
                                    </label>
                                    <p class="description">Customers can opt-in to automatically purchase more credits</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Referrals Tab -->
                <div class="ak-tab-content" id="ak-tab-referrals">
                    <div class="ak-settings-section">
                        <h2>Referral Program</h2>
                        <p class="description">Configure rewards for customer referrals.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Referral Reward</th>
                                <td>
                                    <input type="number" name="ak_referral_reward_credits" 
                                           value="<?php echo esc_attr(get_option('ak_referral_reward_credits', 10)); ?>" 
                                           class="small-text" min="0" max="100">
                                    <span>SMS credits</span>
                                    <p class="description">Both referrer and new customer receive this reward when signup is complete</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Legal Pages Tab -->
                <div class="ak-tab-content" id="ak-tab-legal">
                    <div class="ak-settings-section">
                        <h2>Legal Pages</h2>
                        <p class="description">Select the pages for Terms & Conditions and Privacy Policy.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Terms & Conditions Page</th>
                                <td>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'name' => 'ak_terms_page_id',
                                        'selected' => get_option('ak_terms_page_id'),
                                        'show_option_none' => '— Select Page —',
                                        'option_none_value' => ''
                                    ));
                                    ?>
                                    <p class="description">Users must agree to this before proceeding</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Privacy Policy Page</th>
                                <td>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'name' => 'ak_privacy_page_id',
                                        'selected' => get_option('ak_privacy_page_id'),
                                        'show_option_none' => '— Select Page —',
                                        'option_none_value' => ''
                                    ));
                                    ?>
                                    <p class="description">GDPR compliance page</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Webhooks Tab -->
                <div class="ak-tab-content" id="ak-tab-webhooks">
                    <div class="ak-settings-section">
                        <h2>Webhook URLs</h2>
                        <p class="description">Add these URLs to your third-party service settings.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Stripe Webhook</th>
                                <td>
                                    <code class="ak-webhook-url"><?php echo esc_url(rest_url('ak-billing/v1/webhook')); ?></code>
                                    <button type="button" class="button ak-copy-btn" data-copy="<?php echo esc_url(rest_url('ak-billing/v1/webhook')); ?>">Copy</button>
                                    <p class="description">Events: checkout.session.completed, invoice.paid, customer.subscription.deleted</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Credit Deduction API</th>
                                <td>
                                    <code class="ak-webhook-url"><?php echo esc_url(rest_url('ak-credit/v1/deduct')); ?></code>
                                    <button type="button" class="button ak-copy-btn" data-copy="<?php echo esc_url(rest_url('ak-credit/v1/deduct')); ?>">Copy</button>
                                    <p class="description">Use this in Zapier to deduct credits. Requires API key header.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Test Stripe connection
     */
    public function test_stripe_connection() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $secret_key = get_option('ak_stripe_secret_key');
        
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'No API key configured'));
        }
        
        $response = wp_remote_get('https://api.stripe.com/v1/balance', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error(array('message' => $body['error']['message']));
        }
        
        wp_send_json_success(array('message' => 'Connected successfully!'));
    }
    
    /**
     * Test Twilio connection
     */
    public function test_twilio_connection() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $account_sid = get_option('ak_twilio_account_sid');
        $auth_token = get_option('ak_twilio_auth_token');
        
        if (empty($account_sid) || empty($auth_token)) {
            wp_send_json_error(array('message' => 'Account SID and Auth Token required'));
        }
        
        $response = wp_remote_get('https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '.json', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token)
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['code'])) {
            wp_send_json_error(array('message' => $body['message']));
        }
        
        if (isset($body['friendly_name'])) {
            wp_send_json_success(array('message' => 'Connected to: ' . $body['friendly_name']));
        }
        
        wp_send_json_error(array('message' => 'Unexpected response from Twilio'));
    }
    
    /**
     * Test ElevenLabs connection
     */
    public function test_elevenlabs_connection() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $api_key = get_option('ak_elevenlabs_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'No API key configured'));
        }
        
        $response = wp_remote_get('https://api.elevenlabs.io/v1/user', array(
            'headers' => array(
                'xi-api-key' => $api_key
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['detail'])) {
            wp_send_json_error(array('message' => $body['detail']['message'] ?? 'Invalid API key'));
        }
        
        if (isset($body['subscription'])) {
            $chars_left = $body['subscription']['character_limit'] - $body['subscription']['character_count'];
            wp_send_json_success(array('message' => 'Connected! ' . number_format($chars_left) . ' characters remaining'));
        }
        
        wp_send_json_error(array('message' => 'Unexpected response from ElevenLabs'));
    }
}

// Initialize if in admin
if (is_admin()) {
    new AK_Dashboard_Admin_Settings();
}
