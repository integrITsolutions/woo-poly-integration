=== Woo-Poly Integration (Modernized Fork) ===
Contributors: IntegrITSolutions, hyyan
Tags: polylang, woocommerce, multilingual, hpos, block-cart
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0-alpha.1
License: MIT
License URI: https://opensource.org/licenses/MIT
WC requires at least: 9.0
WC tested up to: 10.7

Maintained fork of hyyan/woo-poly-integration for modern WooCommerce and Polylang stacks.

== Description ==

Woo-Poly Integration (Modernized Fork) is the maintained continuation of the original
`hyyan/woo-poly-integration` plugin (upstream last released as v1.5.1).

This fork keeps the original multilingual WooCommerce integration goals while adding
compatibility for current platforms:

- WordPress 6.6+
- WooCommerce 9.0+
- Polylang 3.7+ options-object API support
- PHP 7.4+
- HPOS-aware order language storage
- Cart/Checkout block compatibility

[Read the project docs](https://github.com/IntegrITSolutions/woo-poly-integration/wiki)

== Features ==

- Page translation for WooCommerce core pages
- WooCommerce endpoint translation
- Product translation support, including:
  - Categories
  - Tags
  - Attributes
  - Variations
  - Product galleries
  - Meta synchronization
- Cart synchronization and cart-item translation across languages
- Cart and Checkout block add-to-cart/cart-item compatibility
- Coupon synchronization and translation helpers
- Email language translation hooks
- Stock synchronization across translated products
- Order language storage with HPOS-aware dual-write behavior (`wc_orders_meta` + Polylang taxonomy):
  - Order meta key (`_hyyan_wpi_language`)
  - Polylang language taxonomy compatibility
- Polylang 3.7+ options-object support (with backward-compatible fallback)

== What's New in 2.0.0-alpha.1 ==

- Major modernization fork maintained by IntegrIT Solutions
- Compatibility baseline raised to WP 6.6+, WC 9.0+, PHP 7.4+
- HPOS compatibility declared and order language storage made HPOS-aware
- Cart/Checkout block compatibility added for Store API cart flows
- Polylang 3.7+ options-object API support added across settings integration
- Legacy WooCommerce analytics integration removed in v2

== What You Need To Know ==

1. Activate and configure both [Polylang](https://wordpress.org/plugins/polylang/) and [WooCommerce](https://wordpress.org/plugins/woocommerce/) before activating this plugin.
2. Translate WooCommerce pages in WordPress as part of your initial setup.
3. For variable products, set your default language before large catalog setup.
4. Polylang URL modification mode `The language is set from content` is not supported.

== Installation ==

= Standard install =

1. Download the latest fork release from:
   https://github.com/IntegrITSolutions/woo-poly-integration
2. In WordPress Admin, go to Plugins -> Add New -> Upload Plugin.
3. Upload the zip, install, and activate.

= Classical way =

1. Download the plugin zip from the fork repository.
2. Upload/extract it into your WordPress `wp-content/plugins/` directory.
3. Activate it in WordPress Admin.

= Composer way =

1. Run:
`composer require integritsol/woo-poly-integration`

In all cases, ensure Polylang and WooCommerce are active before enabling Woo-Poly Integration.

== Frequently Asked Questions ==

= Does this work with other e-commerce plugins? =

No. This plugin is for WooCommerce + Polylang integration only.

= Does this work with WPML? =

No. This plugin is for WooCommerce + Polylang.

= Do I need theme changes? =

Usually no. Most functionality works without theme customization.

= Product category or tag pages are blank =

Check your permalink settings and flush permalinks.

== Screenshots ==

1. Add and translate products from one workflow
2. Product meta synchronization across translations
3. Orders store customer language context
4. Orders language can be adjusted in admin
5. Plugin settings for translation and synchronization behavior

== Changelog ==

== 2.0.0-alpha.1 ==

- Modernized maintained fork of the original abandoned upstream
- Compatibility targets: WordPress 6.6-6.9, WooCommerce 9.0-10.7, PHP 7.4+
- HPOS compatibility declaration and HPOS-aware order-language storage
- Cart and Checkout block compatibility (Store API cart path coverage)
- Polylang 3.7+ options-object API support
- Legacy WooCommerce analytics integration removed

== 1.5.1 ==

* fixes #545 keep fields unlocked if products does not exist in default language props mrleemon
* fixes #549 Quick edit Product synchronisation issues
* fixes #548 incorrect save hook caused inconsistent synchronisation behaviour, especially changing product type and visibility
* fixes #542 shop page breadcrumb in secondary language

== Upgrade Notice ==

= 2.0.0-alpha.1 =

This is the first release of the modernized fork. It raises minimum supported
versions and removes legacy analytics integration.
