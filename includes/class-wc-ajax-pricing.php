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
    
}