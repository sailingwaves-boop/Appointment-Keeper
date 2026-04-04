/**
 * AI Outreach JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Load history on page load
        loadOutreachHistory();
        
        // Form submission
        $('#ak-outreach-form').on('submit', function(e) {
            e.preventDefault();
            sendOutreach();
        });
    });
    
    function sendOutreach() {
        var $form = $('#ak-outreach-form');
        var $submit = $('#ak-outreach-submit');
        var $btnText = $submit.find('.ak-btn-text');
        var $btnLoading = $submit.find('.ak-btn-loading');
        var $result = $('#ak-outreach-result');
        
        var contactName = $('#contact_name').val().trim();
        var contactPhone = $('#contact_phone').val().trim();
        var method = $('input[name="method"]:checked').val();
        
        if (!contactName || !contactPhone) {
            showResult('Please fill in both name and phone number.', 'error');
            return;
        }
        
        // Disable form
        $submit.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        $result.hide();
        
        $.ajax({
            url: akOutreach.ajaxurl,
            type: 'POST',
            data: {
                action: 'ak_send_outreach',
                nonce: akOutreach.nonce,
                contact_name: contactName,
                contact_phone: contactPhone,
                method: method
            },
            success: function(response) {
                if (response.success) {
                    showResult(response.data.message, 'success');
                    
                    // Clear form
                    $('#contact_name').val('');
                    $('#contact_phone').val('');
                    
                    // Update remaining count
                    if (response.data.remaining_today !== undefined) {
                        updateRemainingCount(response.data.remaining_today);
                    }
                    
                    // Reload history
                    loadOutreachHistory();
                } else {
                    showResult(response.data.message || 'Something went wrong.', 'error');
                }
            },
            error: function() {
                showResult('Network error. Please try again.', 'error');
            },
            complete: function() {
                $submit.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    }
    
    function showResult(message, type) {
        var $result = $('#ak-outreach-result');
        $result
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();
        
        // Auto-hide success after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $result.fadeOut();
            }, 5000);
        }
    }
    
    function updateRemainingCount(remaining) {
        $('.ak-stat-card').each(function() {
            if ($(this).find('.ak-stat-label').text() === 'Invites Left Today') {
                $(this).find('.ak-stat-value').text(remaining);
            }
        });
    }
    
    function loadOutreachHistory() {
        var $container = $('#ak-history-container');
        
        $.ajax({
            url: akOutreach.ajaxurl,
            type: 'POST',
            data: {
                action: 'ak_get_outreach_history',
                nonce: akOutreach.nonce
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    renderHistory(response.data.history);
                } else {
                    $container.html('<p class="ak-no-history">No outreach history yet. Start inviting!</p>');
                }
            },
            error: function() {
                $container.html('<p class="ak-no-history">Could not load history.</p>');
            }
        });
    }
    
    function renderHistory(history) {
        var $container = $('#ak-history-container');
        
        if (!history || history.length === 0) {
            $container.html('<p class="ak-no-history">No outreach history yet. Start inviting!</p>');
            return;
        }
        
        var html = '<table class="ak-history-table">';
        html += '<thead><tr>';
        html += '<th>Contact</th>';
        html += '<th>Method</th>';
        html += '<th>Status</th>';
        html += '<th>Date</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        history.forEach(function(item) {
            var statusClass = item.converted == 1 ? 'converted' : (item.method === 'call' ? 'called' : 'sent');
            var statusText = item.converted == 1 ? 'Converted!' : (item.status.charAt(0).toUpperCase() + item.status.slice(1));
            
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(item.contact_name) + '</strong><br><small>' + maskPhone(item.contact_phone) + '</small></td>';
            html += '<td>' + (item.method === 'call' ? '📞 AI Call' : '💬 SMS') + '</td>';
            html += '<td><span class="ak-status-badge ak-status-' + statusClass + '">' + statusText + '</span></td>';
            html += '<td>' + formatDate(item.created_at) + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $container.html(html);
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function maskPhone(phone) {
        if (phone.length > 6) {
            return phone.substring(0, 6) + '****' + phone.substring(phone.length - 2);
        }
        return phone;
    }
    
    function formatDate(dateStr) {
        var date = new Date(dateStr);
        var options = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-GB', options);
    }
    
})(jQuery);
