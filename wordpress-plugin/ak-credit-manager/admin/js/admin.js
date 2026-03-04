/**
 * Credit Manager Admin JavaScript
 */

(function($) {
    'use strict';

    var currentUserId = null;

    // Quick search
    var searchTimeout;
    $('#ak-quick-search').on('input', function() {
        var search = $(this).val();
        var $results = $('#ak-quick-search-results');

        clearTimeout(searchTimeout);

        if (search.length < 2) {
            $results.removeClass('active').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: akCreditManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_cm_search_customers',
                    nonce: akCreditManager.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.customers.length > 0) {
                        var html = '';
                        $.each(response.data.customers, function(i, c) {
                            html += '<div class="ak-search-result" data-user-id="' + c.ID + '">';
                            html += '<div class="name">' + escapeHtml(c.display_name) + '</div>';
                            html += '<div class="email">' + escapeHtml(c.user_email) + '</div>';
                            html += '<div class="credits">SMS: ' + c.sms_credits + ' | Calls: ' + c.call_credits + ' | Emails: ' + c.email_credits + '</div>';
                            html += '</div>';
                        });
                        $results.html(html).addClass('active');
                    } else {
                        $results.html('<div class="ak-search-result"><em>No customers found</em></div>').addClass('active');
                    }
                }
            });
        }, 300);
    });

    // Select customer from search
    $(document).on('click', '.ak-search-result[data-user-id]', function() {
        var userId = $(this).data('user-id');
        loadCustomer(userId);
        $('#ak-quick-search').val('');
        $('#ak-quick-search-results').removeClass('active').empty();
    });

    // Hide search results on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#ak-quick-search, #ak-quick-search-results').length) {
            $('#ak-quick-search-results').removeClass('active');
        }
    });

    // Load customer details
    function loadCustomer(userId) {
        currentUserId = userId;
        
        $.ajax({
            url: akCreditManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_get_customer',
                nonce: akCreditManager.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    var c = response.data.customer;
                    
                    $('#ak-panel-customer-name').text(c.name);
                    $('#ak-panel-customer-email').text(c.email);
                    $('#ak-panel-plan').text(c.plan.charAt(0).toUpperCase() + c.plan.slice(1));
                    $('#ak-panel-sms').text(c.sms_credits);
                    $('#ak-panel-calls').text(c.call_credits);
                    $('#ak-panel-emails').text(c.email_credits);
                    $('#ak-panel-user-id').val(c.id);
                    
                    // Load history
                    var historyHtml = '';
                    if (response.data.history && response.data.history.length > 0) {
                        $.each(response.data.history, function(i, h) {
                            var actionClass = 'ak-action-' + h.action;
                            var sign = (h.action === 'deduct') ? '-' : '+';
                            historyHtml += '<tr class="' + actionClass + '">';
                            historyHtml += '<td>' + formatDate(h.created_at) + '</td>';
                            historyHtml += '<td>' + h.action.replace('_', ' ') + '</td>';
                            historyHtml += '<td>' + h.credit_type.toUpperCase() + '</td>';
                            historyHtml += '<td>' + sign + h.amount + '</td>';
                            historyHtml += '<td>' + h.balance_after + '</td>';
                            historyHtml += '<td>' + (h.reason || '-') + '</td>';
                            historyHtml += '<td>' + (h.performed_by_name || 'System') + '</td>';
                            historyHtml += '</tr>';
                        });
                    } else {
                        historyHtml = '<tr><td colspan="7">No transactions yet</td></tr>';
                    }
                    $('#ak-panel-history').html(historyHtml);
                    
                    $('#ak-customer-panel').slideDown();
                }
            }
        });
    }

    // Add credits
    $('.ak-btn-add-credits').on('click', function() {
        var amount = parseInt($('#ak-panel-amount').val());
        var type = $('#ak-panel-credit-type').val();
        var reason = $('#ak-panel-reason').val();
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        if (!confirm(akCreditManager.strings.confirmAdd)) return;
        
        $.ajax({
            url: akCreditManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_add_credits',
                nonce: akCreditManager.nonce,
                user_id: currentUserId,
                credit_type: type,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomer(currentUserId);
                    $('#ak-panel-amount').val('');
                    $('#ak-panel-reason').val('');
                } else {
                    alert(response.data.message || akCreditManager.strings.error);
                }
            }
        });
    });

    // Remove credits
    $('.ak-btn-remove-credits').on('click', function() {
        var amount = parseInt($('#ak-panel-amount').val());
        var type = $('#ak-panel-credit-type').val();
        var reason = $('#ak-panel-reason').val();
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        if (!confirm(akCreditManager.strings.confirmRemove)) return;
        
        $.ajax({
            url: akCreditManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_remove_credits',
                nonce: akCreditManager.nonce,
                user_id: currentUserId,
                credit_type: type,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomer(currentUserId);
                    $('#ak-panel-amount').val('');
                    $('#ak-panel-reason').val('');
                } else {
                    alert(response.data.message || akCreditManager.strings.error);
                }
            }
        });
    });

    // Give free month
    $('.ak-btn-free-month').on('click', function() {
        if (!confirm(akCreditManager.strings.confirmFreeMonth)) return;
        
        $.ajax({
            url: akCreditManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_give_free_month',
                nonce: akCreditManager.nonce,
                user_id: currentUserId,
                reason: 'Free month from admin'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCustomer(currentUserId);
                } else {
                    alert(response.data.message || akCreditManager.strings.error);
                }
            }
        });
    });

    // Manage button in customers list
    $(document).on('click', '.ak-manage-btn', function() {
        var userId = $(this).data('user');
        loadCustomer(userId);
        $('html, body').animate({ scrollTop: $('#ak-customer-panel').offset().top - 50 }, 500);
    });

    // Select all checkbox
    $('#ak-select-all').on('change', function() {
        $('.ak-customer-select').prop('checked', $(this).is(':checked'));
    });

    // Bulk action dropdown - show/hide amount field
    $('#ak-bulk-action').on('change', function() {
        var action = $(this).val();
        if (action && action !== 'free_month') {
            $('#ak-bulk-amount').show();
        } else {
            $('#ak-bulk-amount').hide();
        }
    });

    // Apply bulk action
    $('#ak-apply-bulk').on('click', function() {
        var action = $('#ak-bulk-action').val();
        var amount = parseInt($('#ak-bulk-amount').val()) || 0;
        var userIds = [];
        
        $('.ak-customer-select:checked').each(function() {
            userIds.push($(this).val());
        });
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        if (userIds.length === 0) {
            alert('Please select at least one customer');
            return;
        }
        
        if (action !== 'free_month' && (!amount || amount <= 0)) {
            alert('Please enter a valid amount');
            return;
        }
        
        if (!confirm(akCreditManager.strings.confirmBulk)) return;
        
        $.ajax({
            url: akCreditManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_cm_bulk_action',
                nonce: akCreditManager.nonce,
                bulk_action: action,
                user_ids: userIds,
                amount: amount
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || akCreditManager.strings.error);
                }
            }
        });
    });

    // Filter low credits
    $('#ak-filter-low-credits').on('change', function() {
        if ($(this).is(':checked')) {
            $('.ak-customers-table tbody tr').not('.ak-low-credits').hide();
        } else {
            $('.ak-customers-table tbody tr').show();
        }
    });

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

})(jQuery);
