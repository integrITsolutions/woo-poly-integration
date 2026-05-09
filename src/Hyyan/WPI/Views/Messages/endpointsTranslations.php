<?php

if (!defined('ABSPATH')) {
    exit('restricted access');
}
?>

<?php

/* translators: 1: URL to Polylang strings page, 2: Link text. */
printf(
    __( 'You can translate woocommerce endpoints, email strings, shipping methods from polylang strings tab. <a target="_blank" href="%1$s">%2$s</a>', 'woo-poly-integration'), 
    add_query_arg(
        array(
          'page'	 => 'mlang_strings',
          'group' => \Hyyan\WPI\Endpoints::getPolylangStringSection(),
        ), 
        admin_url( 'admin.php' ) 
    ), 
    __( 'Translate', 'woo-poly-integration' )
)
?>
