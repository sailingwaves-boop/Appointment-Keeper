<?php
/**
 * Twilio SMS Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Twilio_SMS {
    
    private $account_sid;
    private $auth_token;
    private $from_number;
    
    public function __construct() {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $this->account_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $this->auth_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $this->from_number = isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : '';
    }
    
    /**
     * Check if Twilio is configured
     */
    public function is_configured() {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->from_number);
    }
    
    /**
     * Send SMS via Twilio API
     */
    public function send_sms($to, $message) {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'Twilio is not configured. Please add your Twilio credentials in the settings.'
            );
        }
        
        // Format phone number
        $to = $this->format_phone_number($to);
        
        if (empty($to)) {
            return array(
                'success' => false,
                'error' => 'Invalid phone number provided.'
            );
        }
        
        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Messages.json";
        
        $data = array(
            'To' => $to,
            'From' => $this->from_number,
            'Body' => $message
        );
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $data
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'message_sid' => isset($body['sid']) ? $body['sid'] : '',
                'status' => isset($body['status']) ? $body['status'] : 'sent'
            );
        } else {
            return array(
                'success' => false,
                'error' => isset($body['message']) ? $body['message'] : 'Unknown error occurred'
            );
        }
    }
    
    /**
     * Format phone number to E.164 format
     */
    private function format_phone_number($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If doesn't start with +, assume UK number and add +44
        if (strpos($phone, '+') !== 0) {
            // Remove leading 0 if present
            $phone = ltrim($phone, '0');
            $phone = '+44' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Parse message template with variables
     */
    public static function parse_template($template, $data) {
        $placeholders = array(
            '{customer_name}' => isset($data['customer_name']) ? $data['customer_name'] : '',
            '{customer_email}' => isset($data['customer_email']) ? $data['customer_email'] : '',
            '{customer_phone}' => isset($data['customer_phone']) ? $data['customer_phone'] : '',
            '{original_amount}' => isset($data['original_amount']) ? number_format($data['original_amount'], 2) : '0.00',
            '{current_balance}' => isset($data['current_balance']) ? number_format($data['current_balance'], 2) : '0.00',
            '{currency}' => isset($data['currency']) ? $data['currency'] : 'GBP',
            '{debt_type}' => isset($data['debt_type']) ? $data['debt_type'] : '',
            '{payment_amount}' => isset($data['payment_amount']) ? number_format($data['payment_amount'], 2) : '0.00'
        );
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Test Twilio connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'Twilio credentials not configured.'
            );
        }
        
        // Fetch account info to test credentials
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}.json";
        
        $args = array(
            'method' => 'GET',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token)
            )
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'Twilio connection successful!'
            );
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return array(
                'success' => false,
                'error' => isset($body['message']) ? $body['message'] : 'Authentication failed'
            );
        }
    }
}
