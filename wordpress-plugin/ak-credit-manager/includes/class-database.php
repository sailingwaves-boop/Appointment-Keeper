<?php
/**
 * Database Handler Class for Credit Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_Database {
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Credit Transaction Log table
        $log_table = $wpdb->prefix . 'ak_credit_log';
        $sql_log = "CREATE TABLE $log_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            action enum('add','deduct','refund','free_month','plan_upgrade','manual_adjust') NOT NULL,
            credit_type enum('sms','call','email','all') NOT NULL,
            amount int(11) NOT NULL,
            balance_before int(11) DEFAULT 0,
            balance_after int(11) DEFAULT 0,
            reason text DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            performed_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY credit_type (credit_type),
            KEY created_at (created_at),
            KEY performed_by (performed_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_log);
        
        // Ensure the credits table exists (shared with Debt Ledger)
        self::ensure_credits_table();
    }
    
    /**
     * Ensure the customer credits table exists
     */
    public static function ensure_credits_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $credits_table = $wpdb->prefix . 'ak_customer_credits';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$credits_table'") !== $credits_table) {
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
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_credits);
        }
    }
    
    /**
     * Get customer credits
     */
    public static function get_customer_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        self::ensure_credits_table();
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$credits) {
            // Create default credits for new customer
            $settings = get_option('ak_credit_manager_settings', array());
            $plans = isset($settings['plans']) ? $settings['plans'] : array();
            $basic = isset($plans['basic']) ? $plans['basic'] : array('sms_credits' => 50, 'call_credits' => 20, 'email_credits' => 100);
            
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'sms_credits' => $basic['sms_credits'],
                'call_credits' => $basic['call_credits'],
                'email_credits' => $basic['email_credits'],
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
     * Update customer credits directly
     */
    public static function update_customer_credits($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table, $data, array('user_id' => $user_id));
    }
    
    /**
     * Log credit transaction
     */
    public static function log_transaction($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_credit_log';
        
        $data['created_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get transaction log for a user
     */
    public static function get_user_transactions($user_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_credit_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as performed_by_name 
            FROM $table l 
            LEFT JOIN {$wpdb->users} u ON l.performed_by = u.ID
            WHERE l.user_id = %d 
            ORDER BY l.created_at DESC 
            LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get all transactions (admin view)
     */
    public static function get_all_transactions($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_credit_log';
        
        $defaults = array(
            'action' => '',
            'credit_type' => '',
            'user_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '1=1';
        
        if (!empty($args['action'])) {
            $where .= $wpdb->prepare(' AND l.action = %s', $args['action']);
        }
        
        if (!empty($args['credit_type'])) {
            $where .= $wpdb->prepare(' AND l.credit_type = %s', $args['credit_type']);
        }
        
        if (!empty($args['user_id'])) {
            $where .= $wpdb->prepare(' AND l.user_id = %d', $args['user_id']);
        }
        
        if (!empty($args['date_from'])) {
            $where .= $wpdb->prepare(' AND l.created_at >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where .= $wpdb->prepare(' AND l.created_at <= %s', $args['date_to']);
        }
        
        $sql = "SELECT l.*, u.display_name as user_name, p.display_name as performed_by_name 
                FROM $table l 
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                LEFT JOIN {$wpdb->users} p ON l.performed_by = p.ID
                WHERE $where 
                ORDER BY l.{$args['orderby']} {$args['order']} 
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
    }
    
    /**
     * Search customers by name, email, or ID
     */
    public static function search_customers($search, $limit = 20) {
        global $wpdb;
        
        $credits_table = $wpdb->prefix . 'ak_customer_credits';
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        // Search in WordPress users
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, 
                    COALESCE(c.sms_credits, 0) as sms_credits,
                    COALESCE(c.call_credits, 0) as call_credits,
                    COALESCE(c.email_credits, 0) as email_credits,
                    COALESCE(c.plan_type, 'none') as plan_type
            FROM {$wpdb->users} u
            LEFT JOIN $credits_table c ON u.ID = c.user_id
            WHERE u.display_name LIKE %s 
               OR u.user_email LIKE %s 
               OR u.ID = %d
            ORDER BY u.display_name
            LIMIT %d",
            $search_like,
            $search_like,
            intval($search),
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Get all customers with credits
     */
    public static function get_all_customers_with_credits($args = array()) {
        global $wpdb;
        
        $credits_table = $wpdb->prefix . 'ak_customer_credits';
        
        $defaults = array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'limit' => 50,
            'offset' => 0,
            'low_credits_only' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '1=1';
        
        if ($args['low_credits_only']) {
            $settings = get_option('ak_credit_manager_settings', array());
            $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
            $where .= $wpdb->prepare(' AND (c.sms_credits <= %d OR c.call_credits <= %d OR c.email_credits <= %d)', $threshold, $threshold, $threshold);
        }
        
        $sql = "SELECT u.ID, u.display_name, u.user_email, 
                       COALESCE(c.sms_credits, 0) as sms_credits,
                       COALESCE(c.call_credits, 0) as call_credits,
                       COALESCE(c.email_credits, 0) as email_credits,
                       COALESCE(c.plan_type, 'none') as plan_type,
                       c.updated_at
                FROM {$wpdb->users} u
                INNER JOIN $credits_table c ON u.ID = c.user_id
                WHERE $where
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
    }
    
    /**
     * Get credit statistics
     */
    public static function get_credit_stats() {
        global $wpdb;
        
        $credits_table = $wpdb->prefix . 'ak_customer_credits';
        $log_table = $wpdb->prefix . 'ak_credit_log';
        
        $stats = array();
        
        // Total customers with credits
        $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM $credits_table");
        
        // Total credits in system
        $stats['total_sms'] = $wpdb->get_var("SELECT SUM(sms_credits) FROM $credits_table") ?: 0;
        $stats['total_calls'] = $wpdb->get_var("SELECT SUM(call_credits) FROM $credits_table") ?: 0;
        $stats['total_emails'] = $wpdb->get_var("SELECT SUM(email_credits) FROM $credits_table") ?: 0;
        
        // Low credit customers
        $settings = get_option('ak_credit_manager_settings', array());
        $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
        
        $stats['low_credit_customers'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $credits_table WHERE sms_credits <= %d OR call_credits <= %d OR email_credits <= %d",
            $threshold, $threshold, $threshold
        ));
        
        // Credits used today
        $today = date('Y-m-d 00:00:00');
        $stats['used_today'] = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN credit_type = 'sms' AND action = 'deduct' THEN amount ELSE 0 END) as sms,
                SUM(CASE WHEN credit_type = 'call' AND action = 'deduct' THEN amount ELSE 0 END) as calls,
                SUM(CASE WHEN credit_type = 'email' AND action = 'deduct' THEN amount ELSE 0 END) as emails
            FROM $log_table WHERE created_at >= %s",
            $today
        ));
        
        return $stats;
    }
}
