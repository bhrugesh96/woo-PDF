<?php
/**
 * Plugin Name: Woo PDF
 * Plugin URI: https://wordpress.org/
 * Description: A plugin to download pdf for WooCommerce orders.
 * Version: 1.0.0
 * Author: Bhrugesh Bavishi
 * License: GPL v2 or later
 * @package WCPDF
 */

 //direct script not allow
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WOO_PDF_PLUGIN_FILE' ) ) {
	define( 'WOO_PDF_PLUGIN_FILE', __FILE__ );
}

// Include the main Woo_Order_PDF_Generate class.
if ( ! class_exists( 'Woo_Order_PDF_Generate', false ) ) {
	include_once dirname( WOO_PDF_PLUGIN_FILE ) . '/inc/class-woo-order-pdf-download.php';
}

//Run Woo PDF class
$Woo_Order_PDF_Download = new Woo_Order_PDF_Download();

?>