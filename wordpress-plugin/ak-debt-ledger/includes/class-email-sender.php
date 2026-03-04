<?php
/**
 * Email Sender Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Email_Sender {
    
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $this->from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
        $this->from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
    }
    
    /**
     * Send email reminder
     */
    public function send_email($to, $subject, $message) {
        if (empty($to) || !is_email($to)) {
            return array(
                'success' => false,
                'error' => 'Invalid email address provided.'
            );
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>'
        );
        
        // Convert plain text to HTML
        $html_message = $this->convert_to_html($message);
        
        $sent = wp_mail($to, $subject, $html_message, $headers);
        
        if ($sent) {
            return array(
                'success' => true,
                'message' => 'Email sent successfully'
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to send email. Please check your WordPress email configuration.'
            );
        }
    }
    
    /**
     * Convert plain text to HTML email
     */
    private function convert_to_html($text) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . esc_html($this->from_name) . '</h2>
                </div>
                <div class="content">
                    ' . nl2br(esc_html($text)) . '
                </div>
                <div class="footer">
                    <p>This is an automated message from ' . esc_html($this->from_name) . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Parse email template with variables
     */
    public static function parse_template($template, $data) {
        return AK_Debt_Ledger_Twilio_SMS::parse_template($template, $data);
    }
    
    /**
     * Send payment confirmation email
     */
    public function send_payment_confirmation($to, $data) {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        $subject = 'Payment Confirmed - ' . $data['currency'] . $data['payment_amount'];
        
        $message = sprintf(
            "Dear %s,\n\nYour payment of %s%s has been confirmed.\n\n" .
            "Previous balance: %s%s\n" .
            "Payment amount: %s%s\n" .
            "New balance: %s%s\n\n" .
            "Thank you for your payment.\n\n" .
            "Best regards,\n%s",
            $data['customer_name'],
            $data['currency'],
            number_format($data['payment_amount'], 2),
            $data['currency'],
            number_format($data['original_amount'], 2),
            $data['currency'],
            number_format($data['payment_amount'], 2),
            $data['currency'],
            number_format($data['current_balance'], 2),
            $this->from_name
        );
        
        return $this->send_email($to, $subject, $message);
    }
}
