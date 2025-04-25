<?php
/**
 * Admin settings page for WooCommerce AJAX Pricing
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        // Output security fields
        settings_fields('wc_ajax_pricing_settings');
        // Output setting sections and their fields
        do_settings_sections('wc_ajax_pricing_settings');
        // Output submit button
        submit_button();
        ?>
    </form>
    
    <div class="card">
        <h2><?php _e('How to Use', 'wc-ajax-pricing'); ?></h2>
        <p><?php _e('This plugin automatically enables AJAX pricing for your WooCommerce store to work with full page caching.', 'wc-ajax-pricing'); ?></p>
        <p><?php _e('Key features:', 'wc-ajax-pricing'); ?></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php _e('Displays cached page content quickly while loading prices via AJAX', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Works with WooCommerce\'s existing geolocation for country-based pricing', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Compatible with sales, dynamic pricing, and other price modifications', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Optimized for minimal performance impact', 'wc-ajax-pricing'); ?></li>
        </ul>
        
        <h3><?php _e('Caching Plugin Recommendations', 'wc-ajax-pricing'); ?></h3>
        <p><?php _e('For best results, configure your caching plugin with these settings:', 'wc-ajax-pricing'); ?></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php _e('Enable full page caching', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Exclude WooCommerce cart, checkout, and account pages from caching', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Do NOT exclude product or shop pages (these should be cached)', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Configure your caching plugin to not cache AJAX requests', 'wc-ajax-pricing'); ?></li>
        </ul>
    </div>
    
    <div class="card">
        <h2><?php _e('Troubleshooting', 'wc-ajax-pricing'); ?></h2>
        <p><?php _e('If you experience issues with the AJAX pricing:', 'wc-ajax-pricing'); ?></p>
        <ol style="list-style-type: decimal; margin-left: 20px;">
            <li><?php _e('Check your browser console for JavaScript errors', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Ensure your theme is not overriding WooCommerce price HTML in an incompatible way', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Verify your caching plugin is configured correctly (see recommendations above)', 'wc-ajax-pricing'); ?></li>
            <li><?php _e('Temporarily disable other WooCommerce extensions to check for conflicts', 'wc-ajax-pricing'); ?></li>
        </ol>
    </div>
</div>