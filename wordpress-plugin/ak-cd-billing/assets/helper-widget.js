/**
 * AppointmentKeeper Helper Widget JS
 */

(function($) {
    'use strict';
    
    var Helper = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Toggle panel
            $('#ak-helper-toggle').on('click', this.togglePanel);
            $('#ak-helper-close').on('click', this.closePanel);
            
            // Quick actions
            $('.ak-helper-action-btn').on('click', this.handleAction);
            
            // Send button
            $('#ak-helper-send').on('click', this.handleInput);
            $('#ak-helper-input').on('keypress', function(e) {
                if (e.which === 13) {
                    Helper.handleInput();
                }
            });
            
            // Click outside to close
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.ak-helper-panel, .ak-helper-toggle').length) {
                    Helper.closePanel();
                }
            });
        },
        
        togglePanel: function() {
            $('#ak-helper-panel').toggleClass('active');
            $('#ak-helper-toggle').toggleClass('active');
        },
        
        closePanel: function() {
            $('#ak-helper-panel').removeClass('active');
            $('#ak-helper-toggle').removeClass('active');
        },
        
        handleAction: function() {
            var action = $(this).data('action');
            var dynamicArea = $('#ak-helper-dynamic');
            
            dynamicArea.html('<div style="text-align:center;padding:20px;color:#888;">Loading...</div>').show();
            
            switch(action) {
                case 'view-appointments':
                    Helper.loadAppointments();
                    break;
                    
                case 'send-reminder':
                    Helper.showReminderForm();
                    break;
                    
                case 'send-directions':
                    Helper.showDirectionsForm();
                    break;
                    
                case 'make-call':
                    Helper.showCallForm();
                    break;
                    
                case 'book-appointment':
                    // Redirect to booking page
                    window.location.href = akHelperData.upgradeUrl.replace('/choose-plan', '/booking');
                    break;
            }
        },
        
        loadAppointments: function() {
            $.ajax({
                url: akHelperData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_helper_get_appointments',
                    nonce: akHelperData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#ak-helper-dynamic').html(response.data.html);
                        Helper.bindAppointmentActions();
                    } else {
                        $('#ak-helper-dynamic').html('<div class="ak-helper-message error">' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $('#ak-helper-dynamic').html('<div class="ak-helper-message error">Connection error</div>');
                }
            });
        },
        
        bindAppointmentActions: function() {
            // Bind reminder buttons
            $('.ak-send-reminder-btn').off('click').on('click', function() {
                var card = $(this).closest('.ak-apt-card');
                var service = card.find('.ak-apt-service').text();
                var datetime = card.find('.ak-apt-datetime').text();
                
                Helper.showReminderForm(service + ' - ' + datetime);
            });
            
            // Bind directions buttons
            $('.ak-send-directions-btn').off('click').on('click', function() {
                var address = $(this).data('address');
                Helper.showDirectionsForm(address);
            });
        },
        
        showReminderForm: function(prefillMessage) {
            var defaultMsg = prefillMessage ? 
                'Reminder: You have ' + prefillMessage + ' coming up. See you soon!' :
                'This is a friendly reminder about your upcoming appointment.';
            
            var html = '<div class="ak-helper-form">' +
                '<h4 style="margin:0 0 15px 0;color:#1e3a5f;">📱 Send SMS Reminder</h4>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Phone Number</label>' +
                    '<input type="tel" id="ak-reminder-phone" placeholder="+44 7700 900123">' +
                '</div>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Message</label>' +
                    '<textarea id="ak-reminder-message">' + defaultMsg + '</textarea>' +
                '</div>' +
                '<button class="ak-helper-submit-btn" id="ak-send-reminder-submit">Send Reminder</button>' +
                '<div id="ak-reminder-result"></div>' +
            '</div>';
            
            $('#ak-helper-dynamic').html(html).show();
            
            $('#ak-send-reminder-submit').on('click', function() {
                Helper.sendReminder();
            });
        },
        
        sendReminder: function() {
            var phone = $('#ak-reminder-phone').val();
            var message = $('#ak-reminder-message').val();
            var btn = $('#ak-send-reminder-submit');
            
            if (!phone || !message) {
                $('#ak-reminder-result').html('<div class="ak-helper-message error">Please fill in all fields</div>');
                return;
            }
            
            btn.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: akHelperData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_helper_send_reminder',
                    phone: phone,
                    message: message,
                    nonce: akHelperData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#ak-reminder-result').html('<div class="ak-helper-message success">✓ ' + response.data.message + '</div>');
                        btn.text('Sent!');
                    } else {
                        $('#ak-reminder-result').html('<div class="ak-helper-message error">' + response.data.message + '</div>');
                        btn.prop('disabled', false).text('Send Reminder');
                    }
                },
                error: function() {
                    $('#ak-reminder-result').html('<div class="ak-helper-message error">Connection error</div>');
                    btn.prop('disabled', false).text('Send Reminder');
                }
            });
        },
        
        showDirectionsForm: function(prefillAddress) {
            var html = '<div class="ak-helper-form">' +
                '<h4 style="margin:0 0 15px 0;color:#1e3a5f;">🗺️ Send GPS Directions</h4>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Phone Number</label>' +
                    '<input type="tel" id="ak-directions-phone" placeholder="+44 7700 900123">' +
                '</div>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Destination Address/Postcode</label>' +
                    '<input type="text" id="ak-directions-address" value="' + (prefillAddress || '') + '" placeholder="W1A 1AA or full address">' +
                '</div>' +
                '<button class="ak-helper-submit-btn" id="ak-send-directions-submit">Send Directions</button>' +
                '<div id="ak-directions-result"></div>' +
            '</div>';
            
            $('#ak-helper-dynamic').html(html).show();
            
            $('#ak-send-directions-submit').on('click', function() {
                Helper.sendDirections();
            });
        },
        
        sendDirections: function() {
            var phone = $('#ak-directions-phone').val();
            var address = $('#ak-directions-address').val();
            var btn = $('#ak-send-directions-submit');
            
            if (!phone || !address) {
                $('#ak-directions-result').html('<div class="ak-helper-message error">Please fill in all fields</div>');
                return;
            }
            
            btn.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: akHelperData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_helper_send_directions',
                    phone: phone,
                    address: address,
                    nonce: akHelperData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#ak-directions-result').html('<div class="ak-helper-message success">✓ ' + response.data.message + '</div>');
                        btn.text('Sent!');
                    } else {
                        $('#ak-directions-result').html('<div class="ak-helper-message error">' + response.data.message + '</div>');
                        btn.prop('disabled', false).text('Send Directions');
                    }
                },
                error: function() {
                    $('#ak-directions-result').html('<div class="ak-helper-message error">Connection error</div>');
                    btn.prop('disabled', false).text('Send Directions');
                }
            });
        },
        
        showCallForm: function() {
            var html = '<div class="ak-helper-form">' +
                '<h4 style="margin:0 0 15px 0;color:#1e3a5f;">📞 Make AI Voice Call</h4>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Phone Number</label>' +
                    '<input type="tel" id="ak-call-phone" placeholder="+44 7700 900123">' +
                '</div>' +
                '<div class="ak-helper-form-group">' +
                    '<label>Message to Speak</label>' +
                    '<textarea id="ak-call-message" placeholder="Hello! This is a reminder about your appointment tomorrow at 10am. Please call us if you need to reschedule."></textarea>' +
                '</div>' +
                '<div class="ak-helper-form-group">' +
                    '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">' +
                        '<input type="checkbox" id="ak-call-ai-voice" checked> Use AI voice (ElevenLabs)' +
                    '</label>' +
                '</div>' +
                '<button class="ak-helper-submit-btn" id="ak-make-call-submit">Make Call</button>' +
                '<div id="ak-call-result"></div>' +
            '</div>';
            
            $('#ak-helper-dynamic').html(html).show();
            
            $('#ak-make-call-submit').on('click', function() {
                Helper.makeCall();
            });
        },
        
        makeCall: function() {
            var phone = $('#ak-call-phone').val();
            var message = $('#ak-call-message').val();
            var aiVoice = $('#ak-call-ai-voice').is(':checked');
            var btn = $('#ak-make-call-submit');
            
            if (!phone || !message) {
                $('#ak-call-result').html('<div class="ak-helper-message error">Please fill in all fields</div>');
                return;
            }
            
            btn.prop('disabled', true).text('Calling...');
            
            $.ajax({
                url: akHelperData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_helper_make_call',
                    phone: phone,
                    message: message,
                    ai_voice: aiVoice ? 'true' : 'false',
                    nonce: akHelperData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#ak-call-result').html('<div class="ak-helper-message success">✓ ' + response.data.message + '</div>');
                        btn.text('Call Started!');
                    } else {
                        $('#ak-call-result').html('<div class="ak-helper-message error">' + response.data.message + '</div>');
                        btn.prop('disabled', false).text('Make Call');
                    }
                },
                error: function() {
                    $('#ak-call-result').html('<div class="ak-helper-message error">Connection error</div>');
                    btn.prop('disabled', false).text('Make Call');
                }
            });
        },
        
        handleInput: function() {
            var input = $('#ak-helper-input');
            var message = input.val().trim().toLowerCase();
            
            if (!message) return;
            
            input.val('');
            
            // Simple command parsing
            if (message.includes('appointment') || message.includes('booking')) {
                Helper.loadAppointments();
            } else if (message.includes('sms') || message.includes('text') || message.includes('remind')) {
                Helper.showReminderForm();
            } else if (message.includes('direction') || message.includes('map') || message.includes('gps')) {
                Helper.showDirectionsForm();
            } else if (message.includes('call') || message.includes('phone')) {
                Helper.showCallForm();
            } else {
                $('#ak-helper-dynamic').html(
                    '<div style="padding:15px;text-align:center;color:#666;">' +
                    '<p>Try commands like:</p>' +
                    '<p style="font-size:13px;">"view appointments"<br>"send reminder"<br>"send directions"<br>"make call"</p>' +
                    '</div>'
                ).show();
            }
        }
    };
    
    $(document).ready(function() {
        Helper.init();
    });
    
})(jQuery);
