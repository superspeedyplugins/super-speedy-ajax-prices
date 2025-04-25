/**
 * WooCommerce AJAX Pricing JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize the AJAX pricing functionality
    function initAjaxPricing() {
        // Find all price placeholders on the page
        const priceElements = document.querySelectorAll('.ajax-price');
        
        if (!priceElements.length) {
            return;
        }
        
        // Collect product IDs from price placeholders
        const productIds = [];
        priceElements.forEach(function(element) {
            const productId = element.getAttribute('data-product-id');
            if (productId && !productIds.includes(productId)) {
                productIds.push(productId);
            }
        });
        
        if (!productIds.length) {
            return;
        }
        
        // Show loading indicator
        priceElements.forEach(function(element) {
            const placeholder = element.querySelector('.ajax-price-placeholder');
            if (placeholder) {
                placeholder.setAttribute('data-original-content', placeholder.innerHTML);
                placeholder.innerHTML = wc_ajax_pricing_params.loading_text;
            }
        });
        
        // Fetch prices via AJAX
        fetchPrices(productIds);
    }
    
    // Fetch prices via AJAX
    function fetchPrices(productIds) {
        $.ajax({
            url: wc_ajax_pricing_params.ajax_url,
            type: 'POST',
            data: {
                action: 'get_wc_prices',
                nonce: wc_ajax_pricing_params.nonce,
                product_ids: productIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.prices) {
                    updatePrices(response.data.prices);
                } else {
                    restoreOriginalPrices();
                }
            },
            error: function() {
                restoreOriginalPrices();
            }
        });
    }
    
    // Update prices in the DOM
    function updatePrices(prices) {
        const priceElements = document.querySelectorAll('.ajax-price');
        
        priceElements.forEach(function(element) {
            const productId = element.getAttribute('data-product-id');
            const placeholder = element.querySelector('.ajax-price-placeholder');
            
            if (productId && placeholder && prices[productId]) {
                // Update the price HTML
                placeholder.innerHTML = prices[productId].price_html;
                
                // Add class to signify the price is loaded
                element.classList.add('ajax-price-loaded');
            } else if (placeholder) {
                // Restore original content if price not found
                restoreOriginalPrice(placeholder);
            }
        });
        
        // Trigger event for other scripts that might need to react to price updates
        $(document.body).trigger('wc_ajax_pricing_updated');
    }
    
    // Restore original prices if the AJAX request fails
    function restoreOriginalPrices() {
        const placeholders = document.querySelectorAll('.ajax-price-placeholder');
        
        placeholders.forEach(function(placeholder) {
            restoreOriginalPrice(placeholder);
        });
    }
    
    // Restore original price for a single placeholder
    function restoreOriginalPrice(placeholder) {
        const originalContent = placeholder.getAttribute('data-original-content');
        if (originalContent) {
            placeholder.innerHTML = originalContent;
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initAjaxPricing();
        
        // Re-initialize when fragments are refreshed (e.g., after adding to cart)
        $(document.body).on('wc_fragments_refreshed', function() {
            initAjaxPricing();
        });
        
        // Re-initialize when variation is selected on variable products
        $(document.body).on('found_variation', function() {
            // Short delay to allow variation price to update first
            setTimeout(function() {
                initAjaxPricing();
            }, 100);
        });
    });
    
})(jQuery);