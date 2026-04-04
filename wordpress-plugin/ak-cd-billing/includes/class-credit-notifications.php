<?php
/**
 * Low Credit Notification System
 * Sends email alerts when credits are running low
 * Supports auto top-up option
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Notifications {
    
    public function __construct() {
        // Hook into credit deduction
        add_action('ak_credits_deducted', array($this, 'check_low_credits'), 10, 2);
        
        // Cron job for batch checking (daily)
        add_action('ak_daily_credit_check', array($this, 'daily_credit_check'));
        
        // Schedule cron if not already
        if (!wp_next_scheduled('ak_daily_credit_check')) {
            wp_schedule_event(time(), 'daily', 'ak_daily_credit_check');
        }
        
        // User settings for auto top-up
        add_action('wp_ajax_ak_toggle_auto_topup', array($this, 'toggle_auto_topup'));
        add_action('wp_ajax_ak_update_topup_settings', array($this, 'update_topup_settings'));
    }
    
    /**
     * Check if user's credits are low after deduction
     */
    public function check_low_credits($user_id, $credit_type) {
        // Check if notifications are enabled
        if (get_option('ak_enable_low_credit_emails', 'yes') !== 'yes') {
            return;
        }
        
        $threshold = intval(get_option('ak_low_credit_threshold', 10));
        
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$credits) {
            return;
        }
        
        // Check each credit type
        $low_types = array();
        
        if ($credits->sms_credits <= $threshold) {
            $low_types['SMS'] = $credits->sms_credits;
        }
        if ($credits->call_credits <= $threshold) {
            $low_types['Call'] = $credits->call_credits;
        }
        if ($credits->email_credits <= $threshold) {
            $low_types['Email'] = $credits->email_credits;
        }
        
        if (empty($low_types)) {
            return;
        }
        
        // Check if we've already notified recently (within 24 hours)
        $last_notification = get_user_meta($user_id, 'ak_last_low_credit_notification', true);
        if ($last_notification && (time() - $last_notification) < (24 * 60 * 60)) {
            return;
        }
        
        // Send notification
        $this->send_low_credit_email($user_id, $low_types);
        
        // Update last notification time
        update_user_meta($user_id, 'ak_last_low_credit_notification', time());
        
        // Process auto top-up if enabled
        $this->process_auto_topup($user_id, $low_types);
    }
    
    /**
     * Send low credit warning email
     */
    private function send_low_credit_email($user_id, $low_types) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        $credit_list = '';
        foreach ($low_types as $type => $amount) {
            $credit_list .= '<li style="padding:5px 0;"><strong>' . esc_html($type) . '</strong>: ' . intval($amount) . ' remaining</li>';
        }
        
        $subject = 'Your AppointmentKeeper credits are running low';
        
        $message = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;">
                            <tr>
                                <td style="background:linear-gradient(135deg,#ff9800 0%,#f57c00 100%);padding:35px 30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:24px;">⚠️ Low Credit Warning</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px 35px;">
                                    <h2 style="margin:0 0 15px 0;color:#1e3a5f;">Hi ' . esc_html($user->first_name ?: $user->display_name) . ',</h2>
                                    <p style="margin:0 0 20px 0;color:#555;font-size:15px;line-height:1.6;">
                                        Your AppointmentKeeper credits are running low:
                                    </p>
                                    <div style="background:#fff3e0;padding:20px;border-radius:10px;margin:20px 0;border-left:4px solid #ff9800;">
                                        <ul style="margin:0;padding-left:20px;color:#555;">
                                            ' . $credit_list . '
                                        </ul>
                                    </div>
                                    <p style="margin:0 0 25px 0;color:#555;font-size:15px;">
                                        Top up now to ensure your appointment reminders keep sending without interruption.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <a href="' . esc_url(home_url('/pricing')) . '" style="display:inline-block;padding:14px 30px;background:#28a745;color:#fff;text-decoration:none;border-radius:50px;font-size:15px;font-weight:600;">
                                                    Top Up Credits
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:25px 0 0 0;color:#888;font-size:13px;text-align:center;">
                                        Want automatic top-ups? Enable auto top-up in your <a href="' . esc_url(home_url('/my-dashboard')) . '" style="color:#1e3a5f;">dashboard settings</a>.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Process auto top-up if enabled
     */
    private function process_auto_topup($user_id, $low_types) {
        // Check if auto top-up is enabled for this user
        $auto_topup = get_user_meta($user_id, 'ak_auto_topup_enabled', true);
        
        if ($auto_topup !== 'yes') {
            return;
        }
        
        // Check if admin has enabled auto top-up feature
        if (get_option('ak_enable_auto_topup', 'no') !== 'yes') {
            return;
        }
        
        $stripe_customer_id = get_user_meta($user_id, 'ak_stripe_customer_id', true);
        
        if (!$stripe_customer_id) {
            return;
        }
        
        // Get auto top-up settings
        $topup_amount = get_user_meta($user_id, 'ak_auto_topup_amount', true) ?: 50;
        $topup_price = 9.99; // Fixed price for 50 SMS credits
        
        // Create a charge using Stripe
        $secret_key = get_option('ak_stripe_secret_key');
        
        if (!$secret_key) {
            return;
        }
        
        // Create invoice item and invoice
        $response = wp_remote_post('https://api.stripe.com/v1/invoiceitems', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'customer' => $stripe_customer_id,
                'amount' => intval($topup_price * 100),
                'currency' => 'gbp',
                'description' => 'Auto Top-Up: ' . $topup_amount . ' SMS Credits'
            )
        ));
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            // Log error
            update_user_meta($user_id, 'ak_last_topup_error', $body['error']['message']);
            return;
        }
        
        // Create and pay invoice
        $invoice_response = wp_remote_post('https://api.stripe.com/v1/invoices', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'customer' => $stripe_customer_id,
                'auto_advance' => 'true'
            )
        ));
        
        if (!is_wp_error($invoice_response)) {
            $invoice_body = json_decode(wp_remote_retrieve_body($invoice_response), true);
            
            if (isset($invoice_body['id'])) {
                // Pay the invoice
                wp_remote_post('https://api.stripe.com/v1/invoices/' . $invoice_body['id'] . '/pay', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $secret_key
                    )
                ));
                
                // Add credits
                global $wpdb;
                $table = $wpdb->prefix . 'ak_customer_credits';
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET sms_credits = sms_credits + %d WHERE user_id = %d",
                    $topup_amount,
                    $user_id
                ));
                
                // Log the top-up
                update_user_meta($user_id, 'ak_last_auto_topup', current_time('mysql'));
            }
        }
    }
    
    /**
     * Daily credit check for all users
     */
    public function daily_credit_check() {
        if (get_option('ak_enable_low_credit_emails', 'yes') !== 'yes') {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        $threshold = intval(get_option('ak_low_credit_threshold', 10));
        
        // Find users with low credits
        $low_credit_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, sms_credits, call_credits, email_credits FROM $table 
             WHERE sms_credits <= %d OR call_credits <= %d OR email_credits <= %d",
            $threshold, $threshold, $threshold
        ));
        
        foreach ($low_credit_users as $user_credits) {
            $this->check_low_credits($user_credits->user_id, 'daily_check');
        }
    }
    
    /**
     * Toggle auto top-up for user
     */
    public function toggle_auto_topup() {
        check_ajax_referer('ak_billing_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }
        
        $user_id = get_current_user_id();
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        
        update_user_meta($user_id, 'ak_auto_topup_enabled', $enabled ? 'yes' : 'no');
        
        wp_send_json_success(array('enabled' => $enabled));
    }
    
    /**
     * Update auto top-up settings
     */
    public function update_topup_settings() {
        check_ajax_referer('ak_billing_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }
        
        $user_id = get_current_user_id();
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 50;
        
        // Validate amount
        $allowed_amounts = array(25, 50, 100, 200);
        if (!in_array($amount, $allowed_amounts)) {
            $amount = 50;
        }
        
        update_user_meta($user_id, 'ak_auto_topup_amount', $amount);
        
        wp_send_json_success(array('amount' => $amount));
    }
}

// Initialize
new AK_Credit_Notifications();
