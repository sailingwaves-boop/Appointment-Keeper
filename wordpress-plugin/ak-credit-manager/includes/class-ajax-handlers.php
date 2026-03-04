<?php
/**
 * AJAX Handlers for Credit Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_Ajax_Handlers {
    
    public function __construct() {
        add_action('wp_ajax_ak_cm_search_customers', array($this, 'search_customers'));
        add_action('wp_ajax_ak_cm_get_customer', array($this, 'get_customer'));
        add_action('wp_ajax_ak_cm_add_credits', array($this, 'add_credits'));
        add_action('wp_ajax_ak_cm_remove_credits', array($this, 'remove_credits'));
        add_action('wp_ajax_ak_cm_give_free_month', array($this, 'give_free_month'));
        add_action('wp_ajax_ak_cm_get_history', array($this, 'get_history'));
        add_action('wp_ajax_ak_cm_bulk_action', array($this, 'bulk_action'));
    }
    
    /**
     * Verify nonce and permissions
     */
    private function verify() {
        if (!check_ajax_referer('ak_credit_manager_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
    }
    
    /**
     * Search customers
     */
    public function search_customers() {
        $this->verify();
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($search) < 2) {
            wp_send_json_success(array('customers' => array()));
        }
        
        $customers = AK_Credit_Manager_Database::search_customers($search);
        
        wp_send_json_success(array('customers' => $customers));
    }
    
    /**
     * Get single customer details
     */
    public function get_customer() {
        $this->verify();
        
        $user_id = intval($_POST['user_id']);
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Customer not found.'));
        }
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        $history = AK_Credit_Manager_Database::get_user_transactions($user_id, 20);
        
        wp_send_json_success(array(
            'customer' => array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'plan' => $credits->plan_type,
                'sms_credits' => $credits->sms_credits,
                'call_credits' => $credits->call_credits,
                'email_credits' => $credits->email_credits
            ),
            'history' => $history
        ));
    }
    
    /**
     * Add credits
     */
    public function add_credits() {
        $this->verify();
        
        $user_id = intval($_POST['user_id']);
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = intval($_POST['amount']);
        $reason = sanitize_text_field($_POST['reason']);
        
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than 0.'));
        }
        
        $result = AK_Credit_Manager_Operations::add_credits($user_id, $credit_type, $amount, $reason);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Remove credits
     */
    public function remove_credits() {
        $this->verify();
        
        $user_id = intval($_POST['user_id']);
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = intval($_POST['amount']);
        $reason = sanitize_text_field($_POST['reason']);
        
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than 0.'));
        }
        
        // Use manual_adjust with negative amount
        $result = AK_Credit_Manager_Operations::manual_adjust($user_id, $credit_type, -$amount, $reason ?: 'Manual removal');
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Give free month
     */
    public function give_free_month() {
        $this->verify();
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_text_field($_POST['reason']) ?: 'Free month awarded';
        
        $result = AK_Credit_Manager_Operations::give_free_month($user_id, $reason);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get transaction history
     */
    public function get_history() {
        $this->verify();
        
        $user_id = intval($_POST['user_id']);
        
        $history = AK_Credit_Manager_Database::get_user_transactions($user_id, 50);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Bulk action
     */
    public function bulk_action() {
        $this->verify();
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        $action = sanitize_text_field($_POST['bulk_action']);
        $amount = intval($_POST['amount']);
        
        if (empty($user_ids)) {
            wp_send_json_error(array('message' => 'No customers selected.'));
        }
        
        $results = array('success' => 0, 'failed' => 0);
        
        switch ($action) {
            case 'add_sms':
                $result = AK_Credit_Manager_Operations::bulk_add_credits($user_ids, 'sms', $amount, 'Bulk add');
                $results = array('success' => $result['success'], 'failed' => $result['failed']);
                break;
                
            case 'add_calls':
                $result = AK_Credit_Manager_Operations::bulk_add_credits($user_ids, 'call', $amount, 'Bulk add');
                $results = array('success' => $result['success'], 'failed' => $result['failed']);
                break;
                
            case 'add_emails':
                $result = AK_Credit_Manager_Operations::bulk_add_credits($user_ids, 'email', $amount, 'Bulk add');
                $results = array('success' => $result['success'], 'failed' => $result['failed']);
                break;
                
            case 'free_month':
                $result = AK_Credit_Manager_Operations::bulk_free_month($user_ids, 'Bulk free month');
                $results = array('success' => $result['success'], 'failed' => $result['failed']);
                break;
                
            default:
                wp_send_json_error(array('message' => 'Invalid action.'));
        }
        
        wp_send_json_success(array(
            'message' => sprintf('Processed %d customers. Success: %d, Failed: %d', 
                count($user_ids), $results['success'], $results['failed']),
            'results' => $results
        ));
    }
}
