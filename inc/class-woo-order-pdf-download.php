<?php
/**
 * Register Woo_Order_PDF_Download class.
 *
 * @package Woo-Pdf
 */

defined( 'ABSPATH' ) || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Main Class.
 */
class Woo_Order_PDF_Download {
	/**
	 * Default constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'woocommerce_install_exist' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_pdf_header' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_pdf_content' ) );
		add_action( 'wp_ajax_get_order_details', array( $this, 'woo_pdf_order_details_ajax' ) );
		add_action( 'wp_ajax_nopriv_get_order_details', array( $this, 'woo_pdf_order_details_ajax' ) );
		include_once dirname( WOO_PDF_PLUGIN_FILE ) . '/vendor/autoload.php';
	}

	/**
	 * Check WooCommerce plugin is installed or not.
	 */
	public function woocommerce_install_exist() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="error"><p>' . esc_html__( 'WC Order PDF Download plugin requires the WooCommerce plugin to be installed and activated!', 'woopdf' ) . '</p></div>';
				}
			);
			return;
		}
	}

	/**
	 * Add column header to 'Orders' page after 'Status' column in pdf.
	 *
	 * @param array $columns Order Column in woocoommerce.
	 */
	public function order_pdf_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;
			if ( 'order_status' === $column_name ) {
				$new_columns['order_download'] = esc_html__( 'PDF', 'woopdf' );
			}
		}
		return $new_columns;
	}

	/**
	 * Add link for the pdf column.
	 *
	 * @param array $column Order Column in woocoommerce.
	 */
	public function order_pdf_content( $column ) {
		global $post;
		if ( 'order_download' === $column ) {
			$order   = wc_get_order( $post->ID );
			$pdf_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=get_order_details&order_id=' . $post->ID ), 'generate_wp_wcopd' );
			echo "<a class='order_download_pdf_col' href='" . esc_url( $pdf_url ) . "'>" . esc_html__( 'Download Invoice', 'woopdf' ) . '</a>';
		}
	}


	/**
	 * Retrieve order details in PDF.
	 */
	public function woo_pdf_order_details_ajax() {

		check_ajax_referer( 'generate_wp_wcopd', 'security' );

		if ( isset( $_REQUEST['order_id'] ) && ! empty( $_REQUEST['order_id'] ) ) {

			$order_id = sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) );
			$allowed  = true; // Set default is allowed.

			// Check if user is log in.
			if ( ! is_user_logged_in() ) {
				$allowed = false;
			}

			// Check current user can view order.
			if ( ! current_user_can( 'manage_options' ) && isset( $_GET['my-account'] ) ) {
				if ( ! current_user_can( 'view_order', $order_id ) ) {
					$allowed = false;
				}
			}

			if ( ! $allowed ) {
				wp_die( esc_html__( 'You not authorized.', 'woopdf' ) );
			}

			$order              = wc_get_order( $order_id );
			$billing_first_name = $order->get_billing_first_name();
			$billing_last_name  = $order->get_billing_last_name();
			$currency           = $order->get_currency();
			$order_total        = $order->get_total();
			$order_items        = $order->get_items();
			$payment_method     = $order->get_payment_method_title();
			$site_logo_id       = get_theme_mod( 'custom_logo' );
			$sitelogo           = wp_get_attachment_image_src( $site_logo_id, 'full' );

			$html = '';

			$html .= '<table style="text-align:center;width:100%;border:0;margin-bottom:10mm">';
			$html .= '<tr>';

			if ( empty( $sitelogo[0] ) ) {
				$html .= '<td><h2>' . get_bloginfo( 'name' ) . '</h2></td>';
			} else {
				$html .= '<td><img style="max-width:200px" src="' . $sitelogo[0] . '"></td>';
			}

			$html .= '</tr>';
			$html .= '</table>';
			$html .= '<table cellpadding="10" cellspacing="0" border="1" style="border:1px dashed black;width:100%">';
			$html .= '<tr><td><strong>' . esc_html__( 'Order Number', 'woopdf' ) . '</strong></td><td>' . $order_id . '</td></tr>';
			$html .= '<tr><td><strong>' . esc_html__( 'First Name', 'woopdf' ) . '</strong></td><td>' . $billing_first_name . '</td></tr>';
			$html .= '<tr><td><strong>' . esc_html__( 'Last Name', 'woopdf' ) . '</strong></td><td>' . $billing_last_name . '</td></tr>';
			$html .= '<tr><td><strong>' . esc_html__( 'Payment Method', 'woopdf' ) . '</strong></td><td>' . $payment_method . '</td></tr>';
			$html .= '<tr><td><strong>' . esc_html__( 'Items', 'woopdf' ) . '</strong></td>';
			$html .= '<td><table cellpadding="5" cellspacing="0" border="1" style="border:1px dashed black;width:100%"><tr><td><strong>' . esc_html__( 'Item Name', 'woopdf' ) . '</strong></td><td><strong>' . esc_html__( 'Quantity', 'woopdf' ) . '</strong></td><td><strong>' . esc_html__( 'Price', 'woopdf' ) . '</strong></td></tr>';

			foreach ( $order_items as $item_id => $order_item ) {
				$html .= '<tr><td>' . $order_item->get_name() . '</td><td>' . $order_item->get_quantity() . '</td><td>' . $currency . ' ' . $order_item->get_total() . '</td></tr>';
			}

			$html .= '</table></td></tr>';
			$html .= '<tr><td><strong>' . esc_html__( 'Order Total', 'woopdf' ) . '</strong></td><td>' . $currency . ' ' . $order_total . '</td></tr>';
			$html .= '</table>';

			$filename = 'ORDER-INVOICE-' . $order_id;
			$options  = new Options();

			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'defaultFont', 'Courier' );

			$dompdf = new DOMPDF( $options );

			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$dompdf->stream( $filename, array( 'Attachment' => 1 ) );

		}
		exit;
	}

}
