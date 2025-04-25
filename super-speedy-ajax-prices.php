<?php
/**
 * Plugin Name: Super Speedy AJAX Prices
 * Description: Enables AJAX pricing for WooCommerce to work with full page caching
 * Version: 1.0.1
 * Author: Dave Hilditch
 * Text Domain: wc-ajax-pricing
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_AJAX_PRICING_PATH', plugin_dir_path(__FILE__));
define('WC_AJAX_PRICING_URL', plugin_dir_url(__FILE__));
define('WC_AJAX_PRICING_VERSION', '1.0.0');

// Include required files
require_once WC_AJAX_PRICING_PATH . 'includes/class-wc-ajax-pricing.php';

// Initialize the main plugin class
function wc_ajax_pricing_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_ajax_pricing_woocommerce_missing_notice');
        return;
    }
    
    // Initialize the plugin
    $wc_ajax_pricing = new WC_AJAX_Pricing();
    $wc_ajax_pricing->init();
}
add_action('plugins_loaded', 'wc_ajax_pricing_init');

// Admin notice for missing WooCommerce
function wc_ajax_pricing_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce AJAX Pricing requires WooCommerce to be installed and active.', 'wc-ajax-pricing'); ?></p>
    </div>
    <?php
}