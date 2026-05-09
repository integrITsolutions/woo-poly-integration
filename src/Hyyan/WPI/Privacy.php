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
 * Privacy.
 *
 */
class Privacy
{

    /**
     * Construct initiated on init by Plugin.php
     */
    public function __construct()
    {
        $this->registerPrivacyStrings();
        add_filter('woocommerce_get_privacy_policy_text', array($this, 'translatePrivacyPolicyText'), 10, 2);
        add_filter( 'woocommerce_demo_store', array( $this, 'translateDemoStoreNotice' ), 10, 2 );
        add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( $this, 'translateText' ), 10, 1 );
        add_filter( 'woocommerce_privacy_export_order_personal_data_meta', array( $this, 'addOrderLanguageMetaToPrivacyExport' ), 10, 1 );
        add_filter( 'woocommerce_privacy_remove_order_personal_data_meta', array( $this, 'addOrderLanguageMetaToPrivacyErasure' ), 10, 1 );
        add_filter( 'woocommerce_privacy_remove_order_personal_data_meta_value', array( $this, 'eraseOrderLanguageMetaValue' ), 10, 5 );
    }

    /**
     * Register woocommerce privacy policy messages in polylang strings
     * translations table.
     */
    public function registerPrivacyStrings()
    {
        $this->registerString('woocommerce_checkout_privacy_policy_text', get_option('woocommerce_checkout_privacy_policy_text', sprintf(__('Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.', 'woocommerce'), '[privacy_policy]')));                        
        $this->registerString('woocommerce_registration_privacy_policy_text', get_option('woocommerce_registration_privacy_policy_text', sprintf(__('Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our %s.', 'woocommerce'), '[privacy_policy]')));
        $this->registerString( 'woocommerce_store_notice', get_option( 'woocommerce_demo_store_notice' ) );
        $this->registerString( 'woocommerce_checkout_terms_and_conditions_checkbox_text', get_option( 'woocommerce_checkout_terms_and_conditions_checkbox_text' ) );
    }

    
    /**
     * Register setting and value in polylang strings translations table.
     * 
     * @param string   $setting     Name of setting/string to translate
     * @param string   $value       Value in base language
     */
    private function registerString($setting, $value)
    {
        if (function_exists('pll_register_string')) {
            pll_register_string($setting, $value, __('WooCommerce Privacy', 'woo-poly-integration'), true);
        }
    }

    /**
     * Register setting and value in polylang strings translations table.
     * 
     * @param string   $text        Text to be translated
     * @param string   $type        Name of privacy policy type 'checkout' or ‘registration’
     */
    public function translatePrivacyPolicyText($text, $type)
    {
        return $this->translateText($text);
    }
    public function translateText( $text )
    {
        if (function_exists('pll_register_string')) {
            $trans = pll__($text);
            return ($trans) ? $trans : $text;
        } else {
            return $text;
        }
    }

    public function translateDemoStoreNotice( $html, $notice ) {
        $trans = '';
        if ( function_exists( 'pll_register_string' ) ) {
            $trans = pll__( $notice );
            return '<p class="woocommerce-store-notice demo_store">' . wp_kses_post( $trans ) . ' <a href="#" class="woocommerce-store-notice__dismiss-link">' . esc_html__( 'Dismiss', 'woocommerce' ) . '</a></p>';
        } else {
            return $html;
        }
    }

    /**
     * Include order language meta in WooCommerce privacy exports.
     *
     * @param array $meta_to_export
     * @return array
     */
    public function addOrderLanguageMetaToPrivacyExport($meta_to_export)
    {
        if (!is_array($meta_to_export)) {
            $meta_to_export = array();
        }

        $meta_to_export[Order::META_KEY_LANGUAGE] = __('Order language', 'woo-poly-integration');
        return $meta_to_export;
    }

    /**
     * Include order language meta in WooCommerce privacy erasure list.
     *
     * @param array $meta_to_remove
     * @return array
     */
    public function addOrderLanguageMetaToPrivacyErasure($meta_to_remove)
    {
        if (!is_array($meta_to_remove)) {
            $meta_to_remove = array();
        }

        $meta_to_remove[Order::META_KEY_LANGUAGE] = 'text';
        return $meta_to_remove;
    }

    /**
     * Force full erasure of order language meta in privacy removal flow.
     *
     * Returning empty string makes WooCommerce delete the meta key.
     *
     * @param string   $anon_value
     * @param string   $meta_key
     * @param string   $value
     * @param string   $data_type
     * @param \WC_Order $order
     * @return string
     */
    public function eraseOrderLanguageMetaValue($anon_value, $meta_key, $value, $data_type, $order)
    {
        if ($meta_key === Order::META_KEY_LANGUAGE) {
            return '';
        }

        return $anon_value;
    }

}
