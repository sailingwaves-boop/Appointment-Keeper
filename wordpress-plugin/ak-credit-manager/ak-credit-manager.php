<?php
/**
 * Plugin Name: AppointmentKeeper Credit Manager
 * Plugin URI: https://appointmentkeeper.com
 * Description: Central credit management system for AppointmentKeeper. Add, remove, and track customer credits. Works standalone and integrates with Amelia admin panel.
 * Version: 1.0.0
 * Author: AppointmentKeeper
 * License: GPL v2 or later
 * Text Domain: ak-credit-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AK_CREDIT_MANAGER_VERSION', '1.0.0');
define('AK_CREDIT_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AK_CREDIT_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AK_CREDIT_MANAGER_PLUGIN_DIR . 'includes/class-database.php';
require_once AK_CREDIT_MANAGER_PLUGIN_DIR . 'includes/class-credit-operations.php';
require_once AK_CREDIT_MANAGER_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once AK_CREDIT_MANAGER_PLUGIN_DIR . 'includes/class-amelia-integration.php';
require_once AK_CREDIT_MANAGER_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

class AK_Credit_Manager {
    
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
        AK_Credit_Manager_Database::create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up if needed
    }
    
    private function set_default_options() {
        $default_settings = array(
            // Plan configurations
            'plans' => array(
                'basic' => array(
                    'name' => 'Basic',
                    'sms_credits' => 50,
                    'call_credits' => 20,
                    'email_credits' => 100,
                    'price' => 9.99
                ),
                'standard' => array(
                    'name' => 'Standard',
                    'sms_credits' => 150,
                    'call_credits' => 50,
                    'email_credits' => 300,
                    'price' => 24.99
                ),
                'premium' => array(
                    'name' => 'Premium',
                    'sms_credits' => 500,
                    'call_credits' => 150,
                    'email_credits' => 1000,
                    'price' => 49.99
                ),
                'enterprise' => array(
                    'name' => 'Enterprise',
                    'sms_credits' => 2000,
                    'call_credits' => 500,
                    'email_credits' => 5000,
                    'price' => 149.99
                )
            ),
            // Free month credits (same as basic by default)
            'free_month_sms' => 50,
            'free_month_calls' => 20,
            'free_month_emails' => 100,
            // Low credit warning threshold
            'low_credit_threshold' => 10,
            // Credit costs per action
            'cost_per_sms' => 1,
            'cost_per_call' => 1,
            'cost_per_email' => 1
        );
        
        if (!get_option('ak_credit_manager_settings')) {
            add_option('ak_credit_manager_settings', $default_settings);
        }
    }
    
    public function init() {
        load_plugin_textdomain('ak-credit-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        if (is_admin()) {
            new AK_Credit_Manager_Admin_Pages();
            new AK_Credit_Manager_Ajax_Handlers();
            new AK_Credit_Manager_Amelia_Integration();
        }
    }
}

AK_Credit_Manager::get_instance();
