<?php

/**
 * This file is part of the hyyan/woo-poly-integration plugin.
 * (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI;

/**
 * Login.
 *
 * Handle login
 *
 * @deprecated since 2.0.0 Not instantiated by the plugin core since v1.x and
 *             kept only for historical/reference purposes.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Login
{
    // @internal Not currently used. Was disabled in v1.x.

    /**
     * Construct object.
     */
    public function __construct()
    {
        add_filter(
                'woocommerce_login_redirect', array($this, 'getLoginRedirectPermalink'), 10, 2
        );
    }

    /**
     * Find the correct login redirect permalink.
     *
     * @param string $to redirect url
     *
     * @return string redirect url
     */
    public function getLoginRedirectPermalink($to)
    {
        $ID = url_to_postid($to);
        $translatedID = pll_get_post($ID);

        if ($translatedID) {
            return get_permalink($translatedID);
        }

        return $to;
    }
}
