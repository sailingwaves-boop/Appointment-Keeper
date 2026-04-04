/**
 * AppointmentKeeper Admin Settings JS
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Tab switching
        $('.ak-settings-tabs .ak-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Update active states
            $('.ak-settings-tabs .ak-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show selected tab content
            $('.ak-tab-content').removeClass('active');
            $('#ak-tab-' + tabId).addClass('active');
        });
        
        // Toggle password visibility
        $('.ak-toggle-key').on('click', function() {
            var targetId = $(this).data('target');
            var input = $('#' + targetId);
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).text('Hide');
            } else {
                input.attr('type', 'password');
                $(this).text('Show');
            }
        });
        
        // Copy webhook URL
        $('.ak-copy-btn').on('click', function() {
            var url = $(this).data('copy');
            var btn = $(this);
            
            navigator.clipboard.writeText(url).then(function() {
                btn.addClass('copied').text('Copied!');
                setTimeout(function() {
                    btn.removeClass('copied').text('Copy');
                }, 2000);
            });
        });
        
        // Test connections
        $('.ak-test-connection').on('click', function() {
            var service = $(this).data('service');
            var resultSpan = $('#ak-' + service + '-result');
            var btn = $(this);
            
            btn.prop('disabled', true);
            resultSpan.removeClass('success error').addClass('loading').text('Testing...');
            
            $.ajax({
                url: akAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_test_' + service,
                    nonce: akAdminData.nonce
                },
                success: function(response) {
                    resultSpan.removeClass('loading');
                    
                    if (response.success) {
                        resultSpan.addClass('success').text(response.data.message);
                    } else {
                        resultSpan.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    resultSpan.removeClass('loading').addClass('error').text('Connection failed');
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });
        
    });
    
})(jQuery);
