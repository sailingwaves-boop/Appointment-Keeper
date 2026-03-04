/**
 * AK Debt Ledger Admin JavaScript
 */

(function($) {
    'use strict';

    // Number of debtors dropdown - show/hide extra debtor fields
    $('#num_debtors').on('change', function() {
        var num = parseInt($(this).val());
        
        // Hide all extra debtor fields first
        $('.ak-debtor-extra').hide();
        
        // Show based on selection
        if (num >= 2) {
            $('#debtor-2-fields').show();
        }
        if (num >= 3) {
            $('#debtor-3-fields').show();
        }
    });

    // Customer Search
    var searchTimeout;
    $('#ak-customer-search').on('input', function() {
        var search = $(this).val();
        var $results = $('#ak-customer-results');

        clearTimeout(searchTimeout);

        if (search.length < 2) {
            $results.removeClass('active').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: akDebtLedger.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_search_customers',
                    nonce: akDebtLedger.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.customers.length > 0) {
                        var html = '';
                        $.each(response.data.customers, function(i, customer) {
                            html += '<div class="ak-search-result" data-customer="' + encodeURIComponent(JSON.stringify(customer)) + '">';
                            html += '<div class="name">' + escapeHtml(customer.name) + '</div>';
                            html += '<div class="details">' + escapeHtml(customer.email || '') + ' | ' + escapeHtml(customer.phone || '') + '</div>';
                            html += '</div>';
                        });
                        $results.html(html).addClass('active');
                    } else if (!response.success && response.data.message) {
                        $results.html('<div class="ak-search-result"><em>' + response.data.message + '</em></div>').addClass('active');
                    } else {
                        $results.html('<div class="ak-search-result"><em>No customers found</em></div>').addClass('active');
                    }
                }
            });
        }, 300);
    });

    // Select customer from search results
    $(document).on('click', '.ak-search-result[data-customer]', function() {
        var customer = JSON.parse(decodeURIComponent($(this).data('customer')));
        
        $('#amelia_customer_id').val(customer.id);
        $('#customer_name').val(customer.name);
        $('#customer_phone').val(customer.phone || '');
        $('#customer_email').val(customer.email || '');
        
        $('#ak-customer-search').val('');
        $('#ak-customer-results').removeClass('active').empty();
    });

    // Hide search results on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#ak-customer-search, #ak-customer-results').length) {
            $('#ak-customer-results').removeClass('active');
        }
    });

    // Toggle reminder fields based on consent checkbox
    function toggleReminderFields() {
        var checked = $('#consent_to_reminders').is(':checked');
        if (checked) {
            $('.ak-reminder-fields').addClass('active');
        } else {
            $('.ak-reminder-fields').removeClass('active');
        }
    }
    
    $('#consent_to_reminders').on('change', toggleReminderFields);
    toggleReminderFields(); // Initial state

    // Copy original amount to current balance if empty
    $('#original_amount').on('change', function() {
        var $balance = $('#current_balance');
        if (!$balance.val()) {
            $balance.val($(this).val());
        }
    });

    // Save debt form
    $('#ak-debt-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        
        $submit.prop('disabled', true).addClass('ak-loading');
        
        var formData = {
            action: 'ak_save_debt',
            nonce: akDebtLedger.nonce,
            entry_id: $form.find('[name="entry_id"]').val(),
            creditor_name: $form.find('[name="creditor_name"]').val(),
            creditor_reference: $form.find('[name="creditor_reference"]').val(),
            amelia_customer_id: $form.find('[name="amelia_customer_id"]').val(),
            customer_name: $form.find('[name="customer_name"]').val(),
            customer_phone: $form.find('[name="customer_phone"]').val(),
            customer_email: $form.find('[name="customer_email"]').val(),
            customer_address: $form.find('[name="customer_address"]').val(),
            debtor2_name: $form.find('[name="debtor2_name"]').val(),
            debtor2_phone: $form.find('[name="debtor2_phone"]').val(),
            debtor3_name: $form.find('[name="debtor3_name"]').val(),
            debtor3_phone: $form.find('[name="debtor3_phone"]').val(),
            num_debtors: $form.find('[name="num_debtors"]').val(),
            original_amount: $form.find('[name="original_amount"]').val(),
            current_balance: $form.find('[name="current_balance"]').val(),
            currency: $form.find('[name="currency"]').val(),
            debt_type: $form.find('[name="debt_type"]').val(),
            status: $form.find('[name="status"]').val(),
            notes: $form.find('[name="notes"]').val(),
            consent_to_reminders: $form.find('[name="consent_to_reminders"]').is(':checked') ? 1 : 0,
            preferred_channel: $form.find('[name="preferred_channel"]').val(),
            next_reminder_at: $form.find('[name="next_reminder_at"]').val()
        };
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    window.location.href = 'admin.php?page=ak-debt-ledger&message=' + encodeURIComponent(response.data.message);
                } else {
                    alert(response.data.message);
                    $submit.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $submit.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Add payment form
    $('#ak-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        
        $submit.prop('disabled', true).addClass('ak-loading');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_add_payment',
                nonce: akDebtLedger.nonce,
                ledger_id: $form.find('[name="ledger_id"]').val(),
                payment_date: $form.find('[name="payment_date"]').val(),
                amount: $form.find('[name="payment_amount"]').val(),
                method: $form.find('[name="payment_method"]').val(),
                reference: $form.find('[name="payment_reference"]').val(),
                note: $form.find('[name="payment_note"]').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $submit.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $submit.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Confirm payment
    $(document).on('click', '.ak-confirm-payment', function() {
        var $btn = $(this);
        
        if (!confirm(akDebtLedger.strings.confirmPayment)) {
            return;
        }
        
        $btn.prop('disabled', true).addClass('ak-loading');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_confirm_payment',
                nonce: akDebtLedger.nonce,
                payment_id: $btn.data('id'),
                ledger_id: $btn.data('ledger')
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $btn.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Mark as paid
    $(document).on('click', '.ak-mark-paid', function() {
        var $btn = $(this);
        
        if (!confirm(akDebtLedger.strings.confirmMarkPaid)) {
            return;
        }
        
        $btn.prop('disabled', true).addClass('ak-loading');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_mark_paid',
                nonce: akDebtLedger.nonce,
                entry_id: $btn.data('id')
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $btn.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Write off
    $(document).on('click', '.ak-write-off', function() {
        var $btn = $(this);
        
        if (!confirm(akDebtLedger.strings.confirmWriteOff)) {
            return;
        }
        
        $btn.prop('disabled', true).addClass('ak-loading');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_write_off',
                nonce: akDebtLedger.nonce,
                entry_id: $btn.data('id')
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $btn.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Send reminder
    $(document).on('click', '.ak-send-reminder', function() {
        var $btn = $(this);
        
        $btn.prop('disabled', true).text(akDebtLedger.strings.sending);
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_send_reminder',
                nonce: akDebtLedger.nonce,
                entry_id: $btn.data('id')
            },
            success: function(response) {
                if (response.success) {
                    $btn.text(akDebtLedger.strings.sent);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span>');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span>');
            }
        });
    });

    // Delete entry
    $(document).on('click', '.ak-delete-entry', function() {
        var $btn = $(this);
        
        if (!confirm(akDebtLedger.strings.confirmDelete)) {
            return;
        }
        
        $btn.prop('disabled', true).addClass('ak-loading');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_delete_entry',
                nonce: akDebtLedger.nonce,
                entry_id: $btn.data('id')
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).removeClass('ak-loading');
                }
            },
            error: function() {
                alert(akDebtLedger.strings.error);
                $btn.prop('disabled', false).removeClass('ak-loading');
            }
        });
    });

    // Status filter
    $('#ak-status-filter').on('change', function() {
        var status = $(this).val();
        
        if (status) {
            $('.ak-ledger-table tbody tr').hide();
            $('.ak-ledger-table tbody tr[data-status="' + status + '"]').show();
        } else {
            $('.ak-ledger-table tbody tr').show();
        }
    });

    // Test Twilio connection
    $('#ak-test-twilio').on('click', function() {
        var $btn = $(this);
        var $result = $('#ak-twilio-test-result');
        
        $btn.prop('disabled', true);
        $result.removeClass('success error').text('Testing...');
        
        $.ajax({
            url: akDebtLedger.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_test_twilio',
                nonce: akDebtLedger.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').text(response.data.message);
                } else {
                    $result.addClass('error').text(response.data.message);
                }
                $btn.prop('disabled', false);
            },
            error: function() {
                $result.addClass('error').text('Connection test failed');
                $btn.prop('disabled', false);
            }
        });
    });

    // Reset template button
    $(document).on('click', '.ak-reset-template', function() {
        var target = $(this).data('target');
        var defaultVal = $(this).data('default');
        $('#' + target).val(defaultVal);
    });

    // Use pre-made template
    $(document).on('click', '.ak-use-template', function() {
        var sms = $(this).data('sms');
        var subject = $(this).data('subject');
        var body = $(this).data('body');
        
        $('#sms_template').val(sms);
        $('#email_subject_template').val(subject);
        $('#email_body_template').val(body);
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#sms_template').offset().top - 100
        }, 500);
    });

    // SMS character counter
    function updateSmsCharCount() {
        var count = $('#sms_template').val().length;
        $('#sms-char-count').text(count);
        
        if (count > 160) {
            $('#sms-char-count').css('color', '#dc3545');
        } else {
            $('#sms-char-count').css('color', '');
        }
    }
    
    $('#sms_template').on('input', updateSmsCharCount);
    updateSmsCharCount(); // Initial count

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
