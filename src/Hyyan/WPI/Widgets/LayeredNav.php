<?php

/**
 * This file is part of the hyyan/woo-poly-integration plugin.
 * (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Widgets;

/**
 * LayeredNav.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class LayeredNav
{
    /**
     * Construct object.
     */
    public function __construct()
    {
        add_action('init', array($this, 'layeredNavInit'), 1000);
    }

    /**
     * Layered Nav Init.
     *
     * @global array $_chosen_attributes
     *
     * @return false if not layered nav filter
     */
    public function layeredNavInit()
    {
        if (
                !(is_active_widget(false, false, 'woocommerce_layered_nav', true) &&
                !is_admin())
        ) {
            return false;
        }

        global $_chosen_attributes;

        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $tax) {
            $attribute = wc_sanitize_taxonomy_name($tax->attribute_name);
            $taxonomy = wc_attribute_taxonomy_name($attribute);
            $name = 'filter_'.$attribute;

            if (empty($_GET[$name]) || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $raw = $_GET[$name];
            if (is_array($raw)) {
                continue;
            }

            $raw = wp_unslash($raw);
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $terms = array_filter(array_map('sanitize_title', explode(',', $raw)));
            $termsTranslations = array();

            foreach ($terms as $term_slug) {
                $source_term = get_term_by('slug', $term_slug, $taxonomy);
                if (!$source_term || is_wp_error($source_term)) {
                    $termsTranslations[] = $term_slug;
                    continue;
                }

                $translation_id = pll_get_term($source_term->term_id);
                if ($translation_id && (int) $translation_id !== (int) $source_term->term_id) {
                    $translated_term = get_term($translation_id, $taxonomy);
                    if ($translated_term && !is_wp_error($translated_term)) {
                        $termsTranslations[] = $translated_term->slug;
                        continue;
                    }
                }

                $termsTranslations[] = $term_slug;
            }

            $_GET[$name] = implode(',', $termsTranslations);
            $_chosen_attributes[$taxonomy]['terms'] = $termsTranslations;
        }
    }
}
