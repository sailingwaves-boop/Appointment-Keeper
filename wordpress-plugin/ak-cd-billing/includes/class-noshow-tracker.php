<?php
/**
 * No-Show Tracking
 * Track customers who missed appointments, flag repeat offenders
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_NoShow_Tracker {
    
    public function __construct() {
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_ak_mark_noshow', array($this, 'mark_noshow'));
        add_action('wp_ajax_ak_mark_attended', array($this, 'mark_attended'));
        add_action('wp_ajax_ak_get_noshow_stats', array($this, 'get_customer_stats'));
        
        // Add no-show column to dashboard
        add_filter('ak_appointment_display', array($this, 'add_noshow_buttons'), 10, 2);
        
        // Shortcode for no-show report
        add_shortcode('ak_noshow_report', array($this, 'render_noshow_report'));
        
        // Auto-mark appointments as no-show after X hours
        add_action('ak_check_noshow_appointments', array($this, 'auto_mark_noshows'));
        
        // Schedule cron
        if (!wp_next_scheduled('ak_check_noshow_appointments')) {
            wp_schedule_event(time(), 'hourly', 'ak_check_noshow_appointments');
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ak_dashboard_settings', 'ak_noshow_tracking_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_noshow_auto_mark_hours', array(
            'default' => 2,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_noshow_flag_threshold', array(
            'default' => 3,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_noshow_send_warning', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_noshow_warning_template', array(
            'default' => "Hi {customer_name}, We noticed you missed your {service_name} appointment on {date}. Please let us know if you need to reschedule. Repeated no-shows may result in booking restrictions. - {business_name}",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
    }
    
    /**
     * Mark appointment as no-show
     */
    public function mark_noshow() {
        check_ajax_referer('ak_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        $customer_id = intval($_POST['customer_id']);
        
        global $wpdb;
        
        // Update Amelia booking status if table exists
        $amelia_table = $wpdb->prefix . 'amelia_customer_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") === $amelia_table) {
            $wpdb->update(
                $amelia_table,
                array('status' => 'no-show'),
                array('id' => $booking_id)
            );
        }
        
        // Record no-show
        $this->record_noshow($customer_id, $booking_id);
        
        // Check if customer should be flagged
        $noshow_count = $this->get_noshow_count($customer_id);
        $threshold = get_option('ak_noshow_flag_threshold', 3);
        
        if ($noshow_count >= $threshold) {
            $this->flag_customer($customer_id);
        }
        
        // Send warning SMS if enabled
        if (get_option('ak_noshow_send_warning', 'yes') === 'yes') {
            $this->send_noshow_warning($customer_id, $booking_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Marked as no-show',
            'noshow_count' => $noshow_count
        ));
    }
    
    /**
     * Mark appointment as attended
     */
    public function mark_attended() {
        check_ajax_referer('ak_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        global $wpdb;
        
        // Update Amelia booking status
        $amelia_table = $wpdb->prefix . 'amelia_customer_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") === $amelia_table) {
            $wpdb->update(
                $amelia_table,
                array('status' => 'approved'),
                array('id' => $booking_id)
            );
        }
        
        // Mark as attended in our records
        $this->record_attendance($booking_id);
        
        wp_send_json_success(array('message' => 'Marked as attended'));
    }
    
    /**
     * Record a no-show
     */
    private function record_noshow($customer_id, $booking_id) {
        $noshows = get_option('ak_noshow_records', array());
        
        $noshows[] = array(
            'customer_id' => $customer_id,
            'booking_id' => $booking_id,
            'date' => current_time('mysql'),
            'type' => 'noshow'
        );
        
        // Keep last 500 records
        if (count($noshows) > 500) {
            $noshows = array_slice($noshows, -500);
        }
        
        update_option('ak_noshow_records', $noshows);
        
        // Update customer's no-show count
        $customer_noshows = get_user_meta($customer_id, 'ak_noshow_count', true) ?: 0;
        update_user_meta($customer_id, 'ak_noshow_count', $customer_noshows + 1);
        update_user_meta($customer_id, 'ak_last_noshow_date', current_time('mysql'));
    }
    
    /**
     * Record attendance
     */
    private function record_attendance($booking_id) {
        $attendance = get_option('ak_attendance_records', array());
        
        $attendance[] = array(
            'booking_id' => $booking_id,
            'date' => current_time('mysql'),
            'type' => 'attended'
        );
        
        if (count($attendance) > 500) {
            $attendance = array_slice($attendance, -500);
        }
        
        update_option('ak_attendance_records', $attendance);
    }
    
    /**
     * Get no-show count for customer
     */
    private function get_noshow_count($customer_id) {
        return intval(get_user_meta($customer_id, 'ak_noshow_count', true));
    }
    
    /**
     * Flag customer as repeat offender
     */
    private function flag_customer($customer_id) {
        update_user_meta($customer_id, 'ak_noshow_flagged', 'yes');
        update_user_meta($customer_id, 'ak_noshow_flagged_date', current_time('mysql'));
        
        // Log the flag
        $flags = get_option('ak_noshow_flags', array());
        $flags[] = array(
            'customer_id' => $customer_id,
            'date' => current_time('mysql')
        );
        update_option('ak_noshow_flags', $flags);
    }
    
    /**
     * Send no-show warning SMS
     */
    private function send_noshow_warning($customer_id, $booking_id) {
        global $wpdb;
        
        // Get customer details
        $customer = null;
        $amelia_users = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_users'") === $amelia_users) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $amelia_users WHERE id = %d",
                $customer_id
            ));
        }
        
        if (!$customer || empty($customer->phone)) {
            return;
        }
        
        // Get booking details for message
        $booking_details = $this->get_booking_details($booking_id);
        
        $template = get_option('ak_noshow_warning_template', '');
        $business_name = get_option('ak_business_name', get_bloginfo('name'));
        
        $message = str_replace(
            array('{customer_name}', '{service_name}', '{date}', '{business_name}'),
            array(
                trim($customer->firstName . ' ' . $customer->lastName),
                $booking_details['service_name'] ?? 'your appointment',
                $booking_details['date'] ?? '',
                $business_name
            ),
            $template
        );
        
        // Send SMS (use admin credits since customer might not have account)
        $admins = get_users(array('role' => 'administrator', 'number' => 1));
        $admin_id = !empty($admins) ? $admins[0]->ID : null;
        
        if ($admin_id) {
            $twilio = new AK_Twilio_Service();
            $twilio->send_sms($customer->phone, $message, $admin_id);
        }
    }
    
    /**
     * Get booking details
     */
    private function get_booking_details($booking_id) {
        global $wpdb;
        
        $details = array(
            'service_name' => 'your appointment',
            'date' => ''
        );
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT cb.*, a.bookingStart, s.name as service_name
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             JOIN {$wpdb->prefix}amelia_appointments a ON cb.appointmentId = a.id
             JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
             WHERE cb.id = %d",
            $booking_id
        ));
        
        if ($booking) {
            $details['service_name'] = $booking->service_name;
            $details['date'] = date('l, j F', strtotime($booking->bookingStart));
        }
        
        return $details;
    }
    
    /**
     * Auto-mark appointments as no-show
     */
    public function auto_mark_noshows() {
        if (get_option('ak_noshow_tracking_enabled', 'yes') !== 'yes') {
            return;
        }
        
        $hours = get_option('ak_noshow_auto_mark_hours', 2);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_customer_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return;
        }
        
        // Find past appointments still marked as 'approved' (not attended)
        $past_appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT cb.id, cb.customerId, a.bookingStart
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             JOIN {$wpdb->prefix}amelia_appointments a ON cb.appointmentId = a.id
             WHERE cb.status = 'approved'
             AND a.bookingStart < %s
             AND a.bookingStart > %s",
            $cutoff,
            date('Y-m-d H:i:s', strtotime('-7 days')) // Only check last 7 days
        ));
        
        foreach ($past_appointments as $apt) {
            // Check if already recorded
            $already_recorded = $this->is_already_recorded($apt->id);
            
            if (!$already_recorded) {
                $this->record_noshow($apt->customerId, $apt->id);
                
                // Update Amelia status
                $wpdb->update(
                    $amelia_table,
                    array('status' => 'no-show'),
                    array('id' => $apt->id)
                );
            }
        }
    }
    
    /**
     * Check if booking already recorded
     */
    private function is_already_recorded($booking_id) {
        $noshows = get_option('ak_noshow_records', array());
        $attendance = get_option('ak_attendance_records', array());
        
        foreach ($noshows as $record) {
            if ($record['booking_id'] == $booking_id) {
                return true;
            }
        }
        
        foreach ($attendance as $record) {
            if ($record['booking_id'] == $booking_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Render no-show report (admin)
     */
    public function render_noshow_report() {
        if (!current_user_can('manage_options')) {
            return '<p>Admin access required.</p>';
        }
        
        global $wpdb;
        
        // Get flagged customers
        $flagged = get_users(array(
            'meta_key' => 'ak_noshow_flagged',
            'meta_value' => 'yes'
        ));
        
        // Get recent no-shows
        $noshows = get_option('ak_noshow_records', array());
        $recent_noshows = array_slice(array_reverse($noshows), 0, 20);
        
        ob_start();
        ?>
        <div class="ak-noshow-report">
            <h2>No-Show Report</h2>
            
            <div class="ak-report-stats">
                <div class="ak-stat-card">
                    <span class="ak-stat-number"><?php echo count($flagged); ?></span>
                    <span class="ak-stat-label">Flagged Customers</span>
                </div>
                <div class="ak-stat-card">
                    <span class="ak-stat-number"><?php echo count($noshows); ?></span>
                    <span class="ak-stat-label">Total No-Shows</span>
                </div>
            </div>
            
            <?php if (!empty($flagged)): ?>
            <h3>Flagged Customers (<?php echo get_option('ak_noshow_flag_threshold', 3); ?>+ no-shows)</h3>
            <table class="ak-report-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>No-Shows</th>
                        <th>Flagged Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flagged as $user): ?>
                    <tr>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo get_user_meta($user->ID, 'ak_noshow_count', true); ?></td>
                        <td><?php echo date('d M Y', strtotime(get_user_meta($user->ID, 'ak_noshow_flagged_date', true))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <h3>Recent No-Shows</h3>
            <table class="ak-report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Booking ID</th>
                        <th>Customer ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_noshows as $record): ?>
                    <tr>
                        <td><?php echo date('d M Y H:i', strtotime($record['date'])); ?></td>
                        <td><?php echo esc_html($record['booking_id']); ?></td>
                        <td><?php echo esc_html($record['customer_id']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .ak-noshow-report { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .ak-report-stats { display: flex; gap: 20px; margin-bottom: 30px; }
        .ak-stat-card { background: #f8fafb; padding: 20px 30px; border-radius: 10px; text-align: center; }
        .ak-stat-number { display: block; font-size: 32px; font-weight: 700; color: #dc3545; }
        .ak-stat-label { font-size: 13px; color: #888; }
        .ak-report-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .ak-report-table th, .ak-report-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .ak-report-table th { background: #f8fafb; font-weight: 600; color: #555; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get stats for admin display
     */
    public static function get_stats() {
        $noshows = get_option('ak_noshow_records', array());
        $flagged = get_users(array(
            'meta_key' => 'ak_noshow_flagged',
            'meta_value' => 'yes',
            'count_total' => true
        ));
        
        return array(
            'total_noshows' => count($noshows),
            'flagged_customers' => count($flagged)
        );
    }
}

// Initialize
new AK_NoShow_Tracker();
