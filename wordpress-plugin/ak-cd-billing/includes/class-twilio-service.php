<?php
/**
 * Twilio Service - SMS and Voice Calls
 * Handles all Twilio communications
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Twilio_Service {
    
    private $account_sid;
    private $auth_token;
    private $phone_number;
    
    public function __construct() {
        $this->account_sid = get_option('ak_twilio_account_sid');
        $this->auth_token = get_option('ak_twilio_auth_token');
        $this->phone_number = get_option('ak_twilio_phone_number');
        
        // AJAX handlers
        add_action('wp_ajax_ak_send_sms', array($this, 'ajax_send_sms'));
        add_action('wp_ajax_ak_make_call', array($this, 'ajax_make_call'));
        
        // Hooks for other plugins
        add_action('ak_send_sms', array($this, 'send_sms'), 10, 3);
        add_action('ak_make_call', array($this, 'make_call'), 10, 3);
        add_action('ak_send_invite_sms', array($this, 'send_invite_sms'), 10, 2);
    }
    
    /**
     * Check if Twilio is configured
     */
    public function is_configured() {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->phone_number);
    }
    
    /**
     * Send SMS message
     */
    public function send_sms($to, $message, $user_id = null) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        // Check credits if user_id provided
        if ($user_id) {
            $has_credits = $this->check_and_deduct_credits($user_id, 'sms');
            if (!$has_credits) {
                return array('success' => false, 'error' => 'Insufficient SMS credits');
            }
        }
        
        // Format phone number
        $to = $this->format_phone_number($to);
        
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/Messages.json';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'To' => $to,
                'From' => $this->phone_number,
                'Body' => $message
            )
        ));
        
        if (is_wp_error($response)) {
            // Refund credit if failed
            if ($user_id) {
                $this->refund_credit($user_id, 'sms');
            }
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error_code'])) {
            // Refund credit if failed
            if ($user_id) {
                $this->refund_credit($user_id, 'sms');
            }
            return array('success' => false, 'error' => $body['message']);
        }
        
        // Log successful send
        if ($user_id) {
            $this->log_usage($user_id, 'sms_sent', $to, null, 'success', $body['sid']);
        }
        
        return array(
            'success' => true,
            'sid' => $body['sid'],
            'status' => $body['status']
        );
    }
    
    /**
     * Make a voice call with TwiML
     */
    public function make_call($to, $twiml_or_message, $user_id = null, $use_elevenlabs = false) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        // Check credits
        if ($user_id) {
            $has_credits = $this->check_and_deduct_credits($user_id, 'call');
            if (!$has_credits) {
                return array('success' => false, 'error' => 'Insufficient call credits');
            }
        }
        
        // Format phone number
        $to = $this->format_phone_number($to);
        
        // Check if it's already TwiML or just a message
        if (strpos($twiml_or_message, '<Response>') !== false) {
            $twiml = $twiml_or_message;
        } else {
            // Use ElevenLabs for voice if enabled
            if ($use_elevenlabs && class_exists('AK_ElevenLabs_Service')) {
                $elevenlabs = new AK_ElevenLabs_Service();
                $audio_url = $elevenlabs->generate_speech($twiml_or_message);
                
                if ($audio_url) {
                    $twiml = '<Response><Play>' . esc_url($audio_url) . '</Play></Response>';
                } else {
                    // Fallback to Twilio TTS
                    $twiml = '<Response><Say voice="alice" language="en-GB">' . esc_html($twiml_or_message) . '</Say></Response>';
                }
            } else {
                // Use Twilio's built-in TTS
                $twiml = '<Response><Say voice="alice" language="en-GB">' . esc_html($twiml_or_message) . '</Say></Response>';
            }
        }
        
        // Create a TwiML bin or use inline TwiML via URL
        // For simplicity, we'll use a callback URL approach
        $callback_url = add_query_arg(array(
            'ak_twiml' => base64_encode($twiml),
            'ak_call_nonce' => wp_create_nonce('ak_call_' . $to)
        ), rest_url('ak-twilio/v1/twiml'));
        
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/Calls.json';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'To' => $to,
                'From' => $this->phone_number,
                'Url' => $callback_url
            )
        ));
        
        if (is_wp_error($response)) {
            if ($user_id) {
                $this->refund_credit($user_id, 'call');
            }
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error_code'])) {
            if ($user_id) {
                $this->refund_credit($user_id, 'call');
            }
            return array('success' => false, 'error' => $body['message']);
        }
        
        // Log successful call
        if ($user_id) {
            $this->log_usage($user_id, 'call_made', $to, null, 'success', $body['sid']);
        }
        
        return array(
            'success' => true,
            'sid' => $body['sid'],
            'status' => $body['status']
        );
    }
    
    /**
     * Send GPS directions via SMS
     */
    public function send_gps_directions($to, $destination_postcode, $user_id = null) {
        // Create Google Maps link
        $maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($destination_postcode);
        
        // Shorten URL using is.gd (free, no API key needed)
        $short_url = $this->shorten_url($maps_url);
        
        $message = "Here are directions to your appointment:\n" . $short_url . "\n\nSee you soon! - AppointmentKeeper";
        
        return $this->send_sms($to, $message, $user_id);
    }
    
    /**
     * Shorten URL
     */
    private function shorten_url($url) {
        $response = wp_remote_get('https://is.gd/create.php?format=simple&url=' . urlencode($url));
        
        if (!is_wp_error($response)) {
            $short = wp_remote_retrieve_body($response);
            if (filter_var($short, FILTER_VALIDATE_URL)) {
                return $short;
            }
        }
        
        return $url; // Return original if shortening fails
    }
    
    /**
     * Send invite SMS to friends
     */
    public function send_invite_sms($user_id, $invites) {
        $user = get_user_by('ID', $user_id);
        $referral_code = get_user_meta($user_id, 'ak_referral_code', true);
        $signup_url = home_url('/register?ref=' . $referral_code);
        
        foreach ($invites as $invite) {
            $message = "Hi " . ($invite['name'] ?: 'there') . "! " . 
                       $user->display_name . " invited you to try AppointmentKeeper. " .
                       "Sign up and get bonus credits: " . $signup_url;
            
            // Send without deducting credits (invites are free)
            $this->send_sms($invite['phone'], $message);
            
            // Update invite status
            $this->update_invite_status($user_id, $invite['phone'], 'sent');
        }
    }
    
    /**
     * Update invite status
     */
    private function update_invite_status($user_id, $phone, $status) {
        $invites = get_user_meta($user_id, 'ak_pending_invites', true);
        
        if (is_array($invites)) {
            foreach ($invites as &$invite) {
                if ($invite['phone'] === $phone) {
                    $invite['status'] = $status;
                    $invite['sent_at'] = current_time('mysql');
                }
            }
            update_user_meta($user_id, 'ak_pending_invites', $invites);
        }
    }
    
    /**
     * Format phone number to E.164
     */
    private function format_phone_number($phone) {
        // Remove all non-numeric except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If doesn't start with +, assume UK
        if (strpos($phone, '+') !== 0) {
            // Remove leading 0 if present
            $phone = ltrim($phone, '0');
            $phone = '+44' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Check and deduct credits
     */
    private function check_and_deduct_credits($user_id, $type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $column = $type . '_credits';
        
        $credits = $wpdb->get_var($wpdb->prepare(
            "SELECT $column FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($credits < 1) {
            return false;
        }
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET $column = $column - 1 WHERE user_id = %d AND $column > 0",
            $user_id
        ));
        
        // Trigger low credit check
        do_action('ak_credits_deducted', $user_id, $type);
        
        return true;
    }
    
    /**
     * Refund a credit
     */
    private function refund_credit($user_id, $type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        $column = $type . '_credits';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET $column = $column + 1 WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Log usage
     */
    private function log_usage($user_id, $type, $phone = null, $email = null, $status = 'success', $external_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        
        // Create table if not exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $this->create_usage_table();
        }
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'usage_type' => $type,
                'recipient_phone' => $phone,
                'recipient_email' => $email,
                'status' => $status,
                'external_id' => $external_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Create usage log table
     */
    private function create_usage_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_usage_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            usage_type VARCHAR(50) NOT NULL,
            recipient_phone VARCHAR(50) DEFAULT NULL,
            recipient_email VARCHAR(100) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            external_id VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY usage_type (usage_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX: Send SMS
     */
    public function ajax_send_sms() {
        check_ajax_referer('ak_twilio_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in first.'));
        }
        
        $to = sanitize_text_field($_POST['to']);
        $message = sanitize_textarea_field($_POST['message']);
        $user_id = get_current_user_id();
        
        if (empty($to) || empty($message)) {
            wp_send_json_error(array('message' => 'Phone number and message required.'));
        }
        
        $result = $this->send_sms($to, $message, $user_id);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'SMS sent successfully!'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * AJAX: Make call
     */
    public function ajax_make_call() {
        check_ajax_referer('ak_twilio_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in first.'));
        }
        
        $to = sanitize_text_field($_POST['to']);
        $message = sanitize_textarea_field($_POST['message']);
        $use_ai_voice = isset($_POST['ai_voice']) && $_POST['ai_voice'] === 'true';
        $user_id = get_current_user_id();
        
        if (empty($to) || empty($message)) {
            wp_send_json_error(array('message' => 'Phone number and message required.'));
        }
        
        $result = $this->make_call($to, $message, $user_id, $use_ai_voice);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Call initiated!'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
}

// Register REST endpoint for TwiML callback
add_action('rest_api_init', function() {
    register_rest_route('ak-twilio/v1', '/twiml', array(
        'methods' => 'GET,POST',
        'callback' => function($request) {
            $twiml = base64_decode($request->get_param('ak_twiml'));
            
            header('Content-Type: application/xml');
            echo $twiml;
            exit;
        },
        'permission_callback' => '__return_true'
    ));
});

// Initialize
new AK_Twilio_Service();
