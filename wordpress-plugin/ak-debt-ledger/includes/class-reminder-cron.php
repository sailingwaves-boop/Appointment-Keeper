<?php
/**
 * Reminder Cron Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Reminder_Cron {
    
    public function __construct() {
        add_action('ak_debt_ledger_reminder_cron', array($this, 'process_reminders'));
    }
    
    /**
     * Process due reminders
     */
    public function process_reminders() {
        $due_entries = AK_Debt_Ledger_Database::get_due_reminders();
        
        if (empty($due_entries)) {
            return;
        }
        
        $settings = get_option('ak_debt_ledger_settings', array());
        $interval_days = isset($settings['reminder_interval_days']) ? intval($settings['reminder_interval_days']) : 7;
        
        foreach ($due_entries as $entry) {
            $this->send_reminder($entry, $settings, $interval_days);
        }
    }
    
    /**
     * Send reminder for a ledger entry
     */
    public function send_reminder($entry, $settings = null, $interval_days = null) {
        if ($settings === null) {
            $settings = get_option('ak_debt_ledger_settings', array());
        }
        
        if ($interval_days === null) {
            $interval_days = isset($settings['reminder_interval_days']) ? intval($settings['reminder_interval_days']) : 7;
        }
        
        // Prepare template data
        $data = array(
            'customer_name' => $entry->customer_name,
            'customer_email' => $entry->customer_email,
            'customer_phone' => $entry->customer_phone,
            'original_amount' => $entry->original_amount,
            'current_balance' => $entry->current_balance,
            'currency' => $entry->currency,
            'debt_type' => $entry->debt_type
        );
        
        $sms_sent = false;
        $email_sent = false;
        
        // Send SMS if preferred
        if (in_array($entry->preferred_channel, array('sms', 'both')) && !empty($entry->customer_phone)) {
            $sms_template = isset($settings['sms_template']) ? $settings['sms_template'] : '';
            $sms_message = AK_Debt_Ledger_Twilio_SMS::parse_template($sms_template, $data);
            
            $twilio = new AK_Debt_Ledger_Twilio_SMS();
            $result = $twilio->send_sms($entry->customer_phone, $sms_message);
            
            // Log the reminder
            AK_Debt_Ledger_Database::log_reminder(array(
                'ledger_id' => $entry->id,
                'channel' => 'sms',
                'message' => $sms_message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : $result['error']
            ));
            
            $sms_sent = $result['success'];
        }
        
        // Send email if preferred
        if (in_array($entry->preferred_channel, array('email', 'both')) && !empty($entry->customer_email)) {
            $email_subject = isset($settings['email_subject_template']) ? $settings['email_subject_template'] : '';
            $email_body = isset($settings['email_body_template']) ? $settings['email_body_template'] : '';
            
            $email_subject = AK_Debt_Ledger_Twilio_SMS::parse_template($email_subject, $data);
            $email_body = AK_Debt_Ledger_Twilio_SMS::parse_template($email_body, $data);
            
            $email_sender = new AK_Debt_Ledger_Email_Sender();
            $result = $email_sender->send_email($entry->customer_email, $email_subject, $email_body);
            
            // Log the reminder
            AK_Debt_Ledger_Database::log_reminder(array(
                'ledger_id' => $entry->id,
                'channel' => 'email',
                'message' => $email_body,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : (isset($result['error']) ? $result['error'] : null)
            ));
            
            $email_sent = $result['success'];
        }
        
        // Update ledger entry
        if ($sms_sent || $email_sent) {
            $next_reminder = date('Y-m-d H:i:s', strtotime("+{$interval_days} days"));
            
            AK_Debt_Ledger_Database::update_ledger_entry($entry->id, array(
                'last_reminder_at' => current_time('mysql'),
                'reminder_count' => $entry->reminder_count + 1,
                'next_reminder_at' => $next_reminder
            ));
        }
        
        return array(
            'sms_sent' => $sms_sent,
            'email_sent' => $email_sent
        );
    }
    
    /**
     * Send immediate reminder (manual trigger)
     */
    public static function send_now($ledger_id) {
        $entry = AK_Debt_Ledger_Database::get_ledger_entry($ledger_id);
        
        if (!$entry) {
            return array(
                'success' => false,
                'error' => 'Ledger entry not found.'
            );
        }
        
        if (!$entry->consent_to_reminders) {
            return array(
                'success' => false,
                'error' => 'Customer has not consented to receive reminders.'
            );
        }
        
        if ($entry->status !== 'open') {
            return array(
                'success' => false,
                'error' => 'Cannot send reminder for closed debts.'
            );
        }
        
        $cron = new self();
        $result = $cron->send_reminder($entry);
        
        if ($result['sms_sent'] || $result['email_sent']) {
            $channels = array();
            if ($result['sms_sent']) $channels[] = 'SMS';
            if ($result['email_sent']) $channels[] = 'Email';
            
            return array(
                'success' => true,
                'message' => 'Reminder sent via: ' . implode(', ', $channels)
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to send reminder. Check your notification settings.'
            );
        }
    }
}
