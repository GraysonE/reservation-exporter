<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use WP_REST_Server;
use MPHB\Ajax;

class Export {

	public function __construct() {

		add_action( 'rest_api_init', function () {
			register_rest_route( RE_API_NAMESPACE, '/export', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array(
						$this,
						'get'
					),
				),
			) );
		} );

	}

	public function get() {
		// pull from export_bookings_csv();
		$mphb_ajax = new Ajax();
		$bookings  = $mphb_ajax->export_bookings_csv();
		var_dump( $bookings );


		wp_send_json_success();
	}

	public function mphb_ajax() {

		// Prepare new export
		$input = $this->retrieveInput(__FUNCTION__);
		$args  = isset($input['args']) ? mphb_clean($input['args']) : array();
		$query = new \MPHB\CSV\Bookings\BookingsQuery($args);

		if ($query->hasErrors()) {
			wp_send_json_error(array('message' => $query->getErrorMessage()));
		} else {
			$args = $query->getInputs(); // Get validated inputs
		}

		// Save selected columns for next tries
		MPHB()->settings()->export()->setUserExportColumns($args['columns']);

		// Query bookings
		$ids = $query->query()->filterByRoomType($args['room'])->getIds();

		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No bookings found for your request.', 'motopress-hotel-booking')));
		}

		// Try to create the file
		$exporter->setupOutput($args);

		if (!file_exists($exporter->pathToFile())) {
			wp_send_json_error(array('message' => __('Uploads directory is not writable.', 'motopress-hotel-booking')));
		}

		// Start new export
		$exporter->data($ids)->save();
		$exporter->dispatch();
	}

}