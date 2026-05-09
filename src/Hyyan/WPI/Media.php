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

/**
 * Media.
 *
 * Handle products media translation
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Media
{

    /**
     * Construct object.
     */
    public function __construct()
    {
        if (static::isMediaTranslationEnabled()) {
            add_filter(
                'woocommerce_product_get_gallery_image_ids',
                 array($this, 'translateGallery')
            );
        }
    }

    /**
     * Check if media translation is enabled in Polylang settings.
     *
     * Reads via PolylangOptions which uses the 3.7+ options-object API where
     * available, falling back to direct get_option for older versions.
     *
     * @return bool true if enabled, false otherwise
     */
    public static function isMediaTranslationEnabled()
    {
        return (bool) PolylangOptions::get('media_support', false);
    }

    /**
     * Translate product gallery.
     *
     * @param array $IDS current attachment IDS
     *
     * @return array translated attachment IDS
     */
    public function translateGallery(array $IDS)
    {
        $translations = array();
        foreach ($IDS as $ID) {
            $tr = pll_get_post($ID);
            if ($tr) {
                $translations [] = $tr;
                continue;
            }
            $translations [] = $ID;
        }

        return $translations;
    }
}
