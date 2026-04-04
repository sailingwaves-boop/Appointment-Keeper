<?php
/**
 * Scheduled Auto-Reminders
 * Automatically sends SMS/Email reminders before Amelia appointments
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Auto_Reminders {
    
    // Default reminder times (hours before appointment)
    private $default_reminder_times = array(24, 2); // 24 hours and 2 hours before
    
    public function __construct() {
        // Schedule cron events
        add_action('init', array($this, 'schedule_cron'));
        add_action('ak_process_reminders', array($this, 'process_reminders'));
        
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX for manual trigger
        add_action('wp_ajax_ak_trigger_reminders', array($this, 'manual_trigger'));
        
        // Add reminder settings tab
        add_filter('ak_admin_settings_tabs', array($this, 'add_settings_tab'));
    }
    
    /**
     * Schedule the cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('ak_process_reminders')) {
            wp_schedule_event(time(), 'hourly', 'ak_process_reminders');
        }
    }
    
    /**
     * Register reminder settings
     */
    public function register_settings() {
        // Enable/disable auto-reminders
        register_setting('ak_dashboard_settings', 'ak_auto_reminders_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Reminder times
        register_setting('ak_dashboard_settings', 'ak_reminder_24h_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_reminder_2h_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_reminder_1h_enabled', array(
            'default' => 'no',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // SMS Templates
        register_setting('ak_dashboard_settings', 'ak_reminder_sms_24h', array(
            'default' => "Hi {customer_name}! Reminder: You have {service_name} tomorrow at {time}. Location: {location}. See you soon! - {business_name}",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_reminder_sms_2h', array(
            'default' => "Hi {customer_name}! Your {service_name} appointment is in 2 hours at {time}. {location}. See you shortly!",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // Send method
        register_setting('ak_dashboard_settings', 'ak_reminder_method', array(
            'default' => 'sms',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Include GPS directions
        register_setting('ak_dashboard_settings', 'ak_reminder_include_gps', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Business name for messages
        register_setting('ak_dashboard_settings', 'ak_business_name', array(
            'default' => get_bloginfo('name'),
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }
    
    /**
     * Process reminders - called by cron
     */
    public function process_reminders() {
        // Check if enabled
        if (get_option('ak_auto_reminders_enabled', 'yes') !== 'yes') {
            return;
        }
        
        // Check Twilio is configured
        $twilio = new AK_Twilio_Service();
        if (!$twilio->is_configured()) {
            $this->log_reminder_event('error', 'Twilio not configured');
            return;
        }
        
        global $wpdb;
        
        // Check if Amelia tables exist
        $amelia_appointments = $wpdb->prefix . 'amelia_appointments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_appointments'") !== $amelia_appointments) {
            $this->log_reminder_event('error', 'Amelia not installed');
            return;
        }
        
        $now = current_time('mysql');
        $reminders_sent = 0;
        
        // Process 24-hour reminders
        if (get_option('ak_reminder_24h_enabled', 'yes') === 'yes') {
            $reminders_sent += $this->send_reminders_for_timeframe(24, '24h');
        }
        
        // Process 2-hour reminders
        if (get_option('ak_reminder_2h_enabled', 'yes') === 'yes') {
            $reminders_sent += $this->send_reminders_for_timeframe(2, '2h');
        }
        
        // Process 1-hour reminders
        if (get_option('ak_reminder_1h_enabled', 'no') === 'yes') {
            $reminders_sent += $this->send_reminders_for_timeframe(1, '1h');
        }
        
        $this->log_reminder_event('success', "Processed reminders. Sent: $reminders_sent");
    }
    
    /**
     * Send reminders for a specific timeframe
     */
    private function send_reminders_for_timeframe($hours_before, $timeframe_key) {
        global $wpdb;
        
        // Calculate time window (appointments happening in X hours, with 30-min buffer)
        $target_start = date('Y-m-d H:i:s', strtotime("+{$hours_before} hours -15 minutes"));
        $target_end = date('Y-m-d H:i:s', strtotime("+{$hours_before} hours +15 minutes"));
        
        // Get appointments in this window
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                a.id as appointment_id,
                a.bookingStart,
                a.bookingEnd,
                a.serviceId,
                a.locationId,
                s.name as service_name,
                l.name as location_name,
                l.address as location_address,
                cb.id as booking_id,
                cb.customerId,
                u.firstName as customer_first_name,
                u.lastName as customer_last_name,
                u.email as customer_email,
                u.phone as customer_phone
            FROM {$wpdb->prefix}amelia_appointments a
            JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
            LEFT JOIN {$wpdb->prefix}amelia_locations l ON a.locationId = l.id
            JOIN {$wpdb->prefix}amelia_customer_bookings cb ON cb.appointmentId = a.id
            JOIN {$wpdb->prefix}amelia_users u ON cb.customerId = u.id
            WHERE a.bookingStart BETWEEN %s AND %s
            AND cb.status = 'approved'
            AND a.status = 'approved'",
            $target_start,
            $target_end
        ));
        
        if (empty($appointments)) {
            return 0;
        }
        
        $sent_count = 0;
        $template = get_option("ak_reminder_sms_{$timeframe_key}", '');
        $include_gps = get_option('ak_reminder_include_gps', 'yes') === 'yes';
        $business_name = get_option('ak_business_name', get_bloginfo('name'));
        
        foreach ($appointments as $apt) {
            // Check if reminder already sent
            if ($this->reminder_already_sent($apt->booking_id, $timeframe_key)) {
                continue;
            }
            
            // Get customer's phone
            $phone = $apt->customer_phone;
            if (empty($phone)) {
                // Try to get from WordPress user
                $wp_user = get_user_by('email', $apt->customer_email);
                if ($wp_user) {
                    $phone = get_user_meta($wp_user->ID, 'ak_phone', true);
                }
            }
            
            if (empty($phone)) {
                $this->log_reminder_event('skipped', "No phone for booking {$apt->booking_id}");
                continue;
            }
            
            // Build message
            $message = $this->build_reminder_message($template, $apt, $business_name);
            
            // Add GPS link if enabled
            if ($include_gps && !empty($apt->location_address)) {
                $maps_url = 'https://maps.google.com/?q=' . urlencode($apt->location_address);
                // Shorten URL
                $short_url = $this->shorten_url($maps_url);
                $message .= "\n\nDirections: " . $short_url;
            }
            
            // Get user ID for credit deduction (find by email)
            $wp_user = get_user_by('email', $apt->customer_email);
            $user_id = $wp_user ? $wp_user->ID : null;
            
            // If customer doesn't have a WP account, use the site owner's credits
            // Or you could skip - depends on business logic
            if (!$user_id) {
                // Use first admin's credits as fallback (or skip)
                $admins = get_users(array('role' => 'administrator', 'number' => 1));
                $user_id = !empty($admins) ? $admins[0]->ID : null;
            }
            
            if (!$user_id) {
                $this->log_reminder_event('skipped', "No user for credit deduction - booking {$apt->booking_id}");
                continue;
            }
            
            // Send SMS
            $twilio = new AK_Twilio_Service();
            $result = $twilio->send_sms($phone, $message, $user_id);
            
            if ($result['success']) {
                $this->mark_reminder_sent($apt->booking_id, $timeframe_key);
                $sent_count++;
                $this->log_reminder_event('sent', "Reminder sent to {$phone} for booking {$apt->booking_id}");
            } else {
                $this->log_reminder_event('failed', "Failed to send to {$phone}: " . $result['error']);
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Build reminder message from template
     */
    private function build_reminder_message($template, $appointment, $business_name) {
        $customer_name = trim($appointment->customer_first_name . ' ' . $appointment->customer_last_name);
        if (empty($customer_name)) {
            $customer_name = 'there';
        }
        
        $time = date('g:i A', strtotime($appointment->bookingStart));
        $date = date('l, j F', strtotime($appointment->bookingStart));
        
        $replacements = array(
            '{customer_name}' => $customer_name,
            '{customer_first_name}' => $appointment->customer_first_name ?: 'there',
            '{service_name}' => $appointment->service_name,
            '{time}' => $time,
            '{date}' => $date,
            '{location}' => $appointment->location_name ?: '',
            '{location_address}' => $appointment->location_address ?: '',
            '{business_name}' => $business_name
        );
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Clean up any double spaces or empty location references
        $message = preg_replace('/\s+/', ' ', $message);
        $message = str_replace('Location: .', '', $message);
        
        return trim($message);
    }
    
    /**
     * Check if reminder was already sent
     */
    private function reminder_already_sent($booking_id, $timeframe) {
        $sent_reminders = get_option('ak_sent_reminders', array());
        $key = $booking_id . '_' . $timeframe;
        
        return isset($sent_reminders[$key]);
    }
    
    /**
     * Mark reminder as sent
     */
    private function mark_reminder_sent($booking_id, $timeframe) {
        $sent_reminders = get_option('ak_sent_reminders', array());
        $key = $booking_id . '_' . $timeframe;
        
        $sent_reminders[$key] = array(
            'sent_at' => current_time('mysql'),
            'booking_id' => $booking_id,
            'timeframe' => $timeframe
        );
        
        // Keep only last 1000 entries
        if (count($sent_reminders) > 1000) {
            $sent_reminders = array_slice($sent_reminders, -1000, 1000, true);
        }
        
        update_option('ak_sent_reminders', $sent_reminders);
    }
    
    /**
     * Shorten URL
     */
    private function shorten_url($url) {
        $response = wp_remote_get('https://is.gd/create.php?format=simple&url=' . urlencode($url), array(
            'timeout' => 5
        ));
        
        if (!is_wp_error($response)) {
            $short = wp_remote_retrieve_body($response);
            if (filter_var($short, FILTER_VALIDATE_URL)) {
                return $short;
            }
        }
        
        return $url;
    }
    
    /**
     * Log reminder events
     */
    private function log_reminder_event($type, $message) {
        $logs = get_option('ak_reminder_logs', array());
        
        $logs[] = array(
            'type' => $type,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
        
        // Keep last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('ak_reminder_logs', $logs);
    }
    
    /**
     * Manual trigger for testing
     */
    public function manual_trigger() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $this->process_reminders();
        
        $logs = get_option('ak_reminder_logs', array());
        $recent_logs = array_slice($logs, -5);
        
        wp_send_json_success(array(
            'message' => 'Reminders processed',
            'logs' => $recent_logs
        ));
    }
    
    /**
     * Get reminder statistics
     */
    public static function get_stats() {
        $sent_reminders = get_option('ak_sent_reminders', array());
        $logs = get_option('ak_reminder_logs', array());
        
        // Count today's reminders
        $today = date('Y-m-d');
        $today_count = 0;
        foreach ($sent_reminders as $reminder) {
            if (isset($reminder['sent_at']) && strpos($reminder['sent_at'], $today) === 0) {
                $today_count++;
            }
        }
        
        // Count this week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_count = 0;
        foreach ($sent_reminders as $reminder) {
            if (isset($reminder['sent_at']) && $reminder['sent_at'] >= $week_start) {
                $week_count++;
            }
        }
        
        return array(
            'total' => count($sent_reminders),
            'today' => $today_count,
            'this_week' => $week_count
        );
    }
}

// Initialize
new AK_Auto_Reminders();
