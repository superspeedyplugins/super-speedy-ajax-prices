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

        // Ensure variation price transients are keyed per user tax context.
        // WooCommerce 10.5 changed how the cache hash is generated (PR #61286):
        // it no longer serialises full callback objects, so user-specific state
        // (VAT exemption, tax location) no longer implicitly differentiates
        // cache entries. We must add that context explicitly so that two users
        // with different tax profiles cannot share the same cached prices.
        add_filter('woocommerce_get_variation_prices_hash', array($this, 'add_user_context_to_variation_prices_hash'), 10, 3);

        // Fix WooCommerce 10.4+ tax/price display bug.
        //
        // WooCommerce never automatically sets is_vat_exempt based on the
        // customer's location. When a customer is in a non-tax country (e.g.
        // UK/US for an EU shop) — whether via geolocation, manual address
        // change at checkout, or session data — WC_Tax::get_rates() returns
        // empty. The taxes_influence_price() method (added in WC 10.4)
        // incorrectly treats empty rates as "taxes don't matter" and reuses
        // the tax-inclusive cached price for non-tax visitors.
        //
        // This fix detects when a customer has no applicable tax rates but
        // the shop base location does, and sets them as VAT exempt. This
        // ensures correct price display for both simple and variable products
        // regardless of how the customer's location was determined.
        //
        // Hook on 'wp' for normal page loads (after WC customer is initialised).
        add_action( 'wp', array( $this, 'maybe_set_vat_exempt_from_geolocation' ), 20 );
        
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
        $this->maybe_set_vat_exempt_from_geolocation();
        
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
     * Detect when a customer should be VAT exempt and set the flag.
     *
     * WooCommerce never automatically sets is_vat_exempt based on the
     * customer's location — regardless of whether that location comes from
     * geolocation, a manual address change at checkout, or session data.
     * When the customer is in a country with no configured tax rates, this
     * causes two problems since WC 10.4:
     *
     * 1. Variable products: taxes_influence_price() returns false when
     *    WC_Tax::get_rates() is empty, causing the price cache to share
     *    entries between taxed (EU) and non-taxed (UK/US) visitors.
     *
     * 2. Simple products: wc_get_price_including_tax() checks is_vat_exempt
     *    to decide whether to strip VAT. Without this flag, it falls through
     *    to a secondary branch that only works on fresh calculations, not
     *    when prices are served from cache.
     *
     * This method checks whether the customer's tax rates are empty while
     * the shop base location has rates, and if so, sets the customer as
     * VAT exempt. This is safe because:
     * - It only runs when prices are entered inclusive of tax
     * - It only sets exempt when there genuinely are no tax rates for the
     *   customer's location but the base location does have rates
     * - The flag is session-based and does not persist to the database
     *
     * @since 1.0.3
     */
    public function maybe_set_vat_exempt_from_geolocation() {
        // Guard: need WooCommerce, tax enabled, and a customer object.
        if ( ! function_exists( 'WC' ) || ! wc_tax_enabled() || empty( WC()->customer ) ) {
            return;
        }

        // Only relevant when prices are entered inclusive of tax.
        if ( ! wc_prices_include_tax() ) {
            return;
        }

        // If the customer is already marked as VAT exempt (e.g. by another
        // plugin or a valid VAT number check), don't interfere.
        if ( WC()->customer->get_is_vat_exempt() ) {
            return;
        }

        // Get the tax rates that apply to the customer's current address.
        // We use the default (standard) tax class — if there are no standard
        // rates for the customer, there won't be reduced rates either.
        $customer_tax_rates = WC_Tax::get_rates( '' );

        // Get the base (shop) tax rates for comparison.
        $base_tax_rates = WC_Tax::get_base_tax_rates( '' );

        // If the customer has no applicable tax rates but the shop base does,
        // the customer is in a non-tax jurisdiction and should be VAT exempt.
        if ( empty( $customer_tax_rates ) && ! empty( $base_tax_rates ) ) {
            WC()->customer->set_is_vat_exempt( true );
        }
    }

    /**
     * Add user-specific tax context to the variation prices cache hash.
     *
     * WooCommerce 10.5 (PR #61286) changed hash generation so it no longer
     * serialises callback objects. Without this filter, users with different
     * VAT/tax profiles can collide on the same transient cache key and receive
     * incorrect prices. We add the minimal set of data that distinguishes one
     * tax profile from another so that each unique combination gets its own
     * cache entry while still allowing users with identical profiles to share.
     *
     * The address fields added to the hash respect the "Calculate tax based on"
     * setting (WooCommerce > Settings > Tax):
     * - "Customer shipping address" → shipping country + state
     * - "Customer billing address"  → billing country + state
     * - "Shop base address"         → no per-customer location needed (all
     *   customers share the same base tax rates)
     *
     * In all cases we include is_vat_exempt, which correctly reflects the
     * fix from maybe_set_vat_exempt_from_geolocation() because that method
     * runs on the 'wp' hook (priority 20) before prices are rendered, and
     * during AJAX it is called explicitly at the start of get_ajax_prices().
     *
     * @param array      $price_hash  Existing hash components.
     * @param WC_Product $product     The variable product.
     * @param bool       $for_display Whether prices are for display (inc. tax).
     * @return array
     */
    public function add_user_context_to_variation_prices_hash( $price_hash, $product, $for_display ) {
        if ( WC()->customer ) {
            $price_hash[] = (int) WC()->customer->get_is_vat_exempt();

            $tax_based_on = get_option( 'woocommerce_tax_based_on', 'shipping' );

            if ( 'billing' === $tax_based_on ) {
                $price_hash[] = WC()->customer->get_billing_country();
                $price_hash[] = WC()->customer->get_billing_state();
            } elseif ( 'base' !== $tax_based_on ) {
                // Default: shipping address (also the fallback for any
                // unrecognised value of the option).
                $price_hash[] = WC()->customer->get_shipping_country();
                $price_hash[] = WC()->customer->get_shipping_state();
            }
            // When 'base', all customers share the same shop base tax
            // location, so no per-customer location is needed in the hash.
        }

        return $price_hash;
    }

}