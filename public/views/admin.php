<?php

namespace GraysonErhard\ReservationExporter\Views;


add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu' );

function admin_menu() {
	$hook = add_management_page( 'Export Reservations', 'Reservation Exporter', 'install_plugins',
		'reservation-exporter', __NAMESPACE__ . '\admin_page', 'dashicons-download' );
	add_action( "load-$hook", __NAMESPACE__ . '\admin_page_load' );
}


function admin_page_load() {
	// ...
}

function admin_page() {
	echo 'sup';
}