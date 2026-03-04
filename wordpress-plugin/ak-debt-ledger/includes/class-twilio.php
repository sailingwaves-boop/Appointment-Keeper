<?php
/**
 * Twilio Integration Class (SMS, Voice Calls, SendGrid Email)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Twilio {
    
    private $account_sid;
    private $auth_token;
    private $sms_from_number;
    private $call_from_number;
    private $sendgrid_api_key;
    private $from_email;
    
    public function __construct() {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $this->account_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $this->auth_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $this->sms_from_number = isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : '';
        $this->call_from_number = isset($settings['twilio_call_number']) ? $settings['twilio_call_number'] : $this->sms_from_number;
        $this->sendgrid_api_key = isset($settings['sendgrid_api_key']) ? $settings['sendgrid_api_key'] : '';
        $this->from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
    }
    
    /**
     * Check if Twilio SMS is configured
     */
    public function is_sms_configured() {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->sms_from_number);
    }
    
    /**
     * Check if Twilio Voice is configured
     */
    public function is_voice_configured() {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->call_from_number);
    }
    
    /**
     * Check if SendGrid is configured
     */
    public function is_email_configured() {
        return !empty($this->sendgrid_api_key);
    }
    
    /**
     * Send SMS with credit check
     */
    public function send_sms_with_credits($user_id, $to, $message, $reference_type = 'debt', $reference_id = null) {
        // Check credits first
        $credits = AK_Debt_Ledger_Database::get_customer_credits($user_id);
        
        if ($credits->sms_credits < 1) {
            return array(
                'success' => false,
                'error' => 'Insufficient SMS credits. Please top up your account.'
            );
        }
        
        // Send the SMS
        $result = $this->send_sms($to, $message);
        
        if ($result['success']) {
            // Deduct credit
            $new_balance = AK_Debt_Ledger_Database::deduct_credits($user_id, 'sms', 1);
            
            // Log usage
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'sms_sent',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_phone' => $to,
                'message_preview' => substr($message, 0, 100),
                'credits_used' => 1,
                'balance_after' => $new_balance,
                'status' => 'success'
            ));
        } else {
            // Log failed attempt (no credit deduction)
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'sms_sent',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_phone' => $to,
                'message_preview' => substr($message, 0, 100),
                'credits_used' => 0,
                'balance_after' => $credits->sms_credits,
                'status' => 'failed',
                'error_message' => $result['error']
            ));
        }
        
        return $result;
    }
    
    /**
     * Send SMS via Twilio API
     */
    public function send_sms($to, $message) {
        if (!$this->is_sms_configured()) {
            return array(
                'success' => false,
                'error' => 'Twilio SMS is not configured. Please add your credentials in Settings.'
            );
        }
        
        $to = $this->format_phone_number($to);
        
        if (empty($to)) {
            return array(
                'success' => false,
                'error' => 'Invalid phone number provided.'
            );
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Messages.json";
        
        $data = array(
            'To' => $to,
            'From' => $this->sms_from_number,
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
     * Make voice call with credit check
     */
    public function make_call_with_credits($user_id, $to, $message, $reference_type = 'debt', $reference_id = null) {
        // Check credits first
        $credits = AK_Debt_Ledger_Database::get_customer_credits($user_id);
        
        if ($credits->call_credits < 1) {
            return array(
                'success' => false,
                'error' => 'Insufficient call credits. Please top up your account.'
            );
        }
        
        // Make the call
        $result = $this->make_call($to, $message);
        
        if ($result['success']) {
            // Deduct credit
            $new_balance = AK_Debt_Ledger_Database::deduct_credits($user_id, 'call', 1);
            
            // Log usage
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'call_made',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_phone' => $to,
                'message_preview' => substr($message, 0, 100),
                'credits_used' => 1,
                'balance_after' => $new_balance,
                'status' => 'success'
            ));
        } else {
            // Log failed attempt (no credit deduction)
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'call_made',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_phone' => $to,
                'message_preview' => substr($message, 0, 100),
                'credits_used' => 0,
                'balance_after' => $credits->call_credits,
                'status' => 'failed',
                'error_message' => $result['error']
            ));
        }
        
        return $result;
    }
    
    /**
     * Make voice call via Twilio API
     */
    public function make_call($to, $message) {
        if (!$this->is_voice_configured()) {
            return array(
                'success' => false,
                'error' => 'Twilio Voice is not configured. Please add your credentials in Settings.'
            );
        }
        
        $to = $this->format_phone_number($to);
        
        if (empty($to)) {
            return array(
                'success' => false,
                'error' => 'Invalid phone number provided.'
            );
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Calls.json";
        
        // Create TwiML for the voice message
        $twiml = '<Response><Say voice="alice">' . esc_html($message) . '</Say></Response>';
        $twiml_url = 'http://twimlets.com/echo?Twiml=' . urlencode($twiml);
        
        $data = array(
            'To' => $to,
            'From' => $this->call_from_number,
            'Url' => $twiml_url
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
                'call_sid' => isset($body['sid']) ? $body['sid'] : '',
                'status' => isset($body['status']) ? $body['status'] : 'initiated'
            );
        } else {
            return array(
                'success' => false,
                'error' => isset($body['message']) ? $body['message'] : 'Unknown error occurred'
            );
        }
    }
    
    /**
     * Send email with credit check (via SendGrid)
     */
    public function send_email_with_credits($user_id, $to, $subject, $message, $reference_type = 'debt', $reference_id = null) {
        // Check credits first
        $credits = AK_Debt_Ledger_Database::get_customer_credits($user_id);
        
        if ($credits->email_credits < 1) {
            return array(
                'success' => false,
                'error' => 'Insufficient email credits. Please top up your account.'
            );
        }
        
        // Send the email
        $result = $this->send_email($to, $subject, $message);
        
        if ($result['success']) {
            // Deduct credit
            $new_balance = AK_Debt_Ledger_Database::deduct_credits($user_id, 'email', 1);
            
            // Log usage
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'email_sent',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_email' => $to,
                'message_preview' => substr($subject . ': ' . $message, 0, 100),
                'credits_used' => 1,
                'balance_after' => $new_balance,
                'status' => 'success'
            ));
        } else {
            // Log failed attempt (no credit deduction)
            AK_Debt_Ledger_Database::log_usage(array(
                'user_id' => $user_id,
                'usage_type' => 'email_sent',
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'recipient_email' => $to,
                'message_preview' => substr($subject . ': ' . $message, 0, 100),
                'credits_used' => 0,
                'balance_after' => $credits->email_credits,
                'status' => 'failed',
                'error_message' => $result['error']
            ));
        }
        
        return $result;
    }
    
    /**
     * Send email via SendGrid API
     */
    public function send_email($to, $subject, $message) {
        if (!$this->is_email_configured()) {
            // Fallback to WordPress mail
            return $this->send_email_wp($to, $subject, $message);
        }
        
        $url = 'https://api.sendgrid.com/v3/mail/send';
        
        $data = array(
            'personalizations' => array(
                array(
                    'to' => array(
                        array('email' => $to)
                    ),
                    'subject' => $subject
                )
            ),
            'from' => array(
                'email' => $this->from_email,
                'name' => get_bloginfo('name')
            ),
            'content' => array(
                array(
                    'type' => 'text/html',
                    'value' => $this->convert_to_html($message)
                )
            )
        );
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->sendgrid_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'status' => 'sent'
            );
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return array(
                'success' => false,
                'error' => isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Failed to send email'
            );
        }
    }
    
    /**
     * Send email via WordPress mail (fallback)
     */
    private function send_email_wp($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $this->from_email . '>'
        );
        
        $html_message = $this->convert_to_html($message);
        
        $sent = wp_mail($to, $subject, $html_message, $headers);
        
        if ($sent) {
            return array(
                'success' => true,
                'status' => 'sent'
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to send email via WordPress mail.'
            );
        }
    }
    
    /**
     * Convert plain text to HTML email
     */
    private function convert_to_html($text) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . esc_html(get_bloginfo('name')) . '</h2>
                </div>
                <div class="content">
                    ' . nl2br(esc_html($text)) . '
                </div>
                <div class="footer">
                    <p>This is an automated message from ' . esc_html(get_bloginfo('name')) . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Format phone number to E.164 format
     */
    private function format_phone_number($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (strpos($phone, '+') !== 0) {
            $phone = ltrim($phone, '0');
            $phone = '+44' . $phone; // Default to UK
        }
        
        return $phone;
    }
    
    /**
     * Parse message template with variables
     */
    public static function parse_template($template, $data) {
        $placeholders = array(
            '{customer_name}' => isset($data['customer_name']) ? $data['customer_name'] : '',
            '{creditor_name}' => isset($data['creditor_name']) ? $data['creditor_name'] : '',
            '{customer_email}' => isset($data['customer_email']) ? $data['customer_email'] : '',
            '{customer_phone}' => isset($data['customer_phone']) ? $data['customer_phone'] : '',
            '{original_amount}' => isset($data['original_amount']) ? number_format($data['original_amount'], 2) : '0.00',
            '{current_balance}' => isset($data['current_balance']) ? number_format($data['current_balance'], 2) : '0.00',
            '{currency}' => isset($data['currency']) ? $data['currency'] : 'GBP',
            '{debt_type}' => isset($data['debt_type']) ? $data['debt_type'] : '',
            '{payment_amount}' => isset($data['payment_amount']) ? number_format($data['payment_amount'], 2) : '0.00',
            '{friend_name}' => isset($data['friend_name']) ? $data['friend_name'] : '',
            '{referrer_name}' => isset($data['referrer_name']) ? $data['referrer_name'] : '',
            '{signup_link}' => isset($data['signup_link']) ? $data['signup_link'] : ''
        );
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Test Twilio connection
     */
    public function test_connection() {
        if (!$this->is_sms_configured()) {
            return array(
                'success' => false,
                'error' => 'Twilio credentials not configured.'
            );
        }
        
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
