<?php
/**
 * Team Admin Panel
 * For Premium (up to 3 members) and Enterprise (unlimited) plans
 * Team members share credits from owner's pool
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Team_Admin {
    
    // Team limits by plan
    private $team_limits = array(
        'basic' => 0,
        'standard' => 0,
        'premium' => 3,
        'enterprise' => 999 // Unlimited
    );
    
    public function __construct() {
        add_action('init', array($this, 'create_team_page'));
        add_shortcode('ak_team_admin', array($this, 'render_team_admin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ak_invite_team_member', array($this, 'invite_team_member'));
        add_action('wp_ajax_ak_remove_team_member', array($this, 'remove_team_member'));
        add_action('wp_ajax_ak_update_member_allocation', array($this, 'update_member_allocation'));
        add_action('wp_ajax_nopriv_ak_accept_team_invite', array($this, 'accept_team_invite'));
        add_action('wp_ajax_ak_accept_team_invite', array($this, 'accept_team_invite'));
        
        // Add team menu to dashboard
        add_filter('ak_dashboard_tabs', array($this, 'add_team_tab'));
    }
    
    public function create_team_page() {
        if (get_option('ak_team_page_created') === 'yes') {
            return;
        }
        
        $page_exists = get_page_by_path('team');
        
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Team Management',
                'post_name' => 'team',
                'post_content' => '[ak_team_admin]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        }
        
        update_option('ak_team_page_created', 'yes');
    }
    
    public function enqueue_assets() {
        if (!is_page('team') && !has_shortcode(get_post()->post_content ?? '', 'ak_team_admin')) {
            return;
        }
        
        wp_enqueue_style(
            'ak-team-admin',
            AK_DASHBOARD_PLUGIN_URL . 'assets/team-admin.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-team-admin',
            AK_DASHBOARD_PLUGIN_URL . 'assets/team-admin.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('ak-team-admin', 'akTeamData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_team_nonce')
        ));
    }
    
    /**
     * Get user's team limit based on plan
     */
    private function get_team_limit($user_id) {
        $plan = get_user_meta($user_id, 'ak_subscription_plan', true);
        return isset($this->team_limits[$plan]) ? $this->team_limits[$plan] : 0;
    }
    
    /**
     * Check if user can have team members
     */
    private function can_have_team($user_id) {
        return $this->get_team_limit($user_id) > 0;
    }
    
    /**
     * Get team members for an owner
     */
    private function get_team_members($owner_id) {
        $members = get_user_meta($owner_id, 'ak_team_members', true);
        return is_array($members) ? $members : array();
    }
    
    /**
     * Get owner's current credits
     */
    private function get_owner_credits($owner_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $owner_id
        ));
        
        return array(
            'sms' => $credits ? $credits->sms_credits : 0,
            'call' => $credits ? $credits->call_credits : 0,
            'email' => $credits ? $credits->email_credits : 0
        );
    }
    
    public function render_team_admin() {
        if (!is_user_logged_in()) {
            return '<div class="ak-notice">Please <a href="' . home_url('/?ak_login=1') . '">sign in</a> to manage your team.</div>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Check if user is a team member (not owner)
        $team_owner_id = get_user_meta($user_id, 'ak_team_owner_id', true);
        if ($team_owner_id) {
            return $this->render_member_view($user_id, $team_owner_id);
        }
        
        // Check if user can have team
        if (!$this->can_have_team($user_id)) {
            return $this->render_upgrade_prompt();
        }
        
        $plan = get_user_meta($user_id, 'ak_subscription_plan', true);
        $team_limit = $this->get_team_limit($user_id);
        $team_members = $this->get_team_members($user_id);
        $member_count = count($team_members);
        $owner_credits = $this->get_owner_credits($user_id);
        
        // Calculate allocated credits
        $allocated = array('sms' => 0, 'call' => 0, 'email' => 0);
        foreach ($team_members as $member) {
            $allocated['sms'] += intval($member['allocated_sms'] ?? 0);
            $allocated['call'] += intval($member['allocated_call'] ?? 0);
            $allocated['email'] += intval($member['allocated_email'] ?? 0);
        }
        
        ob_start();
        ?>
        <div class="ak-team-page">
            <div class="ak-team-header">
                <h1>Team Management</h1>
                <p>Invite team members and allocate credits from your pool</p>
                
                <div class="ak-team-stats">
                    <div class="ak-stat-box">
                        <span class="ak-stat-label">Team Members</span>
                        <span class="ak-stat-value"><?php echo $member_count; ?> / <?php echo $team_limit === 999 ? '∞' : $team_limit; ?></span>
                    </div>
                    <div class="ak-stat-box">
                        <span class="ak-stat-label">Your Credits</span>
                        <span class="ak-stat-value"><?php echo $owner_credits['sms']; ?> SMS</span>
                    </div>
                    <div class="ak-stat-box">
                        <span class="ak-stat-label">Allocated</span>
                        <span class="ak-stat-value"><?php echo $allocated['sms']; ?> SMS</span>
                    </div>
                </div>
            </div>
            
            <!-- Invite Form -->
            <?php if ($member_count < $team_limit): ?>
            <div class="ak-invite-section">
                <h2>Invite Team Member</h2>
                <form id="ak-invite-form" class="ak-invite-form">
                    <div class="ak-form-row">
                        <div class="ak-form-group">
                            <label>Email Address</label>
                            <input type="email" id="ak-invite-email" placeholder="colleague@example.com" required>
                        </div>
                        <div class="ak-form-group">
                            <label>Name</label>
                            <input type="text" id="ak-invite-name" placeholder="John Smith" required>
                        </div>
                    </div>
                    <div class="ak-form-row">
                        <div class="ak-form-group">
                            <label>SMS Credits to Allocate</label>
                            <select id="ak-invite-sms">
                                <option value="0">0</option>
                                <option value="20" selected>20</option>
                                <option value="40">40</option>
                                <option value="60">60</option>
                                <option value="80">80</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="ak-form-group">
                            <label>Call Credits</label>
                            <select id="ak-invite-call">
                                <option value="0">0</option>
                                <option value="5" selected>5</option>
                                <option value="10">10</option>
                                <option value="20">20</option>
                            </select>
                        </div>
                    </div>
                    <div class="ak-invite-note">
                        Credits will be deducted from your pool and allocated to this member.
                    </div>
                    <button type="submit" class="ak-invite-btn">Send Invitation</button>
                    <div id="ak-invite-result"></div>
                </form>
            </div>
            <?php else: ?>
            <div class="ak-team-full">
                <p>You've reached your team limit (<?php echo $team_limit; ?> members).</p>
                <?php if ($plan !== 'enterprise'): ?>
                <a href="<?php echo home_url('/choose-plan'); ?>" class="ak-upgrade-link">Upgrade to Enterprise for unlimited team members</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Team Members List -->
            <div class="ak-team-list-section">
                <h2>Team Members</h2>
                
                <?php if (empty($team_members)): ?>
                <div class="ak-no-members">
                    <p>No team members yet. Invite someone above!</p>
                </div>
                <?php else: ?>
                <div class="ak-team-list">
                    <?php foreach ($team_members as $index => $member): 
                        $member_user = get_user_by('email', $member['email']);
                        $status_class = $member['status'] === 'active' ? 'ak-status-active' : 'ak-status-pending';
                    ?>
                    <div class="ak-member-card" data-email="<?php echo esc_attr($member['email']); ?>">
                        <div class="ak-member-info">
                            <div class="ak-member-avatar">
                                <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                            </div>
                            <div class="ak-member-details">
                                <span class="ak-member-name"><?php echo esc_html($member['name']); ?></span>
                                <span class="ak-member-email"><?php echo esc_html($member['email']); ?></span>
                                <span class="<?php echo $status_class; ?>"><?php echo ucfirst($member['status']); ?></span>
                            </div>
                        </div>
                        <div class="ak-member-credits">
                            <div class="ak-credit-allocation">
                                <label>SMS:</label>
                                <select class="ak-allocation-select" data-type="sms">
                                    <?php for ($i = 0; $i <= 100; $i += 20): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($member['allocated_sms'] ?? 0, $i); ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="ak-credit-allocation">
                                <label>Calls:</label>
                                <select class="ak-allocation-select" data-type="call">
                                    <?php for ($i = 0; $i <= 20; $i += 5): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($member['allocated_call'] ?? 0, $i); ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="ak-member-actions">
                            <button class="ak-remove-member" data-email="<?php echo esc_attr($member['email']); ?>">Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="ak-team-footer">
                <a href="<?php echo home_url('/my-dashboard'); ?>" class="ak-back-link">← Back to Dashboard</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render view for team members (non-owners)
     */
    private function render_member_view($user_id, $owner_id) {
        $owner = get_user_by('ID', $owner_id);
        $members = $this->get_team_members($owner_id);
        $user = wp_get_current_user();
        
        // Find this user's allocation
        $my_allocation = null;
        foreach ($members as $member) {
            if ($member['email'] === $user->user_email) {
                $my_allocation = $member;
                break;
            }
        }
        
        ob_start();
        ?>
        <div class="ak-team-page">
            <div class="ak-team-header">
                <h1>Your Team</h1>
                <p>You're a member of <?php echo esc_html($owner->display_name); ?>'s team</p>
            </div>
            
            <div class="ak-member-allocation-view">
                <h2>Your Allocated Credits</h2>
                <div class="ak-allocation-cards">
                    <div class="ak-alloc-card">
                        <span class="ak-alloc-icon">💬</span>
                        <span class="ak-alloc-value"><?php echo intval($my_allocation['allocated_sms'] ?? 0); ?></span>
                        <span class="ak-alloc-label">SMS Credits</span>
                    </div>
                    <div class="ak-alloc-card">
                        <span class="ak-alloc-icon">📞</span>
                        <span class="ak-alloc-value"><?php echo intval($my_allocation['allocated_call'] ?? 0); ?></span>
                        <span class="ak-alloc-label">Call Credits</span>
                    </div>
                    <div class="ak-alloc-card">
                        <span class="ak-alloc-icon">✉️</span>
                        <span class="ak-alloc-value"><?php echo intval($my_allocation['allocated_email'] ?? 0); ?></span>
                        <span class="ak-alloc-label">Email Credits</span>
                    </div>
                </div>
                <p class="ak-allocation-note">Credits are allocated by your team owner. Contact <?php echo esc_html($owner->display_name); ?> to request more.</p>
            </div>
            
            <div class="ak-team-footer">
                <a href="<?php echo home_url('/my-dashboard'); ?>" class="ak-back-link">← Back to Dashboard</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render upgrade prompt for non-team plans
     */
    private function render_upgrade_prompt() {
        ob_start();
        ?>
        <div class="ak-team-page">
            <div class="ak-upgrade-prompt">
                <div class="ak-upgrade-icon">👥</div>
                <h2>Team Feature</h2>
                <p>Invite team members and share your credits with them.</p>
                <div class="ak-plan-comparison">
                    <div class="ak-plan-option">
                        <h3>Premium</h3>
                        <p>Up to 3 team members</p>
                        <span class="ak-plan-price">£49.99/mo</span>
                    </div>
                    <div class="ak-plan-option ak-recommended">
                        <span class="ak-rec-badge">Recommended</span>
                        <h3>Enterprise</h3>
                        <p>Unlimited team members + Helper included</p>
                        <span class="ak-plan-price">£149.99/mo</span>
                    </div>
                </div>
                <a href="<?php echo home_url('/choose-plan'); ?>" class="ak-upgrade-btn">Upgrade Your Plan</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Invite team member
     */
    public function invite_team_member() {
        check_ajax_referer('ak_team_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $owner_id = get_current_user_id();
        
        if (!$this->can_have_team($owner_id)) {
            wp_send_json_error(array('message' => 'Your plan does not include team features'));
        }
        
        $email = sanitize_email($_POST['email']);
        $name = sanitize_text_field($_POST['name']);
        $sms_allocation = intval($_POST['sms']);
        $call_allocation = intval($_POST['call']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        // Check team limit
        $members = $this->get_team_members($owner_id);
        $limit = $this->get_team_limit($owner_id);
        
        if (count($members) >= $limit) {
            wp_send_json_error(array('message' => 'Team limit reached'));
        }
        
        // Check if already a member
        foreach ($members as $member) {
            if ($member['email'] === $email) {
                wp_send_json_error(array('message' => 'This person is already on your team'));
            }
        }
        
        // Check owner has enough credits
        $owner_credits = $this->get_owner_credits($owner_id);
        if ($owner_credits['sms'] < $sms_allocation || $owner_credits['call'] < $call_allocation) {
            wp_send_json_error(array('message' => 'Insufficient credits to allocate'));
        }
        
        // Deduct from owner's credits
        $this->deduct_owner_credits($owner_id, $sms_allocation, $call_allocation, 0);
        
        // Generate invite token
        $invite_token = wp_generate_password(32, false);
        
        // Add to team members
        $members[] = array(
            'email' => $email,
            'name' => $name,
            'allocated_sms' => $sms_allocation,
            'allocated_call' => $call_allocation,
            'allocated_email' => 0,
            'status' => 'pending',
            'invite_token' => $invite_token,
            'invited_at' => current_time('mysql')
        );
        
        update_user_meta($owner_id, 'ak_team_members', $members);
        
        // Send invite email
        $this->send_invite_email($email, $name, $invite_token, $owner_id);
        
        wp_send_json_success(array('message' => 'Invitation sent!'));
    }
    
    /**
     * Deduct credits from owner's pool
     */
    private function deduct_owner_credits($owner_id, $sms, $call, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET 
             sms_credits = sms_credits - %d,
             call_credits = call_credits - %d,
             email_credits = email_credits - %d
             WHERE user_id = %d",
            $sms, $call, $email, $owner_id
        ));
    }
    
    /**
     * Send team invitation email
     */
    private function send_invite_email($email, $name, $token, $owner_id) {
        $owner = get_user_by('ID', $owner_id);
        $accept_url = add_query_arg(array(
            'ak_team_invite' => $token,
            'owner' => $owner_id
        ), home_url('/team'));
        
        $subject = $owner->display_name . ' invited you to their AppointmentKeeper team';
        
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
                                <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a87 100%);padding:35px 30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:24px;">Team Invitation</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px 35px;">
                                    <h2 style="margin:0 0 15px 0;color:#1e3a5f;">Hi ' . esc_html($name) . ',</h2>
                                    <p style="margin:0 0 20px 0;color:#555;font-size:15px;line-height:1.6;">
                                        <strong>' . esc_html($owner->display_name) . '</strong> has invited you to join their team on AppointmentKeeper.
                                    </p>
                                    <p style="margin:0 0 25px 0;color:#555;font-size:15px;">
                                        As a team member, you\'ll have access to allocated credits for sending appointment reminders.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <a href="' . esc_url($accept_url) . '" style="display:inline-block;padding:16px 40px;background:linear-gradient(135deg,#28a745 0%,#218838 100%);color:#fff;text-decoration:none;border-radius:50px;font-size:16px;font-weight:600;">
                                                    Accept Invitation
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
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * AJAX: Remove team member
     */
    public function remove_team_member() {
        check_ajax_referer('ak_team_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $owner_id = get_current_user_id();
        $email = sanitize_email($_POST['email']);
        
        $members = $this->get_team_members($owner_id);
        $removed_member = null;
        
        foreach ($members as $index => $member) {
            if ($member['email'] === $email) {
                $removed_member = $member;
                unset($members[$index]);
                break;
            }
        }
        
        if (!$removed_member) {
            wp_send_json_error(array('message' => 'Member not found'));
        }
        
        // Return allocated credits to owner
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET 
             sms_credits = sms_credits + %d,
             call_credits = call_credits + %d
             WHERE user_id = %d",
            $removed_member['allocated_sms'] ?? 0,
            $removed_member['allocated_call'] ?? 0,
            $owner_id
        ));
        
        // Remove team owner reference from member user
        $member_user = get_user_by('email', $email);
        if ($member_user) {
            delete_user_meta($member_user->ID, 'ak_team_owner_id');
        }
        
        update_user_meta($owner_id, 'ak_team_members', array_values($members));
        
        wp_send_json_success(array('message' => 'Member removed'));
    }
    
    /**
     * AJAX: Accept team invite
     */
    public function accept_team_invite() {
        $token = sanitize_text_field($_GET['ak_team_invite'] ?? $_POST['token'] ?? '');
        $owner_id = intval($_GET['owner'] ?? $_POST['owner'] ?? 0);
        
        if (!$token || !$owner_id) {
            return;
        }
        
        $members = $this->get_team_members($owner_id);
        $found = false;
        
        foreach ($members as &$member) {
            if (isset($member['invite_token']) && $member['invite_token'] === $token) {
                $member['status'] = 'active';
                unset($member['invite_token']);
                $found = true;
                
                // If user exists, link them
                $member_user = get_user_by('email', $member['email']);
                if ($member_user) {
                    update_user_meta($member_user->ID, 'ak_team_owner_id', $owner_id);
                }
                
                break;
            }
        }
        
        if ($found) {
            update_user_meta($owner_id, 'ak_team_members', $members);
        }
    }
    
    /**
     * AJAX: Update member allocation
     */
    public function update_member_allocation() {
        check_ajax_referer('ak_team_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in'));
        }
        
        $owner_id = get_current_user_id();
        $email = sanitize_email($_POST['email']);
        $type = sanitize_text_field($_POST['type']);
        $new_value = intval($_POST['value']);
        
        $members = $this->get_team_members($owner_id);
        $owner_credits = $this->get_owner_credits($owner_id);
        
        foreach ($members as &$member) {
            if ($member['email'] === $email) {
                $current = intval($member['allocated_' . $type] ?? 0);
                $diff = $new_value - $current;
                
                // Check if owner has enough
                if ($diff > 0 && $owner_credits[$type] < $diff) {
                    wp_send_json_error(array('message' => 'Insufficient credits'));
                }
                
                // Update allocation
                $member['allocated_' . $type] = $new_value;
                
                // Adjust owner's credits
                global $wpdb;
                $table = $wpdb->prefix . 'ak_customer_credits';
                $column = $type . '_credits';
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET $column = $column - %d WHERE user_id = %d",
                    $diff, $owner_id
                ));
                
                break;
            }
        }
        
        update_user_meta($owner_id, 'ak_team_members', $members);
        
        wp_send_json_success(array('message' => 'Allocation updated'));
    }
}

// Initialize
new AK_Team_Admin();
