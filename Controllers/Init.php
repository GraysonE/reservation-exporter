<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use GraysonErhard\ReservationExporter\Controllers\Enqueue;


class Init {

	public function __construct() {

		include_once RE_VIEW_PATH . '/admin.php';
		add_action( 'rest_api_init', [ $this, 'api' ] );
		new Enqueue();
		new Export();

	}

	public function api() {
		define( 'RE_API_BASE_URL', trailingslashit( get_rest_url() ) . RE_API_NAMESPACE );
	}

	public function get() {

	}

	public function set() {

	}

	public function delete() {

	}

}