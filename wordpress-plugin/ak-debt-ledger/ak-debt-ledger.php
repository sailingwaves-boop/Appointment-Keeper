<?php
/**
 * Plugin Name: AppointmentKeeper AK Debt Ledger
 * Plugin URI: https://appointmentkeeper.com
 * Description: A billing ledger plugin for tracking debts, payments, and sending reminders via Twilio SMS and email. Integrates with Amelia booking plugin.
 * Version: 1.0.0
 * Author: AppointmentKeeper
 * License: GPL v2 or later
 * Text Domain: ak-debt-ledger
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AK_DEBT_LEDGER_VERSION', '1.0.0');
define('AK_DEBT_LEDGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AK_DEBT_LEDGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-database.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-amelia-integration.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-twilio-sms.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-email-sender.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-reminder-cron.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once AK_DEBT_LEDGER_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

/**
 * Main Plugin Class
 */
class AK_Debt_Ledger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        // Create database tables
        AK_Debt_Ledger_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron
        if (!wp_next_scheduled('ak_debt_ledger_reminder_cron')) {
            wp_schedule_event(time(), 'hourly', 'ak_debt_ledger_reminder_cron');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('ak_debt_ledger_reminder_cron');
    }
    
    private function set_default_options() {
        $default_settings = array(
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_phone_number' => '',
            'reminder_interval_days' => 7,
            'sms_template' => 'Hi {customer_name}, this is a reminder that you have an outstanding balance of {currency}{current_balance}. Please arrange payment at your earliest convenience. - AppointmentKeeper',
            'email_subject_template' => 'Payment Reminder - Outstanding Balance of {currency}{current_balance}',
            'email_body_template' => "Dear {customer_name},\n\nThis is a friendly reminder that you have an outstanding balance of {currency}{current_balance}.\n\nPlease arrange payment at your earliest convenience.\n\nIf you have already made this payment, please disregard this message.\n\nBest regards,\nAppointmentKeeper",
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name')
        );
        
        if (!get_option('ak_debt_ledger_settings')) {
            add_option('ak_debt_ledger_settings', $default_settings);
        }
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('ak-debt-ledger', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin pages
        if (is_admin()) {
            new AK_Debt_Ledger_Admin_Pages();
            new AK_Debt_Ledger_Ajax_Handlers();
        }
        
        // Initialize cron handler
        new AK_Debt_Ledger_Reminder_Cron();
    }
}

// Initialize the plugin
AK_Debt_Ledger::get_instance();
