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

use Hyyan\WPI\Tools\FlashMessages;
use Hyyan\WPI\Admin\Settings;
use Hyyan\WPI\Admin\Features;

/**
 * Plugin.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
class Plugin
{

    /** Required woocommerce version */
    const WOOCOMMERCE_VERSION = '9.0.0';

    /** Required polylang version */
    const POLYLANG_VERSION = '3.4.5';

    /** Option: pending v2 order-language migration admin notice. */
    const OPTION_V2_MIGRATION_NOTICE_PENDING = 'wpi_v2_migration_notice_pending';

    /** Option: dismissed v2 order-language migration admin notice. */
    const OPTION_V2_MIGRATION_NOTICE_DISMISSED = 'wpi_v2_migration_notice_dismissed';

    /** Transient used to render one-time migration success notice. */
    const TRANSIENT_V2_MIGRATION_RESULT = 'wpi_v2_migration_result';

    /**
     * Plugin basename (folder/file relative to WP plugins dir). Computed at runtime
     * so the value remains correct when the folder name differs from the slug
     * (zip-suffix updates, vendor relocations, custom deploys).
     */
    public static function pluginBasename()
    {
        return plugin_basename(Hyyan_WPI_DIR);
    }

    /**
     * Construct the plugin.
     */
    public function __construct()
    {
        FlashMessages::register();

        add_action('init', array($this, 'activate'));
        // Load textdomain on `init` (priority 1) per WordPress 6.7 JIT translation
        // requirements. Calling __() before `init` triggers a _doing_it_wrong notice.
        // @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
        add_action('init', array($this, 'loadTextDomain'), 1);
		add_action( 'admin_init', array( __CLASS__, 'admin_activate' ) );

        add_action( 'pll_add_language', array( __CLASS__, 'handleNewLanguage' ) );

        if ( is_admin() ) {
            add_action('admin_notices', function () {
                self::renderV2MigrationNotices();
            });
            add_action('admin_post_wpi_run_migration', function () {
                self::handleV2MigrationRunRequest();
            });
            add_action('admin_post_wpi_dismiss_migration_notice', function () {
                self::handleV2MigrationDismissRequest();
            });

            if ( wp_doing_ajax() ) {
                //skipping ajax
            } else {
                $wcpagecheck_passed = get_option( 'wpi_wcpagecheck_passed' );
                $check_pages		 = Settings::getOption( 'checkpages', Features::getID(), 0 );
                if ( ($check_pages && $check_pages != 'off') || ! ($wcpagecheck_passed) ) {
                    add_action( 'current_screen', array( __CLASS__, 'wpi_ensure_woocommerce_pages_translated' ) );
                }
            }
        }
    }

    /*
     * enable admin features in admin mode
     */
	public static function admin_activate() {
		include_once( plugin_dir_path( __FILE__ ) . 'Admin/StatusReport.php');
	}

    /*
     * when new language is added in polylang, flag that default pages should be rechecked
     * (try not to download immediately as translation files will not be downloaded yet)
     */
    public static function handleNewLanguage( $args ) {
        update_option( 'wpi_wcpagecheck_passed', false );
    }

    /**
     * Load plugin langauge file.
     */
    public function loadTextDomain()
    {
        load_plugin_textdomain(
                'woo-poly-integration', false, plugin_basename(dirname(Hyyan_WPI_DIR)) . '/languages'
        );
    }

    /**
     * Activate plugin.
     *
     * The plugin will register its core if the requirements are full filled , otherwise
     * it will show an admin error message
     *
     * @return bool false if plugin can not be activated
     */
    public function activate()
    {
        if (!static::canActivate()) {
            FlashMessages::remove(MessagesInterface::MSG_SUPPORT);
            FlashMessages::add(
                    MessagesInterface::MSG_ACTIVATE_ERROR, static::getView('Messages/activateError'), array('error'), true
            );

            return false;
        }

        FlashMessages::remove(MessagesInterface::MSG_ACTIVATE_ERROR);
        FlashMessages::add(
                MessagesInterface::MSG_SUPPORT, static::getView('Messages/support')
        );

        add_filter('plugin_action_links_' . self::pluginBasename(), function ($links) {
            $baseURL = is_multisite() ? get_admin_url() : admin_url();
            $settingsLinks = array(
                static::settingsLinkHTML(),
                '<a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki">'
                . __('Docs', 'woo-poly-integration')
                . '</a>',
            );

            return $settingsLinks + $links;
        });

        add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
        
        $oldVersion = get_option('wpi_version');
        $wcpagecheck_passed	 = get_option( 'wpi_wcpagecheck_passed' );
        if ( ! $wcpagecheck_passed || version_compare( self::getVersion(), $oldVersion, '<>' ) ) {
            self::onUpgrade(self::getVersion(), $oldVersion);
        }

        $this->registerCore();
    }

	/*
	 * make settings page link easily available from multiple messages
	 */
	public static function settingsLinkHTML() 
	{
		$baseURL = is_multisite() ? get_admin_url() : admin_url();
		return '<a href="'
		. $baseURL
		. 'options-general.php?page=hyyan-wpi">'
		. __( 'Settings', 'woo-poly-integration' )
		. '</a>';
	}

    /**
     * Check if the plugin can be activated.
     *
     * Detects Polylang via its public function API (works for both Polylang Free and
     * Polylang Pro since Pro doesn't register the unprefixed `Polylang` class).
     * @see https://polylang.pro/documentation/support/developers/function-reference/
     *
     * @return bool true if can be activated , false otherwise
     */
    public static function canActivate()
    {
        $polylang = false;
        $woocommerce = false;

        /* check polylang plugin via public function API */
        if (function_exists('pll_get_post_language') && function_exists('pll_default_language')) {
            // PLL() and its model are still required by code paths that touch internal API.
            // Tested presence of the global is more reliable than class detection across Free/Pro.
            // @internal Polylang internal API; reverify on Polylang minor upgrades.
            if (function_exists('PLL') && isset($GLOBALS['polylang']) && PLL() && isset(PLL()->model)) {
                if (pll_default_language()) {
                    $polylang = true;
                }
            }
        }

        /* check woocommerce plugin */
        if (class_exists('WooCommerce')) {
            $woocommerce = true;
        }


        return ($polylang && Utilities::polylangVersionCheck(self::POLYLANG_VERSION)) &&
                ($woocommerce && Utilities::woocommerceVersionCheck(self::WOOCOMMERCE_VERSION));
    }

    /**
     * On Upgrade
     *
     * Run on the plugin updates only once
     *
     * @param num $newVersion
     * @param num $oldVersion
     */
    public static function onUpgrade($newVersion, $oldVersion)
    {
        update_option('wpi_version', self::getVersion());

        $features = get_option(Admin\Features::getID());
        if (!$features) {
            $features = self::getDefaultFeatures();
            update_option(Admin\Features::getID(), $features);
        }
        Taxonomies\Taxonomies::updatePolyLangFromWooPolyFeatures($features, $features, Admin\Features::getID());

        $metas = get_option(Admin\MetasList::getID());
        if (!$metas) {
            $metas = self::getDefaultMetas();
            update_option(Admin\MetasList::getID(), $metas);
            Taxonomies\Taxonomies::updatePolyLangFromWooPolyMetas($metas, $metas, Admin\MetasList::getID());
        }

        $is_v2_or_newer = version_compare((string) $newVersion, '2.0.0', '>=');
        $old_version = is_string($oldVersion) ? trim($oldVersion) : '';
        $is_from_v1 = (strpos($old_version, '1.') === 0);
        $is_fresh_install = ($old_version === '');
        if ($is_v2_or_newer && ($is_from_v1 || $is_fresh_install)) {
            update_option(self::OPTION_V2_MIGRATION_NOTICE_PENDING, 1);
            update_option(self::OPTION_V2_MIGRATION_NOTICE_DISMISSED, 0);

            if ($is_fresh_install) {
                Order::migrateLegacyTaxonomyToMeta();
                delete_option(self::OPTION_V2_MIGRATION_NOTICE_PENDING);
                update_option(self::OPTION_V2_MIGRATION_NOTICE_DISMISSED, 1);
            }
        }

        flush_rewrite_rules(true);
    }

    /**
     * Render v2 migration warning/success admin notices.
     */
    private static function renderV2MigrationNotices()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $migrated_count = get_transient(self::TRANSIENT_V2_MIGRATION_RESULT);
        if ($migrated_count !== false) {
            delete_transient(self::TRANSIENT_V2_MIGRATION_RESULT);
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html(sprintf(__('Order language migration completed. Updated %d orders.', 'woo-poly-integration'), (int) $migrated_count))
                . '</p></div>';
        }

        if (!self::shouldShowV2MigrationNotice()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Woo-Poly Integration v2 stores order language in order meta for HPOS compatibility. Run the one-time migration to backfill legacy orders.', 'woo-poly-integration')
            . '</p><p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
        wp_nonce_field('wpi_run_migration');
        echo '<input type="hidden" name="action" value="wpi_run_migration" />';
        submit_button(__('Run migration', 'woo-poly-integration'), 'primary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
        wp_nonce_field('wpi_dismiss_migration_notice');
        echo '<input type="hidden" name="action" value="wpi_dismiss_migration_notice" />';
        submit_button(__('Dismiss', 'woo-poly-integration'), 'secondary', 'submit', false);
        echo '</form>';

        echo '</p></div>';
    }

    /**
     * Should we display the migration admin notice.
     *
     * @return bool
     */
    private static function shouldShowV2MigrationNotice()
    {
        if ((bool) get_option(self::OPTION_V2_MIGRATION_NOTICE_DISMISSED, false)) {
            return false;
        }

        return (bool) get_option(self::OPTION_V2_MIGRATION_NOTICE_PENDING, false);
    }

    /**
     * Handle "Run migration" admin POST action.
     */
    private static function handleV2MigrationRunRequest()
    {
        if (!current_user_can('manage_woocommerce')) {
            self::redirectAfterMigrationNoticeAction();
        }
        check_admin_referer('wpi_run_migration');

        $migrated_count = Order::migrateLegacyTaxonomyToMeta();
        delete_option(self::OPTION_V2_MIGRATION_NOTICE_PENDING);
        update_option(self::OPTION_V2_MIGRATION_NOTICE_DISMISSED, 1);
        set_transient(self::TRANSIENT_V2_MIGRATION_RESULT, (int) $migrated_count, MINUTE_IN_SECONDS);

        self::redirectAfterMigrationNoticeAction();
    }

    /**
     * Handle "Dismiss" admin POST action.
     */
    private static function handleV2MigrationDismissRequest()
    {
        if (!current_user_can('manage_woocommerce')) {
            self::redirectAfterMigrationNoticeAction();
        }
        check_admin_referer('wpi_dismiss_migration_notice');

        delete_option(self::OPTION_V2_MIGRATION_NOTICE_PENDING);
        update_option(self::OPTION_V2_MIGRATION_NOTICE_DISMISSED, 1);

        self::redirectAfterMigrationNoticeAction();
    }

    /**
     * Redirect back to referring admin screen after notice action.
     */
    private static function redirectAfterMigrationNoticeAction()
    {
        $redirect_to = wp_get_referer();
        if (!$redirect_to) {
            $redirect_to = admin_url('plugins.php');
        }
        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
     * Default features option. Replaces v1.x's serialize-blob string. The 'reports'
     * key was removed in v2.0.0 — see Plugin::registerCore() for the rationale.
     *
     * @return array
     */
    protected static function getDefaultFeatures()
    {
        return array(
            'fields-locker'            => 'on',
            'emails'                   => 'on',
            // 'reports' removed in v2.0.0.
            'coupons'                  => 'on',
            'stock'                    => 'on',
            'categories'               => 'on',
            'tags'                     => 'on',
            'attributes'               => 'on',
            'new-translation-defaults' => '1',
            'localenumbers'            => 'on',
            'importsync'               => 'on',
            'checkpages'               => 'off',
            'language-downloader'      => 'on',
        );
    }

    /**
     * Default product-meta-sync option. Replaces v1.x's serialize-blob string.
     *
     * @return array
     */
    protected static function getDefaultMetas()
    {
        return array(
            'general' => array(
                'product-type'              => 'product-type',
                '_virtual'                  => '_virtual',
                '_sku'                      => '_sku',
                '_upsell_ids'               => '_upsell_ids',
                '_crosssell_ids'            => '_crosssell_ids',
                '_children'                 => '_children',
                '_product_image_gallery'    => '_product_image_gallery',
                'total_sales'               => 'total_sales',
                '_translation_porduct_type' => '_translation_porduct_type',
                '_visibility'               => '_visibility',
            ),
            'polylang' => array(
                'menu_order'     => 'menu_order',
                '_thumbnail_id'  => '_thumbnail_id',
                'comment_status' => 'comment_status',
            ),
            'stock' => array(
                '_manage_stock'      => '_manage_stock',
                '_stock'             => '_stock',
                '_backorders'        => '_backorders',
                '_stock_status'      => '_stock_status',
                '_low_stock_amount'  => '_low_stock_amount',
                '_sold_individually' => '_sold_individually',
            ),
            'shipping' => array(
                '_weight'                => '_weight',
                '_length'                => '_length',
                '_width'                 => '_width',
                '_height'                => '_height',
                'product_shipping_class' => 'product_shipping_class',
            ),
            'Attributes' => array(
                '_product_attributes'        => '_product_attributes',
                '_custom_product_attributes' => '_custom_product_attributes',
                '_default_attributes'        => '_default_attributes',
            ),
            'Downloadable' => array(
                '_downloadable'       => '_downloadable',
                '_downloadable_files' => '_downloadable_files',
                '_download_limit'     => '_download_limit',
                '_download_expiry'    => '_download_expiry',
                '_download_type'      => '_download_type',
            ),
            'Taxes' => array(
                '_tax_status' => '_tax_status',
                '_tax_class'  => '_tax_class',
            ),
            'price' => array(
                '_regular_price'         => '_regular_price',
                '_sale_price'            => '_sale_price',
                '_sale_price_dates_from' => '_sale_price_dates_from',
                '_sale_price_dates_to'   => '_sale_price_dates_to',
                '_price'                 => '_price',
            ),
            'Variables' => array(
                '_min_variation_price'            => '_min_variation_price',
                '_max_variation_price'            => '_max_variation_price',
                '_min_price_variation_id'         => '_min_price_variation_id',
                '_max_price_variation_id'         => '_max_price_variation_id',
                '_min_variation_regular_price'    => '_min_variation_regular_price',
                '_max_variation_regular_price'    => '_max_variation_regular_price',
                '_min_regular_price_variation_id' => '_min_regular_price_variation_id',
                '_max_regular_price_variation_id' => '_max_regular_price_variation_id',
                '_min_variation_sale_price'       => '_min_variation_sale_price',
                '_max_variation_sale_price'       => '_max_variation_sale_price',
                '_min_sale_price_variation_id'    => '_min_sale_price_variation_id',
                '_max_sale_price_variation_id'    => '_max_sale_price_variation_id',
            ),
        );
    }

    /**
     * Get current plugin version.
     *
     * @return int
     */
    public static function getVersion()
    {
        $data = get_plugin_data(Hyyan_WPI_DIR);

        return $data['Version'];
    }

    /**
     * Get plugin view.
     *
     * @param string $name view name
     * @param array  $vars array of vars to pass to the view
     *
     * @return string the view content
     */
    public static function getView($name, array $vars = array())
    {
        $result = '';
        $path = dirname(Hyyan_WPI_DIR) . '/src/Hyyan/WPI/Views/' . $name . '.php';
        if (file_exists($path)) {
            ob_start();
            include $path;
            $result = ob_get_clean();
        }

        return $result;
    }

    /**
     * Add plugin core classes.
     */
    protected function registerCore()
    {
        new Emails();
        new Admin\Settings();
        new Cart();
        //new Login();
        new Order();
        new Pages();
        new Endpoints();
        new Product\Product();
        new Taxonomies\Taxonomies();
        new Media();
        new Permalinks();
        new Privacy();
        new Language();
        new Coupon();
        // Reports module dropped in v2.0.0:
        //   The legacy WooCommerce Reports screen (`woocommerce_page_wc-reports`) is on
        //   WooCommerce's roadmap for retirement, and the new WC Analytics dashboard
        //   has no documented extension API for per-language filtering. The previous
        //   integration relied on internal Polylang SQL helpers
        //   (PLL()->model->post->join_clause / where_clause) and the legacy CPT-only
        //   wc_reports_* hooks. Both are unsuitable as the long-term integration
        //   surface. Users needing per-language reporting can install a dedicated
        //   analytics plugin or open an issue to discuss a wc-admin Analytics
        //   integration.
        new Widgets\SearchWidget();
        new Widgets\LayeredNav();
        new Gateways();
        new Shipping();
        new Breadcrumb();
        new Tax();
        new LocaleNumbers();
        new Ajax();
    }

    /**
     * Show row meta on the plugin screen.
     * allows documentation link to be available even when plugin is deactivated
     *
     * @param	mixed $links Plugin Row Meta
     * @param	mixed $file  Plugin Base file
     * @return	array
     */
    public static function plugin_row_meta($links, $file)
    {
        if (self::pluginBasename() === $file) {
            $row_meta = array(
                'docs' => '<a target="_blank" href="https://github.com/hyyan/woo-poly-integration/wiki"'
                . '" aria-label="' . esc_attr__('View WooCommerce-Polylang Integration documentation', 'woo-poly-integration') . '">'
                . esc_html__('Docs', 'woo-poly-integration') . '</a>',
                'support' => '<a target="_blank" href="https://github.com/hyyan/woo-poly-integration/issues"'
                . '" aria-label="' . esc_attr__('View Issue tracker on GitHub', 'woo-poly-integration') . '">'
                . esc_html__('Support', 'woo-poly-integration') . '</a>',
            );
            return array_merge($links, $row_meta);
        }

        return (array) $links;
    }

	/*
	 * Ensure woocommerce pages exist, are translated and published
	 * Missing pages will be added in appropriate language
	 *
	 */
	public static function wpi_ensure_woocommerce_pages_translated() {

		//to avoid repetition, only do this when we are going to be alerted to the results
			if ( ! is_admin() || wp_doing_ajax() ) {
				return;
			}

		/*
		 * only recheck this when on relevant pages such as pages list,
		 * and settings pages for the main plugins Polylang, WooCommerce and woopoly
		 * which might affect the woocommerce pages or transations
		 */
		$allowedPages	 = array(
			'woocommerce_page_wc-settings',
			'languages_page_mlang_settings',
			'toplevel_page_mlang',
			'settings_page_hyyan-wpi',
			'edit-page',
			'page',
			'plugins',
		);
		$screen			 = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
		if ( ! $screen || ! in_array( $screen->id, $allowedPages ) ) {
			return;
		}
		//avoid any re-entrance
		if ( get_option( 'wpi_wcpagecheck_passed' ) == 'checking' ) {
			return;
		}
		update_option( 'wpi_wcpagecheck_passed', 'checking' );

		//each of the main pages to create
		$page_types	 = array( 'cart', 'checkout', 'myaccount', 'shop' );
		$pages		 = array();
		$warnings	 = array();
		$failure		 = false;
		//only create pages if the setting is on, otherwise only warnings will be shown
		$create_pages	 = Settings::getOption( 'checkpages', Features::getID(), 0 );
		if ( $create_pages && $create_pages == 'off' ) {
			$create_pages = false;
		}

		//get status of current language environment
		$default_lang	 = pll_default_language();
		$default_locale	 = pll_default_language( 'locale' );
		$start_locale		 = ( is_admin() ) ? get_user_locale() : get_locale();
		//important: in admin mode and 'Show all language' there is no polylang current language
		$pll_start_locale	 = pll_current_language( 'locale' );

		/*
		 * important, we must be in the base language before doing the check
		 * because otherwise the posts will be pll filtered into other language
		 * and appear to be missing if not translated
		 */
		if ( $pll_start_locale ) {
			if ( $default_locale != $pll_start_locale ) {
				Utilities::switchLocale( $default_locale );
			}
		} elseif ( $default_locale != $start_locale ) {
			Utilities::switchLocale( $default_locale );
		}

		/*
		 * get the current id of each woocommerce page in the base language
		 * using the native woocommerce function to fill any missing page
		 */
		foreach ( $page_types as $page_type ) {
			$pageid = wc_get_page_id( $page_type );
			if ( $pageid == -1 || ! get_post( $pageid ) ) {
				if ( $create_pages ) {
				    //if any of the pages is missing, rerun the woocommerce page creation
				    //which will just fill in any missing page
				    \WC_Install::create_pages();
				    $pageid = wc_get_page_id( $page_type );
					$warnings[ $page_type . '::' . $default_locale ] = sprintf(
					__( '%1$s page in base language %2$s was not found and was created using woocommerce create_pages() as page <a href="%3$s">%4$s</a>', 'woo-poly-integration' ), $page_type, $default_locale, edit_post_link( $pageid, 'link' ), $pageid );
				} else {
					$warnings[ $page_type . '::' . $default_locale ] = sprintf(
					__( '%1$s page in language %2$s was not found and must be created for the shop to work: this will be done automatically if Check WooCommerce Pages option is enabled in %3$s.  Translations for this page may also be missing.', 'woo-poly-integration' ), $page_type, $default_locale, static::settingsLinkHTML() );
					$failure										 = true;
				}
			}
			$pages[ $page_type ] = $pageid;
		}

		//check the page is published in each language
		//get the locales and the slugs
		$langs		 = pll_languages_list( array( 'fields' => 'locale' ) );
		$lang_slugs	 = pll_languages_list();

		//for each page, check all the translations and fill in and link where necessary
			foreach ( $pages as $page_type => $orig_page_id ) {
				$changed	  = false;
				$translations = array();
				$orig_page = get_post( $orig_page_id );
				if ( $orig_page ) {
				$orig_postlocale = pll_get_post_language( $orig_page_id, 'locale' );
				$orig_postlang	 = pll_get_post_language( $orig_page_id, 'slug' );
				//default pages may not have language set correctly
				if ( ! $orig_postlocale ) {
					$orig_postlocale = $default_locale;
					$orig_postlang									 = $default_lang;
					pll_set_post_language( $orig_page_id, $orig_postlang );
					$warnings[ $page_type . '::' . $default_locale ] = sprintf(
					__( '%1$s page did not have language - language set to %2$s on page <a href="%3$s">%4$s</a>', 'woo-poly-integration' ), $page_type, $default_locale, edit_post_link( $orig_page_id, 'link' ), $orig_page_id );
				}
				$translations[ $orig_postlang ] = $orig_page_id;
				foreach ( $langs as $langId => $langLocale ) {
					$translation_id	 = $orig_page_id;
					$langSlug		 = $lang_slugs[ $langId ];
					$isNewPost		 = false;


					//if this is not the original language
					if ( $langLocale != $orig_postlocale ) {

						// and there is no translation in target language
							$translation_id = pll_get_post( $orig_page_id, $langSlug );
						if ( $translation_id == 0 || $translation_id == $orig_page_id ) {
							if ( $create_pages ) {
                                //then create new post in target language
                                $isNewPost = true;
                                Utilities::switchLocale( $langLocale );

                                //default to copy source page
                                $post_name		 = $orig_page->post_name;
                                $post_title		 = $orig_page->post_title;
                                $post_content	 = $orig_page->post_content;

                                //ideally, get correct translation
                                switch ( $page_type ) {
                                    case 'shop':
                                        $post_name		 = _x( 'shop', 'Page slug', 'woocommerce' );
                                        $post_title		 = _x( 'Shop', 'Page title', 'woocommerce' );
                                        $post_content	 = '';
                                        break;
                                    case 'cart':
                                        $post_name		 = _x( 'cart', 'Page slug', 'woocommerce' );
                                        $post_title		 = _x( 'Cart', 'Page title', 'woocommerce' );
                                        $post_content	 = '<!-- wp:shortcode -->[' . apply_filters( 'woocommerce_cart_shortcode_tag', 'woocommerce_cart' ) . ']<!-- /wp:shortcode -->';
                                        break;
                                    case 'checkout':
                                        $post_name		 = _x( 'checkout', 'Page slug', 'woocommerce' );
                                        $post_title		 = _x( 'Checkout', 'Page title', 'woocommerce' );
                                        $post_content	 = '<!-- wp:shortcode -->[' . apply_filters( 'woocommerce_checkout_shortcode_tag', 'woocommerce_checkout' ) . ']<!-- /wp:shortcode -->';
                                        break;
                                    case 'myaccount':
                                        $post_name		 = _x( 'my-account', 'Page slug', 'woocommerce' );
                                        $post_title		 = _x( 'My account', 'Page title', 'woocommerce' );
                                        $post_content	 = '<!-- wp:shortcode -->[' . apply_filters( 'woocommerce_my_account_shortcode_tag', 'woocommerce_my_account' ) . ']<!-- /wp:shortcode -->';
                                        break;
                                }


                                $page_data		 = array(
                                    'post_status'	 => 'publish',
                                    'post_type'		 => 'page',
                                    'post_author'	 => 1,
                                    'post_name'		 => $post_name,
                                    'post_title'	 => $post_title,
                                    'post_content'	 => $post_content,
                                    //'post_parent'	 => $post_parent,
                                    'comment_status' => 'closed',
                                );
                                $translation_id	 = wp_insert_post( $page_data );
							}
							//if there now is a translation is where there was not before, creation must have been successful
							if ( $translation_id ) {
							    pll_set_post_language( $translation_id, $langSlug );
							    $changed = true;
								$warnings[ $page_type . '::' . $langLocale ] = sprintf(
								__( '%1$s page in language %2$s was not found and was created as page <a href="%3$s">%4$s</a>', 'woo-poly-integration' ), $page_type, $langLocale, get_edit_post_link( $translation_id, 'link' ), $translation_id );
							} else {
								$warnings[ $page_type . '::' . $langLocale ] = sprintf(
								__( '%1$s page in language %2$s was not found and must be created for the shop to work in this language: this will be done automatically if Check WooCommerce Pages option is enabled in %3$s.', 'woo-poly-integration' ), $page_type, $langLocale, static::settingsLinkHTML() );
								$failure									 = true;
							}
						}
						//always add the existing translations back into the translations array
						if ( $translation_id ) {
						    $translations [ $langSlug ] = $translation_id;
					    }
					}
					//if this woocommerce page is an existing post, check post status
					if ( $translation_id && ! $isNewPost ) {
						$thisPost = get_post( $translation_id );
						if ( $thisPost ) {
							$postStatus = $thisPost->post_status;
							if ( $postStatus != 'publish' ) {
								$baseURL = is_multisite() ? get_admin_url() : admin_url();
								if ( $postStatus == 'trash' ) {
									$warnings[ $page_type . '::' . $langSlug ] = sprintf(
									__( '%1$s page in language %2$s has been deleted, please check the <a href="%3$s">trash</a>, and restore page %4$s', 'woo-poly-integration' ), $page_type, $langLocale, $baseURL . 'edit.php?post_status=trash&post_type=page&lang=' . $langSlug, $translation_id );
								} else {
									$warnings[ $page_type . '::' . $langSlug ] = sprintf(
									__( '%1$s page in language %2$s is in status %3$s and needs to be published for the shop to work properly, check page <a href="%4$s">%5$s</a>', 'woo-poly-integration' ), $page_type, $langLocale, $postStatus, get_edit_post_link( $translation_id, 'link' ), $translation_id );
								}
								$failure = true;
							}
						} else {
								$warnings[ $page_type . '::' . $langSlug ] = sprintf(
							__( '%1$s page in language %2$s was linked in polylang but cannot be found, link will be removed to missing page %3$s', 'woo-poly-integration' ), $page_type, $langLocale, $translation_id );
							unset( $translations[ $langSlug ] );
							$failure									 = true;
							$changed									 = true;
							}
						}
					}
				}
				if ( $changed ) {
					pll_save_post_translations( $translations );
				}
			}

		/*
		 * update result of page checks
		 */
		if ( $warnings ) {
			FlashMessages::add(
			'pagechecks', implode( '<br/>', $warnings )
			, array( 'updated' ), true
			);
		} else {
			FlashMessages::remove( 'pagechecks' );
		}
		if ( $failure ) {
			update_option( 'wpi_wcpagecheck_passed', '0' );
		} else {
			update_option( 'wpi_wcpagecheck_passed', '1' );
		}

		/*
		 * check current locale and reset it if changed
		 */
		$locale = get_locale();
		if ( $locale != $start_locale ) {
			Utilities::switch_wp_locale( $start_locale );
		}
		if ( $locale != $pll_start_locale ) {
			Utilities::switch_pll_locale( $pll_start_locale );
		}
	}

}
