<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use WP_Query;

class BookingExport extends Export {

	public $plugin_key = 'mphb_';

	public $plugin_key_alt = '_mphb_';

	protected $export = [];

	public function __construct() {

	}

	/**
	 * @param $booking
	 */
	public function get_booking_data() {

		$booking_query = new WP_Query( array( 'post_type' => 'mphb_booking' ) );

		foreach ( $booking_query->posts as $i => $booking ) {

			$this->export[ $i ]                = new \stdClass();
			$this->export[ $i ]->purchase_id   = $booking->ID;
			$this->export[ $i ]->purchase_date = $booking->post_date;
			$this->export[ $i ]->status        = ucfirst( $booking->post_status );

			$this->export[ $i ]->arrival_time = get_post_meta( $booking->ID, $this->plugin_key . 'arrival-time', true );
			$this->export[ $i ]->check_in     = get_post_meta( $booking->ID, $this->plugin_key . 'check_in_date',
				true );
			$this->export[ $i ]->check_out    = get_post_meta( $booking->ID, $this->plugin_key . 'check_out_date',
				true );
			$this->export[ $i ]->note         = get_post_meta( $booking->ID, $this->plugin_key . 'note', true );

			$this->export[ $i ]->ten_percent_discount = get_post_meta( $booking->ID,
				$this->plugin_key . 'ten-percent-discount', true );

			$this->export[ $i ]->first_name = get_post_meta( $booking->ID, $this->plugin_key . 'first_name', true );
			$this->export[ $i ]->last_name  = get_post_meta( $booking->ID, $this->plugin_key . 'last_name', true );
			$this->export[ $i ]->email      = get_post_meta( $booking->ID, $this->plugin_key . 'email', true );
			$this->export[ $i ]->phone      = get_post_meta( $booking->ID, $this->plugin_key . 'phone', true );

			$this->export[ $i ]->address_1 = get_post_meta( $booking->ID, $this->plugin_key . 'address_1', true );
			$this->export[ $i ]->state     = get_post_meta( $booking->ID, $this->plugin_key . 'state', true );
			$this->export[ $i ]->city      = get_post_meta( $booking->ID, $this->plugin_key . 'city', true );
			$this->export[ $i ]->zip       = get_post_meta( $booking->ID, $this->plugin_key . 'zip', true );
			$this->export[ $i ]->country   = get_post_meta( $booking->ID, $this->plugin_key . 'country', true );
			$this->export[ $i ]->language  = get_post_meta( $booking->ID, $this->plugin_key . 'language', true );

			$this->export[ $i ]->total_price     = get_post_meta( $booking->ID, $this->plugin_key . 'total_price',
				true );
			$this->export[ $i ]->wait_payment_id = get_post_meta( $booking->ID, $this->plugin_key_alt . 'wait_payment',
				true );
			$this->export[ $i ]->coupon_id       = get_post_meta( $booking->ID, $this->plugin_key . 'coupon_id', true );

			$payment_details                     = get_post_meta( $this->export[ $i ]->wait_payment_id );
			$this->export[ $i ]->payment_gateway = get_post_meta( $this->export[ $i ]->wait_payment_id,
				$this->plugin_key_alt . 'gateway', true );
			$this->export[ $i ]->sku             = get_post_meta( $booking->ID, $this->plugin_key . 'sku', true );


			$this->export[ $i ]->accommodation = json_decode( get_post_meta( $booking->ID,
				$this->plugin_key_alt . 'booking_price_breakdown', true ) );

			$this->export[ $i ]->total_taxes = 0;
			foreach ( $this->export[ $i ]->accommodation->rooms as $room ) {
				$this->export[ $i ]->total_taxes += (int) $room->taxes->room->total;
			}

			$this->export[ $i ]->discount_total = 0;
			$this->export[ $i ]->total_children = 0;
			$this->export[ $i ]->total_adults   = 0;

			foreach ( $this->export[ $i ]->accommodation->rooms as $room ) {

				$this->export[ $i ]->discount_total += (int) $room->discount_total;
				$this->export[ $i ]->total_children += (int) $room->room->children;
				$this->export[ $i ]->total_adults   += (int) $room->room->adults;

			}

		}


		return $this->export;
	}

}