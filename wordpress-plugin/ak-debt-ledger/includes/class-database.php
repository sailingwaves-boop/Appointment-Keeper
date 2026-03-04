<?php
/**
 * Database Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Database {
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Ledger table
        $ledger_table = $wpdb->prefix . 'ak_debt_ledger';
        $sql_ledger = "CREATE TABLE $ledger_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            amelia_customer_id bigint(20) UNSIGNED DEFAULT NULL,
            customer_name varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_address text DEFAULT NULL,
            original_amount decimal(10,2) NOT NULL,
            current_balance decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'GBP',
            debt_type enum('missed','part-paid','other') DEFAULT 'other',
            status enum('open','paid','written_off') DEFAULT 'open',
            notes text DEFAULT NULL,
            consent_to_reminders tinyint(1) DEFAULT 0,
            preferred_channel enum('sms','email','both') DEFAULT 'both',
            next_reminder_at datetime DEFAULT NULL,
            last_reminder_at datetime DEFAULT NULL,
            reminder_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY amelia_customer_id (amelia_customer_id),
            KEY status (status),
            KEY next_reminder_at (next_reminder_at)
        ) $charset_collate;";
        
        // Payments table
        $payments_table = $wpdb->prefix . 'ak_debt_payments';
        $sql_payments = "CREATE TABLE $payments_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ledger_id bigint(20) UNSIGNED NOT NULL,
            payment_date datetime NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(100) DEFAULT NULL,
            reference varchar(255) DEFAULT NULL,
            note text DEFAULT NULL,
            confirmed tinyint(1) DEFAULT 0,
            confirmed_at datetime DEFAULT NULL,
            confirmed_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ledger_id (ledger_id)
        ) $charset_collate;";
        
        // Reminder log table
        $reminders_table = $wpdb->prefix . 'ak_debt_reminders';
        $sql_reminders = "CREATE TABLE $reminders_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ledger_id bigint(20) UNSIGNED NOT NULL,
            channel enum('sms','email') NOT NULL,
            message text NOT NULL,
            status enum('sent','failed') NOT NULL,
            error_message text DEFAULT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ledger_id (ledger_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_ledger);
        dbDelta($sql_payments);
        dbDelta($sql_reminders);
    }
    
    /**
     * Get all ledger entries
     */
    public static function get_all_ledger_entries($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        $defaults = array(
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '1=1';
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        }
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
    }
    
    /**
     * Get single ledger entry
     */
    public static function get_ledger_entry($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Insert ledger entry
     */
    public static function insert_ledger_entry($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update ledger entry
     */
    public static function update_ledger_entry($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    /**
     * Delete ledger entry
     */
    public static function delete_ledger_entry($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        return $wpdb->delete($table, array('id' => $id));
    }
    
    /**
     * Get payments for a ledger entry
     */
    public static function get_payments($ledger_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_payments';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ledger_id = %d ORDER BY payment_date DESC",
            $ledger_id
        ));
    }
    
    /**
     * Insert payment
     */
    public static function insert_payment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_payments';
        
        $data['created_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Confirm payment
     */
    public static function confirm_payment($payment_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_payments';
        
        return $wpdb->update(
            $table,
            array(
                'confirmed' => 1,
                'confirmed_at' => current_time('mysql'),
                'confirmed_by' => $user_id
            ),
            array('id' => $payment_id)
        );
    }
    
    /**
     * Get single payment
     */
    public static function get_payment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_payments';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Log reminder
     */
    public static function log_reminder($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_reminders';
        
        $data['sent_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get ledger entries due for reminder
     */
    public static function get_due_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_ledger';
        
        $now = current_time('mysql');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE status = 'open' 
            AND current_balance > 0 
            AND consent_to_reminders = 1 
            AND next_reminder_at IS NOT NULL 
            AND next_reminder_at <= %s",
            $now
        ));
    }
    
    /**
     * Get reminder history for a ledger entry
     */
    public static function get_reminder_history($ledger_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_debt_reminders';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ledger_id = %d ORDER BY sent_at DESC",
            $ledger_id
        ));
    }
}
