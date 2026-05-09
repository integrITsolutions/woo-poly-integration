<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Widgets;

/**
 * SearchWidget.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class SearchWidget
{
    /**
     * Constuct object.
     */
    public function __construct()
    {
        add_filter('get_product_search_form', array(
            $this, 'fixSearchForm',
        ));
    }

    /**
     * Fix search form to avoid duplicated products results.
     *
     * Polylang 3.4 deprecated direct property access to PLL_Language::$search_url
     * and PLL_Language::$home_url; use get_search_url() / get_home_url() methods.
     * Polylang 3.7 made the underlying properties private.
     * @see https://polylang.pro/polylang-3-4-eases-the-translation-of-custom-tables/
     *
     * @global \Polylang $polylang
     *
     * @param string $form
     *
     * @return string modified form
     */
    public function fixSearchForm($form)
    {
        global $polylang;

        if (!$form) {
            return $form;
        }

        $current_language = function_exists('pll_current_language')
            ? pll_current_language(\OBJECT)
            // @internal Polylang internal API; reverify on Polylang minor upgrades.
            : (isset($polylang->curlang) ? $polylang->curlang : null);

        if (!$current_language) {
            return $form;
        }

        // @internal Polylang internal API; reverify on Polylang minor upgrades.
        if (isset($polylang->links_model) && $polylang->links_model->using_permalinks) {
            $search_url = method_exists($current_language, 'get_search_url')
                ? $current_language->get_search_url()
                : (isset($current_language->search_url) ? $current_language->search_url : '');

            if ($search_url) {
                /* Take care to modify only the url in the <form> tag */
                preg_match('#<form.+>#', $form, $matches);
                if (!empty($matches)) {
                    $old = reset($matches);
                    $new = preg_replace(
                        // @internal Polylang internal API; reverify on Polylang minor upgrades.
                        '#' . preg_quote($polylang->links_model->home, '#') . '\/?#',
                        $search_url,
                        $old
                    );
                    $form = str_replace($old, $new, $form);
                }
            }
        } elseif (isset($current_language->slug)) {
            $form = str_replace(
                '</form>',
                '<input type="hidden" name="lang" value="'
                    . esc_attr($current_language->slug)
                    . '" /></form>',
                $form
            );
        }

        return $form;
    }
}
