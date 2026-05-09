<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Product;

use Hyyan\WPI\Admin\Settings;
use Hyyan\WPI\Admin\Features;
use Hyyan\WPI\Compat\PolylangOptions;
use Hyyan\WPI\Utilities;

/**
 * Product.
 *
 * Handle product translation
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Product
{
    /**
     * Construct object.
     */
    public function __construct()
    {

        // Declare 'product' and 'product_variation' to Polylang at runtime via filter.
        add_filter(
                'pll_get_post_types', array($this, 'manageProductTranslation')
        );

        // Idempotently persist the post type registration in Polylang's stored options
        // so the Polylang Settings UI checkboxes reflect reality. Runs on init after
        // Polylang has loaded.
        add_action('init', array($this, 'ensureProductPostTypesRegisteredInPolylangOptions'), 12);

        // Idempotently enable post_parent sync (used for grouped products).
        // The original v1.x version was hooked to `admin_init` via add_filter (a bug —
        // add_filter on an action runs the callback as a filter, but admin_init's return
        // value is discarded). We use add_action correctly here.
        add_action('admin_init', array($this, 'ensurePostParentSyncEnabled'));

        //Product title/description sync/translate, defaults to 0-Off for back-compatiblity
        $translate_option = Settings::getOption('new-translation-defaults', Features::getID(), 0);
        if ($translate_option) {
            add_filter('default_title', array($this, 'wpi_editor_title'));
            add_filter('default_content', array($this, 'wpi_editor_content'));
            add_filter('default_excerpt', array($this, 'wpi_editor_excerpt'));
        }
                
        //TODO: this filter appears to be unnecessary - remove
        //woocommerce_product_attribute_terms is already getting terms for a particular attribute
        //which is already the language version of the attribute ...
        // get attributes in current language
        /*
         *
        add_filter(
                'woocommerce_product_attribute_terms', array($this, 'getProductAttributesInLanguage')
        );
         */
        //show cross-sells and up-sells in correct language
        add_filter('woocommerce_product_get_upsell_ids', array($this, 'getUpsellsInLanguage'), 10, 2);
        add_filter('woocommerce_product_get_cross_sell_ids', array($this, 'getCrosssellsInLanguage'), 10, 2);
        add_filter('woocommerce_product_get_children', array($this, 'getChildrenInLanguage'), 10, 2);
        
        //for this ajax call our action has to come before the woocommerce action because the woocommerce action does a redirect
        add_action( 'wp_ajax_woocommerce_feature_product', array( __CLASS__, 'sync_ajax_woocommerce_feature_product' ), 5 );

        new Meta();
        new Variable();
        new Duplicator();

		if ( ('on' === Settings::getOption( 'stock', Features::getID(), 'on' )) &&
		    ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) ) 
		    {
            new Stock();
        }
    }


    /*
     * #234 WooCommerce allows featured to be toggled in the products admin list
     * by clicking on the star
     */
    public static function sync_ajax_woocommerce_feature_product() {
      $product_id = isset($_GET['product_id']) ? absint(wp_unslash($_GET['product_id'])) : 0;
      if (! $product_id) {
        return;
      }

      if (! current_user_can('edit_products')) {
        return;
      }

      $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
      if (! wp_verify_nonce($nonce, 'woocommerce-feature-product')) {
        return;
      }

      $metas = Meta::getDisabledProductMetaToCopy();
      if ( in_array( '_visibility', $metas ) ) {
        return;
      }

      $product = wc_get_product($product_id);
      if ( $product ) {
        //woocommerce action runs last so we need to set translation feature to the opposite of current value
        $targetValue = ! $product->get_featured();

        $product_translations = Utilities::getProductTranslationsArrayByObject( $product );
        foreach ( $product_translations as $product_translation ) {
          if ( $product_translation != $product->get_id() ) {
            $translation = wc_get_product( $product_translation );
            if ( $translation ) {
              $translation->set_featured( $targetValue );
              $translation->save();
            }
          }
        }
      }
    }

    
    /**
     * filter child ids of Grouped Product
     *
     * @param array      $related_ids array of product ids
     * @param WC_Product $product current product
     *
     * @return array filtered result
     */
    public function getChildrenInLanguage($relatedIds, $product)
    {
        return $this->getProductIdsInLanguage($relatedIds, $product);
    }
    /**
     * filter upsells display
     *
     * @param array      $related_ids array of product ids
     * @param WC_Product $product current product
     *
     * @return array filtered result
     */
    public function getUpsellsInLanguage($relatedIds, $product)
    {
        return $this->getProductIdsInLanguage($relatedIds, $product);
    }
    /**
     * filter Cross-sells display
     *
     * @param array      $related_ids array of product ids
     * @param WC_Product $product current product
     *
     * @return array filtered result
     */
    public function getCrosssellsInLanguage($relatedIds, $product)
    {
        return $this->getProductIdsInLanguage($relatedIds, $product);
    }
    /**
     * filter product ids
     *
     * @param array      $product_ids array of product ids
     * @param WC_Product $product current product
     *
     * @return array filtered result
     */
    public function getProductIdsInLanguage($productIds, $product)
    {
        $productLang = pll_get_post_language($product->get_id());
        $mappedIds = array();
        foreach ($productIds as $productId) {
            $correctLanguageId = pll_get_post($productId, $productLang);
            if ($correctLanguageId) {
                $mappedIds[]=$correctLanguageId;
            } else {
                //what do you want to do if product not available in current display language?
                //allow the available product language to be returned
                $mappedIds[]=$productId;
            }
        }
        return $mappedIds;
    }

    
    
    /**
     * Declare 'product' (and 'product_variation') to Polylang as translatable
     * post types at runtime.
     *
     * @param array $types post type names already managed by Polylang
     * @return array
     */
    public function manageProductTranslation(array $types)
    {
        if (!in_array('product', $types, true)) {
            $types[] = 'product';
        }
        // Note: v1.x didn't add 'product_variation' to the filter return. We don't
        // either — Polylang treats variations specially via parent post sync.
        return $types;
    }

    /**
     * Idempotently ensure 'product' and 'product_variation' are registered in
     * Polylang's stored options.
     *
     * Replaces the v1.x pattern of mutating `get_option('polylang')` directly,
     * which is unsafe on Polylang 3.7+ per the options-object refactor.
     *
     * @see \Hyyan\WPI\Compat\PolylangOptions
     */
    public function ensureProductPostTypesRegisteredInPolylangOptions()
    {
        PolylangOptions::registerPostType('product');
        PolylangOptions::registerPostType('product_variation');
    }

    /**
     * Tell Polylang to sync the post parent (used for grouped products to keep
     * child references aligned across translations).
     */
    public function ensurePostParentSyncEnabled()
    {
        PolylangOptions::enableSync('post_parent');
    }

    /**
     * Get product attributes in right language.
     * @param array $args array of arguments for get_terms function in WooCommerce
     *                    attributes html markup
     *
     * @return array
     */
    public function getProductAttributesInLanguage($args)
    {
        global $post;
        $lang = '';

        if (isset($_GET['new_lang'])) {
            $lang = sanitize_key(wp_unslash($_GET['new_lang']));
        } elseif (!empty($post)) {
            $lang = pll_get_post_language($post->ID);
        } else {
            // @internal Polylang internal API; reverify on Polylang minor upgrades.
            $lang = PLL()->pref_lang;
        }

        $args['lang'] = $lang;

        return $args;
    }
        

    // Make sure Polylang copies the title when creating a translation
    public function wpi_editor_title($title)
    {
        // Polylang sets the 'from_post' parameter
        if (isset($_GET['from_post'])) {
            $my_post = get_post(absint(wp_unslash($_GET['from_post'])));
            if ($my_post) {
                return $my_post->post_title;
            }
        }
        return $title;
    }

    // Make sure Polylang copies the content when creating a translation
    public function wpi_editor_content($content)
    {
        // Polylang sets the 'from_post' parameter
        if (isset($_GET['from_post'])) {
            $my_post = get_post(absint(wp_unslash($_GET['from_post'])));
            if ($my_post) {
                return $my_post->post_content;
            }
        }
        return $content;
    }

    // Make sure Polylang copies the excerpt [woocommerce short description] when creating a translation
    public function wpi_editor_excerpt($excerpt)
    {
        // Polylang sets the 'from_post' parameter
        if (isset($_GET['from_post'])) {
            $my_post = get_post(absint(wp_unslash($_GET['from_post'])));
            if ($my_post) {
                return $my_post->post_excerpt;
            }
        }
        return $excerpt;
    }
}
