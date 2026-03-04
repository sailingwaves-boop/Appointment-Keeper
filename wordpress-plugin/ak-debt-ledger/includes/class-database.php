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
        
        // Customer Credits table
        $credits_table = $wpdb->prefix . 'ak_customer_credits';
        $sql_credits = "CREATE TABLE $credits_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            amelia_customer_id bigint(20) UNSIGNED DEFAULT NULL,
            sms_credits int(11) DEFAULT 0,
            call_credits int(11) DEFAULT 0,
            email_credits int(11) DEFAULT 0,
            plan_type varchar(50) DEFAULT 'basic',
            low_credit_alert_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY amelia_customer_id (amelia_customer_id)
        ) $charset_collate;";
        
        // Usage Log table
        $usage_table = $wpdb->prefix . 'ak_usage_log';
        $sql_usage = "CREATE TABLE $usage_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            usage_type enum('sms_sent','call_made','email_sent') NOT NULL,
            reference_type enum('appointment','debt','referral') DEFAULT 'appointment',
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            recipient_phone varchar(50) DEFAULT NULL,
            recipient_email varchar(255) DEFAULT NULL,
            message_preview text DEFAULT NULL,
            credits_used int(11) DEFAULT 1,
            balance_after int(11) DEFAULT 0,
            status enum('success','failed','pending') DEFAULT 'success',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY usage_type (usage_type),
            KEY reference_type (reference_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Ledger table
        $ledger_table = $wpdb->prefix . 'ak_debt_ledger';
        $sql_ledger = "CREATE TABLE $ledger_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            creditor_name varchar(255) DEFAULT NULL,
            creditor_reference varchar(255) DEFAULT NULL,
            amelia_customer_id bigint(20) UNSIGNED DEFAULT NULL,
            customer_name varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_address text DEFAULT NULL,
            debtor2_name varchar(255) DEFAULT NULL,
            debtor2_phone varchar(50) DEFAULT NULL,
            debtor3_name varchar(255) DEFAULT NULL,
            debtor3_phone varchar(50) DEFAULT NULL,
            num_debtors int(1) DEFAULT 1,
            original_amount decimal(10,2) NOT NULL,
            current_balance decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'GBP',
            debt_type enum('missed','part-paid','other') DEFAULT 'other',
            status enum('open','paid','written_off') DEFAULT 'open',
            notes text DEFAULT NULL,
            consent_to_reminders tinyint(1) DEFAULT 0,
            remind_via_sms tinyint(1) DEFAULT 1,
            remind_via_email tinyint(1) DEFAULT 1,
            remind_via_call tinyint(1) DEFAULT 0,
            next_reminder_at datetime DEFAULT NULL,
            last_reminder_at datetime DEFAULT NULL,
            reminder_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
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
            user_id bigint(20) UNSIGNED NOT NULL,
            channel enum('sms','email','call') NOT NULL,
            message text NOT NULL,
            status enum('sent','failed') NOT NULL,
            error_message text DEFAULT NULL,
            credits_used int(11) DEFAULT 0,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ledger_id (ledger_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Referrals table
        $referrals_table = $wpdb->prefix . 'ak_referrals';
        $sql_referrals = "CREATE TABLE $referrals_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            friend_name varchar(255) NOT NULL,
            friend_phone varchar(50) NOT NULL,
            invite_sent tinyint(1) DEFAULT 0,
            invite_sent_at datetime DEFAULT NULL,
            signed_up tinyint(1) DEFAULT 0,
            signed_up_at datetime DEFAULT NULL,
            reward_given tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY friend_phone (friend_phone)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_credits);
        dbDelta($sql_usage);
        dbDelta($sql_ledger);
        dbDelta($sql_payments);
        dbDelta($sql_reminders);
        dbDelta($sql_referrals);
    }
    
    /**
     * Get or create customer credits
     */
    public static function get_customer_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$credits) {
            // Create default credits for new customer
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'sms_credits' => 50,
                'call_credits' => 20,
                'email_credits' => 100,
                'plan_type' => 'basic'
            ));
            
            $credits = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d",
                $user_id
            ));
        }
        
        return $credits;
    }
    
    /**
     * Update customer credits
     */
    public static function update_customer_credits($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        return $wpdb->update($table, $data, array('user_id' => $user_id));
    }
    
    /**
     * Deduct credits
     */
    public static function deduct_credits($user_id, $type, $amount = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $column = '';
        switch ($type) {
            case 'sms':
                $column = 'sms_credits';
                break;
            case 'call':
                $column = 'call_credits';
                break;
            case 'email':
                $column = 'email_credits';
                break;
            default:
                return false;
        }
        
        $credits = self::get_customer_credits($user_id);
        
        if ($credits->$column < $amount) {
            return false; // Not enough credits
        }
        
        $new_balance = $credits->$column - $amount;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET $column = $column - %d, updated_at = NOW() WHERE user_id = %d",
            $amount,
            $user_id
        ));
        
        // Check for low credits alert
        if ($new_balance <= 10 && !$credits->low_credit_alert_sent) {
            self::send_low_credit_alert($user_id, $type, $new_balance);
        }
        
        return $new_balance;
    }
    
    /**
     * Send low credit alert
     */
    public static function send_low_credit_alert($user_id, $type, $balance) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $type_name = ucfirst($type);
        $subject = "Low {$type_name} Credits Alert - AppointmentKeeper";
        $message = "Hi {$user->display_name},\n\n";
        $message .= "You're running low on {$type_name} credits. You have {$balance} remaining.\n\n";
        $message .= "Top up now to avoid interruption to your reminders.\n\n";
        $message .= "Best regards,\nAppointmentKeeper";
        
        wp_mail($user->user_email, $subject, $message);
        
        // Mark alert as sent
        $table = $wpdb->prefix . 'ak_customer_credits';
        $wpdb->update($table, array('low_credit_alert_sent' => 1), array('user_id' => $user_id));
    }
    
    /**
     * Log usage
     */
    public static function log_usage($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        $data['created_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get usage history for a user
     */
    public static function get_usage_history($user_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get usage summary for a user (this month)
     */
    public static function get_usage_summary($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        $first_of_month = date('Y-m-01 00:00:00');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN usage_type = 'sms_sent' AND status = 'success' THEN credits_used ELSE 0 END) as sms_used,
                SUM(CASE WHEN usage_type = 'call_made' AND status = 'success' THEN credits_used ELSE 0 END) as calls_used,
                SUM(CASE WHEN usage_type = 'email_sent' AND status = 'success' THEN credits_used ELSE 0 END) as emails_used,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
            FROM $table 
            WHERE user_id = %d AND created_at >= %s",
            $user_id,
            $first_of_month
        ));
    }
    
    /**
     * Get all ledger entries for a user
     */
    public static function get_user_ledger_entries($user_id, $args = array()) {
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
        
        $where = $wpdb->prepare('user_id = %d', $user_id);
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        }
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
    }
    
    /**
     * Get all ledger entries (admin)
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
    
    /**
     * Insert referral
     */
    public static function insert_referral($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_referrals';
        
        $data['created_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user referrals
     */
    public static function get_user_referrals($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_referrals';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Update referral
     */
    public static function update_referral($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_referrals';
        
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    /**
     * Check if phone number was referred
     */
    public static function check_referral_signup($phone) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_referrals';
        
        // Normalize phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE friend_phone LIKE %s AND signed_up = 0",
            '%' . $wpdb->esc_like($phone) . '%'
        ));
    }
}
