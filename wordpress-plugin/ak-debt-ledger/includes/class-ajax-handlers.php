<?php
/**
 * AJAX Handlers Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Ajax_Handlers {
    
    public function __construct() {
        // Search Amelia customers
        add_action('wp_ajax_ak_search_customers', array($this, 'search_customers'));
        
        // Save debt entry
        add_action('wp_ajax_ak_save_debt', array($this, 'save_debt'));
        
        // Add payment
        add_action('wp_ajax_ak_add_payment', array($this, 'add_payment'));
        
        // Confirm payment
        add_action('wp_ajax_ak_confirm_payment', array($this, 'confirm_payment'));
        
        // Mark as paid
        add_action('wp_ajax_ak_mark_paid', array($this, 'mark_paid'));
        
        // Write off
        add_action('wp_ajax_ak_write_off', array($this, 'write_off'));
        
        // Send reminder
        add_action('wp_ajax_ak_send_reminder', array($this, 'send_reminder'));
        
        // Delete entry
        add_action('wp_ajax_ak_delete_entry', array($this, 'delete_entry'));
        
        // Test Twilio
        add_action('wp_ajax_ak_test_twilio', array($this, 'test_twilio'));
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!check_ajax_referer('ak_debt_ledger_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ak-debt-ledger')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ak-debt-ledger')));
        }
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
        
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        
        $data = array(
            'amelia_customer_id' => isset($_POST['amelia_customer_id']) && !empty($_POST['amelia_customer_id']) ? intval($_POST['amelia_customer_id']) : null,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_address' => sanitize_textarea_field($_POST['customer_address']),
            'original_amount' => floatval($_POST['original_amount']),
            'current_balance' => floatval($_POST['current_balance']),
            'currency' => sanitize_text_field($_POST['currency']),
            'debt_type' => sanitize_text_field($_POST['debt_type']),
            'status' => sanitize_text_field($_POST['status']),
            'notes' => sanitize_textarea_field($_POST['notes']),
            'consent_to_reminders' => isset($_POST['consent_to_reminders']) ? 1 : 0,
            'preferred_channel' => sanitize_text_field($_POST['preferred_channel']),
            'next_reminder_at' => !empty($_POST['next_reminder_at']) ? sanitize_text_field($_POST['next_reminder_at']) : null
        );
        
        // Validation
        if (empty($data['customer_name'])) {
            wp_send_json_error(array('message' => __('Customer name is required.', 'ak-debt-ledger')));
        }
        
        if ($data['original_amount'] <= 0) {
            wp_send_json_error(array('message' => __('Original amount must be greater than 0.', 'ak-debt-ledger')));
        }
        
        if ($entry_id > 0) {
            // Update
            AK_Debt_Ledger_Database::update_ledger_entry($entry_id, $data);
            wp_send_json_success(array(
                'message' => __('Debt updated successfully.', 'ak-debt-ledger'),
                'id' => $entry_id
            ));
        } else {
            // Insert
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
        
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($ledger_id);
        
        if (!$entry) {
            wp_send_json_error(array('message' => __('Debt entry not found.', 'ak-debt-ledger')));
        }
        
        $payment_data = array(
            'ledger_id' => $ledger_id,
            'payment_date' => sanitize_text_field($_POST['payment_date']),
            'amount' => $amount,
            'method' => sanitize_text_field($_POST['method']),
            'reference' => sanitize_text_field($_POST['reference']),
            'note' => sanitize_text_field($_POST['note']),
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
        
        $payment = AK_Debt_Ledger_Database::get_payment($payment_id);
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($ledger_id);
        
        if (!$payment || !$entry) {
            wp_send_json_error(array('message' => __('Payment or debt entry not found.', 'ak-debt-ledger')));
        }
        
        // Confirm the payment
        AK_Debt_Ledger_Database::confirm_payment($payment_id, get_current_user_id());
        
        // Send confirmation notification
        $data = array(
            'customer_name' => $entry->customer_name,
            'customer_email' => $entry->customer_email,
            'customer_phone' => $entry->customer_phone,
            'original_amount' => $entry->original_amount,
            'current_balance' => $entry->current_balance,
            'currency' => $entry->currency,
            'payment_amount' => $payment->amount
        );
        
        $notifications_sent = array();
        
        // Send SMS confirmation
        if (!empty($entry->customer_phone) && in_array($entry->preferred_channel, array('sms', 'both'))) {
            $sms_message = sprintf(
                'Hi %s! Your payment of %s%s has been confirmed. Remaining balance: %s%s. Thank you!',
                $entry->customer_name,
                $entry->currency,
                number_format($payment->amount, 2),
                $entry->currency,
                number_format($entry->current_balance, 2)
            );
            
            $twilio = new AK_Debt_Ledger_Twilio_SMS();
            $result = $twilio->send_sms($entry->customer_phone, $sms_message);
            
            if ($result['success']) {
                $notifications_sent[] = 'SMS';
            }
        }
        
        // Send email confirmation
        if (!empty($entry->customer_email) && in_array($entry->preferred_channel, array('email', 'both'))) {
            $email_sender = new AK_Debt_Ledger_Email_Sender();
            $result = $email_sender->send_payment_confirmation($entry->customer_email, $data);
            
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
        
        $result = AK_Debt_Ledger_Reminder_Cron::send_now($entry_id);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
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
        
        AK_Debt_Ledger_Database::delete_ledger_entry($entry_id);
        
        wp_send_json_success(array('message' => __('Entry deleted.', 'ak-debt-ledger')));
    }
    
    /**
     * Test Twilio connection
     */
    public function test_twilio() {
        $this->verify_nonce();
        
        $twilio = new AK_Debt_Ledger_Twilio_SMS();
        $result = $twilio->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
}
