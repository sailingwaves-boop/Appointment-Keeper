<?php
/**
 * REST API Class - External API endpoints for Zapier and other integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('ak-credit/v1', '/deduct', array(
            'methods' => 'POST',
            'callback' => array($this, 'deduct_credits'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        
        register_rest_route('ak-credit/v1', '/balance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_balance'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        
        register_rest_route('ak-credit/v1', '/add', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_credits'),
            'permission_callback' => array($this, 'check_api_key')
        ));
    }
    
    /**
     * Check API key for authentication
     */
    public function check_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            $api_key = $request->get_param('api_key');
        }
        
        $stored_key = get_option('ak_credit_manager_api_key');
        
        if (empty($stored_key)) {
            return new WP_Error('no_api_key', 'API key not configured on server.', array('status' => 500));
        }
        
        if ($api_key !== $stored_key) {
            return new WP_Error('invalid_api_key', 'Invalid API key.', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Deduct credits endpoint
     * POST /wp-json/ak-credit/v1/deduct
     */
    public function deduct_credits($request) {
        $email = sanitize_email($request->get_param('email'));
        $user_id = intval($request->get_param('user_id'));
        $credit_type = sanitize_text_field($request->get_param('credit_type'));
        $amount = intval($request->get_param('amount'));
        $reason = sanitize_text_field($request->get_param('reason'));
        
        // Get user ID from email if not provided
        if (empty($user_id) && !empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if (empty($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found. Provide valid email or user_id.'
            ), 400);
        }
        
        if (empty($credit_type) || !in_array($credit_type, array('sms', 'call', 'email'))) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid credit_type. Use: sms, call, or email.'
            ), 400);
        }
        
        if (empty($amount) || $amount < 1) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Amount must be at least 1.'
            ), 400);
        }
        
        // Perform deduction
        $result = AK_Credit_Manager_Operations::deduct_credits(
            $user_id,
            $credit_type,
            $amount,
            $reason ?: 'Deducted via API (Zapier)',
            'api',
            null
        );
        
        if ($result['success']) {
            // Get settings for low credit threshold
            $settings = get_option('ak_credit_manager_settings', array());
            $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
            
            $response = array(
                'success' => true,
                'balance_before' => $result['balance_before'],
                'balance_after' => $result['balance_after'],
                'credit_type' => $credit_type,
                'amount_deducted' => $amount,
                'low_balance_warning' => $result['balance_after'] <= $threshold
            );
            
            if ($result['balance_after'] <= $threshold) {
                $response['warning_message'] = 'Customer has low ' . $credit_type . ' credits. Only ' . $result['balance_after'] . ' remaining.';
            }
            
            return new WP_REST_Response($response, 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result['error'],
                'balance' => isset($result['balance']) ? $result['balance'] : null
            ), 400);
        }
    }
    
    /**
     * Get balance endpoint
     * GET /wp-json/ak-credit/v1/balance?email=customer@example.com
     */
    public function get_balance($request) {
        $email = sanitize_email($request->get_param('email'));
        $user_id = intval($request->get_param('user_id'));
        
        // Get user ID from email if not provided
        if (empty($user_id) && !empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if (empty($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found. Provide valid email or user_id.'
            ), 400);
        }
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        
        if (!$credits) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No credit record found for this user.'
            ), 404);
        }
        
        $settings = get_option('ak_credit_manager_settings', array());
        $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
        
        return new WP_REST_Response(array(
            'success' => true,
            'user_id' => $user_id,
            'sms_credits' => intval($credits->sms_credits),
            'call_credits' => intval($credits->call_credits),
            'email_credits' => intval($credits->email_credits),
            'plan_type' => $credits->plan_type,
            'low_balance_warning' => (
                $credits->sms_credits <= $threshold ||
                $credits->call_credits <= $threshold ||
                $credits->email_credits <= $threshold
            )
        ), 200);
    }
    
    /**
     * Add credits endpoint
     * POST /wp-json/ak-credit/v1/add
     */
    public function add_credits($request) {
        $email = sanitize_email($request->get_param('email'));
        $user_id = intval($request->get_param('user_id'));
        $credit_type = sanitize_text_field($request->get_param('credit_type'));
        $amount = intval($request->get_param('amount'));
        $reason = sanitize_text_field($request->get_param('reason'));
        
        // Get user ID from email if not provided
        if (empty($user_id) && !empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if (empty($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found. Provide valid email or user_id.'
            ), 400);
        }
        
        if (empty($credit_type) || !in_array($credit_type, array('sms', 'call', 'email'))) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid credit_type. Use: sms, call, or email.'
            ), 400);
        }
        
        if (empty($amount) || $amount < 1) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Amount must be at least 1.'
            ), 400);
        }
        
        // Perform addition
        $result = AK_Credit_Manager_Operations::add_credits(
            $user_id,
            $credit_type,
            $amount,
            $reason ?: 'Added via API',
            'api_add',
            0
        );
        
        if ($result['success']) {
            return new WP_REST_Response(array(
                'success' => true,
                'balance_before' => $result['balance_before'],
                'balance_after' => $result['balance_after'],
                'credit_type' => $credit_type,
                'amount_added' => $amount
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result['error']
            ), 400);
        }
    }
}
