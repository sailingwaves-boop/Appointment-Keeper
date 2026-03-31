<?php
/**
 * Signup Handler Class - Registration, verification, and login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Signup_Handler {
    
    public function __construct() {
        add_action('wp_ajax_nopriv_ak_signup', array($this, 'handle_signup'));
        add_action('wp_ajax_nopriv_ak_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_ak_verify_email', array($this, 'handle_verify_email'));
        add_action('wp_ajax_nopriv_ak_resend_verification', array($this, 'handle_resend_verification'));
        add_action('wp_ajax_ak_resend_verification', array($this, 'handle_resend_verification'));
        
        add_action('wp_footer', array($this, 'render_popup'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        add_shortcode('ak_signup_button', array($this, 'signup_button_shortcode'));
        add_shortcode('ak_login_button', array($this, 'login_button_shortcode'));
    }
    
    /**
     * Enqueue popup assets
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'ak-signup',
            AK_DASHBOARD_PLUGIN_URL . 'assets/signup.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-signup',
            AK_DASHBOARD_PLUGIN_URL . 'assets/signup.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
        
        // Get Nextend Google login URL if available
        $google_login_url = '';
        if (class_exists('NextendSocialLogin')) {
            $google_login_url = site_url('/?loginSocial=google');
        }
        
        wp_localize_script('ak-signup', 'akSignupData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_signup_nonce'),
            'dashboardUrl' => home_url('/my-dashboard'),
            'planSelectionUrl' => home_url('/choose-plan'),
            'googleLoginUrl' => $google_login_url
        ));
    }
    
    /**
     * Render the popup HTML in footer
     */
    public function render_popup() {
        if (is_user_logged_in()) {
            return; // Don't show popup to logged in users
        }
        ?>
        <div class="ak-signup-overlay">
            <div class="ak-signup-popup">
                <button class="ak-popup-close">&times;</button>
                
                <div class="ak-signup-header">
                    <h2>Get Started</h2>
                    <p>Create your free account</p>
                </div>
                
                <div class="ak-error-message"></div>
                <div class="ak-success-message"></div>
                
                <!-- Social Login -->
                <div class="ak-social-buttons">
                    <button type="button" class="ak-social-btn ak-google-btn">
                        <svg viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Continue with Google
                    </button>
                </div>
                
                <div class="ak-divider">
                    <div class="ak-divider-line"></div>
                    <span class="ak-divider-text">or</span>
                    <div class="ak-divider-line"></div>
                </div>
                
                <!-- Signup Form -->
                <div id="ak-signup-form-container">
                    <form id="ak-signup-form" class="ak-signup-form">
                        <div class="ak-form-group">
                            <input type="text" name="ak_name" placeholder="Your name" required>
                        </div>
                        <div class="ak-form-group">
                            <input type="email" name="ak_email" placeholder="Email address" required>
                        </div>
                        <div class="ak-form-group">
                            <input type="password" name="ak_password" placeholder="Create password" required>
                        </div>
                        <button type="submit" class="ak-submit-btn">Create Account</button>
                    </form>
                    
                    <div class="ak-signup-footer ak-form-toggle">
                        Already have an account? <a href="#" class="ak-toggle-form" data-mode="login">Sign in</a>
                    </div>
                </div>
                
                <!-- Login Form -->
                <div id="ak-login-form-container" style="display: none;">
                    <form id="ak-login-form" class="ak-signup-form">
                        <div class="ak-form-group">
                            <input type="email" name="ak_login_email" placeholder="Email address" required>
                        </div>
                        <div class="ak-form-group">
                            <input type="password" name="ak_login_password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="ak-submit-btn">Sign In</button>
                    </form>
                    
                    <div class="ak-signup-footer ak-form-toggle">
                        Don't have an account? <a href="#" class="ak-toggle-form" data-mode="signup">Sign up</a>
                    </div>
                </div>
                
                <!-- Verification Sent -->
                <div id="ak-verification-container" class="ak-verification-sent" style="display: none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                    <h3>Check Your Email</h3>
                    <p>We sent a verification link to<br><strong id="ak-verification-email"></strong></p>
                    <p>Click the link in the email to verify your account.</p>
                    <p><span class="ak-resend-link">Resend verification email</span></p>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle signup AJAX
     */
    public function handle_signup() {
        check_ajax_referer('ak_signup_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        // Validate
        if (empty($name) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'All fields are required.'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error(array('message' => 'Password must be at least 6 characters.'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'An account with this email already exists.'));
        }
        
        if (username_exists($email)) {
            wp_send_json_error(array('message' => 'An account with this email already exists.'));
        }
        
        // Create user (unverified)
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Update user info
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ));
        
        // Set as unverified
        update_user_meta($user_id, 'ak_email_verified', false);
        
        // Generate verification token
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'ak_verification_token', $token);
        update_user_meta($user_id, 'ak_verification_expires', time() + (24 * 60 * 60)); // 24 hours
        
        // Send verification email
        $this->send_verification_email($email, $name, $token);
        
        wp_send_json_success(array('message' => 'Account created! Check your email to verify.'));
    }
    
    /**
     * Send verification email
     */
    private function send_verification_email($email, $name, $token) {
        $verify_url = add_query_arg(array(
            'ak_verify' => $token
        ), home_url('/'));
        
        $subject = 'Verify your AppointmentKeeper account';
        
        $message = "Hi {$name},\n\n";
        $message .= "Welcome to AppointmentKeeper! Please click the link below to verify your email address:\n\n";
        $message .= $verify_url . "\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you didn't create this account, you can ignore this email.\n\n";
        $message .= "Best regards,\nThe AppointmentKeeper Team";
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Handle email verification
     */
    public function handle_verify_email() {
        check_ajax_referer('ak_signup_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Invalid verification link.'));
        }
        
        // Find user with this token
        $users = get_users(array(
            'meta_key' => 'ak_verification_token',
            'meta_value' => $token,
            'number' => 1
        ));
        
        if (empty($users)) {
            wp_send_json_error(array('message' => 'Invalid or expired verification link.'));
        }
        
        $user = $users[0];
        
        // Check expiry
        $expires = get_user_meta($user->ID, 'ak_verification_expires', true);
        if ($expires && time() > $expires) {
            wp_send_json_error(array('message' => 'Verification link has expired. Please request a new one.'));
        }
        
        // Mark as verified
        update_user_meta($user->ID, 'ak_email_verified', true);
        delete_user_meta($user->ID, 'ak_verification_token');
        delete_user_meta($user->ID, 'ak_verification_expires');
        
        // Log user in
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);
        
        wp_send_json_success(array(
            'message' => 'Email verified!',
            'redirect' => home_url('/choose-plan')
        ));
    }
    
    /**
     * Handle login AJAX
     */
    public function handle_login() {
        check_ajax_referer('ak_signup_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Please enter email and password.'));
        }
        
        // Attempt login
        $user = wp_authenticate($email, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Invalid email or password.'));
        }
        
        // Check if verified
        $verified = get_user_meta($user->ID, 'ak_email_verified', true);
        if ($verified === false || $verified === '0' || $verified === '') {
            // Check if this is an older user without verification (grandfather them in)
            $has_token = get_user_meta($user->ID, 'ak_verification_token', true);
            if ($has_token) {
                wp_send_json_error(array('message' => 'Please verify your email first. Check your inbox.'));
            }
        }
        
        // Log in
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);
        
        // Check if user has selected a plan
        $has_plan = get_user_meta($user->ID, 'ak_subscription_status', true);
        
        if (!$has_plan || $has_plan === 'none') {
            $redirect = home_url('/choose-plan');
        } else {
            $redirect = home_url('/my-dashboard');
        }
        
        wp_send_json_success(array(
            'message' => 'Login successful!',
            'redirect' => $redirect
        ));
    }
    
    /**
     * Resend verification email
     */
    public function handle_resend_verification() {
        check_ajax_referer('ak_signup_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Email not found.'));
        }
        
        // Generate new token
        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'ak_verification_token', $token);
        update_user_meta($user->ID, 'ak_verification_expires', time() + (24 * 60 * 60));
        
        // Send email
        $this->send_verification_email($email, $user->display_name, $token);
        
        wp_send_json_success(array('message' => 'Verification email sent!'));
    }
    
    /**
     * Signup button shortcode
     */
    public function signup_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Get Started Free',
            'class' => ''
        ), $atts);
        
        if (is_user_logged_in()) {
            return '<a href="' . esc_url(home_url('/my-dashboard')) . '" class="ak-signup-trigger ' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
        }
        
        return '<button type="button" class="ak-signup-trigger ' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</button>';
    }
    
    /**
     * Login button shortcode
     */
    public function login_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Sign In',
            'class' => ''
        ), $atts);
        
        if (is_user_logged_in()) {
            return '<a href="' . esc_url(home_url('/my-dashboard')) . '" class="ak-login-trigger ' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
        }
        
        return '<button type="button" class="ak-login-trigger ' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</button>';
    }
}
