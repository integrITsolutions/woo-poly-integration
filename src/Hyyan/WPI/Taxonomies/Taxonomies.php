<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Taxonomies;

use Hyyan\WPI\Admin\Settings;
use Hyyan\WPI\Admin\Features;
use Hyyan\WPI\Compat\PolylangOptions;

/**
 * Taxonomies.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Taxonomies
{
    /**
     * Managed taxonomies.
     *
     * @var array
     */
    protected $managed = array();

    /**
     * Construct object.
     */
    public function __construct()
    {
        /* Just to prepare taxonomies  */
        $this->prepareAndGet();

        /* Manage taxonomies translation */
        add_filter(
                'pll_get_taxonomies', array($this, 'getAllTranslateableTaxonomies'), 10, 2
        );
                
        add_action('update_option_wpi-features', array(__CLASS__, 'updatePolyLangFromWooPolyFeatures'), 10, 3);

        add_action('update_option_wpi-metas-list', array(__CLASS__, 'updatePolyLangFromWooPolyMetas'), 10, 3);
    }

    /**
         * All this function needs to do is:
         *   if called requesting all available settings
         *			return all taxonomies enabled in woo-poly
         * This is because Polylang only saves the options which are turned on in Polylang so needs to
         * be told about the others.
     *
     * @param array $taxonomies array of cutoms taxonomies managed by polylang
          * @param bool  $is_settings true when displaying the list of custom taxonomies in Polylang settings
     *
     * @return array
     */
    public function getAllTranslateableTaxonomies($taxonomies, $is_settings)
    {
        //if not called to get all settings, simply return the input
        if (!($is_settings)) {
            return $taxonomies;
        }

        //otherwise, called by Polylang Settings, return translatable taxonomies
        $add = array();
        $tax_types = array(
            'attributes' => 'Hyyan\WPI\Taxonomies\Attributes',
            'categories' => 'Hyyan\WPI\Taxonomies\Categories',
            'tags' => 'Hyyan\WPI\Taxonomies\Tags',
            //'shipping-class' => 'Hyyan\WPI\Taxonomies\ShippingCalss',
        );

        //for each type, add it
        foreach ($tax_types as $tax_type => $class) {
            $names = $class::getNames();
            if ('on' === Settings::getOption($tax_type, Features::getID(), 'on')) {
                $add = array_merge($add, $names);
            }
        }
                
        return array_merge($taxonomies, $add);
    }

        
        
    /**
         * Hook to allow some customization when WooPoly Settings are saved,
         * for example some settings should be updated in Polylang Settings
         * [we could also catch some mutually incompatible woopoly settings,
         *  by hooking pre_update_option_wpi-metas-list]
     *
     * @param array $old_value   previous WooPoly settings
     * @param array $new_value   new WooPoly settings
          * @param string $option		 option name
     *
     * @return array
     */
    public static function updatePolyLangFromWooPolyMetas($old_value, $new_value, $option)
    {
        //we could update Polylang settings for Featured Image, Comment Status, Page Order
        //if the WooPoly settings have changed, but note this would also affect Posts
        return true;
    }

    /**
         * When WooPoly settings are saved, we should try to update the related Polylang Settings
     *
     * @param array $old_value   previous WooPoly settings
     * @param array $new_value   new WooPoly settings
          * @param string $option		option name
     *
     * @return array
     */
    /**
     * When WooPoly settings are saved, sync the related Polylang taxonomy
     * registrations (product_cat, product_tag, attribute taxonomies).
     *
     * Refactored from v1.x's direct option mutation. Goes through
     * {@see PolylangOptions} which uses the 3.7+ options-object API where
     * available, falling back to direct option mutation on older versions.
     *
     * @param array  $old_value previous WooPoly settings
     * @param array  $new_value new WooPoly settings
     * @param string $option    option name
     */
    public static function updatePolyLangFromWooPolyFeatures($old_value, $new_value, $option)
    {
        // Categories: explicitly enable or disable.
        if (isset($new_value['categories']) && $new_value['categories'] === 'on') {
            PolylangOptions::registerTaxonomy('product_cat');
        } else {
            PolylangOptions::removeFromList('taxonomies', 'product_cat');
        }

        // Tags: explicitly enable or disable.
        if (isset($new_value['tags']) && $new_value['tags'] === 'on') {
            PolylangOptions::registerTaxonomy('product_tag');
        } else {
            PolylangOptions::removeFromList('taxonomies', 'product_tag');
        }

        // Attributes: don't force ON for all attributes (each attribute toggles
        // individually via newProductAttribute), but force OFF when the global
        // setting is disabled.
        if (isset($old_value['attributes']) && isset($new_value['attributes'])
            && $old_value['attributes'] !== $new_value['attributes']
            && $new_value['attributes'] !== 'on'
        ) {
            $remove = Attributes::getNames();
            foreach ($remove as $tax) {
                PolylangOptions::removeFromList('taxonomies', $tax);
            }
        }
    }
    
    
    /**
     * Get managed taxonomies.
     *
     * @return array taxonomies that must be added and removed to polylang
     */
    protected function prepareAndGet()
    {
        $add = array();
        $remove = array();
        $supported = array(
            'attributes' => 'Hyyan\WPI\Taxonomies\Attributes',
            'categories' => 'Hyyan\WPI\Taxonomies\Categories',
            'tags' => 'Hyyan\WPI\Taxonomies\Tags',
            //'shipping-class' => 'Hyyan\WPI\Taxonomies\ShippingCalss',
        );

        foreach ($supported as $option => $class) {
            $names = $class::getNames();
            if ('on' === Settings::getOption($option, Features::getID(), 'on')) {
                $add = array_merge($add, $names);
                if (!isset($this->managed[$class])) {
                    $this->managed[$class] = new $class();
                }
            } else {
                $remove = array_merge($remove, $names);
            }
        }

        return array($add, $remove);
    }
}
