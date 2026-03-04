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
            'creditor_name' => $entry->creditor_name ?: get_bloginfo('name'),
            'customer_email' => $entry->customer_email,
            'customer_phone' => $entry->customer_phone,
            'original_amount' => $entry->original_amount,
            'current_balance' => $entry->current_balance,
            'currency' => $entry->currency,
            'debt_type' => $entry->debt_type
        );
        
        $twilio = new AK_Debt_Ledger_Twilio();
        $sms_sent = false;
        $email_sent = false;
        $call_made = false;
        $credits_used = 0;
        
        // Send SMS if enabled
        if ($entry->remind_via_sms && !empty($entry->customer_phone)) {
            $sms_template = isset($settings['sms_template']) ? $settings['sms_template'] : '';
            $sms_message = AK_Debt_Ledger_Twilio::parse_template($sms_template, $data);
            
            $result = $twilio->send_sms_with_credits($entry->user_id, $entry->customer_phone, $sms_message, 'debt', $entry->id);
            
            // Log the reminder
            AK_Debt_Ledger_Database::log_reminder(array(
                'ledger_id' => $entry->id,
                'user_id' => $entry->user_id,
                'channel' => 'sms',
                'message' => $sms_message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : $result['error'],
                'credits_used' => $result['success'] ? 1 : 0
            ));
            
            if ($result['success']) {
                $sms_sent = true;
                $credits_used++;
            }
        }
        
        // Send email if enabled
        if ($entry->remind_via_email && !empty($entry->customer_email)) {
            $email_subject = isset($settings['email_subject_template']) ? $settings['email_subject_template'] : '';
            $email_body = isset($settings['email_body_template']) ? $settings['email_body_template'] : '';
            
            $email_subject = AK_Debt_Ledger_Twilio::parse_template($email_subject, $data);
            $email_body = AK_Debt_Ledger_Twilio::parse_template($email_body, $data);
            
            $result = $twilio->send_email_with_credits($entry->user_id, $entry->customer_email, $email_subject, $email_body, 'debt', $entry->id);
            
            // Log the reminder
            AK_Debt_Ledger_Database::log_reminder(array(
                'ledger_id' => $entry->id,
                'user_id' => $entry->user_id,
                'channel' => 'email',
                'message' => $email_body,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : (isset($result['error']) ? $result['error'] : null),
                'credits_used' => $result['success'] ? 1 : 0
            ));
            
            if ($result['success']) {
                $email_sent = true;
                $credits_used++;
            }
        }
        
        // Make voice call if enabled
        if ($entry->remind_via_call && !empty($entry->customer_phone)) {
            $call_script = isset($settings['call_script_template']) ? $settings['call_script_template'] : '';
            $call_message = AK_Debt_Ledger_Twilio::parse_template($call_script, $data);
            
            $result = $twilio->make_call_with_credits($entry->user_id, $entry->customer_phone, $call_message, 'debt', $entry->id);
            
            // Log the reminder
            AK_Debt_Ledger_Database::log_reminder(array(
                'ledger_id' => $entry->id,
                'user_id' => $entry->user_id,
                'channel' => 'call',
                'message' => $call_message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : $result['error'],
                'credits_used' => $result['success'] ? 1 : 0
            ));
            
            if ($result['success']) {
                $call_made = true;
                $credits_used++;
            }
        }
        
        // Update ledger entry if any reminder was sent
        if ($sms_sent || $email_sent || $call_made) {
            $next_reminder = date('Y-m-d H:i:s', strtotime("+{$interval_days} days"));
            
            AK_Debt_Ledger_Database::update_ledger_entry($entry->id, array(
                'last_reminder_at' => current_time('mysql'),
                'reminder_count' => $entry->reminder_count + 1,
                'next_reminder_at' => $next_reminder
            ));
        }
        
        return array(
            'sms_sent' => $sms_sent,
            'email_sent' => $email_sent,
            'call_made' => $call_made,
            'credits_used' => $credits_used
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
                'error' => 'Reminders are not enabled for this debt.'
            );
        }
        
        if ($entry->status !== 'open') {
            return array(
                'success' => false,
                'error' => 'Cannot send reminder for closed debts.'
            );
        }
        
        // Check if any reminder method is enabled
        if (!$entry->remind_via_sms && !$entry->remind_via_email && !$entry->remind_via_call) {
            return array(
                'success' => false,
                'error' => 'No reminder method selected. Please enable SMS, Email, or Phone Call.'
            );
        }
        
        $cron = new self();
        $result = $cron->send_reminder($entry);
        
        if ($result['sms_sent'] || $result['email_sent'] || $result['call_made']) {
            $channels = array();
            if ($result['sms_sent']) $channels[] = 'SMS';
            if ($result['email_sent']) $channels[] = 'Email';
            if ($result['call_made']) $channels[] = 'Phone Call';
            
            return array(
                'success' => true,
                'message' => 'Reminder sent via: ' . implode(', ', $channels),
                'credits_used' => $result['credits_used']
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to send reminder. Check your credits and notification settings.'
            );
        }
    }
}
