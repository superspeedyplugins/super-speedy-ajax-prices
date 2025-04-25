# Super Speedy AJAX Prices

A WordPress plugin that enables AJAX-based pricing for WooCommerce, allowing you to use full page caching while still displaying accurate, personalized pricing to each visitor.

## Features

- **Full Page Caching Compatible**: Works seamlessly with popular caching plugins
- **Uses Existing WooCommerce Functionality**: Leverages WooCommerce's built-in geolocation and pricing rules
- **Performance Optimized**: Batch-loads all prices in a single AJAX request
- **Variable Products Support**: Works with simple and variable products
- **Fallback Mechanism**: Shows cached prices if AJAX fails
- **Developer Friendly**: Includes hooks for extending functionality

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wc-ajax-pricing` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your caching plugin to cache your WooCommerce pages

## Usage

Once activated, the plugin automatically:

1. Replaces price display with placeholders during initial page load
2. Fetches real-time pricing data via AJAX after the page loads
3. Updates the price display with accurate, user-specific pricing

No configuration is required for basic functionality. The plugin works with:
- Different currencies
- Tax calculations
- Sales and discounts
- WooCommerce pricing extensions

## How It Works

### Technical Architecture

1. **Initial Page Load**:
   - The page is served from cache with price placeholders
   - The user gets fast page loading experience

2. **After Page Load**:
   - JavaScript identifies all price elements on the page
   - Collects product IDs for all visible products
   - Makes a single AJAX request to fetch current prices

3. **Server-Side Processing**:
   - WooCommerce calculates current prices based on:
     - User location (using WooCommerce's geolocation)
     - Current sales and promotions
     - User-specific pricing rules
     - Selected currency

4. **Price Update**:
   - Received prices are displayed, replacing placeholders
   - The page now shows accurate pricing while benefiting from caching

## Compatibility

- WordPress 5.0+
- WooCommerce 3.0+
- Works with most themes and WooCommerce extensions
- Compatible with popular caching plugins including WP Rocket, W3 Total Cache, and LiteSpeed Cache

## Extending

Developers can use these action hooks to extend functionality:

- `wc_ajax_pricing_before_fetch`: Fires before prices are fetched via AJAX
- `wc_ajax_pricing_after_update`: Fires after prices are updated in the DOM

## License

GPL v2 or later