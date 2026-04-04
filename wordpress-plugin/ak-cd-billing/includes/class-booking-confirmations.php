<?php
/**
 * Appointment Booking Confirmations
 * Sends Email, SMS, or AI Call when someone books via Amelia
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Booking_Confirmations {
    
    public function __construct() {
        // Hook into Amelia booking creation
        add_action('AmeliaBookingAddedBeforeNotify', array($this, 'handle_new_booking'), 10, 2);
        
        // Fallback hook for different Amelia versions
        add_action('amelia_booking_added', array($this, 'handle_new_booking_alt'), 10, 1);
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Manual test
        add_action('wp_ajax_ak_test_booking_confirmation', array($this, 'test_confirmation'));
    }
    
    /**
     * Register admin settings
     */
    public function register_settings() {
        // Enable confirmations
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Confirmation methods (can select multiple)
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_email', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_sms', array(
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_call', array(
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Email template
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_email_subject', array(
            'default' => 'Booking Confirmed - {service_name}',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_email_body', array(
            'default' => "Hi {customer_name},\n\nYour booking has been confirmed!\n\nService: {service_name}\nDate: {date}\nTime: {time}\nLocation: {location}\n\nWe look forward to seeing you!\n\nBest regards,\n{business_name}",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // SMS template
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_sms_template', array(
            'default' => "Hi {customer_name}! Your {service_name} booking is confirmed for {date} at {time}. Location: {location}. See you then! - {business_name}",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // Call template
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_call_template', array(
            'default' => "Hello {customer_name}! This is a confirmation call from {business_name}. Your {service_name} appointment has been booked for {date} at {time}. We look forward to seeing you. Thank you for booking with us!",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // Use AI voice for calls
        register_setting('ak_dashboard_settings', 'ak_booking_confirm_use_ai_voice', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }
    
    /**
     * Handle new Amelia booking
     */
    public function handle_new_booking($reservation, $bookings) {
        if (get_option('ak_booking_confirm_enabled', 'yes') !== 'yes') {
            return;
        }
        
        // Extract booking data
        if (isset($reservation['appointment'])) {
            $appointment = $reservation['appointment'];
        } else {
            return;
        }
        
        foreach ($bookings as $booking) {
            $this->send_confirmations($appointment, $booking);
        }
    }
    
    /**
     * Alternative hook for different Amelia versions
     */
    public function handle_new_booking_alt($booking_data) {
        if (get_option('ak_booking_confirm_enabled', 'yes') !== 'yes') {
            return;
        }
        
        if (is_array($booking_data)) {
            $this->process_booking_data($booking_data);
        }
    }
    
    /**
     * Process booking data and send confirmations
     */
    private function process_booking_data($data) {
        // Try to extract appointment and customer info
        $appointment = isset($data['appointment']) ? $data['appointment'] : $data;
        $customer = isset($data['customer']) ? $data['customer'] : array();
        
        // Build booking object
        $booking_info = array(
            'customer_name' => $customer['firstName'] ?? '' . ' ' . ($customer['lastName'] ?? ''),
            'customer_email' => $customer['email'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'service_name' => $appointment['serviceName'] ?? 'your appointment',
            'date' => isset($appointment['bookingStart']) ? date('l, j F Y', strtotime($appointment['bookingStart'])) : '',
            'time' => isset($appointment['bookingStart']) ? date('g:i A', strtotime($appointment['bookingStart'])) : '',
            'location' => $appointment['locationName'] ?? ''
        );
        
        $this->send_all_confirmations($booking_info);
    }
    
    /**
     * Send confirmations for a booking
     */
    private function send_confirmations($appointment, $booking) {
        global $wpdb;
        
        // Get customer details
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}amelia_users WHERE id = %d",
            $booking['customerId']
        ));
        
        if (!$customer) {
            return;
        }
        
        // Get service name
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}amelia_services WHERE id = %d",
            $appointment['serviceId']
        ));
        
        // Get location
        $location = null;
        if (!empty($appointment['locationId'])) {
            $location = $wpdb->get_row($wpdb->prepare(
                "SELECT name, address FROM {$wpdb->prefix}amelia_locations WHERE id = %d",
                $appointment['locationId']
            ));
        }
        
        $booking_info = array(
            'customer_name' => trim($customer->firstName . ' ' . $customer->lastName),
            'customer_first_name' => $customer->firstName,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'service_name' => $service ? $service->name : 'your appointment',
            'date' => date('l, j F Y', strtotime($appointment['bookingStart'])),
            'time' => date('g:i A', strtotime($appointment['bookingStart'])),
            'location' => $location ? $location->name : '',
            'location_address' => $location ? $location->address : ''
        );
        
        $this->send_all_confirmations($booking_info);
    }
    
    /**
     * Send all enabled confirmation types
     */
    private function send_all_confirmations($booking_info) {
        $business_name = get_option('ak_business_name', get_bloginfo('name'));
        
        // Add business name to booking info
        $booking_info['business_name'] = $business_name;
        
        // Send Email
        if (get_option('ak_booking_confirm_email', 'yes') === 'yes' && !empty($booking_info['customer_email'])) {
            $this->send_email_confirmation($booking_info);
        }
        
        // Send SMS
        if (get_option('ak_booking_confirm_sms', 'no') === 'yes' && !empty($booking_info['customer_phone'])) {
            $this->send_sms_confirmation($booking_info);
        }
        
        // Make Call
        if (get_option('ak_booking_confirm_call', 'no') === 'yes' && !empty($booking_info['customer_phone'])) {
            $this->send_call_confirmation($booking_info);
        }
        
        // Log confirmation
        $this->log_confirmation($booking_info);
    }
    
    /**
     * Send email confirmation
     */
    private function send_email_confirmation($booking_info) {
        $subject = get_option('ak_booking_confirm_email_subject', 'Booking Confirmed - {service_name}');
        $body = get_option('ak_booking_confirm_email_body', '');
        
        $subject = $this->replace_variables($subject, $booking_info);
        $body = $this->replace_variables($body, $booking_info);
        
        // Build HTML email
        $html_body = $this->build_email_html($booking_info, $body);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($booking_info['customer_email'], $subject, $html_body, $headers);
    }
    
    /**
     * Build beautiful HTML email
     */
    private function build_email_html($booking_info, $body_text) {
        $business_name = $booking_info['business_name'];
        $body_html = nl2br(esc_html($body_text));
        
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                            <tr>
                                <td style="background:linear-gradient(135deg,#28a745 0%,#20963c 100%);padding:30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:22px;">✓ Booking Confirmed!</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:35px;">
                                    <div style="background:#f8fafb;border-radius:12px;padding:20px;margin-bottom:25px;">
                                        <table width="100%" cellpadding="5">
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Service:</td>
                                                <td style="color:#1e3a5f;font-weight:600;">' . esc_html($booking_info['service_name']) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Date:</td>
                                                <td style="color:#1e3a5f;font-weight:600;">' . esc_html($booking_info['date']) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Time:</td>
                                                <td style="color:#1e3a5f;font-weight:600;">' . esc_html($booking_info['time']) . '</td>
                                            </tr>
                                            ' . (!empty($booking_info['location']) ? '
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Location:</td>
                                                <td style="color:#1e3a5f;font-weight:600;">' . esc_html($booking_info['location']) . '</td>
                                            </tr>' : '') . '
                                        </table>
                                    </div>
                                    <p style="color:#555;font-size:15px;line-height:1.6;margin:0;">
                                        ' . $body_html . '
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafb;padding:20px 35px;text-align:center;border-top:1px solid #eee;">
                                    <p style="margin:0;color:#999;font-size:12px;">
                                        © ' . date('Y') . ' ' . esc_html($business_name) . '
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    /**
     * Send SMS confirmation
     */
    private function send_sms_confirmation($booking_info) {
        $template = get_option('ak_booking_confirm_sms_template', '');
        $message = $this->replace_variables($template, $booking_info);
        
        // Get user ID for credits (try to find by email)
        $wp_user = get_user_by('email', $booking_info['customer_email']);
        $user_id = $wp_user ? $wp_user->ID : null;
        
        // If no user found, use admin's credits
        if (!$user_id) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1));
            $user_id = !empty($admins) ? $admins[0]->ID : null;
        }
        
        if (!$user_id) {
            return;
        }
        
        $twilio = new AK_Twilio_Service();
        $twilio->send_sms($booking_info['customer_phone'], $message, $user_id);
    }
    
    /**
     * Send AI call confirmation
     */
    private function send_call_confirmation($booking_info) {
        $template = get_option('ak_booking_confirm_call_template', '');
        $message = $this->replace_variables($template, $booking_info);
        $use_ai_voice = get_option('ak_booking_confirm_use_ai_voice', 'yes') === 'yes';
        
        // Get user ID for credits
        $wp_user = get_user_by('email', $booking_info['customer_email']);
        $user_id = $wp_user ? $wp_user->ID : null;
        
        if (!$user_id) {
            $admins = get_users(array('role' => 'administrator', 'number' => 1));
            $user_id = !empty($admins) ? $admins[0]->ID : null;
        }
        
        if (!$user_id) {
            return;
        }
        
        $twilio = new AK_Twilio_Service();
        $twilio->make_call($booking_info['customer_phone'], $message, $user_id, $use_ai_voice);
    }
    
    /**
     * Replace template variables
     */
    private function replace_variables($template, $booking_info) {
        $replacements = array(
            '{customer_name}' => $booking_info['customer_name'] ?: 'there',
            '{customer_first_name}' => $booking_info['customer_first_name'] ?? ($booking_info['customer_name'] ?: 'there'),
            '{service_name}' => $booking_info['service_name'],
            '{date}' => $booking_info['date'],
            '{time}' => $booking_info['time'],
            '{location}' => $booking_info['location'] ?: 'TBC',
            '{location_address}' => $booking_info['location_address'] ?? '',
            '{business_name}' => $booking_info['business_name']
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Log confirmation sent
     */
    private function log_confirmation($booking_info) {
        $logs = get_option('ak_booking_confirmation_logs', array());
        
        $methods = array();
        if (get_option('ak_booking_confirm_email', 'yes') === 'yes') $methods[] = 'email';
        if (get_option('ak_booking_confirm_sms', 'no') === 'yes') $methods[] = 'sms';
        if (get_option('ak_booking_confirm_call', 'no') === 'yes') $methods[] = 'call';
        
        $logs[] = array(
            'customer' => $booking_info['customer_name'],
            'service' => $booking_info['service_name'],
            'methods' => implode(', ', $methods),
            'timestamp' => current_time('mysql')
        );
        
        // Keep last 100
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('ak_booking_confirmation_logs', $logs);
    }
    
    /**
     * Test confirmation (AJAX)
     */
    public function test_confirmation() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $test_phone = sanitize_text_field($_POST['phone'] ?? '');
        $test_email = sanitize_email($_POST['email'] ?? '');
        
        $booking_info = array(
            'customer_name' => 'Test Customer',
            'customer_first_name' => 'Test',
            'customer_email' => $test_email ?: get_option('admin_email'),
            'customer_phone' => $test_phone,
            'service_name' => 'Test Appointment',
            'date' => date('l, j F Y', strtotime('+2 days')),
            'time' => '10:00 AM',
            'location' => 'Test Location',
            'location_address' => 'London W1A 1AA',
            'business_name' => get_option('ak_business_name', get_bloginfo('name'))
        );
        
        $results = array();
        
        // Test email
        if (get_option('ak_booking_confirm_email', 'yes') === 'yes') {
            $this->send_email_confirmation($booking_info);
            $results[] = 'Email sent to ' . $booking_info['customer_email'];
        }
        
        // Test SMS
        if (get_option('ak_booking_confirm_sms', 'no') === 'yes' && !empty($test_phone)) {
            $this->send_sms_confirmation($booking_info);
            $results[] = 'SMS sent to ' . $test_phone;
        }
        
        // Test Call
        if (get_option('ak_booking_confirm_call', 'no') === 'yes' && !empty($test_phone)) {
            $this->send_call_confirmation($booking_info);
            $results[] = 'Call initiated to ' . $test_phone;
        }
        
        if (empty($results)) {
            wp_send_json_error(array('message' => 'No confirmation methods enabled'));
        }
        
        wp_send_json_success(array('message' => implode('. ', $results)));
    }
    
    /**
     * Get confirmation stats
     */
    public static function get_stats() {
        $logs = get_option('ak_booking_confirmation_logs', array());
        
        $today = date('Y-m-d');
        $today_count = 0;
        foreach ($logs as $log) {
            if (isset($log['timestamp']) && strpos($log['timestamp'], $today) === 0) {
                $today_count++;
            }
        }
        
        return array(
            'total' => count($logs),
            'today' => $today_count
        );
    }
}

// Initialize
new AK_Booking_Confirmations();
