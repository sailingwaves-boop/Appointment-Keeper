<?php
/**
 * Amelia Integration Class - Hooks into Amelia events
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Amelia_Integration {
    
    public function __construct() {
        // Hook into Amelia events if Amelia is active
        if (self::is_amelia_active()) {
            add_action('amelia_after_booking_added', array($this, 'on_booking_added'), 10, 2);
            add_action('amelia_after_booking_status_updated', array($this, 'on_booking_status_updated'), 10, 3);
            add_action('amelia_before_booking_reminder', array($this, 'on_reminder_due'), 10, 2);
        }
    }
    
    /**
     * Check if Amelia is active
     */
    public static function is_amelia_active() {
        return class_exists('AmeliaBooking\\Plugin') || 
               is_plugin_active('ameliabooking/ameliabooking.php') ||
               is_plugin_active('ameliabooking-lite/ameliabooking.php');
    }
    
    /**
     * Handle booking added event
     */
    public function on_booking_added($booking, $booking_id) {
        // Get the customer (business owner) who owns this booking
        $user_id = $this->get_booking_owner_user_id($booking);
        
        if (!$user_id) return;
        
        $settings = get_option('ak_debt_ledger_settings', array());
        
        // Check if auto-confirmation SMS is enabled
        if (empty($settings['auto_confirm_sms'])) return;
        
        $customer_phone = isset($booking['customer']['phone']) ? $booking['customer']['phone'] : '';
        $customer_name = isset($booking['customer']['firstName']) ? $booking['customer']['firstName'] : '';
        
        if (empty($customer_phone)) return;
        
        // Send confirmation SMS
        $message = isset($settings['booking_confirm_sms']) ? $settings['booking_confirm_sms'] : 
            'Hi {customer_name}, your appointment has been confirmed. We look forward to seeing you!';
        
        $message = AK_Debt_Ledger_Twilio::parse_template($message, array(
            'customer_name' => $customer_name
        ));
        
        $twilio = new AK_Debt_Ledger_Twilio();
        $twilio->send_sms_with_credits($user_id, $customer_phone, $message, 'appointment', $booking_id);
    }
    
    /**
     * Handle booking status updated event
     */
    public function on_booking_status_updated($booking, $booking_id, $new_status) {
        $user_id = $this->get_booking_owner_user_id($booking);
        
        if (!$user_id) return;
        
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $customer_phone = isset($booking['customer']['phone']) ? $booking['customer']['phone'] : '';
        $customer_name = isset($booking['customer']['firstName']) ? $booking['customer']['firstName'] : '';
        
        if (empty($customer_phone)) return;
        
        // Handle different status changes
        switch ($new_status) {
            case 'canceled':
                if (!empty($settings['auto_cancel_sms'])) {
                    $message = isset($settings['booking_cancel_sms']) ? $settings['booking_cancel_sms'] : 
                        'Hi {customer_name}, your appointment has been cancelled.';
                    
                    $message = AK_Debt_Ledger_Twilio::parse_template($message, array(
                        'customer_name' => $customer_name
                    ));
                    
                    $twilio = new AK_Debt_Ledger_Twilio();
                    $twilio->send_sms_with_credits($user_id, $customer_phone, $message, 'appointment', $booking_id);
                }
                break;
                
            case 'no-show':
                // Could create a debt entry for no-show fee
                if (!empty($settings['auto_noshow_debt'])) {
                    $noshow_fee = isset($settings['noshow_fee']) ? floatval($settings['noshow_fee']) : 0;
                    
                    if ($noshow_fee > 0) {
                        AK_Debt_Ledger_Database::insert_ledger_entry(array(
                            'user_id' => $user_id,
                            'customer_name' => $customer_name,
                            'customer_phone' => $customer_phone,
                            'original_amount' => $noshow_fee,
                            'current_balance' => $noshow_fee,
                            'debt_type' => 'missed',
                            'status' => 'open',
                            'notes' => 'No-show fee for appointment #' . $booking_id,
                            'consent_to_reminders' => 1
                        ));
                    }
                }
                break;
        }
    }
    
    /**
     * Handle reminder due event
     */
    public function on_reminder_due($booking, $booking_id) {
        $user_id = $this->get_booking_owner_user_id($booking);
        
        if (!$user_id) return;
        
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $customer_phone = isset($booking['customer']['phone']) ? $booking['customer']['phone'] : '';
        $customer_email = isset($booking['customer']['email']) ? $booking['customer']['email'] : '';
        $customer_name = isset($booking['customer']['firstName']) ? $booking['customer']['firstName'] : '';
        
        // Check what reminder methods are enabled
        $send_sms = !empty($settings['reminder_sms_enabled']) && !empty($customer_phone);
        $send_email = !empty($settings['reminder_email_enabled']) && !empty($customer_email);
        $send_call = !empty($settings['reminder_call_enabled']) && !empty($customer_phone);
        
        $twilio = new AK_Debt_Ledger_Twilio();
        
        // Send SMS reminder
        if ($send_sms) {
            $message = isset($settings['reminder_sms_template']) ? $settings['reminder_sms_template'] : 
                'Hi {customer_name}, this is a reminder about your upcoming appointment. See you soon!';
            
            $message = AK_Debt_Ledger_Twilio::parse_template($message, array(
                'customer_name' => $customer_name
            ));
            
            $twilio->send_sms_with_credits($user_id, $customer_phone, $message, 'appointment', $booking_id);
        }
        
        // Send email reminder
        if ($send_email) {
            $subject = isset($settings['reminder_email_subject']) ? $settings['reminder_email_subject'] : 
                'Appointment Reminder';
            $body = isset($settings['reminder_email_body']) ? $settings['reminder_email_body'] : 
                "Hi {customer_name},\n\nThis is a reminder about your upcoming appointment.\n\nSee you soon!";
            
            $subject = AK_Debt_Ledger_Twilio::parse_template($subject, array('customer_name' => $customer_name));
            $body = AK_Debt_Ledger_Twilio::parse_template($body, array('customer_name' => $customer_name));
            
            $twilio->send_email_with_credits($user_id, $customer_email, $subject, $body, 'appointment', $booking_id);
        }
        
        // Make voice call reminder
        if ($send_call) {
            $message = isset($settings['reminder_call_script']) ? $settings['reminder_call_script'] : 
                'Hello {customer_name}, this is a reminder about your upcoming appointment. We look forward to seeing you. Goodbye.';
            
            $message = AK_Debt_Ledger_Twilio::parse_template($message, array(
                'customer_name' => $customer_name
            ));
            
            $twilio->make_call_with_credits($user_id, $customer_phone, $message, 'appointment', $booking_id);
        }
    }
    
    /**
     * Get the WordPress user ID that owns this booking (the business)
     */
    private function get_booking_owner_user_id($booking) {
        // This depends on your Amelia setup
        // Option 1: Use the provider's WordPress user ID
        if (isset($booking['provider']['externalId'])) {
            return intval($booking['provider']['externalId']);
        }
        
        // Option 2: Use a custom field
        if (isset($booking['providerId'])) {
            return $this->get_user_id_from_amelia_provider($booking['providerId']);
        }
        
        // Option 3: Default to admin
        return 1;
    }
    
    /**
     * Get WordPress user ID from Amelia provider ID
     */
    private function get_user_id_from_amelia_provider($provider_id) {
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return null;
        }
        
        $external_id = $wpdb->get_var($wpdb->prepare(
            "SELECT externalId FROM $amelia_table WHERE id = %d AND type = 'provider'",
            $provider_id
        ));
        
        return $external_id ? intval($external_id) : null;
    }
    
    /**
     * Search Amelia customers
     */
    public static function search_customers($search_term) {
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return array(
                'success' => false,
                'message' => 'Amelia plugin is not installed or customers table not found.',
                'customers' => array()
            );
        }
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, firstName, lastName, email, phone 
            FROM $amelia_table 
            WHERE type = 'customer' 
            AND (firstName LIKE %s OR lastName LIKE %s OR email LIKE %s OR phone LIKE %s)
            ORDER BY firstName, lastName
            LIMIT 20",
            $search, $search, $search, $search
        ));
        
        $formatted_customers = array();
        foreach ($customers as $customer) {
            $formatted_customers[] = array(
                'id' => $customer->id,
                'name' => trim($customer->firstName . ' ' . $customer->lastName),
                'email' => $customer->email,
                'phone' => $customer->phone,
                'display' => sprintf(
                    '%s %s (%s)',
                    $customer->firstName,
                    $customer->lastName,
                    $customer->email ? $customer->email : $customer->phone
                )
            );
        }
        
        return array(
            'success' => true,
            'customers' => $formatted_customers
        );
    }
    
    /**
     * Get single Amelia customer
     */
    public static function get_customer($customer_id) {
        global $wpdb;
        
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$amelia_table'") !== $amelia_table) {
            return null;
        }
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, firstName, lastName, email, phone 
            FROM $amelia_table 
            WHERE id = %d AND type = 'customer'",
            $customer_id
        ));
        
        if (!$customer) {
            return null;
        }
        
        return array(
            'id' => $customer->id,
            'name' => trim($customer->firstName . ' ' . $customer->lastName),
            'email' => $customer->email,
            'phone' => $customer->phone
        );
    }
}
