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
     * Debug logging helper. Writes to the WooCommerce log file.
     * Remove or disable after debugging is complete.
     *
     * @param string $message The message to log.
     */
    private function debug_log( $message ) {
        if (!WC_AJAX_PRICING_VERBOSE_LOGGING) return;
        if ( 1==2 && function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->debug( $message, array( 'source' => 'super-speedy-tax-debug' ) );
        } else {
            error_log( '[SuperSpeedy Tax Debug] ' . $message );
        }
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
            $location = WC()->customer->get_taxable_address();
            $this->debug_log( 'get_customer_tax_location() => ' . wp_json_encode( $location ) );
            return $location;
        }
        $this->debug_log( 'get_customer_tax_location() => WC()->customer is empty, returning empty array' );
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
        $this->debug_log( '--- customer_is_tax_exempt_for_class() START --- tax_class="' . $tax_class . '"' );

        // Dump the full WC()->customer object for diagnosis.
        if ( ! empty( WC()->customer ) ) {
            $customer = WC()->customer;
            $this->debug_log( 'WC()->customer exists. Key properties:' );
            $this->debug_log( '  is_vat_exempt: ' . var_export( $customer->get_is_vat_exempt(), true ) );
            $this->debug_log( '  billing_country: ' . $customer->get_billing_country() );
            $this->debug_log( '  billing_state: ' . $customer->get_billing_state() );
            $this->debug_log( '  billing_postcode: ' . $customer->get_billing_postcode() );
            $this->debug_log( '  billing_city: ' . $customer->get_billing_city() );
            $this->debug_log( '  shipping_country: ' . $customer->get_shipping_country() );
            $this->debug_log( '  shipping_state: ' . $customer->get_shipping_state() );
            $this->debug_log( '  shipping_postcode: ' . $customer->get_shipping_postcode() );
            $this->debug_log( '  shipping_city: ' . $customer->get_shipping_city() );
            $this->debug_log( '  calculated_shipping: ' . var_export( $customer->has_calculated_shipping(), true ) );
            $this->debug_log( '  get_taxable_address(): ' . wp_json_encode( $customer->get_taxable_address() ) );
            $this->debug_log( '  customer ID: ' . $customer->get_id() );
        } else {
            $this->debug_log( 'WC()->customer is EMPTY/NULL' );
        }

        // Log WooCommerce tax settings.
        $this->debug_log( 'WC Settings:' );
        $this->debug_log( '  woocommerce_tax_based_on: ' . get_option( 'woocommerce_tax_based_on' ) );
        $this->debug_log( '  woocommerce_default_customer_address: ' . get_option( 'woocommerce_default_customer_address' ) );
        $this->debug_log( '  woocommerce_prices_include_tax: ' . get_option( 'woocommerce_prices_include_tax' ) );
        $this->debug_log( '  woocommerce_tax_display_shop: ' . get_option( 'woocommerce_tax_display_shop' ) );

        // Log base store location.
        $base_country = WC()->countries->get_base_country();
        $base_state   = WC()->countries->get_base_state();
        $this->debug_log( '  Base store country: ' . $base_country );
        $this->debug_log( '  Base store state: ' . $base_state );

        // Explicitly marked VAT-exempt (e.g. via EU VAT number plugin).
        if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) {
            $this->debug_log( 'RESULT: Customer is explicitly VAT-exempt => THIS IS WHERE THE WOO BUG EXISTS' );
            if (WC_AJAX_PRICING_TRUST_VAT_EXEMPT) {
                return true;
            }
        }

        // Resolve the customer's tax location.
        $location = $this->get_customer_tax_location();

        if ( empty( $location ) ) {
            $this->debug_log( 'RESULT: No tax location available => returning FALSE (not exempt)' );
            return false;
        }

        $this->debug_log( 'Resolved tax location: country=' . $location[0] . ', state=' . $location[1] . ', postcode=' . ( $location[2] ?? '' ) . ', city=' . ( $location[3] ?? '' ) );

        // If the customer location is the same as the base store location,
        // they are definitely not exempt (they pay the base tax).
        if ( $location[0] === $base_country && $location[1] === $base_state ) {
            $this->debug_log( 'RESULT: Customer location matches base store (country=' . $base_country . ', state=' . $base_state . ') => returning FALSE (not exempt)' );
            return false;
        }

        $this->debug_log( 'Customer location differs from base store. Looking up tax rates for customer location...' );

        // Look up tax rates for the customer's location.
        $sanitized_tax_class = sanitize_title( $tax_class );
        $this->debug_log( 'Calling WC_Tax::get_rates_from_location( tax_class="' . $sanitized_tax_class . '", location=' . wp_json_encode( $location ) . ' )' );

        $customer_rates = WC_Tax::get_rates_from_location(
            $sanitized_tax_class,
            $location
        );

        $this->debug_log( 'Customer tax rates found: ' . wp_json_encode( $customer_rates ) );
        $this->debug_log( 'Number of customer tax rates: ' . count( $customer_rates ) );

        // Also log what the base rates are for comparison.
        $base_rates = WC_Tax::get_base_tax_rates( $tax_class );
        $this->debug_log( 'Base store tax rates for comparison: ' . wp_json_encode( $base_rates ) );

        $is_exempt = empty( $customer_rates );
        $this->debug_log( 'RESULT: customer_is_tax_exempt_for_class => ' . var_export( $is_exempt, true ) );
        $this->debug_log( '--- customer_is_tax_exempt_for_class() END ---' );

        // If no rates match the customer's location, they are tax-exempt.
        return $is_exempt;
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
        static $call_count = 0;
        $call_count++;

        // Only log details for the first variation per page load to avoid flooding.
        $should_log = ( $call_count <= 3 );

        if ( $should_log ) {
            $current_filter = current_filter();
            $this->debug_log( '=== adjust_variation_price_for_tax_exempt() CALLED ===' );
            $this->debug_log( '  Filter: ' . $current_filter );
            $this->debug_log( '  Variation ID: ' . $variation->get_id() );
            $this->debug_log( '  Parent product ID: ' . $product->get_id() );
            $this->debug_log( '  Input price: ' . var_export( $price, true ) );
            $this->debug_log( '  wc_tax_enabled(): ' . var_export( wc_tax_enabled(), true ) );
            $this->debug_log( '  wc_prices_include_tax(): ' . var_export( wc_prices_include_tax(), true ) );
        }

        // Only act when prices were entered inclusive of tax.
        if ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) {
            if ( $should_log ) {
                $this->debug_log( '  EARLY EXIT: Tax not enabled or prices do not include tax' );
            }
            return $price;
        }

        // Nothing to adjust on empty prices.
        if ( '' === $price || ! is_numeric( $price ) ) {
            if ( $should_log ) {
                $this->debug_log( '  EARLY EXIT: Price is empty or non-numeric' );
            }
            return $price;
        }

        // Only adjust for taxable products.
        if ( ! $variation->is_taxable() ) {
            if ( $should_log ) {
                $this->debug_log( '  EARLY EXIT: Variation is not taxable' );
            }
            return $price;
        }

        // Check if the customer is effectively tax-exempt for this tax class.
        $tax_class = $variation->get_tax_class( 'unfiltered' );

        if ( $should_log ) {
            $this->debug_log( '  Variation tax_class (unfiltered): "' . $tax_class . '"' );
            $this->debug_log( '  Variation tax_status: ' . $variation->get_tax_status() );
        }

        if ( ! $this->customer_is_tax_exempt_for_class( $tax_class ) ) {
            if ( $should_log ) {
                $this->debug_log( '  RESULT: Customer is NOT tax-exempt => returning original price: ' . $price );
            }
            return $price;
        }

        // Get the base store tax rates for this product's tax class.
        $base_rates = WC_Tax::get_base_tax_rates( $tax_class );

        if ( $should_log ) {
            $this->debug_log( '  Customer IS tax-exempt. Base rates: ' . wp_json_encode( $base_rates ) );
        }

        if ( empty( $base_rates ) ) {
            if ( $should_log ) {
                $this->debug_log( '  RESULT: No base rates found => returning original price: ' . $price );
            }
            return $price;
        }

        // Calculate the tax that is embedded in the inclusive price and subtract it.
        $taxes      = WC_Tax::calc_tax( (float) $price, $base_rates, true );
        $tax_amount = array_sum( $taxes );
        $new_price  = (float) $price - $tax_amount;

        if ( $should_log ) {
            $this->debug_log( '  Taxes calculated (inclusive): ' . wp_json_encode( $taxes ) );
            $this->debug_log( '  Tax amount to subtract: ' . $tax_amount );
            $this->debug_log( '  RESULT: Adjusted price: ' . $price . ' - ' . $tax_amount . ' = ' . $new_price );
        }

        return $new_price;
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
        $location_string = 'tax_location_' . implode( '_', $location );
        $price_hash[] = $location_string;

        $this->debug_log( 'add_tax_location_to_prices_hash() => added "' . $location_string . '" to hash for product #' . $product->get_id() . ' (for_display=' . var_export( $for_display, true ) . ')' );

        return $price_hash;
    }

}