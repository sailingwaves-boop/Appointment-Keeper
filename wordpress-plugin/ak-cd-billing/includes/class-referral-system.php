<?php
/**
 * Referral System for AppointmentKeeper
 * Tracks referrals, generates codes, rewards users
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Referral_System {
    
    public function __construct() {
        // Track referral links
        add_action('init', array($this, 'track_referral_click'));
        
        // Reward referrals on subscription
        add_action('ak_subscription_activated', array($this, 'process_referral_rewards'), 10, 2);
        
        // Dashboard referral section shortcode
        add_shortcode('ak_refer_friend', array($this, 'render_referral_dashboard'));
        
        // AJAX handlers
        add_action('wp_ajax_ak_get_referral_stats', array($this, 'get_referral_stats'));
    }
    
    /**
     * Track when someone clicks a referral link
     */
    public function track_referral_click() {
        if (isset($_GET['ref'])) {
            $referrer_id = intval($_GET['ref']);
            
            // Also check for referral codes
            if ($referrer_id === 0 && !empty($_GET['ref'])) {
                $code = sanitize_text_field($_GET['ref']);
                $referrer_id = $this->get_user_by_referral_code($code);
            }
            
            if ($referrer_id > 0) {
                // Set cookie for 30 days
                setcookie('ak_referrer', $referrer_id, time() + (30 * 24 * 60 * 60), '/');
                
                // Log the click
                $this->log_referral_click($referrer_id);
            }
        }
    }
    
    /**
     * Get user by referral code
     */
    private function get_user_by_referral_code($code) {
        $users = get_users(array(
            'meta_key' => 'ak_referral_code',
            'meta_value' => strtoupper($code),
            'number' => 1
        ));
        
        return !empty($users) ? $users[0]->ID : 0;
    }
    
    /**
     * Log referral click for analytics
     */
    private function log_referral_click($referrer_id) {
        $clicks = get_user_meta($referrer_id, 'ak_referral_clicks', true);
        if (!is_array($clicks)) {
            $clicks = array();
        }
        
        $clicks[] = array(
            'date' => current_time('mysql'),
            'ip' => $this->get_client_ip()
        );
        
        // Keep last 100 clicks only
        $clicks = array_slice($clicks, -100);
        
        update_user_meta($referrer_id, 'ak_referral_clicks', $clicks);
        
        // Update total click count
        $total_clicks = intval(get_user_meta($referrer_id, 'ak_referral_click_count', true));
        update_user_meta($referrer_id, 'ak_referral_click_count', $total_clicks + 1);
    }
    
    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * Process referral rewards when subscription is activated
     */
    public function process_referral_rewards($user_id, $plan_id) {
        // Check if this user was referred
        $referrer_id = get_user_meta($user_id, 'ak_referred_by', true);
        
        if (!$referrer_id) {
            // Check pending referrer from signup
            $referrer_id = get_user_meta($user_id, 'ak_pending_referrer', true);
        }
        
        if (!$referrer_id) {
            return;
        }
        
        // Get reward amount
        $reward_credits = get_option('ak_referral_reward_credits', 10);
        
        // Add credits to both users
        $this->add_reward_credits($user_id, $reward_credits, 'new_user');
        $this->add_reward_credits($referrer_id, $reward_credits, 'referrer');
        
        // Update referral status
        $referrals = get_user_meta($referrer_id, 'ak_referrals', true);
        if (is_array($referrals)) {
            foreach ($referrals as &$ref) {
                if ($ref['user_id'] == $user_id) {
                    $ref['status'] = 'rewarded';
                    $ref['rewarded_date'] = current_time('mysql');
                }
            }
            update_user_meta($referrer_id, 'ak_referrals', $referrals);
        }
        
        // Clean up pending meta
        delete_user_meta($user_id, 'ak_pending_referrer');
        delete_user_meta($user_id, 'ak_pending_referral_reward');
        delete_user_meta($referrer_id, 'ak_pending_referrer_reward');
        
        // Send notification emails
        $this->send_referral_reward_email($referrer_id, $user_id, $reward_credits);
    }
    
    /**
     * Add reward credits to user
     */
    private function add_reward_credits($user_id, $amount, $type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                array('sms_credits' => $existing->sms_credits + $amount),
                array('user_id' => $user_id)
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'sms_credits' => $amount,
                    'call_credits' => 0,
                    'email_credits' => 0
                )
            );
        }
        
        // Log the reward
        $this->log_credit_transaction($user_id, 'referral_reward', $amount, $type);
    }
    
    /**
     * Log credit transaction
     */
    private function log_credit_transaction($user_id, $type, $amount, $note = '') {
        $transactions = get_user_meta($user_id, 'ak_credit_transactions', true);
        if (!is_array($transactions)) {
            $transactions = array();
        }
        
        $transactions[] = array(
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'date' => current_time('mysql')
        );
        
        // Keep last 200 transactions
        $transactions = array_slice($transactions, -200);
        
        update_user_meta($user_id, 'ak_credit_transactions', $transactions);
    }
    
    /**
     * Send referral reward notification email
     */
    private function send_referral_reward_email($referrer_id, $new_user_id, $credits) {
        $referrer = get_user_by('ID', $referrer_id);
        $new_user = get_user_by('ID', $new_user_id);
        
        if (!$referrer) return;
        
        $subject = 'You earned ' . $credits . ' free SMS credits!';
        
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;">
                            <tr>
                                <td style="background:linear-gradient(135deg,#28a745 0%,#218838 100%);padding:35px 30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:24px;">🎉 Referral Reward!</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px 35px;">
                                    <h2 style="margin:0 0 15px 0;color:#1e3a5f;">Hi ' . esc_html($referrer->first_name) . ',</h2>
                                    <p style="margin:0 0 20px 0;color:#555;font-size:15px;line-height:1.6;">
                                        Great news! Your friend <strong>' . esc_html($new_user->display_name) . '</strong> just signed up using your referral link.
                                    </p>
                                    <div style="background:#d4edda;padding:20px;border-radius:10px;text-align:center;margin:25px 0;">
                                        <p style="margin:0;font-size:32px;font-weight:700;color:#155724;">+' . intval($credits) . '</p>
                                        <p style="margin:5px 0 0;color:#155724;font-size:14px;">SMS Credits Added</p>
                                    </div>
                                    <p style="margin:0 0 25px 0;color:#555;font-size:15px;">
                                        Keep sharing your referral link to earn more credits!
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <a href="' . esc_url(home_url('/my-dashboard')) . '" style="display:inline-block;padding:14px 30px;background:#1e3a5f;color:#fff;text-decoration:none;border-radius:50px;font-size:15px;font-weight:600;">
                                                    View Your Dashboard
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($referrer->user_email, $subject, $message, $headers);
    }
    
    /**
     * Render referral dashboard widget
     */
    public function render_referral_dashboard() {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Get or generate referral code
        $referral_code = get_user_meta($user_id, 'ak_referral_code', true);
        if (!$referral_code) {
            $first_name = strtoupper(substr($user->first_name ?: 'USER', 0, 4));
            $referral_code = $first_name . $user_id . strtoupper(wp_generate_password(4, false));
            update_user_meta($user_id, 'ak_referral_code', $referral_code);
        }
        
        $referral_url = home_url('/register?ref=' . $referral_code);
        
        // Get stats
        $click_count = intval(get_user_meta($user_id, 'ak_referral_click_count', true));
        $referral_count = intval(get_user_meta($user_id, 'ak_referral_count', true));
        $reward_credits = get_option('ak_referral_reward_credits', 10);
        $total_earned = $referral_count * $reward_credits;
        
        // Get referral list
        $referrals = get_user_meta($user_id, 'ak_referrals', true);
        if (!is_array($referrals)) {
            $referrals = array();
        }
        
        ob_start();
        ?>
        <div class="ak-referral-widget">
            <div class="ak-referral-banner">
                <div class="ak-banner-icon">🎁</div>
                <div class="ak-banner-content">
                    <h3>Refer a Friend, Get <?php echo intval($reward_credits); ?> Free SMS Credits!</h3>
                    <p>When they sign up and subscribe, you both get rewarded.</p>
                </div>
            </div>
            
            <div class="ak-referral-link-box">
                <label>Your Referral Link</label>
                <div class="ak-link-input-group">
                    <input type="text" value="<?php echo esc_url($referral_url); ?>" readonly class="ak-referral-link-input">
                    <button type="button" class="ak-copy-link-btn" onclick="akCopyReferralLink(this)">Copy</button>
                </div>
                <p class="ak-referral-code-text">Or share your code: <strong><?php echo esc_html($referral_code); ?></strong></p>
            </div>
            
            <div class="ak-referral-stats">
                <div class="ak-stat-card">
                    <span class="ak-stat-number"><?php echo intval($click_count); ?></span>
                    <span class="ak-stat-label">Link Clicks</span>
                </div>
                <div class="ak-stat-card">
                    <span class="ak-stat-number"><?php echo intval($referral_count); ?></span>
                    <span class="ak-stat-label">Friends Joined</span>
                </div>
                <div class="ak-stat-card ak-stat-highlight">
                    <span class="ak-stat-number"><?php echo intval($total_earned); ?></span>
                    <span class="ak-stat-label">Credits Earned</span>
                </div>
            </div>
            
            <?php if (!empty($referrals)): ?>
            <div class="ak-referral-history">
                <h4>Your Referrals</h4>
                <table class="ak-referral-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Friend</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse(array_slice($referrals, -10)) as $ref): 
                            $friend = get_user_by('ID', $ref['user_id']);
                            $status_class = $ref['status'] === 'rewarded' ? 'ak-status-success' : 'ak-status-pending';
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($ref['date'])); ?></td>
                            <td><?php echo $friend ? esc_html($friend->display_name) : 'User'; ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst($ref['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="ak-share-buttons">
                <p>Share on:</p>
                <a href="https://wa.me/?text=<?php echo urlencode('Check out AppointmentKeeper! Use my link: ' . $referral_url); ?>" target="_blank" class="ak-share-btn ak-whatsapp">WhatsApp</a>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out AppointmentKeeper! ' . $referral_url); ?>" target="_blank" class="ak-share-btn ak-twitter">Twitter</a>
                <a href="mailto:?subject=<?php echo urlencode('Try AppointmentKeeper'); ?>&body=<?php echo urlencode('I thought you might like AppointmentKeeper: ' . $referral_url); ?>" class="ak-share-btn ak-email">Email</a>
            </div>
        </div>
        
        <script>
        function akCopyReferralLink(btn) {
            var input = btn.parentElement.querySelector('.ak-referral-link-input');
            navigator.clipboard.writeText(input.value).then(function() {
                btn.textContent = 'Copied!';
                btn.classList.add('ak-copied');
                setTimeout(function() {
                    btn.textContent = 'Copy';
                    btn.classList.remove('ak-copied');
                }, 2000);
            });
        }
        </script>
        
        <style>
        .ak-referral-widget { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .ak-referral-banner { display: flex; align-items: center; gap: 15px; background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%); border: 2px solid #ffc107; border-radius: 14px; padding: 20px; margin-bottom: 25px; }
        .ak-banner-icon { font-size: 40px; }
        .ak-banner-content h3 { margin: 0 0 5px 0; color: #e65100; font-size: 18px; }
        .ak-banner-content p { margin: 0; color: #8d6e0a; font-size: 14px; }
        .ak-referral-link-box { background: #f8fafb; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        .ak-referral-link-box label { display: block; font-weight: 600; margin-bottom: 10px; color: #333; }
        .ak-link-input-group { display: flex; gap: 10px; }
        .ak-referral-link-input { flex: 1; padding: 12px 15px; border: 2px solid #e0e7ef; border-radius: 10px; font-size: 13px; color: #555; }
        .ak-copy-link-btn { padding: 12px 25px; background: #1e3a5f; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .ak-copy-link-btn:hover { background: #2d5a87; }
        .ak-copy-link-btn.ak-copied { background: #28a745; }
        .ak-referral-code-text { margin: 10px 0 0 0; font-size: 13px; color: #666; }
        .ak-referral-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .ak-stat-card { background: #fff; border: 1px solid #e0e7ef; border-radius: 12px; padding: 20px; text-align: center; }
        .ak-stat-highlight { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-color: #81c784; }
        .ak-stat-number { display: block; font-size: 28px; font-weight: 700; color: #1e3a5f; }
        .ak-stat-highlight .ak-stat-number { color: #2e7d32; }
        .ak-stat-label { font-size: 12px; color: #888; text-transform: uppercase; }
        .ak-referral-history { margin-bottom: 25px; }
        .ak-referral-history h4 { margin: 0 0 15px 0; color: #1e3a5f; }
        .ak-referral-table { width: 100%; border-collapse: collapse; }
        .ak-referral-table th, .ak-referral-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        .ak-referral-table th { color: #888; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .ak-status-success { color: #28a745; font-weight: 500; }
        .ak-status-pending { color: #ff9800; font-weight: 500; }
        .ak-share-buttons { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ak-share-buttons p { margin: 0; color: #666; font-size: 14px; }
        .ak-share-btn { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500; color: #fff; }
        .ak-whatsapp { background: #25D366; }
        .ak-twitter { background: #1DA1F2; }
        .ak-email { background: #666; }
        @media (max-width: 600px) { .ak-referral-stats { grid-template-columns: 1fr; } .ak-link-input-group { flex-direction: column; } }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get referral stats
     */
    public function get_referral_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        $user_id = get_current_user_id();
        
        wp_send_json_success(array(
            'clicks' => intval(get_user_meta($user_id, 'ak_referral_click_count', true)),
            'referrals' => intval(get_user_meta($user_id, 'ak_referral_count', true)),
            'earned' => intval(get_user_meta($user_id, 'ak_referral_count', true)) * intval(get_option('ak_referral_reward_credits', 10))
        ));
    }
}

// Initialize
new AK_Referral_System();
