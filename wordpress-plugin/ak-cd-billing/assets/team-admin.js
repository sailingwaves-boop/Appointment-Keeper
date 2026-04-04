/**
 * Team Admin JS
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Handle invite form submission
        $('#ak-invite-form').on('submit', function(e) {
            e.preventDefault();
            
            var btn = $(this).find('.ak-invite-btn');
            var resultDiv = $('#ak-invite-result');
            
            btn.prop('disabled', true).text('Sending...');
            resultDiv.html('');
            
            $.ajax({
                url: akTeamData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_invite_team_member',
                    email: $('#ak-invite-email').val(),
                    name: $('#ak-invite-name').val(),
                    sms: $('#ak-invite-sms').val(),
                    call: $('#ak-invite-call').val(),
                    nonce: akTeamData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.html('<div class="ak-message success">' + response.data.message + '</div>');
                        // Clear form
                        $('#ak-invite-email, #ak-invite-name').val('');
                        // Reload page after delay to show new member
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        resultDiv.html('<div class="ak-message error">' + response.data.message + '</div>');
                        btn.prop('disabled', false).text('Send Invitation');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="ak-message error">Connection error. Please try again.</div>');
                    btn.prop('disabled', false).text('Send Invitation');
                }
            });
        });
        
        // Handle remove member
        $('.ak-remove-member').on('click', function() {
            var email = $(this).data('email');
            var card = $(this).closest('.ak-member-card');
            
            if (!confirm('Remove this team member? Their allocated credits will be returned to your pool.')) {
                return;
            }
            
            $.ajax({
                url: akTeamData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_remove_team_member',
                    email: email,
                    nonce: akTeamData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        card.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                }
            });
        });
        
        // Handle allocation changes
        $('.ak-allocation-select').on('change', function() {
            var select = $(this);
            var card = select.closest('.ak-member-card');
            var email = card.data('email');
            var type = select.data('type');
            var value = select.val();
            
            select.prop('disabled', true);
            
            $.ajax({
                url: akTeamData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_update_member_allocation',
                    email: email,
                    type: type,
                    value: value,
                    nonce: akTeamData.nonce
                },
                success: function(response) {
                    select.prop('disabled', false);
                    
                    if (!response.success) {
                        alert(response.data.message);
                        // Revert select - would need to track previous value
                    }
                },
                error: function() {
                    select.prop('disabled', false);
                    alert('Connection error. Please try again.');
                }
            });
        });
        
        // Check for invite token in URL
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('ak_team_invite')) {
            // Show acceptance message
            $('<div class="ak-message success" style="margin-bottom:20px;text-align:center;">You\'ve joined the team! Welcome aboard.</div>')
                .insertBefore('.ak-team-header');
        }
        
    });
    
})(jQuery);
