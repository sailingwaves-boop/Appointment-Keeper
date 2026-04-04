<?php
/**
 * Plugin Name: AppointmentKeeper Customer Dashboard
 * Plugin URI: https://appointmentkeeper.co.uk
 * Description: Unified customer dashboard bringing together Amelia appointments, credits, debt ledger, and referrals in one place.
 * Version: 1.0.0
 * Author: AppointmentKeeper
 * License: GPL v2 or later
 * Text Domain: ak-dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AK_DASHBOARD_VERSION', '1.1.0');
define('AK_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AK_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include signup handler
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-signup-handler.php';

// Include billing handler
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-stripe-billing.php';

// Include admin settings
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-admin-settings.php';

// Include profile form
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-profile-form.php';

// Include referral system
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-referral-system.php';

// Include credit notifications
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-credit-notifications.php';

// Include webhook logger
require_once AK_DASHBOARD_PLUGIN_DIR . 'includes/class-webhook-logger.php';

class AK_Customer_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('ak_customer_dashboard', array($this, 'render_dashboard'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Initialize signup handler
        new AK_Signup_Handler();
        
        // Initialize billing handler
        new AK_Stripe_Billing();
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=ak-dashboard-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function init() {
        // Create dashboard page on activation if it doesn't exist
        if (get_option('ak_dashboard_page_created') !== 'yes') {
            $this->create_dashboard_page();
        }
    }
    
    /**
     * Create the dashboard page automatically
     */
    private function create_dashboard_page() {
        $page_exists = get_page_by_path('my-dashboard');
        
        if (!$page_exists) {
            $page_id = wp_insert_post(array(
                'post_title' => 'My Dashboard',
                'post_name' => 'my-dashboard',
                'post_content' => '[ak_customer_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            
            if ($page_id) {
                update_option('ak_dashboard_page_id', $page_id);
            }
        }
        
        update_option('ak_dashboard_page_created', 'yes');
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_assets() {
        if (!is_user_logged_in()) return;
        
        wp_enqueue_style(
            'ak-dashboard',
            AK_DASHBOARD_PLUGIN_URL . 'assets/dashboard.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-dashboard',
            AK_DASHBOARD_PLUGIN_URL . 'assets/dashboard.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
    }
    
    /**
     * Render the unified dashboard
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        
        // Get credits if Credit Manager is active
        $credits = $this->get_user_credits($user_id);
        
        ob_start();
        ?>
        <div class="ak-dashboard">
            <!-- Header -->
            <div class="ak-dashboard-header">
                <div class="ak-welcome">
                    <h1>Welcome back, <?php echo esc_html($user->display_name); ?>!</h1>
                    <p>Manage your appointments, credits, and more from one place.</p>
                </div>
                <?php if ($credits): ?>
                <div class="ak-credits-summary">
                    <div class="ak-credit-pill ak-sms">
                        <span class="ak-credit-icon">💬</span>
                        <span class="ak-credit-count"><?php echo intval($credits->sms_credits); ?></span>
                        <span class="ak-credit-label">SMS</span>
                    </div>
                    <div class="ak-credit-pill ak-calls">
                        <span class="ak-credit-icon">📞</span>
                        <span class="ak-credit-count"><?php echo intval($credits->call_credits); ?></span>
                        <span class="ak-credit-label">Calls</span>
                    </div>
                    <div class="ak-credit-pill ak-emails">
                        <span class="ak-credit-icon">✉️</span>
                        <span class="ak-credit-count"><?php echo intval($credits->email_credits); ?></span>
                        <span class="ak-credit-label">Emails</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Navigation Tabs -->
            <div class="ak-dashboard-nav">
                <button class="ak-tab-btn active" data-tab="appointments">
                    📅 Appointments
                </button>
                <button class="ak-tab-btn" data-tab="ledger">
                    📒 Debt Ledger
                </button>
                <button class="ak-tab-btn" data-tab="usage">
                    📊 Usage History
                </button>
                <button class="ak-tab-btn" data-tab="refer">
                    👥 Refer Friends
                </button>
            </div>
            
            <!-- Tab Content -->
            <div class="ak-dashboard-content">
                
                <!-- Appointments Tab -->
                <div class="ak-tab-content active" id="ak-tab-appointments">
                    <div class="ak-section-header">
                        <h2>Your Appointments</h2>
                        <p>View and manage your upcoming appointments</p>
                    </div>
                    <div class="ak-appointments-container">
                        <?php 
                        if (shortcode_exists('ameliacustomerpanel')) {
                            echo do_shortcode('[ameliacustomerpanel]');
                        } elseif (shortcode_exists('ameliabooking')) {
                            echo do_shortcode('[ameliabooking]');
                        } else {
                            echo '<div class="ak-notice">Amelia booking system will appear here once configured.</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Debt Ledger Tab -->
                <div class="ak-tab-content" id="ak-tab-ledger">
                    <div class="ak-section-header">
                        <h2>Debt Ledger</h2>
                        <p>Track money owed to you and send payment reminders</p>
                    </div>
                    <div class="ak-ledger-container">
                        <?php 
                        if (shortcode_exists('ak_debt_ledger')) {
                            echo do_shortcode('[ak_debt_ledger]');
                        } else {
                            echo '<div class="ak-notice">Debt Ledger will appear here once the plugin is activated.</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Usage History Tab -->
                <div class="ak-tab-content" id="ak-tab-usage">
                    <div class="ak-section-header">
                        <h2>Usage History</h2>
                        <p>See how you've used your credits this month</p>
                    </div>
                    <div class="ak-usage-container">
                        <?php 
                        if (shortcode_exists('ak_usage_history')) {
                            echo do_shortcode('[ak_usage_history]');
                        } else {
                            echo $this->render_basic_usage_history($user_id);
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Refer Friends Tab -->
                <div class="ak-tab-content" id="ak-tab-refer">
                    <div class="ak-section-header">
                        <h2>Refer Friends</h2>
                        <p>Know someone who's always late for appointments? Invite them and earn rewards!</p>
                    </div>
                    <div class="ak-refer-container">
                        <?php 
                        if (shortcode_exists('ak_refer_friend')) {
                            echo do_shortcode('[ak_refer_friend]');
                        } else {
                            echo $this->render_basic_referral_form($user_id);
                        }
                        ?>
                    </div>
                </div>
                
            </div>
            
            <!-- Quick Actions Footer -->
            <div class="ak-dashboard-footer">
                <div class="ak-quick-actions">
                    <a href="#" class="ak-action-btn ak-book-appointment" onclick="document.querySelector('[data-tab=appointments]').click(); return false;">
                        📅 Book Appointment
                    </a>
                    <a href="#" class="ak-action-btn ak-add-debt" onclick="document.querySelector('[data-tab=ledger]').click(); return false;">
                        ➕ Add Debt
                    </a>
                    <a href="<?php echo esc_url(home_url('/pricing')); ?>" class="ak-action-btn ak-top-up">
                        💳 Top Up Credits
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render login prompt for non-logged-in users
     */
    private function render_login_prompt() {
        ob_start();
        ?>
        <div class="ak-login-prompt">
            <div class="ak-login-box">
                <h2>Welcome to AppointmentKeeper</h2>
                <p>Please log in to access your dashboard.</p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="ak-login-btn">
                    Log In
                </a>
                <p class="ak-register-link">
                    Don't have an account? <a href="<?php echo esc_url(wp_registration_url()); ?>">Sign up here</a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user credits from Credit Manager
     */
    private function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Render basic usage history if shortcode not available
     */
    private function render_basic_usage_history($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return '<div class="ak-notice">Usage tracking will appear here.</div>';
        }
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
            $user_id
        ));
        
        if (empty($history)) {
            return '<div class="ak-notice">No usage recorded yet. Start sending reminders to see your history!</div>';
        }
        
        $html = '<table class="ak-usage-table">';
        $html .= '<thead><tr><th>Date</th><th>Type</th><th>Recipient</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($history as $item) {
            $type_icons = array(
                'sms_sent' => '💬 SMS',
                'call_made' => '📞 Call',
                'email_sent' => '✉️ Email'
            );
            $type = isset($type_icons[$item->usage_type]) ? $type_icons[$item->usage_type] : $item->usage_type;
            $recipient = $item->recipient_phone ?: $item->recipient_email ?: '-';
            $status_class = $item->status === 'success' ? 'ak-status-success' : 'ak-status-failed';
            
            $html .= '<tr>';
            $html .= '<td>' . esc_html(date('d M Y H:i', strtotime($item->created_at))) . '</td>';
            $html .= '<td>' . $type . '</td>';
            $html .= '<td>' . esc_html($recipient) . '</td>';
            $html .= '<td><span class="' . $status_class . '">' . esc_html(ucfirst($item->status)) . '</span></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Render basic referral form if shortcode not available
     */
    private function render_basic_referral_form($user_id) {
        ob_start();
        ?>
        <div class="ak-refer-basic">
            <div class="ak-refer-reward">
                <strong>🎁 Get a FREE month</strong> when a friend signs up!
            </div>
            <p>Know someone who's always late for appointments? Send them an invite!</p>
            <div class="ak-refer-share">
                <p>Share this link with friends:</p>
                <input type="text" readonly value="<?php echo esc_url(home_url('/register?ref=' . $user_id)); ?>" class="ak-share-link">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

AK_Customer_Dashboard::get_instance();
