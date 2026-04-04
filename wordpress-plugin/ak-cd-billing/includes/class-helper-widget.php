<?php
/**
 * AppointmentKeeper Helper Widget
 * AI-powered assistant for managing appointments, SMS, calls, and directions
 * Available as £12/mo add-on or included with Enterprise
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Helper_Widget {
    
    public function __construct() {
        add_action('wp_footer', array($this, 'render_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ak_helper_action', array($this, 'handle_action'));
        add_action('wp_ajax_ak_helper_get_appointments', array($this, 'get_appointments'));
        add_action('wp_ajax_ak_helper_send_reminder', array($this, 'send_reminder'));
        add_action('wp_ajax_ak_helper_send_directions', array($this, 'send_directions'));
        add_action('wp_ajax_ak_helper_make_call', array($this, 'make_call'));
    }
    
    /**
     * Check if user has Helper access
     */
    private function user_has_helper_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Check if user has Helper addon
        $has_helper = get_user_meta($user_id, 'ak_has_helper', true);
        if ($has_helper === 'yes') {
            return true;
        }
        
        // Check if user is on Enterprise plan (Helper included)
        $plan = get_user_meta($user_id, 'ak_subscription_plan', true);
        if ($plan === 'enterprise') {
            return true;
        }
        
        return false;
    }
    
    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }
        
        wp_enqueue_style(
            'ak-helper-widget',
            AK_DASHBOARD_PLUGIN_URL . 'assets/helper-widget.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-helper-widget',
            AK_DASHBOARD_PLUGIN_URL . 'assets/helper-widget.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('ak-helper-widget', 'akHelperData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_helper_nonce'),
            'hasAccess' => $this->user_has_helper_access(),
            'upgradeUrl' => home_url('/choose-plan')
        ));
    }
    
    public function render_widget() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $has_access = $this->user_has_helper_access($user_id);
        $user = wp_get_current_user();
        ?>
        
        <!-- Helper Widget Toggle Button -->
        <button class="ak-helper-toggle" id="ak-helper-toggle" title="AppointmentKeeper Helper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </button>
        
        <!-- Helper Widget Panel -->
        <div class="ak-helper-panel" id="ak-helper-panel">
            <div class="ak-helper-header">
                <div class="ak-helper-title">
                    <span class="ak-helper-icon">🤖</span>
                    <span>AppointmentKeeper Helper</span>
                </div>
                <button class="ak-helper-close" id="ak-helper-close">&times;</button>
            </div>
            
            <?php if (!$has_access): ?>
            <!-- Upgrade Prompt -->
            <div class="ak-helper-upgrade">
                <div class="ak-upgrade-icon">🔒</div>
                <h3>Unlock the Helper</h3>
                <p>Automate your appointment reminders, SMS, calls, and directions with AI.</p>
                <ul class="ak-upgrade-features">
                    <li>📅 Auto-book appointments</li>
                    <li>📱 Send SMS reminders</li>
                    <li>📞 AI voice calls</li>
                    <li>🗺️ GPS directions</li>
                </ul>
                <a href="<?php echo home_url('/choose-plan'); ?>" class="ak-upgrade-btn">
                    Add Helper - £12/month
                </a>
                <p class="ak-upgrade-note">Or upgrade to Enterprise for free access</p>
            </div>
            
            <?php else: ?>
            <!-- Helper Interface -->
            <div class="ak-helper-content">
                <div class="ak-helper-greeting">
                    Hi <?php echo esc_html($user->first_name ?: $user->display_name); ?>! 👋<br>
                    <small>What can I help you with today?</small>
                </div>
                
                <!-- Quick Actions -->
                <div class="ak-helper-actions">
                    <button class="ak-helper-action-btn" data-action="view-appointments">
                        <span class="ak-action-icon">📅</span>
                        <span>View Appointments</span>
                    </button>
                    <button class="ak-helper-action-btn" data-action="send-reminder">
                        <span class="ak-action-icon">📱</span>
                        <span>Send Reminder</span>
                    </button>
                    <button class="ak-helper-action-btn" data-action="send-directions">
                        <span class="ak-action-icon">🗺️</span>
                        <span>Send Directions</span>
                    </button>
                    <button class="ak-helper-action-btn" data-action="make-call">
                        <span class="ak-action-icon">📞</span>
                        <span>Make AI Call</span>
                    </button>
                    <button class="ak-helper-action-btn" data-action="book-appointment">
                        <span class="ak-action-icon">➕</span>
                        <span>Book Appointment</span>
                    </button>
                </div>
                
                <!-- Dynamic Content Area -->
                <div class="ak-helper-dynamic" id="ak-helper-dynamic" style="display:none;">
                    <!-- Content loaded via AJAX -->
                </div>
                
                <!-- Chat-like Input -->
                <div class="ak-helper-input-area">
                    <input type="text" id="ak-helper-input" placeholder="Type a message or command...">
                    <button id="ak-helper-send" class="ak-helper-send-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle helper actions
     */
    public function handle_action() {
        check_ajax_referer('ak_helper_nonce', 'nonce');
        
        if (!is_user_logged_in() || !$this->user_has_helper_access()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $action = sanitize_text_field($_POST['helper_action']);
        
        switch ($action) {
            case 'view-appointments':
                $this->get_appointments();
                break;
            case 'book-appointment':
                wp_send_json_success(array(
                    'type' => 'redirect',
                    'url' => home_url('/booking') // Or wherever Amelia booking is
                ));
                break;
            default:
                wp_send_json_error(array('message' => 'Unknown action'));
        }
    }
    
    /**
     * Get user's appointments from Amelia
     */
    public function get_appointments() {
        check_ajax_referer('ak_helper_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Try to get from Amelia
        global $wpdb;
        $amelia_table = $wpdb->prefix . 'amelia_customer_bookings';
        $appointments = array();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") === $amelia_table) {
            // Get customer ID from Amelia
            $customer_table = $wpdb->prefix . 'amelia_users';
            $amelia_customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $customer_table WHERE email = %s AND type = 'customer'",
                $user->user_email
            ));
            
            if ($amelia_customer) {
                $bookings = $wpdb->get_results($wpdb->prepare(
                    "SELECT cb.*, a.bookingStart, a.bookingEnd, s.name as service_name, l.name as location_name, l.address
                     FROM {$wpdb->prefix}amelia_customer_bookings cb
                     JOIN {$wpdb->prefix}amelia_appointments a ON cb.appointmentId = a.id
                     JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
                     LEFT JOIN {$wpdb->prefix}amelia_locations l ON a.locationId = l.id
                     WHERE cb.customerId = %d AND a.bookingStart >= NOW()
                     ORDER BY a.bookingStart ASC
                     LIMIT 10",
                    $amelia_customer->id
                ));
                
                foreach ($bookings as $booking) {
                    $appointments[] = array(
                        'id' => $booking->id,
                        'service' => $booking->service_name,
                        'date' => date('D, j M Y', strtotime($booking->bookingStart)),
                        'time' => date('g:i A', strtotime($booking->bookingStart)),
                        'location' => $booking->location_name,
                        'address' => $booking->address,
                        'status' => $booking->status
                    );
                }
            }
        }
        
        if (empty($appointments)) {
            // Return demo data for testing
            $appointments = array(
                array(
                    'id' => 1,
                    'service' => 'Haircut',
                    'date' => date('D, j M Y', strtotime('+2 days')),
                    'time' => '10:00 AM',
                    'location' => 'Main Street Salon',
                    'address' => '123 Main St, London W1A 1AA',
                    'status' => 'approved'
                ),
                array(
                    'id' => 2,
                    'service' => 'Consultation',
                    'date' => date('D, j M Y', strtotime('+5 days')),
                    'time' => '2:30 PM',
                    'location' => 'City Office',
                    'address' => '456 City Road, London EC1V 2PX',
                    'status' => 'approved'
                )
            );
        }
        
        // Build HTML response
        $html = '<div class="ak-appointments-list">';
        $html .= '<h4>Upcoming Appointments</h4>';
        
        foreach ($appointments as $apt) {
            $html .= '<div class="ak-apt-card" data-id="' . esc_attr($apt['id']) . '">';
            $html .= '<div class="ak-apt-service">' . esc_html($apt['service']) . '</div>';
            $html .= '<div class="ak-apt-datetime">' . esc_html($apt['date']) . ' at ' . esc_html($apt['time']) . '</div>';
            $html .= '<div class="ak-apt-location">' . esc_html($apt['location']) . '</div>';
            $html .= '<div class="ak-apt-actions">';
            $html .= '<button class="ak-apt-btn ak-send-reminder-btn" data-phone="">📱 Remind</button>';
            $html .= '<button class="ak-apt-btn ak-send-directions-btn" data-address="' . esc_attr($apt['address']) . '">🗺️ Directions</button>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Send SMS reminder
     */
    public function send_reminder() {
        check_ajax_referer('ak_helper_nonce', 'nonce');
        
        if (!is_user_logged_in() || !$this->user_has_helper_access()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        $user_id = get_current_user_id();
        
        if (empty($phone) || empty($message)) {
            wp_send_json_error(array('message' => 'Phone and message required'));
        }
        
        $twilio = new AK_Twilio_Service();
        $result = $twilio->send_sms($phone, $message, $user_id);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Reminder sent!'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Send GPS directions
     */
    public function send_directions() {
        check_ajax_referer('ak_helper_nonce', 'nonce');
        
        if (!is_user_logged_in() || !$this->user_has_helper_access()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $phone = sanitize_text_field($_POST['phone']);
        $address = sanitize_text_field($_POST['address']);
        $user_id = get_current_user_id();
        
        if (empty($phone) || empty($address)) {
            wp_send_json_error(array('message' => 'Phone and address required'));
        }
        
        $twilio = new AK_Twilio_Service();
        $result = $twilio->send_gps_directions($phone, $address, $user_id);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Directions sent!'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Make AI voice call
     */
    public function make_call() {
        check_ajax_referer('ak_helper_nonce', 'nonce');
        
        if (!is_user_logged_in() || !$this->user_has_helper_access()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        $use_ai_voice = isset($_POST['ai_voice']) && $_POST['ai_voice'] === 'true';
        $user_id = get_current_user_id();
        
        if (empty($phone) || empty($message)) {
            wp_send_json_error(array('message' => 'Phone and message required'));
        }
        
        $twilio = new AK_Twilio_Service();
        $result = $twilio->make_call($phone, $message, $user_id, $use_ai_voice);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Call initiated!'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
}

// Initialize
new AK_Helper_Widget();
