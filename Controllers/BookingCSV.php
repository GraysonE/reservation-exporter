<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use Carbon\Carbon;

class BookingCSV extends Export {

	public $export;

	public function __construct( $export ) {
		$this->export     = $export;
		$this->upload_dir = wp_upload_dir();
		$this->set_headers();
		$this->set();
		$this->create_file();
	}

	public function set_headers() {

		$list = array(
			array( 'aaa', 'bbb', 'ccc', 'dddd' ),
			array( '123', '456', '789' ),
			array( '"aaa"', '"bbb"' )
		);

		$fp = fopen( 'file.csv', 'w' );

		foreach ( $list as $fields ) {
			fputcsv( $fp, $fields );
		}

		fclose( $fp );

		$this->csv = 'Purchase ID,';
		$this->csv .= 'Status,';
//		$this->csv .= 'Resort Tax Percentage,'; // Doesn't exist
//		$this->csv .= 'Resort Tax Amount,'; // Doesn't exist
//		$this->csv .= 'Accommodation Tax Percentage,'; // Doesn't exist
		$this->csv .= 'Accommodation Tax Amount,';
		$this->csv .= 'Purchase Total,';
		$this->csv .= 'Discount,';
		$this->csv .= '10% Discount,';
		$this->csv .= 'Total Adults,';
		$this->csv .= 'Total Children,';
		$this->csv .= 'Billing First Name,';
		$this->csv .= 'Billing Last Name,';
		$this->csv .= 'Billing Address,';
		$this->csv .= 'Billing City,';
		$this->csv .= 'Billing State,';
		$this->csv .= 'Billing Zip,';
		$this->csv .= 'Billing Country,';
		$this->csv .= 'Billing Phone,';
		$this->csv .= 'Billing Email,';
		$this->csv .= 'Payment Gateway,';
//		$this->csv .= 'Payment Status,'; // Do we need this?
		$this->csv .= 'Purchase Date,';
		$this->csv .= 'Arrival Time,';
		$this->csv .= 'Check In,';
		$this->csv .= 'Check Out,';
		$this->csv .= 'Quantity,';
		$this->csv .= 'Product Name,';
		$this->csv .= 'SKU';
		$this->csv .= "\n";
	}

	public function set() {

		foreach ( $this->export as $b ) {

			$this->csv .= $b->purchase_id . ',';
			$this->csv .= $b->status . ',';
			$this->csv .= '$' . $b->total_taxes . ','; // Accommodation Tax Amount
			$this->csv .= '$' . $b->total_price . ',';
			$this->csv .= '$' . $b->discount_total . ',';
			$this->csv .= $b->ten_percent_discount . ',';
			$this->csv .= $b->total_adults . ',';
			$this->csv .= $b->total_children . ',';
			$this->csv .= $b->first_name . ',';
			$this->csv .= $b->last_name . ',';
			$this->csv .= $b->address_1 . ',';
			$this->csv .= $b->city . ',';
			$this->csv .= $b->state . ',';
			$this->csv .= $b->zip . ',';
			$this->csv .= $b->country . ',';
			$this->csv .= $b->phone . ',';
			$this->csv .= $b->email . ',';
			$this->csv .= ucfirst( $b->payment_gateway ) . ',';
//			$this->csv .= $b->payment_status . ',';
			$this->csv .= Carbon::parse( $b->purchase_date )->format( 'M d Y' ) . ',';
			$this->csv .= $b->arrival_time . ',';
			$this->csv .= Carbon::parse( $b->check_in )->format( 'M d Y' ) . ',';
			$this->csv .= Carbon::parse( $b->check_out )->format( 'M d Y' ) . ',';
			$this->csv .= count( $b->accommodation->rooms ) . ',';
			$this->csv .= $b->accommodation->rooms[0]->room->type . ','; // Product Name
			$this->csv .= BookingCSV::slugify( $b->accommodation->rooms[0]->room->type ) . ',';
			$this->csv .= "\n";

		}

	}

	public function create_file() {
		$filename = $this->upload_dir['path'] . '/bookings_export.csv';

		$fh = fopen( $filename, "w" );
		file_put_contents( $filename, $this->csv );
	}

	public function get_file_url() {
		return $this->upload_dir['url'] . '/bookings_export.csv';
	}

	public static function slugify( $text ) {
		// replace non letter or digits by -
		$text = preg_replace( '~[^\pL\d]+~u', '-', $text );

		// transliterate
		$text = iconv( 'utf-8', 'us-ascii//TRANSLIT', $text );

		// remove unwanted characters
		$text = preg_replace( '~[^-\w]+~', '', $text );

		// trim
		$text = trim( $text, '-' );

		// remove duplicate -
		$text = preg_replace( '~-+~', '-', $text );

		// lowercase
		$text = strtolower( $text );

		if ( empty( $text ) ) {
			return 'n-a';
		}

		return $text;
	}

}