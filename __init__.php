<?php

/*
 * Plugin Name: Woo-Poly Integration (Modernized Fork)
 * Plugin URI: https://github.com/IntegrITSolutions/woo-poly-integration
 * Description: Integrates WooCommerce with Polylang. Modernized fork of hyyan/woo-poly-integration (archived 2021) with HPOS, Cart/Checkout block, and Polylang 3.7+ options-object support.
 * Author: IntegrIT Solutions (fork) — original by Hyyan Abo Fakher
 * Author URI: https://integritsol.de
 * Text Domain: woo-poly-integration
 * Domain Path: /languages
 * Update URI: https://github.com/IntegrITSolutions/woo-poly-integration
 * License: MIT License
 * Version: 2.0.0-alpha.1
 * Requires at least: 6.6
 * Tested up to: 6.9
 * WC requires at least: 9.0
 * WC tested up to: 10.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('ABSPATH')) {
    exit('restricted access');
}

define('Hyyan_WPI_DIR', __FILE__);
define('Hyyan_WPI_URL', plugin_dir_url(__FILE__));
define('Hyyan_WPI_VERSION', '2.0.0-alpha.1');

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once __DIR__ . '/vendor/class.settings-api.php';
require_once __DIR__ . '/src/Hyyan/WPI/Autoloader.php';

/* register the autoloader */
new Hyyan\WPI\Autoloader(__DIR__ . '/src/');

/*
 * Declare WooCommerce feature compatibility BEFORE WooCommerce initializes.
 * Must run on `before_woocommerce_init` per WC docs.
 *
 * `custom_order_tables` — High-Performance Order Storage (HPOS).
 *   Declared TRUE since v2.0.0-alpha.1 (Phase B): Order module uses dual-write
 *   storage (`_hyyan_wpi_language` order CRUD meta + Polylang taxonomy) and
 *   HPOS-aware query filters (`woocommerce_order_query_args` alongside the
 *   legacy `woocommerce_order_data_store_cpt_get_orders_query`). Both checkout
 *   paths are covered: classic shortcode (`woocommerce_checkout_update_order_meta`)
 *   and Block / Store API (`woocommerce_store_api_checkout_update_order_meta`).
 *   See {@see Hyyan\WPI\Order} for the full architecture.
 *
 * `cart_checkout_blocks` — Cart and Checkout block compatibility.
 *   Declared TRUE since v2.0.0-alpha.1 (Phase C): cart item translation hooks
 *   `woocommerce_get_cart_item_from_session` and `woocommerce_add_cart_item` to
 *   swap the cart item's WC_Product reference at session-load / add time, so the
 *   translated product flows naturally through to Block cart's Store API
 *   responses. WC 10.7's CartItemSchema reads name/description/sku directly
 *   from the cart item's product object, so this is the only supported point
 *   to inject translation. See {@see Hyyan\WPI\Cart} for the full architecture.
 *
 *   Block CHECKOUT order metadata is covered separately via
 *   `woocommerce_store_api_checkout_update_order_meta` in {@see Hyyan\WPI\Order}.
 *
 * @see https://developer.woocommerce.com/docs/code-snippets/declaring-extension-incompatibility/
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});

/* bootstrap the plugin */
new Hyyan\WPI\Plugin();


/*
 * called when plugin is activated in settings, plugins
 */
function onActivate() {
	update_option( 'wpi_wcpagecheck_passed', false );
	update_option( 'hyyan-wpi-flash-messages', '' );
}

register_activation_hook( __FILE__, 'onActivate' );

