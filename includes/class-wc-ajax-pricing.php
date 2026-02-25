<?php
/**
 * Main plugin class
 */
class WC_AJAX_Pricing {
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load plugin textdomain
        add_action('init', array($this, 'load_textdomain'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'), 10);
        
        // Filter WooCommerce price HTML to add placeholders
        add_filter('woocommerce_get_price_html', array($this, 'replace_price_with_placeholder'), 10, 2);
        
        // Register AJAX handlers
        add_action('wp_ajax_get_wc_prices', array($this, 'get_ajax_prices'));
        add_action('wp_ajax_nopriv_get_wc_prices', array($this, 'get_ajax_prices'));

        // Fix variation prices for tax-exempt locations.
        // When prices are entered inclusive of tax and the customer is in a
        // location with no tax, the base-country tax must be subtracted from
        // the raw variation prices so archive/product pages show the correct
        // ex-tax amount. We hook all three price filters and the hash filter.
        add_filter('woocommerce_variation_prices_price', array($this, 'adjust_variation_price_for_tax_exempt'), 10, 3);
        add_filter('woocommerce_variation_prices_regular_price', array($this, 'adjust_variation_price_for_tax_exempt'), 10, 3);
        add_filter('woocommerce_variation_prices_sale_price', array($this, 'adjust_variation_price_for_tax_exempt'), 10, 3);
        add_filter('woocommerce_get_variation_prices_hash', array($this, 'add_tax_location_to_prices_hash'), 10, 3);

        // Include admin settings
        require_once WC_AJAX_PRICING_PATH . 'admin/settings-page.php';
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-ajax-pricing', false, dirname(plugin_basename(WC_AJAX_PRICING_PATH)) . '/languages');
    }
    
    /**
     * Register scripts and styles
     */
    public function register_scripts() {
        // Skip admin area
        if (is_admin()) {
            return;
        }
        
        // Register and enqueue the main script
        wp_register_script(
            'wc-ajax-pricing',
            WC_AJAX_PRICING_URL . 'assets/js/wc-ajax-pricing.js',
            array('jquery'),
            WC_AJAX_PRICING_VERSION,
            true
        );
        
        // Localize the script with necessary data
        wp_localize_script('wc-ajax-pricing', 'wc_ajax_pricing_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc-ajax-pricing-nonce'),
            'loading_text' => get_option('wc_ajax_pricing_loading_text', __('Loading...', 'wc-ajax-pricing')),
            'disable_with_cart' => get_option('wc_ajax_pricing_disable_with_cart', 'no')
            // We don't include dynamic user data here as it would be cached
        ));
        
