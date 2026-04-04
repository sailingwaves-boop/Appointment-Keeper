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
        
        // AI Outreach settings
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_enabled', array(
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_daily_limit', array(
            'default' => 5,
            'sanitize_callback' => 'absint'
        ));
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_sms_cost', array(
            'default' => 2,
            'sanitize_callback' => 'absint'
        ));
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_call_cost', array(
            'default' => 2,
            'sanitize_callback' => 'absint'
        ));
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_sms_template');
        register_setting('ak_dashboard_settings', 'ak_ai_outreach_call_script');
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
                <button class="ak-tab-btn" data-tab="reminders">Auto-Reminders</button>
                <button class="ak-tab-btn" data-tab="noshow">No-Shows</button>
                <button class="ak-tab-btn" data-tab="debtchase">Debt Chase</button>
                <button class="ak-tab-btn" data-tab="notifications">Notifications</button>
                <button class="ak-tab-btn" data-tab="referrals">Referrals</button>
                <button class="ak-tab-btn" data-tab="legal">Legal Pages</button>
                <button class="ak-tab-btn" data-tab="webhooks">Webhooks</button>
                <button class="ak-tab-btn" data-tab="outreach">AI Outreach</button>
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
                
                <!-- Auto-Reminders Tab -->
                <div class="ak-tab-content" id="ak-tab-reminders">
                    <div class="ak-settings-section">
                        <h2>Automatic Appointment Reminders</h2>
                        <p class="description">Send SMS reminders automatically before Amelia appointments. Requires Twilio to be configured.</p>
                        
                        <?php 
                        $stats = AK_Auto_Reminders::get_stats();
                        ?>
                        <div class="ak-reminder-stats" style="background:#f8fafb;padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;gap:30px;">
                            <div><strong><?php echo $stats['today']; ?></strong> sent today</div>
                            <div><strong><?php echo $stats['this_week']; ?></strong> this week</div>
                            <div><strong><?php echo $stats['total']; ?></strong> total</div>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Auto-Reminders</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_auto_reminders_enabled" value="yes"
                                               <?php checked(get_option('ak_auto_reminders_enabled', 'yes'), 'yes'); ?>>
                                        Automatically send SMS reminders before appointments
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Business Name</th>
                                <td>
                                    <input type="text" name="ak_business_name" 
                                           value="<?php echo esc_attr(get_option('ak_business_name', get_bloginfo('name'))); ?>" 
                                           class="regular-text">
                                    <p class="description">Used in reminder messages</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Reminder Times</th>
                                <td>
                                    <label style="display:block;margin-bottom:8px;">
                                        <input type="checkbox" name="ak_reminder_24h_enabled" value="yes"
                                               <?php checked(get_option('ak_reminder_24h_enabled', 'yes'), 'yes'); ?>>
                                        24 hours before
                                    </label>
                                    <label style="display:block;margin-bottom:8px;">
                                        <input type="checkbox" name="ak_reminder_2h_enabled" value="yes"
                                               <?php checked(get_option('ak_reminder_2h_enabled', 'yes'), 'yes'); ?>>
                                        2 hours before
                                    </label>
                                    <label style="display:block;">
                                        <input type="checkbox" name="ak_reminder_1h_enabled" value="yes"
                                               <?php checked(get_option('ak_reminder_1h_enabled', 'no'), 'yes'); ?>>
                                        1 hour before
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Include GPS Directions</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_reminder_include_gps" value="yes"
                                               <?php checked(get_option('ak_reminder_include_gps', 'yes'), 'yes'); ?>>
                                        Add Google Maps link to reminder messages
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">24-Hour Message</th>
                                <td>
                                    <textarea name="ak_reminder_sms_24h" rows="3" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_reminder_sms_24h', 
                                            "Hi {customer_name}! Reminder: You have {service_name} tomorrow at {time}. Location: {location}. See you soon! - {business_name}"
                                        )); 
                                    ?></textarea>
                                    <p class="description">Variables: {customer_name}, {service_name}, {time}, {date}, {location}, {business_name}</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">2-Hour Message</th>
                                <td>
                                    <textarea name="ak_reminder_sms_2h" rows="3" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_reminder_sms_2h', 
                                            "Hi {customer_name}! Your {service_name} appointment is in 2 hours at {time}. {location}. See you shortly!"
                                        )); 
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Reminders</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="ak-test-reminders">
                                        Run Reminder Check Now
                                    </button>
                                    <span id="ak-reminder-result" class="ak-test-result"></span>
                                    <p class="description">Manually trigger the reminder process (normally runs hourly)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Booking Confirmations Section -->
                    <div class="ak-settings-section" style="margin-top:30px;">
                        <h2>Booking Confirmations</h2>
                        <p class="description">Send instant confirmation when someone books an appointment via Amelia.</p>
                        
                        <?php $confirm_stats = AK_Booking_Confirmations::get_stats(); ?>
                        <div style="background:#e8f5e9;padding:12px 18px;border-radius:8px;margin-bottom:20px;display:inline-block;">
                            <strong><?php echo $confirm_stats['today']; ?></strong> confirmations today | 
                            <strong><?php echo $confirm_stats['total']; ?></strong> total
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Confirmations</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_booking_confirm_enabled" value="yes"
                                               <?php checked(get_option('ak_booking_confirm_enabled', 'yes'), 'yes'); ?>>
                                        Send confirmations when appointments are booked
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Confirmation Methods</th>
                                <td>
                                    <label style="display:block;margin-bottom:8px;">
                                        <input type="checkbox" name="ak_booking_confirm_email" value="yes"
                                               <?php checked(get_option('ak_booking_confirm_email', 'yes'), 'yes'); ?>>
                                        📧 Email
                                    </label>
                                    <label style="display:block;margin-bottom:8px;">
                                        <input type="checkbox" name="ak_booking_confirm_sms" value="yes"
                                               <?php checked(get_option('ak_booking_confirm_sms', 'no'), 'yes'); ?>>
                                        📱 SMS (uses 1 credit)
                                    </label>
                                    <label style="display:block;">
                                        <input type="checkbox" name="ak_booking_confirm_call" value="yes"
                                               <?php checked(get_option('ak_booking_confirm_call', 'no'), 'yes'); ?>>
                                        📞 AI Voice Call (uses 1 call credit)
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Use AI Voice for Calls</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_booking_confirm_use_ai_voice" value="yes"
                                               <?php checked(get_option('ak_booking_confirm_use_ai_voice', 'yes'), 'yes'); ?>>
                                        Use ElevenLabs AI voice (unchecked = Twilio robot voice)
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Email Subject</th>
                                <td>
                                    <input type="text" name="ak_booking_confirm_email_subject" class="regular-text"
                                           value="<?php echo esc_attr(get_option('ak_booking_confirm_email_subject', 'Booking Confirmed - {service_name}')); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">SMS Message</th>
                                <td>
                                    <textarea name="ak_booking_confirm_sms_template" rows="3" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_booking_confirm_sms_template', 
                                            "Hi {customer_name}! Your {service_name} booking is confirmed for {date} at {time}. Location: {location}. See you then! - {business_name}"
                                        )); 
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Call Script</th>
                                <td>
                                    <textarea name="ak_booking_confirm_call_template" rows="3" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_booking_confirm_call_template', 
                                            "Hello {customer_name}! This is a confirmation call from {business_name}. Your {service_name} appointment has been booked for {date} at {time}. We look forward to seeing you. Thank you for booking with us!"
                                        )); 
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Confirmation</th>
                                <td>
                                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                                        <input type="tel" id="ak-test-confirm-phone" placeholder="Phone for SMS/Call test" style="width:200px;">
                                        <input type="email" id="ak-test-confirm-email" placeholder="Email (optional)" style="width:200px;">
                                    </div>
                                    <button type="button" class="button button-secondary" id="ak-test-confirmation">
                                        Send Test Confirmation
                                    </button>
                                    <span id="ak-confirm-result" class="ak-test-result"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- No-Show Tracking Tab -->
                <div class="ak-tab-content" id="ak-tab-noshow">
                    <div class="ak-settings-section">
                        <h2>No-Show Tracking</h2>
                        <p class="description">Track customers who miss appointments and flag repeat offenders.</p>
                        
                        <?php $noshow_stats = AK_NoShow_Tracker::get_stats(); ?>
                        <div style="background:#f8d7da;padding:12px 18px;border-radius:8px;margin-bottom:20px;display:inline-block;border:1px solid #f5c6cb;">
                            <strong><?php echo $noshow_stats['total_noshows']; ?></strong> total no-shows | 
                            <strong><?php echo $noshow_stats['flagged_customers']; ?></strong> flagged customers
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Tracking</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_noshow_tracking_enabled" value="yes"
                                               <?php checked(get_option('ak_noshow_tracking_enabled', 'yes'), 'yes'); ?>>
                                        Track no-shows and flag repeat offenders
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Auto-Mark as No-Show</th>
                                <td>
                                    <input type="number" name="ak_noshow_auto_mark_hours" 
                                           value="<?php echo esc_attr(get_option('ak_noshow_auto_mark_hours', 2)); ?>" 
                                           class="small-text" min="1" max="24">
                                    <span>hours after appointment time</span>
                                    <p class="description">Automatically mark appointments as no-show if not checked in</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Flag Threshold</th>
                                <td>
                                    <input type="number" name="ak_noshow_flag_threshold" 
                                           value="<?php echo esc_attr(get_option('ak_noshow_flag_threshold', 3)); ?>" 
                                           class="small-text" min="1" max="10">
                                    <span>no-shows before flagging customer</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Send Warning SMS</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_noshow_send_warning" value="yes"
                                               <?php checked(get_option('ak_noshow_send_warning', 'yes'), 'yes'); ?>>
                                        Send SMS when customer is marked as no-show
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Warning Message</th>
                                <td>
                                    <textarea name="ak_noshow_warning_template" rows="3" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_noshow_warning_template', 
                                            "Hi {customer_name}, We noticed you missed your {service_name} appointment on {date}. Please let us know if you need to reschedule. Repeated no-shows may result in booking restrictions. - {business_name}"
                                        )); 
                                    ?></textarea>
                                    <p class="description">Variables: {customer_name}, {service_name}, {date}, {business_name}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Debt Auto-Chase Tab -->
                <div class="ak-tab-content" id="ak-tab-debtchase">
                    <div class="ak-settings-section">
                        <h2>Debt Auto-Chase</h2>
                        <p class="description">Automatically send payment reminders to debtors at set intervals.</p>
                        
                        <?php $debt_stats = AK_Debt_AutoChase::get_stats(); ?>
                        <div style="background:#fff3cd;padding:12px 18px;border-radius:8px;margin-bottom:20px;display:inline-block;border:1px solid #ffc107;">
                            <strong><?php echo $debt_stats['total_reminders_sent']; ?></strong> reminders sent | 
                            <strong><?php echo $debt_stats['debts_chased']; ?></strong> debts chased
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Auto-Chase</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ak_debt_chase_enabled" value="yes"
                                               <?php checked(get_option('ak_debt_chase_enabled', 'yes'), 'yes'); ?>>
                                        Automatically send payment reminders
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Chase Method</th>
                                <td>
                                    <select name="ak_debt_chase_method">
                                        <option value="sms" <?php selected(get_option('ak_debt_chase_method', 'sms'), 'sms'); ?>>SMS only</option>
                                        <option value="email" <?php selected(get_option('ak_debt_chase_method'), 'email'); ?>>Email only</option>
                                        <option value="both" <?php selected(get_option('ak_debt_chase_method'), 'both'); ?>>Both SMS & Email</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Chase Schedule</th>
                                <td>
                                    <div style="display:flex;gap:15px;flex-wrap:wrap;">
                                        <label>
                                            1st reminder after 
                                            <input type="number" name="ak_debt_chase_day_1" 
                                                   value="<?php echo esc_attr(get_option('ak_debt_chase_day_1', 7)); ?>" 
                                                   class="small-text" min="1"> days
                                        </label>
                                        <label>
                                            2nd after 
                                            <input type="number" name="ak_debt_chase_day_2" 
                                                   value="<?php echo esc_attr(get_option('ak_debt_chase_day_2', 14)); ?>" 
                                                   class="small-text" min="1"> days
                                        </label>
                                        <label>
                                            3rd after 
                                            <input type="number" name="ak_debt_chase_day_3" 
                                                   value="<?php echo esc_attr(get_option('ak_debt_chase_day_3', 30)); ?>" 
                                                   class="small-text" min="1"> days
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Max Reminders</th>
                                <td>
                                    <input type="number" name="ak_debt_chase_max_reminders" 
                                           value="<?php echo esc_attr(get_option('ak_debt_chase_max_reminders', 3)); ?>" 
                                           class="small-text" min="1" max="10">
                                    <span>per debt</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">1st Reminder (Friendly)</th>
                                <td>
                                    <textarea name="ak_debt_chase_msg_1" rows="2" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_debt_chase_msg_1', 
                                            "Hi {debtor_name}, friendly reminder: You have an outstanding balance of £{amount} with {creditor_name}. Please arrange payment at your earliest convenience. Reply PAID if already settled."
                                        )); 
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">2nd Reminder (Follow-up)</th>
                                <td>
                                    <textarea name="ak_debt_chase_msg_2" rows="2" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_debt_chase_msg_2', 
                                            "Hi {debtor_name}, this is a follow-up regarding your outstanding balance of £{amount} with {creditor_name}. Please contact us to discuss payment options."
                                        )); 
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">3rd Reminder (Final)</th>
                                <td>
                                    <textarea name="ak_debt_chase_msg_3" rows="2" class="large-text"><?php 
                                        echo esc_textarea(get_option('ak_debt_chase_msg_3', 
                                            "FINAL REMINDER: {debtor_name}, you have an overdue balance of £{amount} with {creditor_name}. Immediate action required to avoid further steps. Please pay or contact us today."
                                        )); 
                                    ?></textarea>
                                    <p class="description">Variables: {debtor_name}, {amount}, {creditor_name}, {original_amount}</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Test Debt Chase</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="ak-test-debt-chase">
                                        Run Debt Chase Now
                                    </button>
                                    <span id="ak-debt-chase-result" class="ak-test-result"></span>
                                    <p class="description">Manually trigger the debt chase process (normally runs daily)</p>
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
                
                <!-- AI Outreach Tab -->
                <div class="ak-tab-content" id="ak-tab-outreach">
                    <div class="ak-settings-section">
                        <h2>AI Outreach Settings</h2>
                        <p class="description">Let your customers invite friends via AI-powered SMS or voice calls. They spend credits, you gain new customers!</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable AI Outreach</th>
                                <td>
                                    <label class="ak-toggle">
                                        <input type="checkbox" name="ak_ai_outreach_enabled" value="yes" <?php checked(get_option('ak_ai_outreach_enabled'), 'yes'); ?>>
                                        <span class="ak-toggle-slider"></span>
                                    </label>
                                    <p class="description">Allow customers to send promotional invites to their contacts</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Daily Limit Per User</th>
                                <td>
                                    <input type="number" name="ak_ai_outreach_daily_limit" 
                                           value="<?php echo esc_attr(get_option('ak_ai_outreach_daily_limit', 5)); ?>" 
                                           min="1" max="50" class="small-text">
                                    <p class="description">Maximum outreach messages a user can send per day (prevents spam)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">SMS Credit Cost</th>
                                <td>
                                    <input type="number" name="ak_ai_outreach_sms_cost" 
                                           value="<?php echo esc_attr(get_option('ak_ai_outreach_sms_cost', 2)); ?>" 
                                           min="1" max="10" class="small-text">
                                    <span>credits per SMS outreach</span>
                                    <p class="description">Recommended: 2 (premium action = higher cost)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Call Credit Cost</th>
                                <td>
                                    <input type="number" name="ak_ai_outreach_call_cost" 
                                           value="<?php echo esc_attr(get_option('ak_ai_outreach_call_cost', 2)); ?>" 
                                           min="1" max="10" class="small-text">
                                    <span>minutes per AI voice call</span>
                                    <p class="description">Recommended: 2 (premium action = higher cost)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">SMS Message Template</th>
                                <td>
                                    <?php 
                                    $default_sms = "Hi {contact_name}, your friend {sender_name} thought you might like AppointmentKeeper - it helps businesses chase payments and manage appointments automatically. Get a free trial: {signup_link} Reply STOP to opt out.";
                                    $current_sms = get_option('ak_ai_outreach_sms_template', $default_sms);
                                    ?>
                                    <textarea name="ak_ai_outreach_sms_template" rows="4" class="large-text"><?php echo esc_textarea($current_sms); ?></textarea>
                                    <p class="description">
                                        Variables: <code>{contact_name}</code> <code>{sender_name}</code> <code>{signup_link}</code>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">AI Call Script</th>
                                <td>
                                    <?php 
                                    $default_call = "Hello {contact_name}, I'm calling on behalf of your friend {sender_name}. They've been using AppointmentKeeper to manage their appointments and chase payments automatically, and they thought you might find it useful too. You can try it free for 3 days at appointmentkeeper.co.uk. Have a great day!";
                                    $current_call = get_option('ak_ai_outreach_call_script', $default_call);
                                    ?>
                                    <textarea name="ak_ai_outreach_call_script" rows="5" class="large-text"><?php echo esc_textarea($current_call); ?></textarea>
                                    <p class="description">
                                        Variables: <code>{contact_name}</code> <code>{sender_name}</code> (spoken by AI voice)
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="ak-info-box">
                            <h4>How It Works</h4>
                            <ol>
                                <li>Customer goes to <strong>/ai-outreach</strong> page</li>
                                <li>They enter a friend's name & phone number</li>
                                <li>Choose SMS or AI Voice Call</li>
                                <li>Credits are deducted, message is sent</li>
                                <li>If friend signs up, referrer gets bonus credits!</li>
                            </ol>
                            <p><strong>Marketing Page:</strong> Share <code><?php echo home_url('/get-started'); ?></code> on TikTok/Instagram!</p>
                        </div>
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
