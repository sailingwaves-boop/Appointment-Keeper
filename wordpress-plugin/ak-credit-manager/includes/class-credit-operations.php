<?php
/**
 * Credit Operations Class - Core credit add/deduct logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_Operations {
    
    /**
     * Add credits to a user account
     */
    public static function add_credits($user_id, $credit_type, $amount, $reason = '', $action = 'add', $performed_by = null) {
        if ($amount <= 0) {
            return array(
                'success' => false,
                'error' => 'Amount must be greater than 0.'
            );
        }
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        
        if (!$credits) {
            return array(
                'success' => false,
                'error' => 'Customer not found.'
            );
        }
        
        $column = self::get_credit_column($credit_type);
        if (!$column) {
            return array(
                'success' => false,
                'error' => 'Invalid credit type.'
            );
        }
        
        $balance_before = $credits->$column;
        $balance_after = $balance_before + $amount;
        
        // Update credits
        AK_Credit_Manager_Database::update_customer_credits($user_id, array(
            $column => $balance_after,
            'low_credit_alert_sent' => 0 // Reset alert flag when adding credits
        ));
        
        // Log the transaction
        AK_Credit_Manager_Database::log_transaction(array(
            'user_id' => $user_id,
            'action' => $action,
            'credit_type' => $credit_type,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'reason' => $reason,
            'performed_by' => $performed_by ?: get_current_user_id()
        ));
        
        return array(
            'success' => true,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'message' => sprintf('Added %d %s credits. New balance: %d', $amount, $credit_type, $balance_after)
        );
    }
    
    /**
     * Deduct credits from a user account
     */
    public static function deduct_credits($user_id, $credit_type, $amount, $reason = '', $reference_type = null, $reference_id = null) {
        if ($amount <= 0) {
            return array(
                'success' => false,
                'error' => 'Amount must be greater than 0.'
            );
        }
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        
        if (!$credits) {
            return array(
                'success' => false,
                'error' => 'Customer not found.'
            );
        }
        
        $column = self::get_credit_column($credit_type);
        if (!$column) {
            return array(
                'success' => false,
                'error' => 'Invalid credit type.'
            );
        }
        
        $balance_before = $credits->$column;
        
        // Check if enough credits
        if ($balance_before < $amount) {
            return array(
                'success' => false,
                'error' => 'Insufficient credits.',
                'balance' => $balance_before,
                'required' => $amount
            );
        }
        
        $balance_after = $balance_before - $amount;
        
        // Update credits
        AK_Credit_Manager_Database::update_customer_credits($user_id, array(
            $column => $balance_after
        ));
        
        // Log the transaction
        AK_Credit_Manager_Database::log_transaction(array(
            'user_id' => $user_id,
            'action' => 'deduct',
            'credit_type' => $credit_type,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'reason' => $reason,
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'performed_by' => get_current_user_id()
        ));
        
        // Check for low credits alert
        self::check_low_credits($user_id, $credit_type, $balance_after);
        
        return array(
            'success' => true,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'message' => sprintf('Deducted %d %s credits. New balance: %d', $amount, $credit_type, $balance_after)
        );
    }
    
    /**
     * Refund credits to a user account
     */
    public static function refund_credits($user_id, $credit_type, $amount, $reason = '') {
        return self::add_credits($user_id, $credit_type, $amount, $reason, 'refund');
    }
    
    /**
     * Give free month credits
     */
    public static function give_free_month($user_id, $reason = 'Free month awarded') {
        $settings = get_option('ak_credit_manager_settings', array());
        
        $sms = isset($settings['free_month_sms']) ? intval($settings['free_month_sms']) : 50;
        $calls = isset($settings['free_month_calls']) ? intval($settings['free_month_calls']) : 20;
        $emails = isset($settings['free_month_emails']) ? intval($settings['free_month_emails']) : 100;
        
        $results = array();
        $performed_by = get_current_user_id();
        
        if ($sms > 0) {
            $results['sms'] = self::add_credits($user_id, 'sms', $sms, $reason, 'free_month', $performed_by);
        }
        
        if ($calls > 0) {
            $results['call'] = self::add_credits($user_id, 'call', $calls, $reason, 'free_month', $performed_by);
        }
        
        if ($emails > 0) {
            $results['email'] = self::add_credits($user_id, 'email', $emails, $reason, 'free_month', $performed_by);
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Free month credits added: %d SMS, %d Calls, %d Emails', $sms, $calls, $emails),
            'details' => $results
        );
    }
    
    /**
     * Upgrade/set customer plan
     */
    public static function set_plan($user_id, $plan_type, $add_credits = true) {
        $settings = get_option('ak_credit_manager_settings', array());
        $plans = isset($settings['plans']) ? $settings['plans'] : array();
        
        if (!isset($plans[$plan_type])) {
            return array(
                'success' => false,
                'error' => 'Invalid plan type.'
            );
        }
        
        $plan = $plans[$plan_type];
        $performed_by = get_current_user_id();
        
        // Update plan type
        AK_Credit_Manager_Database::update_customer_credits($user_id, array(
            'plan_type' => $plan_type
        ));
        
        // Add plan credits if requested
        if ($add_credits) {
            $reason = 'Plan upgrade to ' . $plan['name'];
            
            if (isset($plan['sms_credits']) && $plan['sms_credits'] > 0) {
                self::add_credits($user_id, 'sms', $plan['sms_credits'], $reason, 'plan_upgrade', $performed_by);
            }
            
            if (isset($plan['call_credits']) && $plan['call_credits'] > 0) {
                self::add_credits($user_id, 'call', $plan['call_credits'], $reason, 'plan_upgrade', $performed_by);
            }
            
            if (isset($plan['email_credits']) && $plan['email_credits'] > 0) {
                self::add_credits($user_id, 'email', $plan['email_credits'], $reason, 'plan_upgrade', $performed_by);
            }
        }
        
        return array(
            'success' => true,
            'message' => 'Plan updated to ' . $plan['name'],
            'plan' => $plan
        );
    }
    
    /**
     * Bulk add credits to multiple users
     */
    public static function bulk_add_credits($user_ids, $credit_type, $amount, $reason = 'Bulk credit add') {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($user_ids as $user_id) {
            $result = self::add_credits($user_id, $credit_type, $amount, $reason);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$user_id] = $result;
        }
        
        return $results;
    }
    
    /**
     * Bulk give free month to multiple users
     */
    public static function bulk_free_month($user_ids, $reason = 'Bulk free month') {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($user_ids as $user_id) {
            $result = self::give_free_month($user_id, $reason);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$user_id] = $result;
        }
        
        return $results;
    }
    
    /**
     * Get credit column name from type
     */
    private static function get_credit_column($credit_type) {
        $columns = array(
            'sms' => 'sms_credits',
            'call' => 'call_credits',
            'email' => 'email_credits'
        );
        
        return isset($columns[$credit_type]) ? $columns[$credit_type] : null;
    }
    
    /**
     * Check for low credits and send alert
     */
    private static function check_low_credits($user_id, $credit_type, $balance) {
        $settings = get_option('ak_credit_manager_settings', array());
        $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
        
        if ($balance <= $threshold) {
            $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
            
            if (!$credits->low_credit_alert_sent) {
                // Send low credit alert
                $user = get_userdata($user_id);
                if ($user) {
                    $type_name = ucfirst($credit_type);
                    $subject = "Low {$type_name} Credits Alert - AppointmentKeeper";
                    $message = "Hi {$user->display_name},\n\n";
                    $message .= "You're running low on {$type_name} credits. You have {$balance} remaining.\n\n";
                    $message .= "Top up now to avoid interruption to your service.\n\n";
                    $message .= "Best regards,\nAppointmentKeeper";
                    
                    wp_mail($user->user_email, $subject, $message);
                    
                    // Mark alert as sent
                    AK_Credit_Manager_Database::update_customer_credits($user_id, array(
                        'low_credit_alert_sent' => 1
                    ));
                }
            }
        }
    }
    
    /**
     * Manual adjustment (can be positive or negative)
     */
    public static function manual_adjust($user_id, $credit_type, $amount, $reason = 'Manual adjustment') {
        if ($amount == 0) {
            return array(
                'success' => false,
                'error' => 'Amount cannot be 0.'
            );
        }
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        
        if (!$credits) {
            return array(
                'success' => false,
                'error' => 'Customer not found.'
            );
        }
        
        $column = self::get_credit_column($credit_type);
        if (!$column) {
            return array(
                'success' => false,
                'error' => 'Invalid credit type.'
            );
        }
        
        $balance_before = $credits->$column;
        $balance_after = max(0, $balance_before + $amount); // Don't allow negative balance
        
        // Update credits
        AK_Credit_Manager_Database::update_customer_credits($user_id, array(
            $column => $balance_after,
            'low_credit_alert_sent' => ($amount > 0) ? 0 : $credits->low_credit_alert_sent
        ));
        
        // Log the transaction
        AK_Credit_Manager_Database::log_transaction(array(
            'user_id' => $user_id,
            'action' => 'manual_adjust',
            'credit_type' => $credit_type,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'reason' => $reason,
            'performed_by' => get_current_user_id()
        ));
        
        $action_word = $amount > 0 ? 'Added' : 'Removed';
        
        return array(
            'success' => true,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'message' => sprintf('%s %d %s credits. New balance: %d', $action_word, abs($amount), $credit_type, $balance_after)
        );
    }
}
