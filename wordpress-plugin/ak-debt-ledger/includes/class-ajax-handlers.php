<?php
/**
 * AJAX Handlers Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Ajax_Handlers {
    
    public function __construct() {
        // Admin AJAX
        add_action('wp_ajax_ak_search_customers', array($this, 'search_customers'));
        add_action('wp_ajax_ak_save_debt', array($this, 'save_debt'));
        add_action('wp_ajax_ak_add_payment', array($this, 'add_payment'));
        add_action('wp_ajax_ak_confirm_payment', array($this, 'confirm_payment'));
        add_action('wp_ajax_ak_mark_paid', array($this, 'mark_paid'));
        add_action('wp_ajax_ak_write_off', array($this, 'write_off'));
        add_action('wp_ajax_ak_send_reminder', array($this, 'send_reminder'));
        add_action('wp_ajax_ak_delete_entry', array($this, 'delete_entry'));
        add_action('wp_ajax_ak_test_twilio', array($this, 'test_twilio'));
        add_action('wp_ajax_ak_send_referral', array($this, 'send_referral'));
        add_action('wp_ajax_ak_get_credits', array($this, 'get_credits'));
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!check_ajax_referer('ak_debt_ledger_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ak-debt-ledger')));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in.', 'ak-debt-ledger')));
        }
    }
    
    /**
     * Get credits for current user
     */
    public function get_credits() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $credits = AK_Debt_Ledger_Database::get_customer_credits($user_id);
        
        wp_send_json_success(array(
            'sms_credits' => $credits->sms_credits,
            'call_credits' => $credits->call_credits,
            'email_credits' => $credits->email_credits
        ));
    }
    
    /**
     * Search Amelia customers
     */
    public function search_customers() {
        $this->verify_nonce();
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($search) < 2) {
            wp_send_json_success(array('customers' => array()));
        }
        
        $result = AK_Debt_Ledger_Amelia_Integration::search_customers($search);
        
        wp_send_json_success($result);
    }
    
    /**
     * Save debt entry
     */
    public function save_debt() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        // If editing, check ownership
        if ($entry_id > 0) {
            $existing = AK_Debt_Ledger_Database::get_ledger_entry($entry_id);
            if (!$existing || ($existing->user_id != $user_id && !current_user_can('manage_options'))) {
                wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
            }
        }
        
        $original_amount = floatval($_POST['original_amount']);
        $current_balance = isset($_POST['current_balance']) ? floatval($_POST['current_balance']) : $original_amount;
        
        $data = array(
            'user_id' => $user_id,
            'creditor_name' => sanitize_text_field($_POST['creditor_name']),
            'creditor_reference' => isset($_POST['creditor_reference']) ? sanitize_text_field($_POST['creditor_reference']) : '',
            'amelia_customer_id' => isset($_POST['amelia_customer_id']) && !empty($_POST['amelia_customer_id']) ? intval($_POST['amelia_customer_id']) : null,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '',
            'customer_address' => isset($_POST['customer_address']) ? sanitize_textarea_field($_POST['customer_address']) : '',
            'debtor2_name' => isset($_POST['debtor2_name']) ? sanitize_text_field($_POST['debtor2_name']) : '',
            'debtor2_phone' => isset($_POST['debtor2_phone']) ? sanitize_text_field($_POST['debtor2_phone']) : '',
            'debtor3_name' => isset($_POST['debtor3_name']) ? sanitize_text_field($_POST['debtor3_name']) : '',
            'debtor3_phone' => isset($_POST['debtor3_phone']) ? sanitize_text_field($_POST['debtor3_phone']) : '',
            'num_debtors' => isset($_POST['num_debtors']) ? intval($_POST['num_debtors']) : 1,
            'original_amount' => $original_amount,
            'current_balance' => $current_balance,
            'currency' => sanitize_text_field($_POST['currency']),
            'debt_type' => isset($_POST['debt_type']) ? sanitize_text_field($_POST['debt_type']) : 'other',
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'open',
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
            'consent_to_reminders' => isset($_POST['consent_to_reminders']) ? 1 : 0,
            'remind_via_sms' => isset($_POST['remind_via_sms']) ? 1 : 0,
            'remind_via_email' => isset($_POST['remind_via_email']) ? 1 : 0,
            'remind_via_call' => isset($_POST['remind_via_call']) ? 1 : 0,
            'next_reminder_at' => !empty($_POST['next_reminder_at']) ? sanitize_text_field($_POST['next_reminder_at']) : null
        );
        
        // Set default next reminder if consent given but no date set
        if ($data['consent_to_reminders'] && empty($data['next_reminder_at'])) {
            $settings = get_option('ak_debt_ledger_settings', array());
            $interval = isset($settings['reminder_interval_days']) ? intval($settings['reminder_interval_days']) : 7;
            $data['next_reminder_at'] = date('Y-m-d H:i:s', strtotime("+{$interval} days"));
        }
        
        // Validation
        if (empty($data['customer_name'])) {
            wp_send_json_error(array('message' => __('Debtor name is required.', 'ak-debt-ledger')));
        }
        
        if ($data['original_amount'] <= 0) {
            wp_send_json_error(array('message' => __('Amount must be greater than 0.', 'ak-debt-ledger')));
        }
        
        if ($entry_id > 0) {
            AK_Debt_Ledger_Database::update_ledger_entry($entry_id, $data);
            wp_send_json_success(array(
                'message' => __('Debt updated successfully.', 'ak-debt-ledger'),
                'id' => $entry_id
            ));
        } else {
            $data['reminder_count'] = 0;
            $new_id = AK_Debt_Ledger_Database::insert_ledger_entry($data);
            wp_send_json_success(array(
                'message' => __('Debt added successfully.', 'ak-debt-ledger'),
                'id' => $new_id
            ));
        }
    }
    
    /**
     * Add payment
     */
    public function add_payment() {
        $this->verify_nonce();
        
        $ledger_id = intval($_POST['ledger_id']);
        $amount = floatval($_POST['amount']);
        $user_id = get_current_user_id();
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($ledger_id);
        
        if (!$entry) {
            wp_send_json_error(array('message' => __('Debt entry not found.', 'ak-debt-ledger')));
        }
        
        // Check ownership
        if ($entry->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        $payment_data = array(
            'ledger_id' => $ledger_id,
            'payment_date' => sanitize_text_field($_POST['payment_date']),
            'amount' => $amount,
            'method' => sanitize_text_field($_POST['method']),
            'reference' => sanitize_text_field($_POST['reference']),
            'note' => isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '',
            'confirmed' => 0
        );
        
        $payment_id = AK_Debt_Ledger_Database::insert_payment($payment_data);
        
        // Update balance
        $new_balance = max(0, $entry->current_balance - $amount);
        $update_data = array('current_balance' => $new_balance);
        
        if ($new_balance <= 0) {
            $update_data['status'] = 'paid';
        }
        
        AK_Debt_Ledger_Database::update_ledger_entry($ledger_id, $update_data);
        
        wp_send_json_success(array(
            'message' => __('Payment recorded successfully.', 'ak-debt-ledger'),
            'payment_id' => $payment_id,
            'new_balance' => $new_balance
        ));
    }
    
    /**
     * Confirm payment
     */
    public function confirm_payment() {
        $this->verify_nonce();
        
        $payment_id = intval($_POST['payment_id']);
        $ledger_id = intval($_POST['ledger_id']);
        $user_id = get_current_user_id();
        
        $payment = AK_Debt_Ledger_Database::get_payment($payment_id);
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($ledger_id);
        
        if (!$payment || !$entry) {
            wp_send_json_error(array('message' => __('Payment or debt entry not found.', 'ak-debt-ledger')));
        }
        
        // Check ownership
        if ($entry->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        // Confirm the payment
        AK_Debt_Ledger_Database::confirm_payment($payment_id, $user_id);
        
        // Send confirmation notification
        $data = array(
            'customer_name' => $entry->customer_name,
            'creditor_name' => $entry->creditor_name,
            'customer_email' => $entry->customer_email,
            'customer_phone' => $entry->customer_phone,
            'original_amount' => $entry->original_amount,
            'current_balance' => $entry->current_balance,
            'currency' => $entry->currency,
            'payment_amount' => $payment->amount
        );
        
        $notifications_sent = array();
        $twilio = new AK_Debt_Ledger_Twilio();
        
        // Send SMS confirmation
        if (!empty($entry->customer_phone) && $entry->remind_via_sms) {
            $sms_message = sprintf(
                'Hi %s! Your payment of %s%s has been confirmed. Remaining balance: %s%s. Thank you! - %s',
                $entry->customer_name,
                $entry->currency,
                number_format($payment->amount, 2),
                $entry->currency,
                number_format($entry->current_balance, 2),
                $entry->creditor_name
            );
            
            $result = $twilio->send_sms_with_credits($entry->user_id, $entry->customer_phone, $sms_message, 'debt', $entry->id);
            
            if ($result['success']) {
                $notifications_sent[] = 'SMS';
            }
        }
        
        // Send email confirmation
        if (!empty($entry->customer_email) && $entry->remind_via_email) {
            $subject = 'Payment Confirmed - ' . $entry->currency . number_format($payment->amount, 2);
            $body = sprintf(
                "Dear %s,\n\nYour payment of %s%s has been confirmed.\n\nPrevious balance: %s%s\nPayment amount: %s%s\nRemaining balance: %s%s\n\nThank you!\n\n%s",
                $entry->customer_name,
                $entry->currency, number_format($payment->amount, 2),
                $entry->currency, number_format($entry->original_amount, 2),
                $entry->currency, number_format($payment->amount, 2),
                $entry->currency, number_format($entry->current_balance, 2),
                $entry->creditor_name
            );
            
            $result = $twilio->send_email_with_credits($entry->user_id, $entry->customer_email, $subject, $body, 'debt', $entry->id);
            
            if ($result['success']) {
                $notifications_sent[] = 'Email';
            }
        }
        
        $message = __('Payment confirmed.', 'ak-debt-ledger');
        if (!empty($notifications_sent)) {
            $message .= ' ' . sprintf(__('Notification sent via: %s', 'ak-debt-ledger'), implode(', ', $notifications_sent));
        }
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * Mark as paid
     */
    public function mark_paid() {
        $this->verify_nonce();
        
        $entry_id = intval($_POST['entry_id']);
        $user_id = get_current_user_id();
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($entry_id);
        
        if (!$entry || ($entry->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        AK_Debt_Ledger_Database::update_ledger_entry($entry_id, array(
            'current_balance' => 0,
            'status' => 'paid'
        ));
        
        wp_send_json_success(array('message' => __('Debt marked as paid.', 'ak-debt-ledger')));
    }
    
    /**
     * Write off
     */
    public function write_off() {
        $this->verify_nonce();
        
        $entry_id = intval($_POST['entry_id']);
        $user_id = get_current_user_id();
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($entry_id);
        
        if (!$entry || ($entry->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        AK_Debt_Ledger_Database::update_ledger_entry($entry_id, array(
            'status' => 'written_off'
        ));
        
        wp_send_json_success(array('message' => __('Debt written off.', 'ak-debt-ledger')));
    }
    
    /**
     * Send reminder now
     */
    public function send_reminder() {
        $this->verify_nonce();
        
        $entry_id = intval($_POST['entry_id']);
        $user_id = get_current_user_id();
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($entry_id);
        
        if (!$entry || ($entry->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        $result = AK_Debt_Ledger_Reminder_Cron::send_now($entry_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'credits_used' => isset($result['credits_used']) ? $result['credits_used'] : 0
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Delete entry
     */
    public function delete_entry() {
        $this->verify_nonce();
        
        $entry_id = intval($_POST['entry_id']);
        $user_id = get_current_user_id();
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($entry_id);
        
        if (!$entry || ($entry->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        AK_Debt_Ledger_Database::delete_ledger_entry($entry_id);
        
        wp_send_json_success(array('message' => __('Entry deleted.', 'ak-debt-ledger')));
    }
    
    /**
     * Test Twilio connection
     */
    public function test_twilio() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
        
        $twilio = new AK_Debt_Ledger_Twilio();
        $result = $twilio->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Send referral invites
     */
    public function send_referral() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $num_friends = intval($_POST['num_friends']);
        
        if ($num_friends < 1 || $num_friends > 3) {
            wp_send_json_error(array('message' => __('Invalid number of friends.', 'ak-debt-ledger')));
        }
        
        $settings = get_option('ak_debt_ledger_settings', array());
        $signup_link = isset($settings['signup_link']) ? $settings['signup_link'] : home_url('/register');
        $template = isset($settings['referral_sms_template']) ? $settings['referral_sms_template'] : 
            'Hi! {referrer_name} thinks you would benefit from AppointmentKeeper - never miss an appointment again! Plus get a FREE Debt Ledger. Sign up here: {signup_link}';
        
        $twilio = new AK_Debt_Ledger_Twilio();
        $sent = 0;
        $failed = 0;
        
        for ($i = 1; $i <= $num_friends; $i++) {
            $name_key = "friend{$i}_name";
            $phone_key = "friend{$i}_phone";
            
            $friend_name = isset($_POST[$name_key]) ? sanitize_text_field($_POST[$name_key]) : '';
            $friend_phone = isset($_POST[$phone_key]) ? sanitize_text_field($_POST[$phone_key]) : '';
            
            if (empty($friend_name) || empty($friend_phone)) {
                continue;
            }
            
            // Create referral record
            $referral_id = AK_Debt_Ledger_Database::insert_referral(array(
                'user_id' => $user_id,
                'friend_name' => $friend_name,
                'friend_phone' => $friend_phone,
                'invite_sent' => 0
            ));
            
            // Parse and send message
            $message = AK_Debt_Ledger_Twilio::parse_template($template, array(
                'referrer_name' => $user->display_name,
                'friend_name' => $friend_name,
                'signup_link' => $signup_link
            ));
            
            $result = $twilio->send_sms_with_credits($user_id, $friend_phone, $message, 'referral', $referral_id);
            
            if ($result['success']) {
                AK_Debt_Ledger_Database::update_referral($referral_id, array(
                    'invite_sent' => 1,
                    'invite_sent_at' => current_time('mysql')
                ));
                $sent++;
            } else {
                $failed++;
            }
        }
        
        if ($sent > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d invite(s) sent successfully!', 'ak-debt-ledger'), $sent),
                'sent' => $sent,
                'failed' => $failed
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to send invites. Check your SMS credits.', 'ak-debt-ledger')
            ));
        }
    }
}
