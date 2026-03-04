<?php
/**
 * Amelia Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Amelia_Integration {
    
    /**
     * Check if Amelia is active
     */
    public static function is_amelia_active() {
        return class_exists('AmeliaBooking\\Plugin') || 
               is_plugin_active('ameliabooking/ameliabooking.php') ||
               is_plugin_active('ameliabooking-lite/ameliabooking.php');
    }
    
    /**
     * Search Amelia customers
     */
    public static function search_customers($search_term) {
        global $wpdb;
        
        // Amelia stores customers in wp_amelia_users table
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        // Check if Amelia table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return array(
                'success' => false,
                'message' => 'Amelia plugin is not installed or customers table not found.',
                'customers' => array()
            );
        }
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, firstName, lastName, email, phone 
            FROM $amelia_table 
            WHERE type = 'customer' 
            AND (firstName LIKE %s OR lastName LIKE %s OR email LIKE %s OR phone LIKE %s)
            ORDER BY firstName, lastName
            LIMIT 20",
            $search, $search, $search, $search
        ));
        
        $formatted_customers = array();
        foreach ($customers as $customer) {
            $formatted_customers[] = array(
                'id' => $customer->id,
                'name' => trim($customer->firstName . ' ' . $customer->lastName),
                'email' => $customer->email,
                'phone' => $customer->phone,
                'display' => sprintf(
                    '%s %s (%s)',
                    $customer->firstName,
                    $customer->lastName,
                    $customer->email ? $customer->email : $customer->phone
                )
            );
        }
        
        return array(
            'success' => true,
            'customers' => $formatted_customers
        );
    }
    
    /**
     * Get single Amelia customer
     */
    public static function get_customer($customer_id) {
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return null;
        }
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, firstName, lastName, email, phone 
            FROM $amelia_table 
            WHERE id = %d AND type = 'customer'",
            $customer_id
        ));
        
        if (!$customer) {
            return null;
        }
        
        return array(
            'id' => $customer->id,
            'name' => trim($customer->firstName . ' ' . $customer->lastName),
            'email' => $customer->email,
            'phone' => $customer->phone
        );
    }
    
    /**
     * Get all Amelia customers (for dropdown)
     */
    public static function get_all_customers($limit = 100) {
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return array();
        }
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, firstName, lastName, email, phone 
            FROM $amelia_table 
            WHERE type = 'customer' 
            ORDER BY firstName, lastName
            LIMIT %d",
            $limit
        ));
        
        $formatted_customers = array();
        foreach ($customers as $customer) {
            $formatted_customers[] = array(
                'id' => $customer->id,
                'name' => trim($customer->firstName . ' ' . $customer->lastName),
                'email' => $customer->email,
                'phone' => $customer->phone
            );
        }
        
        return $formatted_customers;
    }
}
