<?php
/**
 * woo-poly-integration uninstall handler.
 *
 * Runs when the user deletes the plugin from the Plugins admin screen.
 * Cleans up plugin-owned options, transients, and admin notices.
 *
 * Per WP Plugin Handbook (https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/):
 * "Be sure to verify the WP_UNINSTALL_PLUGIN constant is defined before
 * doing anything in your uninstall.php file."
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Plugin-owned options. Listed verbatim — match the keys defined in src/.
$options_to_delete = array(
    'wpi_version',
    'wpi_wcpagecheck_passed',
    'hyyan-wpi-flash-messages',
    'wpi_v2_migration_notice_pending',
    'wpi_v2_migration_notice_dismissed',
    'wpi-features',
    'wpi-metas-list',
);

foreach ($options_to_delete as $opt) {
    if (is_string($opt) && $opt !== '') {
        delete_option($opt);
        // Network-wide delete for multisite.
        if (is_multisite()) {
            delete_site_option($opt);
        }
    }
}

// Transients.
$transients_to_delete = array(
    'wpi_v2_migration_result',
    'coupons-ids',
);

foreach ($transients_to_delete as $t) {
    delete_transient($t);
    if (is_multisite()) {
        delete_site_transient($t);
    }
}

// Note: order language meta `_hyyan_wpi_language` and Polylang language taxonomy
// assignments are deliberately NOT deleted. Removing language data on plugin
// uninstall would corrupt order history. If a site admin truly wants to wipe
// these, a separate WP-CLI command can do so.
