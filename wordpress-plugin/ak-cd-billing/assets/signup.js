/**
 * AppointmentKeeper Signup Popup JavaScript
 */

(function($) {
    'use strict';

    var AKSignup = {
        
        init: function() {
            this.bindEvents();
            this.checkUrlParams();
        },
        
        bindEvents: function() {
            // Open popup
            $(document).on('click', '.ak-signup-trigger, .ak-login-trigger', function(e) {
                e.preventDefault();
                var mode = $(this).hasClass('ak-login-trigger') ? 'login' : 'signup';
                AKSignup.openPopup(mode);
            });
            
            // Close popup
            $(document).on('click', '.ak-popup-close, .ak-signup-overlay', function(e) {
                if (e.target === this) {
                    AKSignup.closePopup();
                }
            });
            
            // ESC key closes popup
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    AKSignup.closePopup();
                }
            });
            
            // Toggle between signup and login
            $(document).on('click', '.ak-toggle-form', function(e) {
                e.preventDefault();
                var mode = $(this).data('mode');
                AKSignup.toggleMode(mode);
            });
            
            // Signup form submit
            $(document).on('submit', '#ak-signup-form', function(e) {
                e.preventDefault();
                AKSignup.handleSignup($(this));
            });
            
            // Login form submit
            $(document).on('submit', '#ak-login-form', function(e) {
                e.preventDefault();
                AKSignup.handleLogin($(this));
            });
            
            // Google sign in
            $(document).on('click', '.ak-google-btn', function(e) {
                e.preventDefault();
                AKSignup.handleGoogleLogin();
            });
            
            // Resend verification
            $(document).on('click', '.ak-resend-link', function(e) {
                e.preventDefault();
                AKSignup.resendVerification();
            });
        },
        
        checkUrlParams: function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            // Check for verification token
            if (urlParams.has('ak_verify')) {
                this.verifyEmail(urlParams.get('ak_verify'));
            }
            
            // Check for signup prompt
            if (urlParams.has('ak_signup')) {
                this.openPopup('signup');
            }
            
            // Check for login prompt
            if (urlParams.has('ak_login')) {
                this.openPopup('login');
            }
        },
        
        openPopup: function(mode) {
            mode = mode || 'signup';
            $('.ak-signup-overlay').addClass('active');
            $('body').css('overflow', 'hidden');
            this.toggleMode(mode);
        },
        
        closePopup: function() {
            $('.ak-signup-overlay').removeClass('active');
            $('body').css('overflow', '');
            this.clearMessages();
        },
        
        toggleMode: function(mode) {
            if (mode === 'login') {
                $('#ak-signup-form-container').hide();
                $('#ak-login-form-container').show();
                $('#ak-verification-container').hide();
                $('.ak-signup-header h2').text('Welcome Back');
                $('.ak-signup-header p').text('Sign in to your account');
            } else {
                $('#ak-signup-form-container').show();
                $('#ak-login-form-container').hide();
                $('#ak-verification-container').hide();
                $('.ak-signup-header h2').text('Get Started');
                $('.ak-signup-header p').text('Create your free account');
            }
            this.clearMessages();
        },
        
        showVerificationSent: function(email) {
            $('#ak-signup-form-container').hide();
            $('#ak-login-form-container').hide();
            $('#ak-verification-container').show();
            $('#ak-verification-email').text(email);
            $('.ak-signup-header h2').text('Check Your Email');
            $('.ak-signup-header p').text('We sent you a verification link');
        },
        
        handleSignup: function($form) {
            var self = this;
            var $btn = $form.find('.ak-submit-btn');
            var btnText = $btn.text();
            
            // Validate
            var name = $form.find('input[name="ak_name"]').val().trim();
            var email = $form.find('input[name="ak_email"]').val().trim();
            var password = $form.find('input[name="ak_password"]').val();
            
            if (!name || !email || !password) {
                this.showError('Please fill in all fields.');
                return;
            }
            
            if (password.length < 6) {
                this.showError('Password must be at least 6 characters.');
                return;
            }
            
            // Submit
            $btn.prop('disabled', true).html('<span class="ak-loading"></span> Creating account...');
            
            $.ajax({
                url: akSignupData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_signup',
                    nonce: akSignupData.nonce,
                    name: name,
                    email: email,
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        self.showVerificationSent(email);
                        self.pendingEmail = email;
                    } else {
                        self.showError(response.data.message || 'Signup failed. Please try again.');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(btnText);
                }
            });
        },
        
        handleLogin: function($form) {
            var self = this;
            var $btn = $form.find('.ak-submit-btn');
            var btnText = $btn.text();
            
            var email = $form.find('input[name="ak_login_email"]').val().trim();
            var password = $form.find('input[name="ak_login_password"]').val();
            
            if (!email || !password) {
                this.showError('Please enter email and password.');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="ak-loading"></span> Signing in...');
            
            $.ajax({
                url: akSignupData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_login',
                    nonce: akSignupData.nonce,
                    email: email,
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect || akSignupData.dashboardUrl;
                    } else {
                        self.showError(response.data.message || 'Login failed. Please try again.');
                    }
                },
                error: function() {
                    self.showError('Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(btnText);
                }
            });
        },
        
        handleGoogleLogin: function() {
            // Trigger Nextend Social Login Google button if available
            var $nextendBtn = $('.nsl-button-google, [data-provider="google"]').first();
            
            if ($nextendBtn.length) {
                $nextendBtn[0].click();
            } else {
                // Fallback - redirect to Nextend login URL if set
                if (akSignupData.googleLoginUrl) {
                    window.location.href = akSignupData.googleLoginUrl;
                } else {
                    this.showError('Google sign in not available. Please use email instead.');
                }
            }
        },
        
        verifyEmail: function(token) {
            var self = this;
            
            $.ajax({
                url: akSignupData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_verify_email',
                    nonce: akSignupData.nonce,
                    token: token
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to plan selection or dashboard
                        window.location.href = response.data.redirect || akSignupData.planSelectionUrl;
                    } else {
                        self.openPopup('login');
                        self.showError(response.data.message || 'Verification failed. Please try again.');
                    }
                },
                error: function() {
                    self.openPopup('login');
                    self.showError('Verification failed. Please try again.');
                }
            });
        },
        
        resendVerification: function() {
            var self = this;
            
            if (!this.pendingEmail) {
                this.showError('Please sign up again.');
                return;
            }
            
            $.ajax({
                url: akSignupData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_resend_verification',
                    nonce: akSignupData.nonce,
                    email: this.pendingEmail
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Verification email sent!');
                    } else {
                        self.showError(response.data.message || 'Could not send email.');
                    }
                }
            });
        },
        
        showError: function(message) {
            $('.ak-error-message').text(message).addClass('active');
            $('.ak-success-message').removeClass('active');
        },
        
        showSuccess: function(message) {
            $('.ak-success-message').text(message).addClass('active');
            $('.ak-error-message').removeClass('active');
        },
        
        clearMessages: function() {
            $('.ak-error-message, .ak-success-message').removeClass('active');
        }
    };
    
    $(document).ready(function() {
        AKSignup.init();
    });

})(jQuery);
