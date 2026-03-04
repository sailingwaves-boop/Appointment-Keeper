<?php
/**
 * Plugin Name: AppointmentKeeper AK Debt Ledger
 * Plugin URI: https://appointmentkeeper.com
 * Description: Complete billing ledger with credit management, SMS/Email/Voice reminders via Twilio, Amelia integration, and referral system.
 * Version: 2.0.0
 * Author: AppointmentKeeper
 * License: GPL v2 or later
 * Text Domain: ak-debt-ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AK_DEBT_LEDGER_VERSION', '2.0.0');
define('AK_DEBT_LEDGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AK_DEBT_LEDGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-database.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-amelia-integration.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-twilio.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-reminder-cron.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-customer-panel.php';

class AK_Debt_Ledger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        AK_Debt_Ledger_Database::create_tables();
        $this->set_default_options();
        
        if (!wp_next_scheduled('ak_debt_ledger_reminder_cron')) {
            wp_schedule_event(time(), 'hourly', 'ak_debt_ledger_reminder_cron');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('ak_debt_ledger_reminder_cron');
    }
    
    private function set_default_options() {
        $default_settings = array(
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_phone_number' => '',
            'twilio_call_number' => '',
            'sendgrid_api_key' => '',
            'reminder_interval_days' => 7,
            'low_credit_threshold' => 10,
            'signup_link' => home_url('/register'),
            'sms_template' => 'Hi {customer_name}, this is a reminder that you have an outstanding balance of {currency}{current_balance}. Please arrange payment. - {creditor_name}',
            'email_subject_template' => 'Payment Reminder - Outstanding Balance of {currency}{current_balance}',
            'email_body_template' => "Dear {customer_name},\n\nThis is a reminder that you have an outstanding balance of {currency}{current_balance}.\n\nPlease arrange payment at your earliest convenience.\n\nBest regards,\n{creditor_name}",
            'call_script_template' => 'Hello {customer_name}. This is a reminder from {creditor_name} that you have an outstanding balance of {currency} {current_balance}. Please arrange payment at your earliest convenience. Thank you. Goodbye.',
            'referral_sms_template' => 'Hi! {referrer_name} thinks you would benefit from AppointmentKeeper - never miss an appointment again! Plus get a FREE Debt Ledger. Sign up here: {signup_link}',
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name')
        );
        
        if (!get_option('ak_debt_ledger_settings')) {
            add_option('ak_debt_ledger_settings', $default_settings);
        }
    }
    
    public function init() {
        load_plugin_textdomain('ak-debt-ledger', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize Amelia integration
        new AK_Debt_Ledger_Amelia_Integration();
        
        if (is_admin()) {
            new AK_Debt_Ledger_Admin_Pages();
            new AK_Debt_Ledger_Ajax_Handlers();
        }
        
        // Customer panel (frontend)
        new AK_Debt_Ledger_Customer_Panel();
        
        // Cron handler
        new AK_Debt_Ledger_Reminder_Cron();
    }
}

AK_Debt_Ledger::get_instance();
