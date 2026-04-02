/**
 * AppointmentKeeper Billing Page JavaScript
 */

(function($) {
    'use strict';

    var AKBilling = {
        selectedPlan: null,
        includeHelper: false,
        plans: {
            basic: { name: 'Basic', price: 9.99 },
            standard: { name: 'Standard', price: 24.99 },
            premium: { name: 'Premium', price: 49.99 },
            enterprise: { name: 'Enterprise', price: 149.99, helperIncluded: true }
        },
        helperPrice: 12.00,
        
        init: function() {
            this.bindEvents();
            this.checkUrlParams();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Plan selection - click button to go to checkout
            $(document).on('click', '.ak-select-plan-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var planId = $(this).data('plan');
                self.selectPlanAndCheckout(planId);
            });
            
            // Prevent card click from triggering checkout
            $(document).on('click', '.ak-plan-card', function(e) {
                // Do nothing - only button click triggers checkout
            });
            
            // Helper checkbox change
            $(document).on('change', '.ak-add-helper', function() {
                // Just update visual state, actual value checked at checkout
            });
        },
        
        checkUrlParams: function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('payment')) {
                var status = urlParams.get('payment');
                var sessionId = urlParams.get('session_id');
                
                if (status === 'success' && sessionId) {
                    this.handlePaymentSuccess(sessionId);
                } else if (status === 'cancelled') {
                    this.showMessage('Payment Cancelled', 'You cancelled the checkout. Feel free to try again when ready.', 'error');
                }
            }
        },
        
        selectPlanAndCheckout: function(planId) {
            if (!this.plans[planId]) return;
            
            this.selectedPlan = planId;
            
            // Check if helper is included or selected
            if (this.plans[planId].helperIncluded) {
                this.includeHelper = true;
            } else {
                var $checkbox = $('.ak-plan-card[data-plan="' + planId + '"] .ak-add-helper');
                this.includeHelper = $checkbox.is(':checked');
            }
            
            // Go directly to checkout
            this.initiateCheckout();
        },
        
        initiateCheckout: function() {
            if (!this.selectedPlan) {
                alert('Please select a plan first.');
                return;
            }
            
            var self = this;
            var $btn = $('.ak-plan-card[data-plan="' + this.selectedPlan + '"] .ak-select-plan-btn');
            var btnText = $btn.text();
            
            $btn.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: akBillingData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_create_checkout',
                    nonce: akBillingData.nonce,
                    plan: this.selectedPlan,
                    include_helper: this.includeHelper ? 'true' : 'false'
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data.message || 'Could not start checkout. Please try again.');
                        $btn.prop('disabled', false).text(btnText);
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                    $btn.prop('disabled', false).text(btnText);
                }
            });
        },
        
        handlePaymentSuccess: function(sessionId) {
            var self = this;
            this.showLoading();
            this.pollPaymentStatus(sessionId, 0);
        },
        
        pollPaymentStatus: function(sessionId, attempts) {
            var self = this;
            var maxAttempts = 10;
            
            if (attempts >= maxAttempts) {
                this.hideLoading();
                this.showMessage(
                    'Payment Processing',
                    'Your payment is being processed. You will receive an email confirmation shortly.',
                    'success'
                );
                return;
            }
            
            $.ajax({
                url: akBillingData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_check_payment_status',
                    nonce: akBillingData.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'complete') {
                            self.hideLoading();
                            self.showMessage(
                                'Welcome to AppointmentKeeper!',
                                'Your trial has started. You have 3 days to explore all features with trial credits.',
                                'success',
                                response.data.redirect
                            );
                        } else {
                            setTimeout(function() {
                                self.pollPaymentStatus(sessionId, attempts + 1);
                            }, 2000);
                        }
                    } else {
                        self.hideLoading();
                        self.showMessage('Error', response.data.message || 'Could not verify payment.', 'error');
                    }
                },
                error: function() {
                    setTimeout(function() {
                        self.pollPaymentStatus(sessionId, attempts + 1);
                    }, 2000);
                }
            });
        },
        
        showLoading: function() {
            if ($('.ak-loading-overlay').length === 0) {
                $('body').append('<div class="ak-loading-overlay"><div class="ak-loading-spinner"></div></div>');
            }
            $('.ak-loading-overlay').show();
        },
        
        hideLoading: function() {
            $('.ak-loading-overlay').hide();
        },
        
        showMessage: function(title, message, type, redirectUrl) {
            var html = '<div class="ak-payment-message ' + type + '">';
            html += '<h2>' + title + '</h2>';
            html += '<p>' + message + '</p>';
            
            if (redirectUrl) {
                html += '<a href="' + redirectUrl + '">Go to Dashboard</a>';
            } else if (type === 'error') {
                html += '<a href="' + window.location.pathname + '">Try Again</a>';
            }
            
            html += '</div>';
            
            $('.ak-billing-page').html(html);
        }
    };
    
    $(document).ready(function() {
        AKBilling.init();
    });

})(jQuery);
