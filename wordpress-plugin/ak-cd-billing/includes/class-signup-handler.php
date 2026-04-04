<?php
/**
 * Signup Handler Class - Registration, verification, and login
 * Includes: Email verification success page, resend with rate limiting
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
        add_shortcode('ak_verification_success', array($this, 'render_verification_success'));
        
        // Handle verification link clicks
        add_action('template_redirect', array($this, 'handle_verification_redirect'));
        
        // Create verification success page
        add_action('init', array($this, 'create_verification_page'));
    }
    
    /**
     * Create verification success page
     */
    public function create_verification_page() {
        if (get_option('ak_verification_page_created') === 'yes') {
            return;
        }
        
        $page_exists = get_page_by_path('email-verified');
        
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Email Verified',
                'post_name' => 'email-verified',
                'post_content' => '[ak_verification_success]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        }
        
        update_option('ak_verification_page_created', 'yes');
    }
    
    /**
     * Handle verification link redirect
     */
    public function handle_verification_redirect() {
        if (!isset($_GET['ak_verify'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['ak_verify']);
        
        // Find user with this token
        $users = get_users(array(
            'meta_key' => 'ak_verification_token',
            'meta_value' => $token,
            'number' => 1
        ));
        
        if (empty($users)) {
            wp_redirect(home_url('/email-verified?status=invalid'));
            exit;
        }
        
        $user = $users[0];
        
        // Check expiry
        $expires = get_user_meta($user->ID, 'ak_verification_expires', true);
        if ($expires && time() > $expires) {
            wp_redirect(home_url('/email-verified?status=expired&email=' . urlencode($user->user_email)));
            exit;
        }
        
        // Mark as verified
        update_user_meta($user->ID, 'ak_email_verified', true);
        update_user_meta($user->ID, 'ak_email_verified_date', current_time('mysql'));
        delete_user_meta($user->ID, 'ak_verification_token');
        delete_user_meta($user->ID, 'ak_verification_expires');
        
        // Log user in
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);
        
        // Redirect to success page
        wp_redirect(home_url('/email-verified?status=success'));
        exit;
    }
    
    /**
     * Render verification success page
     */
    public function render_verification_success() {
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'success';
        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        
        ob_start();
        ?>
        <div class="ak-verification-page">
            <div class="ak-verification-container">
                <?php if ($status === 'success'): ?>
                    <div class="ak-verification-success">
                        <div class="ak-success-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h1>Email Verified!</h1>
                        <p>Your email has been successfully verified. You're all set to continue setting up your account.</p>
                        <a href="<?php echo esc_url(home_url('/complete-profile')); ?>" class="ak-continue-btn">
                            Complete Your Profile
                            <span class="ak-arrow">→</span>
                        </a>
                    </div>
                    
                <?php elseif ($status === 'expired'): ?>
                    <div class="ak-verification-error">
                        <div class="ak-error-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h1>Link Expired</h1>
                        <p>Your verification link has expired. Don't worry, we can send you a new one.</p>
                        <button class="ak-resend-btn" data-email="<?php echo esc_attr($email); ?>">
                            Resend Verification Email
                        </button>
                        <div class="ak-resend-message"></div>
                    </div>
                    
                <?php elseif ($status === 'invalid'): ?>
                    <div class="ak-verification-error">
                        <div class="ak-error-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <h1>Invalid Link</h1>
                        <p>This verification link is invalid or has already been used.</p>
                        <p>If you've already verified your email, you can log in below.</p>
                        <a href="<?php echo esc_url(home_url('/?ak_login=1')); ?>" class="ak-login-btn-link">
                            Go to Login
                        </a>
                    </div>
                    
                <?php else: ?>
                    <div class="ak-verification-info">
                        <div class="ak-info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <h1>Check Your Email</h1>
                        <p>We've sent a verification link to your email address. Click the link to verify your account.</p>
                        <p class="ak-spam-note">Don't see it? Check your spam folder.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .ak-verification-page {
                min-height: 70vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                background: linear-gradient(135deg, #e8f4f8 0%, #f0f7f4 100%);
            }
            
            .ak-verification-container {
                max-width: 480px;
                background: #fff;
                border-radius: 24px;
                padding: 50px 40px;
                text-align: center;
                box-shadow: 0 10px 40px rgba(30, 58, 95, 0.12);
            }
            
            .ak-verification-success .ak-success-icon,
            .ak-verification-error .ak-error-icon,
            .ak-verification-info .ak-info-icon {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 25px;
            }
            
            .ak-success-icon {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            }
            
            .ak-success-icon svg {
                width: 45px;
                height: 45px;
                stroke: #28a745;
            }
            
            .ak-error-icon {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            }
            
            .ak-error-icon svg {
                width: 45px;
                height: 45px;
                stroke: #dc3545;
            }
            
            .ak-info-icon {
                background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%);
            }
            
            .ak-info-icon svg {
                width: 45px;
                height: 45px;
                stroke: #0056b3;
            }
            
            .ak-verification-container h1 {
                margin: 0 0 15px 0;
                font-size: 28px;
                color: #1e3a5f;
            }
            
            .ak-verification-container p {
                margin: 0 0 10px 0;
                color: #666;
                font-size: 15px;
                line-height: 1.6;
            }
            
            .ak-spam-note {
                font-size: 13px !important;
                color: #999 !important;
                margin-top: 20px !important;
            }
            
            .ak-continue-btn,
            .ak-login-btn-link {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 16px 32px;
                background: linear-gradient(135deg, #28a745 0%, #218838 100%);
                color: #fff;
                text-decoration: none;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                margin-top: 25px;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.35);
            }
            
            .ak-continue-btn:hover,
            .ak-login-btn-link:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 25px rgba(40, 167, 69, 0.45);
                color: #fff;
            }
            
            .ak-login-btn-link {
                background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                box-shadow: 0 4px 15px rgba(30, 58, 95, 0.35);
            }
            
            .ak-login-btn-link:hover {
                box-shadow: 0 6px 25px rgba(30, 58, 95, 0.45);
            }
            
            .ak-arrow {
                font-size: 18px;
            }
            
            .ak-resend-btn {
                display: inline-block;
                padding: 14px 28px;
                background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
                color: #fff;
                border: none;
                border-radius: 50px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                margin-top: 25px;
                transition: all 0.3s;
            }
            
            .ak-resend-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(255, 152, 0, 0.4);
            }
            
            .ak-resend-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }
            
            .ak-resend-message {
                margin-top: 15px;
                padding: 10px 15px;
                border-radius: 8px;
                font-size: 14px;
                display: none;
            }
            
            .ak-resend-message.success {
                display: block;
                background: #d4edda;
                color: #155724;
            }
            
            .ak-resend-message.error {
                display: block;
                background: #f8d7da;
                color: #721c24;
            }
            
            @media (max-width: 500px) {
                .ak-verification-container {
                    padding: 40px 25px;
                }
                
                .ak-verification-container h1 {
                    font-size: 24px;
                }
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                $('.ak-resend-btn').on('click', function() {
                    var btn = $(this);
                    var email = btn.data('email');
                    var msgDiv = $('.ak-resend-message');
                    
                    btn.prop('disabled', true).text('Sending...');
                    msgDiv.removeClass('success error').hide();
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'ak_resend_verification',
                            email: email,
                            nonce: '<?php echo wp_create_nonce('ak_signup_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                msgDiv.addClass('success').text('Verification email sent! Check your inbox.').show();
                                btn.text('Email Sent');
                            } else {
                                msgDiv.addClass('error').text(response.data.message).show();
                                btn.prop('disabled', false).text('Resend Verification Email');
                            }
                        },
                        error: function() {
                            msgDiv.addClass('error').text('Connection error. Please try again.').show();
                            btn.prop('disabled', false).text('Resend Verification Email');
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
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
            'profileUrl' => home_url('/complete-profile'),
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
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <h3>Check Your Email</h3>
                    <p>We sent a verification link to<br><strong id="ak-verification-email"></strong></p>
                    <p>Click the link in the email to verify your account.</p>
                    <p><span class="ak-resend-link" id="ak-popup-resend">Resend verification email</span></p>
                    <p class="ak-cooldown-message" style="display:none;font-size:12px;color:#888;"></p>
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
        
        // Check for referral cookie
        if (isset($_COOKIE['ak_referrer'])) {
            $referrer_id = intval($_COOKIE['ak_referrer']);
            if ($referrer_id > 0) {
                update_user_meta($user_id, 'ak_pending_referrer', $referrer_id);
            }
        }
        
        // Send verification email
        $this->send_verification_email($email, $name, $token);
        
        wp_send_json_success(array('message' => 'Account created! Check your email to verify.'));
    }
    
    /**
     * Send verification email (HTML version)
     */
    private function send_verification_email($email, $name, $token) {
        $verify_url = add_query_arg(array(
            'ak_verify' => $token
        ), home_url('/'));
        
        $subject = 'Verify your AppointmentKeeper account';
        
        // HTML Email
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background:#f4f7fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen,Ubuntu,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                            <!-- Header -->
                            <tr>
                                <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a87 100%);padding:35px 30px;text-align:center;">
                                    <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">AppointmentKeeper</h1>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding:40px 35px;">
                                    <h2 style="margin:0 0 15px 0;color:#1e3a5f;font-size:22px;">Hi ' . esc_html($name) . ',</h2>
                                    <p style="margin:0 0 25px 0;color:#555;font-size:15px;line-height:1.6;">
                                        Welcome to AppointmentKeeper! Please verify your email address to complete your registration.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center" style="padding:10px 0 30px;">
                                                <a href="' . esc_url($verify_url) . '" style="display:inline-block;padding:16px 40px;background:linear-gradient(135deg,#28a745 0%,#218838 100%);color:#fff;text-decoration:none;border-radius:50px;font-size:16px;font-weight:600;">
                                                    Verify Email Address
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0 0 10px 0;color:#888;font-size:13px;">
                                        Or copy and paste this link into your browser:
                                    </p>
                                    <p style="margin:0 0 25px 0;word-break:break-all;background:#f4f7fa;padding:12px 15px;border-radius:8px;font-size:12px;color:#666;">
                                        ' . esc_url($verify_url) . '
                                    </p>
                                    <p style="margin:0;color:#888;font-size:13px;">
                                        This link will expire in 24 hours. If you didn\'t create this account, you can safely ignore this email.
                                    </p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background:#f8fafb;padding:25px 35px;text-align:center;border-top:1px solid #eee;">
                                    <p style="margin:0;color:#999;font-size:12px;">
                                        © ' . date('Y') . ' AppointmentKeeper. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AppointmentKeeper <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        wp_mail($email, $subject, $message, $headers);
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
                wp_send_json_error(array(
                    'message' => 'Please verify your email first. Check your inbox.',
                    'show_resend' => true,
                    'email' => $email
                ));
            }
        }
        
        // Log in
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);
        
        // Check if profile is complete
        $profile_complete = get_user_meta($user->ID, 'ak_profile_complete', true);
        
        if ($profile_complete !== 'yes') {
            $redirect = home_url('/complete-profile');
        } else {
            // Check if user has selected a plan
            $has_plan = get_user_meta($user->ID, 'ak_subscription_status', true);
            
            if (!$has_plan || $has_plan === 'none') {
                $redirect = home_url('/choose-plan');
            } else {
                $redirect = home_url('/my-dashboard');
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Login successful!',
            'redirect' => $redirect
        ));
    }
    
    /**
     * Resend verification email with rate limiting
     */
    public function handle_resend_verification() {
        check_ajax_referer('ak_signup_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Email not found.'));
        }
        
        // Rate limiting: Check last resend time
        $last_resend = get_user_meta($user->ID, 'ak_last_verification_resend', true);
        $cooldown = 60; // 60 seconds between resends
        
        if ($last_resend && (time() - $last_resend) < $cooldown) {
            $remaining = $cooldown - (time() - $last_resend);
            wp_send_json_error(array(
                'message' => 'Please wait ' . $remaining . ' seconds before requesting another email.',
                'cooldown' => $remaining
            ));
        }
        
        // Generate new token
        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'ak_verification_token', $token);
        update_user_meta($user->ID, 'ak_verification_expires', time() + (24 * 60 * 60));
        update_user_meta($user->ID, 'ak_last_verification_resend', time());
        
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
