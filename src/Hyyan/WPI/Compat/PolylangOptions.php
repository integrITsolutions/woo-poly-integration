<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Compat;

/**
 * Compatibility wrapper for Polylang's options storage.
 *
 * Polylang 3.7 (April 2025) replaced the plain-array `polylang` option with an
 * ArrayAccess/IteratorAggregate object exposed via `PLL()->options`. Calling
 * `update_option('polylang', $array)` directly is explicitly discouraged in
 * Polylang's published guidance:
 *   "get_option() and update_option() shouldn't be used at all"
 *   — https://polylang.pro/options-as-object/
 *
 * Custom keys written via direct `update_option('polylang', …)` are silently
 * dropped on the next options reload in 3.7+. Mutating the stored option is
 * therefore unsafe across versions.
 *
 * This wrapper provides three behaviours:
 *
 *   1. Read access (getOption) — uses the object API on 3.7+, falls back to
 *      get_option() on older versions, returns array always.
 *   2. Idempotent post-type/taxonomy/sync registration — writes only when the
 *      target value is missing. Uses `PLL()->options->set()` on 3.7+ and
 *      update_option() on older versions.
 *   3. Filter-based runtime declaration — for callers that want to declare
 *      translatable post types without persisting (e.g. inside a
 *      `pll_get_post_types` callback), use the helpers that don't touch
 *      storage.
 *
 * Callers in this plugin should NEVER call `update_option('polylang', ...)`
 * directly; they should funnel through this wrapper.
 *
 * @author IntegrIT Solutions
 */
final class PolylangOptions
{
    /**
     * Returns true if Polylang exposes the options-object API (>= 3.7).
     */
    public static function hasOptionsObjectApi()
    {
        if (!function_exists('PLL')) {
            return false;
        }
        $pll = PLL();
        return $pll && isset($pll->options) && is_object($pll->options);
    }

    /**
     * Read a single key from Polylang's options.
     *
     * @param string $key
     * @param mixed  $default value returned when the key is absent or Polylang is unavailable.
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (self::hasOptionsObjectApi()) {
            $pll = PLL();
            if (!$pll || !isset($pll->options) || !is_object($pll->options)) {
                return $default;
            }
            $opts = $pll->options;
            if (method_exists($opts, 'has') && method_exists($opts, 'get')) {
                return $opts->has($key) ? $opts->get($key) : $default;
            }
            // Fallback for ArrayAccess shape only.
            if (isset($opts[$key])) {
                return $opts[$key];
            }
            return $default;
        }
        $stored = get_option('polylang');
        if (is_array($stored) && array_key_exists($key, $stored)) {
            return $stored[$key];
        }
        return $default;
    }

    /**
     * Write a single key to Polylang's options.
     *
     * Idempotent: if the value is unchanged, no write happens.
     *
     * On Polylang 3.7+, `PLL()->options->set()` returns a `WP_Error` object (which
     * may or may not contain errors) and persists modifications via a `shutdown`
     * hook (verified in Polylang 3.8.3 src/Options/Options.php). On older versions,
     * we fall back to direct `update_option('polylang', $array)` which Polylang's
     * own published guidance discourages but is the only available path.
     *
     * Polylang's option-class sanitize step intersects list-typed values against
     * the live set of registered post types/taxonomies (see Abstract_Object_Types).
     * Callers must ensure the relevant post type / taxonomy is registered before
     * this method runs (priority `init` >= 12 covers WooCommerce's defaults).
     *
     * @param string $key
     * @param mixed  $value
     * @return bool true on success, false if Polylang rejected the value or storage
     *              is unavailable. Caller should treat false as "settings UI may not
     *              reflect this preference" — the runtime `pll_get_post_types` filter
     *              path covers translation behavior independently.
     */
    public static function set($key, $value)
    {
        if (self::hasOptionsObjectApi()) {
            $pll = PLL();
            if (!$pll || !isset($pll->options) || !is_object($pll->options)) {
                return false;
            }
            $opts = $pll->options;
            if (method_exists($opts, 'get') && method_exists($opts, 'set') && method_exists($opts, 'has')) {
                if (!$opts->has($key)) {
                    return false; // Unknown key; Polylang's set() would error anyway.
                }
                $current = $opts->get($key);
                if ($current === $value) {
                    return true;
                }
                $result = $opts->set($key, $value);
                // 3.7+ contract: set() returns WP_Error (empty if successful, populated
                // if sanitize/validate rejected the value). Persistence happens on shutdown.
                if (is_wp_error($result) && $result->has_errors()) {
                    return false;
                }
                return true;
            }
        }

        // Legacy fallback (Polylang < 3.7): direct option mutation.
        $stored = get_option('polylang');
        if (!is_array($stored)) {
            return false;
        }
        if (array_key_exists($key, $stored) && $stored[$key] === $value) {
            return true;
        }
        $stored[$key] = $value;
        return (bool) update_option('polylang', $stored);
    }

    /**
     * Add an entry to a list-typed Polylang option (post_types, taxonomies, sync).
     *
     * Idempotent: if $entry is already present, no write happens.
     *
     * Known race: this is a read-modify-write sequence without locking, so two
     * concurrent writers can lose one update. Keep callers on low-frequency paths
     * (settings save, plugin upgrade, attribute creation), not frontend hot paths.
     *
     * @internal Call only from low-frequency admin/upgrade flows.
     *
     * @param string $key   one of 'post_types', 'taxonomies', 'sync', etc.
     * @param string $entry the value to ensure is present in the list.
     * @return bool true if the entry is now present (already or after write); false if Polylang is unavailable.
     */
    public static function addToList($key, $entry)
    {
        $list = self::get($key, array());
        if (!is_array($list)) {
            $list = array();
        }
        if (in_array($entry, $list, true)) {
            return true;
        }
        $list[] = $entry;
        return self::set($key, $list);
    }

    /**
     * Remove an entry from a list-typed Polylang option.
     *
     * Known race: this is a read-modify-write sequence without locking, so two
     * concurrent writers can lose one update. Keep callers on low-frequency paths
     * (settings save, plugin upgrade, attribute creation), not frontend hot paths.
     *
     * @internal Call only from low-frequency admin/upgrade flows.
     *
     * @param string $key
     * @param string $entry
     * @return bool
     */
    public static function removeFromList($key, $entry)
    {
        $list = self::get($key, array());
        if (!is_array($list)) {
            return true;
        }
        $idx = array_search($entry, $list, true);
        if ($idx === false) {
            return true;
        }
        unset($list[$idx]);
        return self::set($key, array_values($list));
    }

    /**
     * Convenience: ensure a post type is registered as translatable in Polylang's
     * stored options. Use this for one-shot activation/upgrade calls only — the
     * runtime declaration goes via the `pll_get_post_types` filter.
     *
     * @param string $post_type
     * @return bool
     */
    public static function registerPostType($post_type)
    {
        return self::addToList('post_types', $post_type);
    }

    /**
     * Convenience: ensure a taxonomy is registered as translatable.
     *
     * @param string $taxonomy
     * @return bool
     */
    public static function registerTaxonomy($taxonomy)
    {
        return self::addToList('taxonomies', $taxonomy);
    }

    /**
     * Convenience: ensure a sync key is enabled.
     *
     * @param string $sync_key e.g. 'post_parent', '_thumbnail_id', 'comment_status'.
     * @return bool
     */
    public static function enableSync($sync_key)
    {
        return self::addToList('sync', $sync_key);
    }
}