        // Enqueue the script only on relevant pages
        if (is_woocommerce() || is_product() || is_shop() || is_product_category() || is_product_tag()) {
            wp_enqueue_script('wc-ajax-pricing');
        }
    }
    
    /**
     * Replace price HTML with a placeholder
     * 
     * @param string $price_html The price HTML
     * @param object $product The product object
     * @return string Modified price HTML
     */
    public function replace_price_with_placeholder($price_html, $product) {
        // Skip in admin area
        if (is_admin()) {
            return $price_html;
        }
        
        // Check if we should disable AJAX pricing for logged in users
        if (get_option('wc_ajax_pricing_disable_for_logged_in', 'no') === 'yes' && is_user_logged_in()) {
            return $price_html;
        }
        
        // Check if we should disable AJAX pricing when cart has items
        if (get_option('wc_ajax_pricing_disable_with_cart', 'no') === 'yes' && WC()->cart && !WC()->cart->is_empty()) {
            return $price_html;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // Generate a unique class for variable products
        $variation_class = '';
        if ($product->is_type('variable')) {
            $variation_class = ' ajax-price-variable';
        }
        
        // Create placeholder
        $placeholder = '<span class="ajax-price' . $variation_class . '" data-product-id="' . esc_attr($product_id) . '">';
        $placeholder .= '<span class="ajax-price-placeholder">' . $price_html . '</span>';
        $placeholder .= '</span>';
        
        return $placeholder;
    }
    
    /**
     * AJAX handler for getting prices
     */
    public function get_ajax_prices() {
        // Verify nonce
        check_ajax_referer('wc-ajax-pricing-nonce', 'nonce');

        // Ensure VAT exempt status is set before calculating prices.
        // The 'wp' action does not fire during AJAX requests, so we must
        // call the geolocation-based VAT exempt check here explicitly.
        
        // Get product IDs from request
        $product_ids = isset($_POST['product_ids']) ? array_map('absint', $_POST['product_ids']) : array();
        
        if (empty($product_ids)) {
            wp_send_json_error(array('message' => __('No product IDs provided', 'wc-ajax-pricing')));
            return;
        }
        
        $prices = array();
        
        // Get prices for each product
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            // Get the price HTML - this will use WooCommerce's existing
            // geolocation and all pricing rules
            $price_html = $product->get_price_html();
            
            $prices[$product_id] = array(
                'price_html' => $price_html,
                'is_variable' => $product->is_type('variable')
            );
        }
        
        wp_send_json_success(array('prices' => $prices));
    }

    /**
     * Determine the customer's tax location based on WooCommerce configuration.
     *
     * Respects the 'woocommerce_tax_based_on' setting (shipping, billing, or base)
     * and falls back to the WC_Customer object when available (which handles
     * geolocation for guests via the 'woocommerce_default_customer_address' option).
     *
     * @return array Tax location as [ country, state, postcode, city ], or empty array.
     */
    private function get_customer_tax_location() {
        if ( ! empty( WC()->customer ) ) {
            return WC()->customer->get_taxable_address();
        }
        return array();
    }

    /**
     * Check whether the customer in their current tax location is effectively
     * tax-exempt for a given tax class.
     *
     * A customer is considered tax-exempt when:
     *  - They are explicitly marked VAT-exempt on the WC_Customer object, OR
     *  - Their resolved tax location yields zero matching tax rates for the
     *    product's tax class (e.g. shipping to a country with no tax records).
     *
     * @param string $tax_class The product tax class (empty string = standard).
     * @return bool True if the customer should not be charged tax.
     */
    private function customer_is_tax_exempt_for_class( $tax_class = '' ) {
        // Explicitly marked VAT-exempt (e.g. via EU VAT number plugin).
        if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) {
            return true;
        }

        // Resolve the customer's tax location.
        $location = $this->get_customer_tax_location();

        if ( empty( $location ) ) {
            // No location available — cannot determine; assume not exempt.
            return false;
        }

        // If the customer location is the same as the base store location,
        // they are definitely not exempt (they pay the base tax).
        $base_country  = WC()->countries->get_base_country();
        $base_state    = WC()->countries->get_base_state();

        if ( $location[0] === $base_country && $location[1] === $base_state ) {
            return false;
        }

        // Look up tax rates for the customer's location.
        $customer_rates = WC_Tax::get_rates_from_location(
            sanitize_title( $tax_class ),
            $location
        );

        // If no rates match the customer's location, they are tax-exempt.
        return empty( $customer_rates );
    }

    /**
     * Adjust a variation's raw price when the customer is in a tax-exempt location.
     *
     * This filter runs on 'woocommerce_variation_prices_price',
     * 'woocommerce_variation_prices_regular_price', and
     * 'woocommerce_variation_prices_sale_price'.
     *
     * When the store is configured to enter prices inclusive of tax
     * ('woocommerce_prices_include_tax' = 'yes') and the customer's resolved
     * tax location has no matching tax rates, the base-country tax component
     * must be removed from the stored price. This mirrors the logic that
     * wc_get_price_excluding_tax() uses (subtracting base tax rates) but
     * applies it at the variation-price-cache level so that archive pages and
     * product detail pages show the correct amount before the item reaches
     * the cart.
     *
     * @param string|float $price     The variation's raw price (inclusive of base tax).
     * @param WC_Product   $variation The variation product object.
     * @param WC_Product   $product   The parent variable product object.
     * @return string|float Adjusted price with base tax removed, or original price.
     */
    public function adjust_variation_price_for_tax_exempt( $price, $variation, $product ) {
        // Only act when prices were entered inclusive of tax.
        if ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) {
            return $price;
        }

        // Nothing to adjust on empty prices.
        if ( '' === $price || ! is_numeric( $price ) ) {
            return $price;
        }

        // Only adjust for taxable products.
        if ( ! $variation->is_taxable() ) {
            return $price;
        }

        // Check if the customer is effectively tax-exempt for this tax class.
        $tax_class = $variation->get_tax_class( 'unfiltered' );

        if ( ! $this->customer_is_tax_exempt_for_class( $tax_class ) ) {
            return $price;
        }

        // Get the base store tax rates for this product's tax class.
        $base_rates = WC_Tax::get_base_tax_rates( $tax_class );

        if ( empty( $base_rates ) ) {
            return $price;
        }

        // Calculate the tax that is embedded in the inclusive price and subtract it.
        $taxes      = WC_Tax::calc_tax( (float) $price, $base_rates, true );
        $tax_amount = array_sum( $taxes );

        return (float) $price - $tax_amount;
    }

    /**
     * Include the customer's tax location in the variation prices cache hash.
     *
     * Because our 'woocommerce_variation_prices_price' filter adjusts prices
     * based on the customer's geographic tax location, we must ensure that
     * different locations produce different cache keys. Otherwise a price
     * calculated for a Greek customer (with VAT) could be served from cache
     * to a US customer (without VAT), or vice-versa.
     *
     * @param array      $price_hash Array of factors used to build the cache key.
     * @param WC_Product $product    The variable product object.
     * @param bool       $for_display Whether prices are for display.
     * @return array Modified hash array.
     */
    public function add_tax_location_to_prices_hash( $price_hash, $product, $for_display ) {
        if ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) {
            return $price_hash;
        }

        $location = $this->get_customer_tax_location();

        // Add the full tax location so each unique location gets its own cache.
        $price_hash[] = 'tax_location_' . implode( '_', $location );

        return $price_hash;
    }

}