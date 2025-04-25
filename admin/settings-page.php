<?php
/**
 * Admin settings page for WooCommerce AJAX Pricing
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu item
 */
function wc_ajax_pricing_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __('Super Speedy AJAX Prices', 'wc-ajax-pricing'),
        __('AJAX Pricing', 'wc-ajax-pricing'),
        'manage_options',
        'wc-ajax-pricing-settings',
        'wc_ajax_pricing_render_settings_page'
    );
}
add_action('admin_menu', 'wc_ajax_pricing_add_admin_menu');

/**
 * Add settings link on plugin page
 *
 * @param array $links Existing links
 * @return array Modified links
 */
function wc_ajax_pricing_add_plugin_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-ajax-pricing-settings') . '">' .
        __('Settings', 'wc-ajax-pricing') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_super-speedy-ajax-prices/super-speedy-ajax-prices.php',
    'wc_ajax_pricing_add_plugin_settings_link');

/**
 * Render settings page
 */
function wc_ajax_pricing_render_settings_page() {
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
        
        <hr>
        
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
    <?php
}

/**
 * Register plugin settings
 */
function wc_ajax_pricing_register_settings() {
    // Register settings section
    add_settings_section(
        'wc_ajax_pricing_general_section',
        __('General Settings', 'wc-ajax-pricing'),
        'wc_ajax_pricing_render_general_section',
        'wc_ajax_pricing_settings'
    );
    
    // Register setting for disabling AJAX pricing when cart has items
    register_setting(
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_disable_with_cart',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no'
        )
    );
    
    // Add field for disabling AJAX pricing when cart has items
    add_settings_field(
        'wc_ajax_pricing_disable_with_cart',
        __('Disable when cart has items', 'wc-ajax-pricing'),
        'wc_ajax_pricing_render_disable_with_cart_field',
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_general_section'
    );
    
    // Register setting for disabling AJAX pricing for logged in users
    register_setting(
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_disable_for_logged_in',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no'
        )
    );
    
    // Add field for disabling AJAX pricing for logged in users
    add_settings_field(
        'wc_ajax_pricing_disable_for_logged_in',
        __('Disable for logged in users', 'wc-ajax-pricing'),
        'wc_ajax_pricing_render_disable_for_logged_in_field',
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_general_section'
    );
    
    // Register setting for loading text
    register_setting(
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_loading_text',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __('Loading...', 'wc-ajax-pricing')
        )
    );
    
    // Add field for loading text
    add_settings_field(
        'wc_ajax_pricing_loading_text',
        __('Loading Text', 'wc-ajax-pricing'),
        'wc_ajax_pricing_render_loading_text_field',
        'wc_ajax_pricing_settings',
        'wc_ajax_pricing_general_section'
    );
}
add_action('admin_init', 'wc_ajax_pricing_register_settings');

/**
 * Render general section description
 */
function wc_ajax_pricing_render_general_section() {
    echo '<p>' . __('Configure when AJAX pricing should be disabled. Typically, page caching will be OFF when either the user is logged in or when items are added to the cart, so you can then switch off the ajax pricing in those cases.', 'wc-ajax-pricing') . '</p>';
}

/**
 * Render field for disabling AJAX pricing when cart has items
 */
function wc_ajax_pricing_render_disable_with_cart_field() {
    $value = get_option('wc_ajax_pricing_disable_with_cart', 'no');
    ?>
    <label>
        <input type="checkbox" name="wc_ajax_pricing_disable_with_cart" value="yes" <?php checked('yes', $value); ?> />
        <?php _e('Disable AJAX prices once a user has added an item to cart', 'wc-ajax-pricing'); ?>
    </label>
    <p class="description">
        <?php _e('When enabled, regular pricing will be used instead of AJAX pricing once a user has items in their cart. This is useful because most page cache systems disable caching for users with items in cart.', 'wc-ajax-pricing'); ?>
    </p>
    <?php
}

/**
 * Render field for disabling AJAX pricing for logged in users
 */
function wc_ajax_pricing_render_disable_for_logged_in_field() {
    $value = get_option('wc_ajax_pricing_disable_for_logged_in', 'no');
    ?>
    <label>
        <input type="checkbox" name="wc_ajax_pricing_disable_for_logged_in" value="yes" <?php checked('yes', $value); ?> />
        <?php _e('Disable AJAX prices for logged in users', 'wc-ajax-pricing'); ?>
    </label>
    <p class="description">
        <?php _e('When enabled, regular pricing will be used instead of AJAX pricing for logged in users. This is useful because most page cache systems disable caching for logged in users.', 'wc-ajax-pricing'); ?>
    </p>
    <?php
}

/**
 * Render field for loading text
 */
function wc_ajax_pricing_render_loading_text_field() {
    $value = get_option('wc_ajax_pricing_loading_text', __('Loading...', 'wc-ajax-pricing'));
    ?>
    <input type="text" name="wc_ajax_pricing_loading_text" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <p class="description">
        <?php _e('Text to display while prices are loading via AJAX. Default: "Loading..."', 'wc-ajax-pricing'); ?>
    </p>
    <?php
}

?>