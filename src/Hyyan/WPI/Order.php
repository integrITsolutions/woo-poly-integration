<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI;

use Hyyan\WPI\Compat\PolylangOptions;
use Hyyan\WPI\Utilities;

/**
 * Order language handling — HPOS-aware.
 *
 * # Storage architecture (post Phase B)
 *
 * Order language is dual-written to:
 *   1. WooCommerce order CRUD meta — key {@see Order::META_KEY_LANGUAGE}.
 *      Stored in `wc_orders_meta` under HPOS, in `wp_postmeta` on legacy CPT.
 *      This is the authoritative read source.
 *   2. Polylang's `language` taxonomy via `pll_set_post_language()`.
 *      Stored in `wp_term_relationships` keyed by order ID. Survives across HPOS
 *      and CPT modes because `wp_term_relationships` doesn't reference post_type.
 *
 * Read path (see {@see Order::getLanguage()}) — PURE READ, never writes:
 *   1. Try order CRUD meta.
 *   2. Fall back to Polylang taxonomy (`pll_get_post_language`).
 *   3. Return null if neither source has a value.
 *
 * Migration of legacy taxonomy-only orders to the dual-write store happens via
 * {@see Order::migrateLegacyTaxonomyToMeta()} — explicit, batched, idempotent.
 * Earlier versions did opportunistic backfill from inside getLanguage(); that
 * proved unsafe because it triggered `woocommerce_update_order` hooks from cron
 * and email contexts where reads must be side-effect-free.
 *
 * # Why dual-write
 *
 * Polylang's taxonomy storage works under HPOS today via the `shop_order_placehold`
 * placeholder post that WooCommerce creates to keep taxonomies/comments working.
 * That placeholder is a transitional compatibility layer with no published
 * permanence commitment from the WooCommerce team. Storing on order CRUD meta
 * decouples us from that compatibility layer, while keeping the taxonomy write
 * preserves Polylang's per-language order filtering behaviour.
 *
 * Reference: https://github.com/woocommerce/woocommerce/discussions/34829
 *
 * # Hook coverage
 *
 * Different checkout flows fire different hooks. We cover all of them:
 *   - Classic shortcode checkout: `woocommerce_checkout_update_order_meta`
 *     ($order_id int, $data array)
 *   - Block / Store API checkout: `woocommerce_store_api_checkout_update_order_meta`
 *     ($order WC_Order)
 *   - Admin / REST API / subscription renewal / programmatic order creation:
 *     `woocommerce_new_order` ($order_id int, $order WC_Order) — used as a
 *     backstop only when the current request has a determinable language and
 *     the order has no language stored yet. Never overrides an explicit language.
 *
 * # Order admin screen detection
 *
 * Under HPOS the order edit screen is the WC custom page (screen_id =
 * `wc_get_page_screen_id('shop-order')`, typically `woocommerce_page_wc-orders`),
 * not a post-type edit screen. We accept either.
 *
 * @author Hyyan Abo Fakher (original)
 * @author IntegrIT Solutions (modernization)
 */
class Order
{
    /**
     * Order CRUD meta key holding the order's language slug.
     *
     * Namespaced under the historical plugin internal id (`hyyan_wpi`) so it cannot
     * collide with anything Polylang Free or Pro might add. Polylang itself stores
     * language only in the `language` taxonomy and uses NO post meta keys for
     * language assignment (verified against Polylang 3.8.3 source).
     */
    const META_KEY_LANGUAGE = '_hyyan_wpi_language';

