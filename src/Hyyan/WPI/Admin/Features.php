<?php

/**
 * This file is part of the woo-poly-integration plugin.
 * Original (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 * Modernized fork (c) IntegrIT Solutions.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hyyan\WPI\Admin;

use Hyyan\WPI\Utilities;

/**
 * Features.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Features extends AbstractSettings
{
    /**
     * {@inheritdoc}
     */
    public static function getID()
    {
        return 'wpi-features';
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetSections()
    {
        return array(
            array(
                'title' => __('Features', 'woo-poly-integration'),
                /* translators: 1: Opening link tag to features documentation, 2: Closing link tag. */
                'desc' => sprintf(
                    __('The section will allow you to Enable/Disable Plugin Features. For more information please see: %1$sdocumentation pages%2$s.', 'woo-poly-integration'),
                    '<a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Settings---Features">',
                    '</a>'
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetFields()
    {
        return array(
            array(
                'name' => 'fields-locker',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Fields Locker', 'woo-poly-integration'),
                'desc' => __(
                        'Locks Meta fields which are set to be synchronized.', 'woo-poly-integration'
                ),
            ),
            array(
                'name' => 'emails',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Emails', 'woo-poly-integration'),
                'desc' => __(
                        'Use order language whenever WooCommerce sends order emails', 'woo-poly-integration'
                ),
            ),
            // Reports setting removed in v2.0.0 — the Reports module was dropped.
            // The legacy WooCommerce Reports screen is on Woo's roadmap for retirement
            // and the new wc-admin Analytics has no documented per-language extension
            // API. The stored 'reports' value (if present from v1.x) is left intact in
            // the options for reference; nothing reads it.
            array(
                'name' => 'coupons',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Coupons Sync', 'woo-poly-integration'),
                'desc' => __(
                        'Apply coupons rules for product and its translations', 'woo-poly-integration'
                ),
            ),
            array(
                'name' => 'stock',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Stock Sync', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Stock"> '.
                            __(
                        'Sync stock for product and its translations', 'woo-poly-integration'
                ) . '.</a> <strong>' . __(
                        'Note: this setting affects user actions on stock, to control synchronisation when editing products check the settings for Metas List, Stock Metas.', 'woo-poly-integration', 'woo-poly-integration'
                ) . '</strong>',
            ),
            array(
                'name' => 'categories',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Translate Categories', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Product-Categories"> '.
                            __(
                        'Enable categories translations', 'woo-poly-integration'
                )   . '</a>.',
            ),
            array(
                'name' => 'tags',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Translate Tags', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Product-Tags"> '.
                            __(
                        'Enable tags translations', 'woo-poly-integration'
                )   . '</a>.',
            ),
            array(
                'name' => 'attributes',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Translate Attributes', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Product-Attributes"> '.
                            __(
                        'Enable attributes translations', 'woo-poly-integration'
                )  . '</a>.',
            ),

            array(
                'name' => 'new-translation-defaults',
                'type' => 'radio',
                'default' => '0',   //starting this off for backwards compatibility, users should test before turning on
                'label' => __('New Translation Behaviour', 'woo-poly-integration'),
                'desc' => __(
                        'When creating new translations, start with blank text, copy or machine translation?' .
                                            ' (You may want to turn this off if using Polylang Pro, Lingotek or other automatic copy-or-translation solution.) ', 'woo-poly-integration'
                ),
                'options' => array(
                    '0' => 'Blank Text',
                    '1' => __('Copy Source', 'woo-poly-integration'),
                    '2' => __('Translate Source', 'woo-poly-integration') . ' (coming soon..  until available will use Copy Source) ',
                )
            ),
            array(
                'name' => 'localenumbers',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Use locale number formats', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Price-Localization"> ' .
                   __(
                        'Format numbers according to the convention for current language', 'woo-poly-integration')
                 . '</a>.'
                ,
            ),
            array(
                'name' => 'importsync',
                'type' => 'checkbox',
                'default' => 'on',
                'label' => __('Synchronize on Import', 'woo-poly-integration'),
                'desc' => ' <a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki/Upload,-Bulk-Edit,-Quick-Edit"> ' .
                   __(
                        'When using WooCommerce 3.1 importer to importing updates to existing items, apply synchronization rules to update any existing translations.', 'woo-poly-integration')
                 . '</a>.'
                ,
            ),
            array(
              'name'		 => 'checkpages',
              'type'		 => 'checkbox',
              'default'	 => 'off',
              'label'		 => __( 'Check WooCommerce Pages', 'woo-poly-integration' ),
              'desc'		 => '' .
              __(
              'Check if WooCommerce pages are present and correctly translated and published. Especially helpul on initial setup', 'woo-poly-integration' )
              . '.'
            ,
            ),
        );
    }
}
