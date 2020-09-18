<?php


namespace GraysonErhard\ReservationExporter\Controllers;


class Enqueue {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	public function admin_scripts() {
		wp_enqueue_script( 'reservation-exporter', RE_ASSET_URL . 'js/export.es6.js', array( 'jquery' ), true );
	}

	public function admin_styles() {

	}

}