    /**
     * Construct object.
     */
    public function __construct()
    {
        // Manage order translation — declare 'shop_order' to Polylang at runtime.
        add_filter('pll_get_post_types', array($this, 'manageOrderTranslation'));

        // Persist 'shop_order' in Polylang's stored options (idempotent, runs once
        // per request — uses the 3.7+ options-object API where available).
        if (is_admin()) {
            add_action('init', array($this, 'ensureOrderPostTypeRegisteredInPolylangOptions'), 12);
        }

        // Save order language on every checkout flow.
        // Classic shortcode checkout: callback receives int $order_id.
        add_action(
            'woocommerce_checkout_update_order_meta',
            array($this, 'saveOrderLanguageFromClassic'),
            10,
            1
        );
        // Block / Store API checkout: callback receives WC_Order.
        // Confirmed via WC 10.7 source: src/StoreApi/Routes/V1/Checkout.php
        // does `do_action('woocommerce_store_api_checkout_update_order_meta', $this->order)`.
        add_action(
            'woocommerce_store_api_checkout_update_order_meta',
            array($this, 'saveOrderLanguageFromBlock'),
            10,
            1
        );
        // Backstop for non-checkout order creation (admin, REST API, subscriptions,
        // programmatic). Only writes if current request has a known language AND
        // the order has no language stored yet — never overrides an explicit choice.
        add_action(
            'woocommerce_new_order',
            array($this, 'maybeBackfillOrderLanguageOnCreate'),
            20,
            2
        );

        if (is_admin()) {
            $this->limitPolylangFeaturesForOrders();
        }

        // Order query language filtering, registered ONCE in constructor (not
        // inside the My Account callback to avoid stacking).
        // - Legacy CPT path: `woocommerce_order_data_store_cpt_get_orders_query`
        //   forwards 'lang' arg into WP_Query args; Polylang's parse_query then
        //   injects the language tax_query.
        // - HPOS path: `woocommerce_order_query_args` translates 'lang' into a
        //   meta_query against `_hyyan_wpi_language` (since OrdersTableQuery does
        //   NOT trigger Polylang's parse_query — verified in WC 10.7 source).
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'translateLangArgForLegacyQuery'), 10, 2);
        add_filter('woocommerce_order_query_args', array($this, 'translateLangArgForHposQuery'), 10, 1);

        // My Account → Orders: show orders in ALL languages (cross-language view).
        add_filter(
            'woocommerce_my_account_my_orders_query',
            array($this, 'correctMyAccountOrderQuery')
        );

        // Translate products in order details pages.
        add_filter(
            'woocommerce_order_item_product',
            array($this, 'translateProductsInOrdersDetails'),
            10,
            3
        );
    }

    /**
     * Notify Polylang that 'shop_order' is a translatable post type.
     *
     * Used by Polylang to apply the `language` taxonomy at runtime.
     *
     * @param array $types array of custom post names managed by polylang
     *
     * @return array
     */
    public function manageOrderTranslation(array $types)
    {
        if (!in_array('shop_order', $types, true)) {
            $types[] = 'shop_order';
        }
        return $types;
    }

    /**
     * Idempotently ensure 'shop_order' is registered in Polylang's stored options.
     *
     * Replaces the v1.x pattern of mutating `get_option('polylang')` directly,
     * which is incompatible with Polylang 3.7+'s options-object refactor.
     *
     * Runs once per request at init priority 12 (after Polylang's own init at
     * default priority 10). Idempotent: writes only when missing.
     */
    public function ensureOrderPostTypeRegisteredInPolylangOptions()
    {
        PolylangOptions::registerPostType('shop_order');
    }

    /**
     * Set order language: meta-authoritative write with best-effort taxonomy sync.
     *
     * Use this as the single point of truth for writing order language. Order
     * CRUD meta is authoritative for HPOS compatibility. Polylang taxonomy write
     * is a compatibility layer so Polylang UI/queries can see order language.
     *
     * Validates the language slug against Polylang's known languages list before
     * writing. Returns true when meta write succeeded (taxonomy sync may still
     * fail). Returns false when meta write failed, which means no language was
     * persisted.
     *
     * @param int|\WC_Order $order Order ID or WC_Order object.
     * @param string        $lang  Polylang language slug (e.g. 'en', 'de').
     * @return bool true if authoritative meta write succeeded, false on any
     *              validation/order lookup/meta persistence failure.
     */
    public static function setLanguage($order, $lang)
    {
        if (!is_string($lang) || $lang === '') {
            return false;
        }

        // Validate slug against Polylang's known languages, if available.
        if (function_exists('pll_languages_list')) {
            $known = pll_languages_list();
            if (is_array($known) && !empty($known) && !in_array($lang, $known, true)) {
                return false;
            }
        }

        $order_obj = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order_obj) {
            return false;
        }

        // 1. Meta first (authoritative, HPOS-safe).
        $meta_ok = false;
        try {
            $order_obj->update_meta_data(self::META_KEY_LANGUAGE, $lang);
            $saved = $order_obj->save();
            $meta_ok = (bool) $saved;
        } catch (\Throwable $e) {
            $meta_ok = false;
        }

        if (!$meta_ok) {
            // Meta failed: nothing persisted. Do not attempt taxonomy write.
            return false;
        }

        // 2. Taxonomy write is best-effort compatibility only.
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($order_obj->get_id(), $lang);
            if (function_exists('pll_get_post_language')) {
                $stored_slug = pll_get_post_language($order_obj->get_id());
                if ($stored_slug !== $lang && function_exists('_doing_it_wrong')) {
                    _doing_it_wrong(
                        __METHOD__,
                        sprintf(
                            'Order #%d: Polylang taxonomy write failed (HPOS placeholder may have changed). Meta is authoritative; taxonomy is best-effort.',
                            $order_obj->get_id()
                        ),
                        '2.0.0'
                    );
                }
            }
        }

        return true;
    }

    /**
     * Read order language: order-meta-first, taxonomy-fallback. PURE READ.
     *
     * Critical: this method DOES NOT WRITE to the order, even when it falls back
     * to taxonomy lookup. Earlier versions did opportunistic backfill (writing
     * meta on every read where taxonomy returned a value), which fired
     * `woocommerce_update_order` hooks from email/cron contexts and could cascade
     * unintended side effects.
     *
     * Migration of legacy orders (taxonomy-only → meta) happens organically via
     * the next `Order::setLanguage()` call (e.g. checkout, manual admin edit), or
     * can be done explicitly by a one-shot upgrade routine. Reads stay safe and
     * cheap.
     *
     * @param int|\WC_Order $order
     * @param string        $field Which field to return. 'slug' (default), 'locale', 'name',
     *                             or any other PLL_Language field. Pass `\OBJECT` to get the
     *                             PLL_Language object.
     * @return string|\PLL_Language|null Language slug/locale/name (or PLL_Language object), or null
     *                                   if no language is assigned or the field is unresolvable.
     */
    public static function getLanguage($order, $field = 'slug')
    {
        $order_obj = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order_obj) {
            return null;
        }

        $slug = null;

        // 1. Order CRUD meta (authoritative; HPOS-safe).
        $stored = $order_obj->get_meta(self::META_KEY_LANGUAGE, true);
        if (is_string($stored) && $stored !== '') {
            $slug = $stored;
        }

        // 2. Polylang taxonomy fallback (legacy data, pre-Phase B orders).
        // Read-only: does NOT backfill meta. Migration is explicit, not implicit.
        if ($slug === null && function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($order_obj->get_id());
            if (is_string($lang) && $lang !== '') {
                $slug = $lang;
            }
        }

        if ($slug === null) {
            return null;
        }

        // Resolve the requested field. 'slug' is the cheap, common case.
        if ($field === 'slug') {
            return $slug;
        }

        // For other fields, ask Polylang for the language object.
        // @internal Polylang internal API; reverify on Polylang minor upgrades.
        if (!function_exists('PLL') || !PLL() || !isset(PLL()->model)) {
            return null;
        }
        // @internal Polylang internal API; reverify on Polylang minor upgrades.
        $lang_obj = PLL()->model->get_language($slug);
        if (!$lang_obj) {
            return null;
        }
        if ($field === \OBJECT) {
            return $lang_obj;
        }
        if (method_exists($lang_obj, 'get_prop')) {
            $value = $lang_obj->get_prop($field);
            return ($value === false || $value === '') ? null : $value;
        }
        // Legacy Polylang versions: best-effort property read.
        return isset($lang_obj->{$field}) ? $lang_obj->{$field} : null;
    }

    /**
     * One-shot bulk migration: backfill `_hyyan_wpi_language` order meta from
     * Polylang taxonomy assignments for legacy orders (pre-v2.0.0).
     *
     * Triggered explicitly from {@see Plugin::onUpgrade()} or via WP-CLI.
     * Uses the order CRUD save path (which fires update hooks) — that's
     * acceptable in an explicit migration context but unacceptable on every read.
     *
     * For orders without any language assignment in Polylang taxonomy, stores
     * an empty-string sentinel in `_hyyan_wpi_language` so they are excluded
     * from subsequent `NOT EXISTS` batches.
     *
     * @param int $batch_size Orders per batch.
     * @return int Number of orders updated.
     */
    public static function migrateLegacyTaxonomyToMeta($batch_size = 200)
    {
        if (!function_exists('pll_get_post_language') || !function_exists('wc_get_orders')) {
            return 0;
        }

        $count = 0;
        do {
            $orders = wc_get_orders(array(
                'limit' => $batch_size,
                'paginate' => false,
                'return' => 'objects',
                // Only orders without our meta key set.
                'meta_query' => array(
                    array(
                        'key' => self::META_KEY_LANGUAGE,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            ));
            if (empty($orders)) {
                break;
            }

            $migrated_in_batch = 0;
            $marked_empty_in_batch = 0;

            foreach ($orders as $order) {
                try {
                    $tax_lang = pll_get_post_language($order->get_id());
                    if (is_string($tax_lang) && $tax_lang !== '') {
                        $order->update_meta_data(self::META_KEY_LANGUAGE, $tax_lang);
                        if ($order->save()) {
                            $count++;
                            $migrated_in_batch++;
                        }
                    } else {
                        $order->update_meta_data(self::META_KEY_LANGUAGE, '');
                        if ($order->save()) {
                            $marked_empty_in_batch++;
                        }
                    }
                } catch (\Throwable $e) {
                    // Continue with the rest of the batch.
                }
            }

            // Safety net: avoid infinite loops if a batch cannot make progress.
            if ($migrated_in_batch === 0 && $marked_empty_in_batch === 0) {
                break;
            }
        } while (count($orders) === $batch_size);

        return $count;
    }

    /**
     * Hook callback for classic shortcode checkout.
     * WooCommerce passes the int order ID.
     *
     * @param int $order_id
     */
    public function saveOrderLanguageFromClassic($order_id)
    {
        $current = function_exists('pll_current_language') ? pll_current_language() : '';
        if (!$current) {
            return;
        }
        self::setLanguage((int) $order_id, $current);
    }

    /**
     * Hook callback for Block / Store API checkout.
     * WooCommerce passes the WC_Order object.
     *
     * Verified via WC 10.7 source:
     *   src/StoreApi/Routes/V1/Checkout.php
     *   do_action('woocommerce_store_api_checkout_update_order_meta', $this->order);
     *
     * @param \WC_Order $order
     */
    public function saveOrderLanguageFromBlock($order)
    {
        if (!$order instanceof \WC_Order) {
            return;
        }
        $current = function_exists('pll_current_language') ? pll_current_language() : '';
        if (!$current) {
            return;
        }
        self::setLanguage($order, $current);
    }

    /**
     * Backstop hook for non-checkout order creation, FRONTEND ONLY.
     *
     * Conservative on three axes:
     *   1. Never overrides an explicit language already assigned to the order.
     *   2. Only writes when `pll_current_language()` returns a non-empty signal.
     *   3. Skips admin context, WP-CLI, REST without frontend session, and
     *      programmatic order creation in those contexts. Rationale: in those
     *      contexts the request locale is the staff member's UI language, not
     *      the customer's — assigning that to the order is incorrect.
     *
     * Frontend-created orders that bypass the standard checkout (e.g. custom
     * checkout flows that call `wc_create_order()` directly) are still covered.
     *
     * @param int       $order_id
     * @param \WC_Order $order
     */
    public function maybeBackfillOrderLanguageOnCreate($order_id, $order)
    {
        // Skip non-frontend contexts. The official checkout hooks
        // (woocommerce_checkout_update_order_meta and the Store API equivalent)
        // continue to cover their own flows; this backstop is only for orders
        // created via the frontend WHERE neither checkout hook fires.
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            // REST authenticated calls (admin tooling, integrations) shouldn't
            // inherit the request locale as customer language.
            return;
        }

        if (!$order instanceof \WC_Order) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
        }

        // Don't override an existing language assignment.
        if (self::getLanguage($order) !== null) {
            return;
        }

        // Only assign when the request context provides a clear language signal.
        $current = function_exists('pll_current_language') ? pll_current_language() : '';
        if (!$current) {
            return;
        }

        self::setLanguage($order, $current);
    }

    /**
     * Translate products in order details pages.
     *
     * @param \WC_Product $product
     *
     * @return \WC_Product
     */
    public function translateProductsInOrdersDetails($product)
    {
        if ($product) {
            return Utilities::getProductTranslationByObject($product);
        }
        return false;
    }

    /**
     * Add a `lang` arg to the My Account → Orders query so users see orders in
     * ALL languages they've used (not just the currently displayed language).
     *
     * The actual translation of `lang` into a query clause happens in the two
     * filter callbacks registered in the constructor (legacy + HPOS), so calling
     * this method does not stack filter registrations across requests.
     *
     * @param array $query  query arguments
     *
     * @return array
     */
    public function correctMyAccountOrderQuery(array $query)
    {
        if (function_exists('pll_languages_list')) {
            $query['lang'] = implode(',', pll_languages_list());
        }
        return $query;
    }

    /**
     * Legacy CPT path: forward 'lang' arg from wc_get_orders args into the
     * generated WP_Query args, where Polylang's parse_query injects the language
     * tax_query.
     *
     * Hook is a no-op under HPOS (confirmed in WC 10.7 source: the filter is
     * not applied by OrdersTableDataStore).
     *
     * @param array $query  WP_Query arguments
     * @param array $args   wc_get_orders query args
     *
     * @return array
     */
    public function translateLangArgForLegacyQuery($query, $args)
    {
        if (isset($args['lang'])) {
            $query['lang'] = $args['lang'];
        }
        return $query;
    }

    /**
     * HPOS path: translate the `lang` arg into a `meta_query` against
     * `_hyyan_wpi_language`, since OrdersTableQuery doesn't trigger Polylang's
     * parse_query and so the `lang` arg would otherwise be silently ignored.
     *
     * Accepts the same `lang` syntax as Polylang: a single slug, an array of slugs,
     * a comma-separated string, or 'all' (no filter applied).
     *
     * Caveat: legacy orders without `_hyyan_wpi_language` meta (pre-v2.0.0
     * orders that haven't been touched since the upgrade) are excluded from
     * language-filtered results. Run `Order::migrateLegacyTaxonomyToMeta()` to
     * backfill if needed.
     *
     * @param array $args wc_get_orders args
     * @return array
     */
    public function translateLangArgForHposQuery($args)
    {
        if (empty($args['lang'])) {
            return $args;
        }

        // This translator is HPOS-specific. Under legacy CPT datastore the `lang`
        // arg is forwarded into WP_Query args via `translateLangArgForLegacyQuery`
        // and Polylang's parse_query injects the language tax_query there.
        // Injecting meta_query under CPT would emit "not supported on the current
        // order datastore" warning.
        if (!class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil')
            || !\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            return $args;
        }

        $lang = $args['lang'];
        unset($args['lang']);

        if ($lang === 'all') {
            return $args; // No filter.
        }

        if (is_string($lang) && strpos($lang, ',') !== false) {
            $lang = array_filter(array_map('trim', explode(',', $lang)));
        }
        if (is_array($lang) && function_exists('pll_languages_list')) {
            // If the requested set covers every known language, that's equivalent
            // to no filter (My Account view uses this).
            $known = pll_languages_list();
            if (is_array($known) && !array_diff($known, $lang)) {
                return $args;
            }
        }

        if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
            $args['meta_query'] = array();
        }

        $args['meta_query'][] = array(
            'key' => self::META_KEY_LANGUAGE,
            'value' => is_array($lang) ? array_values($lang) : array($lang),
            'compare' => 'IN',
        );

        return $args;
    }

    /**
     * Disallow the user to create translations for shop_order (orders are NOT
     * translated; one order per language is stored separately).
     *
     * Handles both:
     *   - Legacy CPT screen: $screen->post_type === 'shop_order'
     *   - HPOS custom screen: $screen->id === wc_get_page_screen_id('shop-order')
     */
    public function limitPolylangFeaturesForOrders()
    {
        add_action('current_screen', function () {
            $screen = function_exists('get_current_screen') ? get_current_screen() : false;
            if (!$screen) {
                return;
            }

            $is_legacy_order_screen = isset($screen->post_type) && $screen->post_type === 'shop_order';
            $hpos_order_screen_id = function_exists('wc_get_page_screen_id')
                ? wc_get_page_screen_id('shop-order')
                : '';
            $is_hpos_order_screen = $hpos_order_screen_id !== '' && $screen->id === $hpos_order_screen_id;

            if ($is_legacy_order_screen || $is_hpos_order_screen) {
                add_action('admin_print_scripts', function () {
                    $jsID = 'order-translations-buttons';
                    $code = '$(".pll_icon_add,#post-translations").fadeOut()';
                    Utilities::jsScriptWrapper($jsID, $code);
                }, 100);
            }
        });
    }
}
