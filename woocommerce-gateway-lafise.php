<?php
/**
 * Plugin Name: WooCommerce Banco LAFISE Payments Gateway
 * Plugin URI: https://www.jarscr.com/lafise
 * Description: Adds the LAFISE Payments gateway to your WooCommerce website.
 * Version: 6.10.0
 *
 * Author: JARS Costa Rica
 * Author URI: https://www.jarscr.com.com/
 *
 * Text Domain: woocommerce-gateway-lafise
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 6.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//require_once "vendor/autoload.php";
/**
 * WC LAFISE Payment gateway plugin class.
 *
 * @class WC_LAFISE_Payments
 */
class WC_LAFISE_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		// LAFISE Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Make the LAFISE Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_lafise_woocommerce_block_support' ) );

		//Scripts JS CSS
		add_action( 'wp_enqueue_scripts', array( __CLASS__,'woocommerce_gateway_lafise_public_scripts'));

	}

	/**
	 * Add the LAFISE Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {

		$options = get_option( 'woocommerce_lafise_settings', array() );

		if ( isset( $options['hide_for_non_admin_users'] ) ) {
			$hide_for_non_admin_users = $options['hide_for_non_admin_users'];
		} else {
			$hide_for_non_admin_users = 'no';
		}

		if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
			$gateways[] = 'WC_Gateway_LAFISE';
		}
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Make the WC_Gateway_LAFISE class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-gateway-lafise.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_gateway_lafise_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-lafise-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_LAFISE_Blocks_Support() );
				}
			);
		}
	}

	public static function woocommerce_gateway_lafise_public_scripts()
	{
		wp_register_style('jars_lafise_public_styles', plugin_dir_url(__FILE__) . 'assets/css/jars_lafise.css', '', '1.1', 'all');
		wp_enqueue_style('jars_lafise_public_styles');

		wp_register_script('card_lafise_public_code_js', plugin_dir_url(__FILE__) . 'assets/js/frontend/jars_lafise.js', '', '1.1', 'all');
		wp_enqueue_script('card_lafise_public_code_js');
	}

}

WC_LAFISE_Payments::init();
