<?php
/**
 * Debt Auto-Chase
 * Automatically send payment reminders to debtors
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_AutoChase {
    
    public function __construct() {
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Cron for auto-chase
        add_action('ak_process_debt_reminders', array($this, 'process_debt_reminders'));
        
        // Schedule cron (daily)
        if (!wp_next_scheduled('ak_process_debt_reminders')) {
            wp_schedule_event(time(), 'daily', 'ak_process_debt_reminders');
        }
        
        // Manual trigger
        add_action('wp_ajax_ak_trigger_debt_chase', array($this, 'manual_trigger'));
        add_action('wp_ajax_ak_send_single_reminder', array($this, 'send_single_reminder'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ak_dashboard_settings', 'ak_debt_chase_enabled', array(
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Chase schedule (days after debt created)
        register_setting('ak_dashboard_settings', 'ak_debt_chase_day_1', array(
            'default' => 7,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_debt_chase_day_2', array(
            'default' => 14,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_debt_chase_day_3', array(
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
        
        // Chase method
        register_setting('ak_dashboard_settings', 'ak_debt_chase_method', array(
            'default' => 'sms',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Message templates
        register_setting('ak_dashboard_settings', 'ak_debt_chase_msg_1', array(
            'default' => "Hi {debtor_name}, friendly reminder: You have an outstanding balance of £{amount} with {creditor_name}. Please arrange payment at your earliest convenience. Reply PAID if already settled.",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_debt_chase_msg_2', array(
            'default' => "Hi {debtor_name}, this is a follow-up regarding your outstanding balance of £{amount} with {creditor_name}. Please contact us to discuss payment options.",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('ak_dashboard_settings', 'ak_debt_chase_msg_3', array(
            'default' => "FINAL REMINDER: {debtor_name}, you have an overdue balance of £{amount} with {creditor_name}. Immediate action required to avoid further steps. Please pay or contact us today.",
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // Max reminders
        register_setting('ak_dashboard_settings', 'ak_debt_chase_max_reminders', array(
            'default' => 3,
            'sanitize_callback' => 'absint'
        ));
    }
    
    /**
     * Process debt reminders
     */
    public function process_debt_reminders() {
        if (get_option('ak_debt_chase_enabled', 'yes') !== 'yes') {
            return;
        }
        
        global $wpdb;
        $debt_table = $wpdb->prefix . 'ak_debt_ledger';
        
        // Check if debt ledger table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$debt_table'") !== $debt_table) {
            $this->log_chase('error', 'Debt ledger table not found');
            return;
        }
        
        // Get chase schedule
        $day_1 = get_option('ak_debt_chase_day_1', 7);
        $day_2 = get_option('ak_debt_chase_day_2', 14);
        $day_3 = get_option('ak_debt_chase_day_3', 30);
        $max_reminders = get_option('ak_debt_chase_max_reminders', 3);
        
        // Get outstanding debts
        $debts = $wpdb->get_results(
            "SELECT * FROM $debt_table WHERE status IN ('pending', 'partial') AND balance > 0"
        );
        
        $sent_count = 0;
        
        foreach ($debts as $debt) {
            $days_old = floor((time() - strtotime($debt->created_at)) / (60 * 60 * 24));
            $reminders_sent = $this->get_reminders_sent($debt->id);
            
            // Determine which reminder to send
            $reminder_level = 0;
            
            if ($days_old >= $day_1 && $reminders_sent < 1) {
                $reminder_level = 1;
            } elseif ($days_old >= $day_2 && $reminders_sent < 2) {
                $reminder_level = 2;
            } elseif ($days_old >= $day_3 && $reminders_sent < 3) {
                $reminder_level = 3;
            }
            
            if ($reminder_level > 0 && $reminders_sent < $max_reminders) {
                $result = $this->send_debt_reminder($debt, $reminder_level);
                if ($result) {
                    $sent_count++;
                }
            }
        }
        
        $this->log_chase('success', "Processed debt reminders. Sent: $sent_count");
    }
    
    /**
     * Send debt reminder
     */
    private function send_debt_reminder($debt, $level) {
        // Get debtor details from debt record
        $debtor_details = maybe_unserialize($debt->debtor_details);
        
        if (!is_array($debtor_details)) {
            $debtor_details = array('name' => 'Customer', 'phone' => '', 'email' => '');
        }
        
        $phone = $debtor_details['phone'] ?? '';
        $email = $debtor_details['email'] ?? '';
        
        // Get creditor (user who owns the debt)
        $creditor = get_user_by('ID', $debt->creditor_user_id);
        $creditor_name = $creditor ? $creditor->display_name : 'your creditor';
        
        // Get message template
        $template = get_option("ak_debt_chase_msg_{$level}", '');
        
        if (empty($template)) {
            return false;
        }
        
        // Build message
        $message = str_replace(
            array('{debtor_name}', '{amount}', '{creditor_name}', '{original_amount}'),
            array(
                $debtor_details['name'] ?? 'Customer',
                number_format($debt->balance, 2),
                $creditor_name,
                number_format($debt->amount, 2)
            ),
            $template
        );
        
        $method = get_option('ak_debt_chase_method', 'sms');
        $success = false;
        
        // Send based on method
        if ($method === 'sms' && !empty($phone)) {
            $twilio = new AK_Twilio_Service();
            $result = $twilio->send_sms($phone, $message, $debt->creditor_user_id);
            $success = $result['success'];
        } elseif ($method === 'email' && !empty($email)) {
            $success = $this->send_email_reminder($email, $debtor_details['name'] ?? 'Customer', $message, $debt);
        } elseif ($method === 'both') {
            if (!empty($phone)) {
                $twilio = new AK_Twilio_Service();
                $twilio->send_sms($phone, $message, $debt->creditor_user_id);
            }
            if (!empty($email)) {
                $this->send_email_reminder($email, $debtor_details['name'] ?? 'Customer', $message, $debt);
            }
            $success = !empty($phone) || !empty($email);
        }
        
        if ($success) {
            $this->record_reminder_sent($debt->id, $level);
        }
        
        return $success;
    }
    
    /**
     * Send email reminder
     */
    private function send_email_reminder($email, $name, $message, $debt) {
        $creditor = get_user_by('ID', $debt->creditor_user_id);
        $business_name = get_option('ak_business_name', get_bloginfo('name'));
        
        $subject = 'Payment Reminder - Outstanding Balance £' . number_format($debt->balance, 2);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;">
                            <tr>
                                <td style="background:linear-gradient(135deg,#ff9800 0%,#f57c00 100%);padding:30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:22px;">Payment Reminder</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:35px;">
                                    <p style="margin:0 0 20px 0;color:#555;font-size:15px;line-height:1.6;">
                                        Hi ' . esc_html($name) . ',
                                    </p>
                                    <div style="background:#fff3e0;padding:20px;border-radius:10px;border-left:4px solid #ff9800;margin-bottom:20px;">
                                        <p style="margin:0;font-size:14px;color:#555;">' . nl2br(esc_html($message)) . '</p>
                                    </div>
                                    <div style="background:#f8fafb;padding:15px 20px;border-radius:10px;margin-bottom:20px;">
                                        <table width="100%">
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Original Amount:</td>
                                                <td style="text-align:right;font-weight:600;">£' . number_format($debt->amount, 2) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#888;font-size:13px;">Outstanding:</td>
                                                <td style="text-align:right;font-weight:700;color:#dc3545;font-size:18px;">£' . number_format($debt->balance, 2) . '</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <p style="margin:0;color:#888;font-size:13px;">
                                        If you have already made payment, please disregard this message.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafb;padding:20px;text-align:center;border-top:1px solid #eee;">
                                    <p style="margin:0;color:#999;font-size:12px;">
                                        Sent on behalf of ' . esc_html($creditor ? $creditor->display_name : $business_name) . '
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
        
        return wp_mail($email, $subject, $html, $headers);
    }
    
    /**
     * Get reminders sent for a debt
     */
    private function get_reminders_sent($debt_id) {
        $reminders = get_option('ak_debt_reminders_sent', array());
        return isset($reminders[$debt_id]) ? count($reminders[$debt_id]) : 0;
    }
    
    /**
     * Record reminder sent
     */
    private function record_reminder_sent($debt_id, $level) {
        $reminders = get_option('ak_debt_reminders_sent', array());
        
        if (!isset($reminders[$debt_id])) {
            $reminders[$debt_id] = array();
        }
        
        $reminders[$debt_id][] = array(
            'level' => $level,
            'sent_at' => current_time('mysql')
        );
        
        update_option('ak_debt_reminders_sent', $reminders);
    }
    
    /**
     * Log chase activity
     */
    private function log_chase($type, $message) {
        $logs = get_option('ak_debt_chase_logs', array());
        
        $logs[] = array(
            'type' => $type,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
        
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('ak_debt_chase_logs', $logs);
    }
    
    /**
     * Manual trigger (AJAX)
     */
    public function manual_trigger() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $this->process_debt_reminders();
        
        $logs = get_option('ak_debt_chase_logs', array());
        $recent = array_slice($logs, -3);
        
        wp_send_json_success(array(
            'message' => 'Debt chase processed',
            'logs' => $recent
        ));
    }
    
    /**
     * Send single reminder (AJAX)
     */
    public function send_single_reminder() {
        check_ajax_referer('ak_dashboard_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $debt_id = intval($_POST['debt_id']);
        $level = intval($_POST['level']) ?: 1;
        
        global $wpdb;
        $debt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ak_debt_ledger WHERE id = %d",
            $debt_id
        ));
        
        if (!$debt) {
            wp_send_json_error(array('message' => 'Debt not found'));
        }
        
        // Verify ownership
        if ($debt->creditor_user_id != get_current_user_id()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $result = $this->send_debt_reminder($debt, $level);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Reminder sent!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send reminder. Check debtor contact details.'));
        }
    }
    
    /**
     * Get stats
     */
    public static function get_stats() {
        $reminders = get_option('ak_debt_reminders_sent', array());
        $total_sent = 0;
        
        foreach ($reminders as $debt_reminders) {
            $total_sent += count($debt_reminders);
        }
        
        return array(
            'total_reminders_sent' => $total_sent,
            'debts_chased' => count($reminders)
        );
    }
}

// Initialize
new AK_Debt_AutoChase();
