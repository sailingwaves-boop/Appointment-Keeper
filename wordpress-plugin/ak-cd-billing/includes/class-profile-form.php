<?php
/**
 * Profile Completion Form Handler
 * Includes: Terms & GDPR consent, Business Name, Referral tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Profile_Form {
    
    public function __construct() {
        add_action('wp_ajax_ak_complete_profile', array($this, 'handle_profile_submission'));
        add_action('wp_ajax_nopriv_ak_complete_profile', array($this, 'handle_profile_submission'));
        add_action('init', array($this, 'create_profile_page'));
        add_shortcode('ak_profile_form', array($this, 'render_profile_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function create_profile_page() {
        if (get_option('ak_profile_page_created') === 'yes') {
            return;
        }
        
        $page_exists = get_page_by_path('complete-profile');
        
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Complete Your Profile',
                'post_name' => 'complete-profile',
                'post_content' => '[ak_profile_form]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        }
        
        update_option('ak_profile_page_created', 'yes');
    }
    
    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }
        
        wp_enqueue_style(
            'ak-profile-form',
            AK_DASHBOARD_PLUGIN_URL . 'assets/profile-form.css',
            array(),
            AK_DASHBOARD_VERSION . '.' . time()
        );
        
        wp_enqueue_script(
            'ak-profile-form',
            AK_DASHBOARD_PLUGIN_URL . 'assets/profile-form.js',
            array('jquery'),
            AK_DASHBOARD_VERSION . '.' . time(),
            true
        );
        
        wp_localize_script('ak-profile-form', 'akProfileData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_profile_nonce'),
            'planUrl' => home_url('/choose-plan')
        ));
    }
    
    public function render_profile_form() {
        if (!is_user_logged_in()) {
            return '<div class="ak-notice">Please <a href="' . home_url('/?ak_login=1') . '">sign in</a> first.</div>';
        }
        
        $user = wp_get_current_user();
        
        // Check if profile already complete
        $profile_complete = get_user_meta($user->ID, 'ak_profile_complete', true);
        if ($profile_complete === 'yes') {
            wp_redirect(home_url('/choose-plan'));
            exit;
        }
        
        // Get legal page URLs
        $terms_page_id = get_option('ak_terms_page_id');
        $privacy_page_id = get_option('ak_privacy_page_id');
        $terms_url = $terms_page_id ? get_permalink($terms_page_id) : '#';
        $privacy_url = $privacy_page_id ? get_permalink($privacy_page_id) : '#';
        
        // Check for referral code
        $referrer_id = isset($_COOKIE['ak_referrer']) ? intval($_COOKIE['ak_referrer']) : 0;
        
        ob_start();
        ?>
        <div class="ak-profile-page">
            <div class="ak-profile-container">
                <div class="ak-profile-header">
                    <div class="ak-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <h1>Complete Your Profile</h1>
                    <p>Just a few more details to personalise your experience</p>
                    <div class="ak-progress-bar">
                        <div class="ak-progress-step completed">
                            <span class="ak-step-dot"></span>
                            <span class="ak-step-label">Sign Up</span>
                        </div>
                        <div class="ak-progress-line completed"></div>
                        <div class="ak-progress-step completed">
                            <span class="ak-step-dot"></span>
                            <span class="ak-step-label">Verify Email</span>
                        </div>
                        <div class="ak-progress-line"></div>
                        <div class="ak-progress-step active">
                            <span class="ak-step-dot"></span>
                            <span class="ak-step-label">Profile</span>
                        </div>
                        <div class="ak-progress-line"></div>
                        <div class="ak-progress-step">
                            <span class="ak-step-dot"></span>
                            <span class="ak-step-label">Choose Plan</span>
                        </div>
                    </div>
                </div>
                
                <form id="ak-profile-form" class="ak-profile-form">
                    <input type="hidden" name="referrer_id" value="<?php echo esc_attr($referrer_id); ?>">
                    
                    <!-- Personal Information -->
                    <div class="ak-form-section">
                        <div class="ak-section-header">
                            <span class="ak-section-icon ak-icon-user"></span>
                            <h2>Personal Information</h2>
                        </div>
                        
                        <div class="ak-form-row">
                            <div class="ak-form-group">
                                <label for="ak_first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="ak_first_name" name="first_name" required 
                                       value="<?php echo esc_attr($user->first_name); ?>"
                                       placeholder="John">
                            </div>
                            <div class="ak-form-group">
                                <label for="ak_last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="ak_last_name" name="last_name" required
                                       value="<?php echo esc_attr($user->last_name); ?>"
                                       placeholder="Smith">
                            </div>
                        </div>
                        
                        <div class="ak-form-group">
                            <label for="ak_business_name">Business Name <span class="optional">(optional)</span></label>
                            <input type="text" id="ak_business_name" name="business_name"
                                   value="<?php echo esc_attr(get_user_meta($user->ID, 'ak_business_name', true)); ?>"
                                   placeholder="Your business or company name">
                            <p class="ak-field-note">If you're using this for your business, enter your company name</p>
                        </div>
                        
                        <div class="ak-form-row">
                            <div class="ak-form-group">
                                <label for="ak_email">Email <span class="required">*</span></label>
                                <input type="email" id="ak_email" name="email" required readonly
                                       value="<?php echo esc_attr($user->user_email); ?>">
                                <span class="ak-verified-badge">Verified</span>
                            </div>
                            <div class="ak-form-group ak-phone-group">
                                <label for="ak_phone">Phone Number <span class="required">*</span></label>
                                <div class="ak-phone-input">
                                    <select id="ak_country_code" name="country_code" class="ak-country-select">
                                        <option value="+44" selected>+44 UK</option>
                                        <option value="+1">+1 USA</option>
                                        <option value="+353">+353 IE</option>
                                        <option value="+61">+61 AU</option>
                                        <option value="+64">+64 NZ</option>
                                        <option value="+49">+49 DE</option>
                                        <option value="+33">+33 FR</option>
                                        <option value="+34">+34 ES</option>
                                        <option value="+39">+39 IT</option>
                                        <option value="+31">+31 NL</option>
                                        <option value="+32">+32 BE</option>
                                        <option value="+41">+41 CH</option>
                                        <option value="+43">+43 AT</option>
                                        <option value="+46">+46 SE</option>
                                        <option value="+47">+47 NO</option>
                                        <option value="+45">+45 DK</option>
                                        <option value="+48">+48 PL</option>
                                        <option value="+351">+351 PT</option>
                                        <option value="+91">+91 IN</option>
                                        <option value="+86">+86 CN</option>
                                        <option value="+81">+81 JP</option>
                                        <option value="+82">+82 KR</option>
                                        <option value="+65">+65 SG</option>
                                        <option value="+60">+60 MY</option>
                                        <option value="+27">+27 ZA</option>
                                        <option value="+55">+55 BR</option>
                                        <option value="+52">+52 MX</option>
                                    </select>
                                    <input type="tel" id="ak_phone" name="phone" required placeholder="7700 900123">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preferences -->
                    <div class="ak-form-section">
                        <div class="ak-section-header">
                            <span class="ak-section-icon ak-icon-settings"></span>
                            <h2>Preferences</h2>
                        </div>
                        
                        <div class="ak-form-row">
                            <div class="ak-form-group">
                                <label for="ak_contact_method">Preferred Contact Method <span class="ak-select-hint">Select an option</span></label>
                                <select id="ak_contact_method" name="contact_method" class="ak-select">
                                    <option value="" disabled selected>Select an option</option>
                                    <option value="sms">SMS</option>
                                    <option value="phone">Phone Call</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div class="ak-form-group">
                                <label for="ak_hear_about">How did you hear about us?</label>
                                <select id="ak_hear_about" name="hear_about" class="ak-select">
                                    <option value="">Select an option</option>
                                    <option value="google">Google Search</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="twitter">Twitter/X</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="friend">Friend or Colleague</option>
                                    <option value="advertisement">Advertisement</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="ak-form-group ak-specify-group" style="display:none;">
                            <label for="ak_specify">Please specify</label>
                            <input type="text" id="ak_specify" name="hear_about_specify" placeholder="Tell us more...">
                        </div>
                    </div>
                    
                    <!-- Invite Friends -->
                    <div class="ak-form-section ak-referral-section">
                        <div class="ak-section-header">
                            <span class="ak-section-icon ak-icon-gift"></span>
                            <h2>Invite Friends & Earn Rewards</h2>
                        </div>
                        
                        <div class="ak-referral-bonus-card">
                            <div class="ak-bonus-content">
                                <span class="ak-bonus-icon">🎁</span>
                                <div class="ak-bonus-text">
                                    <strong>Get a FREE month</strong> when a friend signs up!
                                    <span>They'll get bonus credits too</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ak-checkbox-group">
                            <label class="ak-checkbox-label ak-invite-toggle">
                                <input type="checkbox" id="ak_want_invite" name="want_invite">
                                <span class="ak-checkmark"></span>
                                <span>I want to invite friends now</span>
                            </label>
                        </div>
                        
                        <div class="ak-invite-section" style="display:none;">
                            <div class="ak-form-group">
                                <label for="ak_invite_count">How many friends do you want to invite?</label>
                                <select id="ak_invite_count" name="invite_count" class="ak-select ak-small-select">
                                    <option value="1">1 friend</option>
                                    <option value="2">2 friends</option>
                                    <option value="3">3 friends</option>
                                </select>
                            </div>
                            
                            <div class="ak-invite-fields">
                                <div class="ak-invite-person" data-person="1">
                                    <h4>Friend 1</h4>
                                    <div class="ak-form-row">
                                        <div class="ak-form-group">
                                            <label>Name</label>
                                            <input type="text" name="invite_name_1" placeholder="Friend's name">
                                        </div>
                                        <div class="ak-form-group ak-phone-group">
                                            <label>Phone</label>
                                            <div class="ak-phone-input">
                                                <select name="invite_country_1" class="ak-country-select">
                                                    <option value="+44" selected>+44</option>
                                                    <option value="+1">+1</option>
                                                    <option value="+353">+353</option>
                                                    <option value="+61">+61</option>
                                                </select>
                                                <input type="tel" name="invite_phone_1" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ak-invite-person" data-person="2" style="display:none;">
                                    <h4>Friend 2</h4>
                                    <div class="ak-form-row">
                                        <div class="ak-form-group">
                                            <label>Name</label>
                                            <input type="text" name="invite_name_2" placeholder="Friend's name">
                                        </div>
                                        <div class="ak-form-group ak-phone-group">
                                            <label>Phone</label>
                                            <div class="ak-phone-input">
                                                <select name="invite_country_2" class="ak-country-select">
                                                    <option value="+44" selected>+44</option>
                                                    <option value="+1">+1</option>
                                                    <option value="+353">+353</option>
                                                    <option value="+61">+61</option>
                                                </select>
                                                <input type="tel" name="invite_phone_2" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ak-invite-person" data-person="3" style="display:none;">
                                    <h4>Friend 3</h4>
                                    <div class="ak-form-row">
                                        <div class="ak-form-group">
                                            <label>Name</label>
                                            <input type="text" name="invite_name_3" placeholder="Friend's name">
                                        </div>
                                        <div class="ak-form-group ak-phone-group">
                                            <label>Phone</label>
                                            <div class="ak-phone-input">
                                                <select name="invite_country_3" class="ak-country-select">
                                                    <option value="+44" selected>+44</option>
                                                    <option value="+1">+1</option>
                                                    <option value="+353">+353</option>
                                                    <option value="+61">+61</option>
                                                </select>
                                                <input type="tel" name="invite_phone_3" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Legal Consent -->
                    <div class="ak-form-section ak-consent-section">
                        <div class="ak-section-header">
                            <span class="ak-section-icon ak-icon-shield"></span>
                            <h2>Terms & Privacy</h2>
                        </div>
                        
                        <div class="ak-consent-boxes">
                            <div class="ak-checkbox-group">
                                <label class="ak-checkbox-label">
                                    <input type="checkbox" name="consent_terms" id="ak_consent_terms" required>
                                    <span class="ak-checkmark"></span>
                                    <span>I agree to the <a href="<?php echo esc_url($terms_url); ?>" target="_blank">Terms & Conditions</a> <span class="required">*</span></span>
                                </label>
                            </div>
                            
                            <div class="ak-checkbox-group">
                                <label class="ak-checkbox-label">
                                    <input type="checkbox" name="consent_privacy" id="ak_consent_privacy" required>
                                    <span class="ak-checkmark"></span>
                                    <span>I consent to my data being processed as described in the <a href="<?php echo esc_url($privacy_url); ?>" target="_blank">Privacy Policy</a> (GDPR) <span class="required">*</span></span>
                                </label>
                            </div>
                            
                            <div class="ak-checkbox-group">
                                <label class="ak-checkbox-label">
                                    <input type="checkbox" name="consent_reminders" id="ak_consent_reminders">
                                    <span class="ak-checkmark"></span>
                                    <span>I agree to receive automated appointment reminders via my preferred contact method</span>
                                </label>
                            </div>
                            
                            <div class="ak-checkbox-group">
                                <label class="ak-checkbox-label">
                                    <input type="checkbox" name="consent_marketing" id="ak_consent_marketing">
                                    <span class="ak-checkmark"></span>
                                    <span>I'd like to receive tips, product updates, and special offers (optional)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <div class="ak-form-actions">
                        <div class="ak-error-message"></div>
                        <button type="submit" class="ak-submit-btn ak-green-btn">
                            <span class="ak-btn-text">Continue to Choose Your Plan</span>
                            <span class="ak-btn-arrow">→</span>
                        </button>
                        <p class="ak-form-note">You can update these settings anytime in your dashboard</p>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function handle_profile_submission() {
        check_ajax_referer('ak_profile_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in first.'));
        }
        
        $user_id = get_current_user_id();
        
        // Get form data
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $business_name = sanitize_text_field($_POST['business_name']);
        $country_code = sanitize_text_field($_POST['country_code']);
        $phone = sanitize_text_field($_POST['phone']);
        $contact_method = sanitize_text_field($_POST['contact_method']);
        $hear_about = sanitize_text_field($_POST['hear_about']);
        $hear_about_specify = sanitize_text_field($_POST['hear_about_specify']);
        $referrer_id = intval($_POST['referrer_id']);
        
        // Consent checkboxes
        $consent_terms = isset($_POST['consent_terms']) && $_POST['consent_terms'] === 'on';
        $consent_privacy = isset($_POST['consent_privacy']) && $_POST['consent_privacy'] === 'on';
        $consent_reminders = isset($_POST['consent_reminders']) && $_POST['consent_reminders'] === 'on';
        $consent_marketing = isset($_POST['consent_marketing']) && $_POST['consent_marketing'] === 'on';
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => 'First and last name are required.'));
        }
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => 'Phone number is required.'));
        }
        
        if (!$consent_terms) {
            wp_send_json_error(array('message' => 'You must agree to the Terms & Conditions.'));
        }
        
        if (!$consent_privacy) {
            wp_send_json_error(array('message' => 'You must consent to our Privacy Policy (GDPR requirement).'));
        }
        
        // Update user
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Save meta
        update_user_meta($user_id, 'ak_business_name', $business_name);
        update_user_meta($user_id, 'ak_phone', $country_code . $phone);
        update_user_meta($user_id, 'ak_country_code', $country_code);
        update_user_meta($user_id, 'ak_contact_method', $contact_method);
        update_user_meta($user_id, 'ak_hear_about', $hear_about);
        update_user_meta($user_id, 'ak_hear_about_specify', $hear_about_specify);
        
        // Store consent records (important for GDPR)
        update_user_meta($user_id, 'ak_consent_terms', $consent_terms ? 'yes' : 'no');
        update_user_meta($user_id, 'ak_consent_terms_date', current_time('mysql'));
        update_user_meta($user_id, 'ak_consent_privacy', $consent_privacy ? 'yes' : 'no');
        update_user_meta($user_id, 'ak_consent_privacy_date', current_time('mysql'));
        update_user_meta($user_id, 'ak_consent_reminders', $consent_reminders ? 'yes' : 'no');
        update_user_meta($user_id, 'ak_consent_marketing', $consent_marketing ? 'yes' : 'no');
        
        update_user_meta($user_id, 'ak_profile_complete', 'yes');
        update_user_meta($user_id, 'ak_profile_completed_date', current_time('mysql'));
        
        // Handle referral
        if ($referrer_id > 0) {
            $this->process_referral($user_id, $referrer_id);
        }
        
        // Handle invites
        $want_invite = isset($_POST['want_invite']) && $_POST['want_invite'] === 'on';
        if ($want_invite) {
            $this->process_invites($user_id);
        }
        
        // Generate referral code for this user
        $this->generate_referral_code($user_id);
        
        wp_send_json_success(array(
            'message' => 'Profile complete!',
            'redirect' => home_url('/choose-plan')
        ));
    }
    
    /**
     * Process a referral reward
     */
    private function process_referral($new_user_id, $referrer_id) {
        // Verify referrer exists
        $referrer = get_user_by('ID', $referrer_id);
        if (!$referrer) {
            return;
        }
        
        // Get reward amount
        $reward_credits = get_option('ak_referral_reward_credits', 10);
        
        // Store referral relationship
        update_user_meta($new_user_id, 'ak_referred_by', $referrer_id);
        
        // Add to referrer's referral count
        $referral_count = intval(get_user_meta($referrer_id, 'ak_referral_count', true));
        update_user_meta($referrer_id, 'ak_referral_count', $referral_count + 1);
        
        // Add referral to list
        $referrals = get_user_meta($referrer_id, 'ak_referrals', true);
        if (!is_array($referrals)) {
            $referrals = array();
        }
        $referrals[] = array(
            'user_id' => $new_user_id,
            'date' => current_time('mysql'),
            'status' => 'pending' // Will become 'rewarded' when they choose a plan
        );
        update_user_meta($referrer_id, 'ak_referrals', $referrals);
        
        // Store pending reward (will be credited when new user subscribes)
        update_user_meta($new_user_id, 'ak_pending_referral_reward', $reward_credits);
        update_user_meta($referrer_id, 'ak_pending_referrer_reward', $reward_credits);
    }
    
    /**
     * Process friend invites
     */
    private function process_invites($user_id) {
        $invite_count = intval($_POST['invite_count']);
        $invites = array();
        
        for ($i = 1; $i <= $invite_count; $i++) {
            $invite_name = sanitize_text_field($_POST['invite_name_' . $i]);
            $invite_country = sanitize_text_field($_POST['invite_country_' . $i]);
            $invite_phone = sanitize_text_field($_POST['invite_phone_' . $i]);
            
            if (!empty($invite_phone)) {
                $invites[] = array(
                    'name' => $invite_name,
                    'phone' => $invite_country . $invite_phone,
                    'status' => 'pending',
                    'created' => current_time('mysql')
                );
            }
        }
        
        if (!empty($invites)) {
            update_user_meta($user_id, 'ak_pending_invites', $invites);
            
            // Queue SMS invites (will be sent by the communication handler)
            do_action('ak_send_invite_sms', $user_id, $invites);
        }
    }
    
    /**
     * Generate unique referral code
     */
    private function generate_referral_code($user_id) {
        $user = get_user_by('ID', $user_id);
        $first_name = strtoupper(substr($user->first_name ?: 'USER', 0, 4));
        $code = $first_name . $user_id . strtoupper(wp_generate_password(4, false));
        
        update_user_meta($user_id, 'ak_referral_code', $code);
        
        return $code;
    }
}

// Initialize
new AK_Profile_Form();
