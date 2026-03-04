/**
 * Credit Manager - Amelia Integration JavaScript
 */

(function($) {
    'use strict';

    var currentUserId = null;

    // Open credit manager panel
    $('#ak-open-credit-manager').on('click', function() {
        $('#ak-credit-manager-panel').show();
    });

    // Close panel
    $('.ak-close-panel').on('click', function() {
        $('#ak-credit-manager-panel').hide();
        resetPanel();
    });

    // Close on escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#ak-credit-manager-panel').hide();
            resetPanel();
        }
    });

    // Search customers
    var searchTimeout;
    $('#ak-credit-customer-search').on('input', function() {
        var search = $(this).val();
        var $results = $('#ak-credit-search-results');

        clearTimeout(searchTimeout);

        if (search.length < 2) {
            $results.empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: akCreditAmelia.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_cm_search_customers',
                    nonce: akCreditAmelia.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.customers.length > 0) {
                        var html = '';
                        $.each(response.data.customers, function(i, c) {
                            html += '<div class="ak-search-result" data-user-id="' + c.ID + '">';
                            html += '<strong>' + escapeHtml(c.display_name) + '</strong> ';
                            html += '<span>' + escapeHtml(c.user_email) + '</span>';
                            html += '</div>';
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="ak-search-result"><em>No customers found</em></div>');
                    }
                }
            });
        }, 300);
    });

    // Select customer from search
    $(document).on('click', '#ak-credit-search-results .ak-search-result[data-user-id]', function() {
        var userId = $(this).data('user-id');
        loadCustomerPanel(userId);
        $('#ak-credit-customer-search').val('');
        $('#ak-credit-search-results').empty();
    });

    // Load customer details in panel
    function loadCustomerPanel(userId) {
        currentUserId = userId;
        
        $.ajax({
            url: akCreditAmelia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_get_customer',
                nonce: akCreditAmelia.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    var c = response.data.customer;
                    
                    $('#ak-customer-name').text(c.name);
                    $('#ak-customer-email').text(c.email);
                    $('#ak-customer-plan').text(c.plan);
                    $('#ak-balance-sms').text(c.sms_credits);
                    $('#ak-balance-calls').text(c.call_credits);
                    $('#ak-balance-emails').text(c.email_credits);
                    
                    $('#ak-credit-customer-details').show();
                    $('#ak-credit-history').hide();
                }
            }
        });
    }

    // Add credits
    $('.ak-add-credits').on('click', function() {
        var type = $('#ak-credit-type').val();
        var amount = parseInt($('#ak-credit-amount').val());
        var reason = $('#ak-credit-reason').val();
        
        if (!currentUserId || !amount || amount <= 0) {
            alert('Please select a customer and enter a valid amount');
            return;
        }
        
        $.ajax({
            url: akCreditAmelia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_add_credits',
                nonce: akCreditAmelia.nonce,
                user_id: currentUserId,
                credit_type: type,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomerPanel(currentUserId);
                    $('#ak-credit-amount').val('');
                    $('#ak-credit-reason').val('');
                } else {
                    alert(response.data.message || 'Error');
                }
            }
        });
    });

    // Remove credits
    $('.ak-remove-credits').on('click', function() {
        var type = $('#ak-credit-type').val();
        var amount = parseInt($('#ak-credit-amount').val());
        var reason = $('#ak-credit-reason').val();
        
        if (!currentUserId || !amount || amount <= 0) {
            alert('Please select a customer and enter a valid amount');
            return;
        }
        
        $.ajax({
            url: akCreditAmelia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_remove_credits',
                nonce: akCreditAmelia.nonce,
                user_id: currentUserId,
                credit_type: type,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomerPanel(currentUserId);
                    $('#ak-credit-amount').val('');
                    $('#ak-credit-reason').val('');
                } else {
                    alert(response.data.message || 'Error');
                }
            }
        });
    });

    // Give free month
    $('.ak-give-free-month').on('click', function() {
        if (!currentUserId) {
            alert('Please select a customer first');
            return;
        }
        
        if (!confirm('Give this customer a free month?')) return;
        
        $.ajax({
            url: akCreditAmelia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_give_free_month',
                nonce: akCreditAmelia.nonce,
                user_id: currentUserId,
                reason: 'Free month from Amelia admin'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomerPanel(currentUserId);
                } else {
                    alert(response.data.message || 'Error');
                }
            }
        });
    });

    // View history
    $('.ak-view-history').on('click', function() {
        if (!currentUserId) return;
        
        $.ajax({
            url: akCreditAmelia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_get_history',
                nonce: akCreditAmelia.nonce,
                user_id: currentUserId
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    var html = '';
                    $.each(response.data.history.slice(0, 10), function(i, h) {
                        var sign = (h.action === 'deduct') ? '-' : '+';
                        html += '<tr>';
                        html += '<td>' + formatDate(h.created_at) + '</td>';
                        html += '<td>' + h.action + '</td>';
                        html += '<td>' + h.credit_type + '</td>';
                        html += '<td>' + sign + h.amount + '</td>';
                        html += '<td>' + (h.reason || '-') + '</td>';
                        html += '</tr>';
                    });
                    $('#ak-history-body').html(html);
                    $('#ak-credit-history').toggle();
                }
            }
        });
    });

    // Manage credits button (from Amelia inline)
    $(document).on('click', '.ak-manage-credits', function() {
        var userId = $(this).data('user');
        $('#ak-credit-manager-panel').show();
        loadCustomerPanel(userId);
    });

    // Reset panel
    function resetPanel() {
        currentUserId = null;
        $('#ak-credit-customer-details').hide();
        $('#ak-credit-history').hide();
        $('#ak-credit-customer-search').val('');
        $('#ak-credit-search-results').empty();
    }

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString();
    }

})(jQuery);
