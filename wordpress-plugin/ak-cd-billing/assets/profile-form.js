/**
 * AppointmentKeeper Profile Form JS
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Show/hide invite section
        $('#ak_want_invite').on('change', function() {
            if ($(this).is(':checked')) {
                $('.ak-invite-section').slideDown(300);
            } else {
                $('.ak-invite-section').slideUp(300);
            }
        });
        
        // Handle invite count change
        $('#ak_invite_count').on('change', function() {
            var count = parseInt($(this).val());
            
            $('.ak-invite-person').each(function() {
                var personNum = parseInt($(this).data('person'));
                if (personNum <= count) {
                    $(this).slideDown(200);
                } else {
                    $(this).slideUp(200);
                }
            });
        });
        
        // Show/hide "specify" field
        $('#ak_hear_about').on('change', function() {
            if ($(this).val() === 'other' || $(this).val() === 'friend') {
                $('.ak-specify-group').slideDown(200);
            } else {
                $('.ak-specify-group').slideUp(200);
            }
        });
        
        // Form submission
        $('#ak-profile-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('.ak-submit-btn');
            var errorDiv = form.find('.ak-error-message');
            
            // Basic validation
            var termsChecked = $('#ak_consent_terms').is(':checked');
            var privacyChecked = $('#ak_consent_privacy').is(':checked');
            
            if (!termsChecked) {
                showError(errorDiv, 'Please agree to the Terms & Conditions');
                return;
            }
            
            if (!privacyChecked) {
                showError(errorDiv, 'Please consent to our Privacy Policy');
                return;
            }
            
            // Disable button and show loading
            submitBtn.prop('disabled', true).addClass('loading');
            submitBtn.find('.ak-btn-text').text('Saving');
            hideError(errorDiv);
            
            $.ajax({
                url: akProfileData.ajaxUrl,
                type: 'POST',
                data: form.serialize() + '&action=ak_complete_profile&nonce=' + akProfileData.nonce,
                success: function(response) {
                    if (response.success) {
                        submitBtn.find('.ak-btn-text').text('Success!');
                        submitBtn.removeClass('loading');
                        
                        // Show success briefly then redirect
                        setTimeout(function() {
                            window.location.href = response.data.redirect || akProfileData.planUrl;
                        }, 500);
                    } else {
                        showError(errorDiv, response.data.message || 'Something went wrong');
                        resetButton(submitBtn);
                    }
                },
                error: function() {
                    showError(errorDiv, 'Connection error. Please try again.');
                    resetButton(submitBtn);
                }
            });
        });
        
        // Helper functions
        function showError(element, message) {
            element.text(message).addClass('active');
            $('html, body').animate({
                scrollTop: element.offset().top - 100
            }, 300);
        }
        
        function hideError(element) {
            element.removeClass('active').text('');
        }
        
        function resetButton(btn) {
            btn.prop('disabled', false).removeClass('loading');
            btn.find('.ak-btn-text').text('Continue to Choose Your Plan');
        }
        
        // Phone number formatting
        $('#ak_phone, input[name^="invite_phone"]').on('input', function() {
            var val = $(this).val().replace(/\D/g, '');
            $(this).val(val);
        });
        
    });
    
})(jQuery);
