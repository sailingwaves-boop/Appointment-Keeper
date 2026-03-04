/**
 * AK Debt Ledger Frontend JavaScript
 */

(function($) {
    'use strict';

    // Toggle add debt form
    $('#ak-add-debt-btn').on('click', function() {
        $('#ak-add-debt-form').slideDown();
        $(this).hide();
    });

    $('#ak-cancel-add').on('click', function() {
        $('#ak-add-debt-form').slideUp();
        $('#ak-add-debt-btn').show();
    });

    // Number of debtors dropdown
    $('#ak-num-debtors').on('change', function() {
        var num = parseInt($(this).val());
        
        $('.ak-extra-debtor').hide();
        
        if (num >= 2) {
            $('#ak-debtor-2').show();
        }
        if (num >= 3) {
            $('#ak-debtor-3').show();
        }
    });

    // Number of friends dropdown
    $('#ak-num-friends').on('change', function() {
        var num = parseInt($(this).val());
        
        if (num === 0) {
            $('#ak-friend-fields').hide();
        } else {
            $('#ak-friend-fields').show();
            $('.ak-friend-field').hide();
            
            for (var i = 1; i <= num; i++) {
                $('#ak-friend-' + i).show();
            }
        }
    });

    // Submit debt form
    $('#ak-debt-form-frontend').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        
        $submit.prop('disabled', true).text(akDebtLedgerFront.strings.sending);
        
        var formData = {
            action: 'ak_save_debt',
            nonce: akDebtLedgerFront.nonce,
            creditor_name: $form.find('[name="creditor_name"]').val(),
            num_debtors: $form.find('[name="num_debtors"]').val(),
            customer_name: $form.find('[name="customer_name"]').val(),
            customer_phone: $form.find('[name="customer_phone"]').val(),
            customer_email: $form.find('[name="customer_email"]').val(),
            debtor2_name: $form.find('[name="debtor2_name"]').val(),
            debtor2_phone: $form.find('[name="debtor2_phone"]').val(),
            debtor3_name: $form.find('[name="debtor3_name"]').val(),
            debtor3_phone: $form.find('[name="debtor3_phone"]').val(),
            original_amount: $form.find('[name="original_amount"]').val(),
            currency: $form.find('[name="currency"]').val(),
            notes: $form.find('[name="notes"]').val(),
            remind_via_sms: $form.find('[name="remind_via_sms"]').is(':checked') ? 1 : 0,
            remind_via_email: $form.find('[name="remind_via_email"]').is(':checked') ? 1 : 0,
            remind_via_call: $form.find('[name="remind_via_call"]').is(':checked') ? 1 : 0,
            consent_to_reminders: $form.find('[name="consent_to_reminders"]').is(':checked') ? 1 : 0
        };
        
        $.ajax({
            url: akDebtLedgerFront.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $submit.prop('disabled', false).text('Add Debt');
                }
            },
            error: function() {
                alert(akDebtLedgerFront.strings.error);
                $submit.prop('disabled', false).text('Add Debt');
            }
        });
    });

    // Send reminder
    $(document).on('click', '.ak-send-reminder', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        
        $btn.prop('disabled', true).text(akDebtLedgerFront.strings.sending);
        
        $.ajax({
            url: akDebtLedgerFront.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_send_reminder',
                nonce: akDebtLedgerFront.nonce,
                entry_id: id
            },
            success: function(response) {
                if (response.success) {
                    $btn.text(akDebtLedgerFront.strings.sent);
                    if (response.data.credits_used) {
                        // Optionally update credit display
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).text('Send Reminder');
                }
            },
            error: function() {
                alert(akDebtLedgerFront.strings.error);
                $btn.prop('disabled', false).text('Send Reminder');
            }
        });
    });

    // Mark as paid
    $(document).on('click', '.ak-mark-paid', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        
        if (!confirm(akDebtLedgerFront.strings.confirmPayment)) {
            return;
        }
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: akDebtLedgerFront.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_mark_paid',
                nonce: akDebtLedgerFront.nonce,
                entry_id: id
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert(akDebtLedgerFront.strings.error);
                $btn.prop('disabled', false);
            }
        });
    });

    // Record payment (opens modal or inline form - simplified for now)
    $(document).on('click', '.ak-record-payment', function() {
        var id = $(this).data('id');
        var amount = prompt('Enter payment amount:');
        
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: akDebtLedgerFront.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ak_add_payment',
                nonce: akDebtLedgerFront.nonce,
                ledger_id: id,
                amount: parseFloat(amount),
                payment_date: new Date().toISOString().split('T')[0],
                method: 'other',
                reference: '',
                note: ''
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message + '\nNew balance: ' + response.data.new_balance);
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert(akDebtLedgerFront.strings.error);
                $btn.prop('disabled', false);
            }
        });
    });

    // Submit referral form
    $('#ak-refer-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        var numFriends = parseInt($form.find('[name="num_friends"]').val());
        
        if (numFriends < 1) {
            return;
        }
        
        $submit.prop('disabled', true).text(akDebtLedgerFront.strings.sending);
        
        var formData = {
            action: 'ak_send_referral',
            nonce: akDebtLedgerFront.nonce,
            num_friends: numFriends,
            friend1_name: $form.find('[name="friend1_name"]').val(),
            friend1_phone: $form.find('[name="friend1_phone"]').val(),
            friend2_name: $form.find('[name="friend2_name"]').val(),
            friend2_phone: $form.find('[name="friend2_phone"]').val(),
            friend3_name: $form.find('[name="friend3_name"]').val(),
            friend3_phone: $form.find('[name="friend3_phone"]').val()
        };
        
        $.ajax({
            url: akDebtLedgerFront.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                    $submit.prop('disabled', false).text('Send Invites');
                }
            },
            error: function() {
                alert(akDebtLedgerFront.strings.error);
                $submit.prop('disabled', false).text('Send Invites');
            }
        });
    });

})(jQuery);
