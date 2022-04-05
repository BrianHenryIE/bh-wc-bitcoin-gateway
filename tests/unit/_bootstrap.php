<?php
/**
 * PHPUnit bootstrap file for WP_Mock.
 *
 * @package    nullcorps/woocommerce-gateway-bitcoin
 */

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

global $project_root_dir;

$class_map = array(
	WC_Settings_API::class    => $project_root_dir . '/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php',
	WC_Payment_Gateway::class => $project_root_dir . '/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php',
);
spl_autoload_register(
	function ( $classname ) use ( $class_map ) {

		if ( array_key_exists( $classname, $class_map ) && file_exists( $class_map[ $classname ] ) ) {
			require_once $class_map[ $classname ];
		}
	}
);


global $plugin_root_dir;
require_once $plugin_root_dir . '/autoload.php';
