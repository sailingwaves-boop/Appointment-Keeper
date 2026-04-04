/**
 * Credits Store JS
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Handle buy button clicks
        $('.ak-buy-btn').on('click', function() {
            var btn = $(this);
            var packId = btn.data('pack');
            
            btn.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: akStoreData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ak_purchase_credits',
                    pack: packId,
                    nonce: akStoreData.nonce
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        // Redirect to Stripe Checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data.message || 'Something went wrong');
                        btn.prop('disabled', false).text('Buy Now');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                    btn.prop('disabled', false).text('Buy Now');
                }
            });
        });
        
        // Check for success/cancelled URL params
        var urlParams = new URLSearchParams(window.location.search);
        var purchaseStatus = urlParams.get('purchase');
        
        if (purchaseStatus === 'success') {
            // Show success message
            var successHtml = '<div class="ak-store-message success">' +
                '<strong>🎉 Purchase Successful!</strong><br>' +
                'Your credits have been added to your account.' +
                '</div>';
            $('.ak-store-header').after(successHtml);
            
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (purchaseStatus === 'cancelled') {
            var cancelHtml = '<div class="ak-store-message error">' +
                'Purchase was cancelled. No charges were made.' +
                '</div>';
            $('.ak-store-header').after(cancelHtml);
            
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        
    });
    
})(jQuery);
