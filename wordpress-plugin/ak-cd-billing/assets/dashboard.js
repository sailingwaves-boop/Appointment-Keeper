/**
 * AppointmentKeeper Customer Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Tab switching
    $('.ak-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update button states
        $('.ak-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Update content
        $('.ak-tab-content').removeClass('active');
        $('#ak-tab-' + tab).addClass('active');
    });

    // Copy share link
    $('.ak-share-link').on('click', function() {
        $(this).select();
        document.execCommand('copy');
        
        var $this = $(this);
        var originalValue = $this.val();
        $this.val('Copied!');
        setTimeout(function() {
            $this.val(originalValue);
        }, 1500);
    });

})(jQuery);
