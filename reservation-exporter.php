<?php

namespace GraysonErhard\ReservationExporter;

use GraysonErhard\ReservationExporter\Controllers\Init;

/**
Plugin Name: Reservation Exporter
Description: Exports reservation data for Buffalo Crossing.
Version: 0.0.0
Author: Grayson Erhard
 */


$upload_dir = wp_upload_dir();

define( 'PROJECT_TITLE', 'Reservation Exporter' );
define( 'API_NONCE_NAME', 're_api' );
define( 'PLUGIN_NAMESPACE', 'RE' );
define( 'RE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RE_API_NAMESPACE', 're/v1' );

// TODO: Getting error when using get_rest_url(). Would like to eventually figure out how to use it.
//define( 'RE_API_BASE_URL', trailingslashit( get_site_url() ) . 'wp-json/' . TF_API_NAMESPACE );
define( 'RE_VIEW_PATH', __DIR__ . '/public/views/' );
//define( 'RE_CONTROLLER_PATH', __DIR__ . '/classes/Controllers/' );
//define( 'RE_ASSET_PATH', __DIR__ . '/public/assets/' );
//define( 'RE_ASSET_URL', plugin_dir_url( __FILE__ ) . 'public/assets/' );

// Load the plugin classes.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
    new Init();
}