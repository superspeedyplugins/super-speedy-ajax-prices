=== Super Speedy AJAX Prices ===
Contributors: dhilditch
Donate link: https://www.superspeedyplugins.com/
Tags: ajax, pricing, woocommerce, caching, speed
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.6
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that enables AJAX-based pricing for WooCommerce, allowing you to use full page caching while still displaying accurate, personalised pricing to each visitor.

== Description ==

A WordPress plugin that enables AJAX-based pricing for WooCommerce, allowing you to use full page caching while still displaying accurate, personalised pricing to each visitor.

Works seamlessly with popular caching plugins by replacing price display with placeholders on the cached page, then batch-fetching real-time pricing via a single AJAX request after page load. Leverages WooCommerce's built-in geolocation and pricing rules, and supports simple and variable products.

== Frequently Asked Questions ==

= Does this work with full page caching? =

Yes, that is the primary use case. Configure your caching plugin to cache WooCommerce product and shop pages, and this plugin will load accurate prices via AJAX after the cached page loads.

= Does it work with variable products? =

Yes, both simple and variable products are supported.

== Changelog ==

= 1.0.6 (26th February 2026) =
* Added super-speedy-settings integration for licence management and auto-updates
* Moved settings page under Super Speedy top-level menu

= 1.0.5 (26th February 2026) =
* Added admin settings page
* Added auto-update support

= 1.0.4 (26th February 2026) =
* Added VAT exempt logic for price determination to improve pricing for large EU stores who sell outside EU
* Fixed detection of when a user is truly tax exempt

= 1.0.3 (25th February 2026) =
* Reworked variation price override to correctly handle tax-exempt locations

= 1.0.2 (23rd February 2026) =
* Added user context to variation prices cache hash to fix variation pricing for tax-exempt locations

= 1.0.1 (25th April 2025) =
* Added option to disable AJAX pricing when cart has items or user is logged in

= 1.0.0 (25th April 2025) =
* Initial release
