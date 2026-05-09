<?php

/**
 * This file is part of the hyyan/woo-poly-integration plugin.
 * (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Taxonomies;

/**
 * ShippingCalss.
 *
 * @deprecated since 2.0.0 Not registered by {@see Taxonomies}; kept for
 *             historical/reference purposes.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class ShippingCalss implements TaxonomiesInterface
{
    // @internal Not currently used. Taxonomy registration was disabled in v1.x.

    /**
     * {@inheritdoc}
     */
    public static function getNames()
    {
        return array('product_shipping_class');
    }
}
