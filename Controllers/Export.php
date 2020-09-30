<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use WP_REST_Server;
use WP_Query;
use GraysonErhard\ReservationExporter\Controllers\BookingExport;
use GraysonErhard\ReservationExporter\Controllers\BookingCSV;

class Export {

	public $csv = '';
	public $csv_url = '';

	public function __construct() {

		add_action( 'rest_api_init', function () {
			register_rest_route( RE_API_NAMESPACE, '/export', [
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [
						$this,
						'get'
					],
				],
			] );
		} );

	}

	public function get() {

		$booking_export = new BookingExport();
		$export         = $booking_export->get_booking_data();
		$booking_csv    = new BookingCSV( $export );
		$csv_url        = $booking_csv->get_file_url();


		wp_send_json_success( [ 'url' => $csv_url, 'export_data' => $export ] );
	}


}