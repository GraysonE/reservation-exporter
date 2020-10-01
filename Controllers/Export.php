<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use Carbon\Carbon;
use WP_REST_Server;
use WP_Query;
use GraysonErhard\ReservationExporter\Controllers\CSV;

class Export
{

    public $export = [];
    public $csv = '';
    public $csv_url = '';

    public function __construct()
    {

        add_action('rest_api_init', function () {
            register_rest_route(RE_API_NAMESPACE, '/export', [
                [
                    'methods' => WP_REST_Server::READABLE, 'callback' => [
                    $this, 'get'
                ],
                ],
            ]);
        });

    }

    public function get()
    {
        $this->get_legacy_booking_data();
        $booking_csv = new CSV($this->export);
        $csv_url     = $booking_csv->get_file_url();

        wp_send_json_success(['url' => $csv_url, 'export' => $this->export]);
    }

    public function get_legacy_booking_data()
    {
        // wp_wpsc_order_booking_info - Booking and Purchase data
        // wp_parking_places - Camp spots
        // wp_wpsc_meta - sku and cart item
        // wp_wpsc_cart_contents_orig - product name and details
        // wp_wpsc_purchase_logs - contains address data, gateway, notes
        // wp_wpsc_submited_form_data - address and customer info

        global $wpdb;
        $limit         = 200;
        $booking_query = "SELECT * FROM wp_wpsc_order_booking_info LIMIT $limit";
        $bookings      = $wpdb->get_results($booking_query);

        foreach ($bookings as $i => $b) {

            // Booking Details
            $this->export[$i] = new \stdClass();
//            $this->export[$i]->booking_details   = $b;
            $this->export[$i]->purchase_id       = $b->entity_id;
            $this->export[$i]->purchase_date     = Carbon::parse($b->created_at)->format('M d Y');
            $this->export[$i]->confirmed         = ($b->status == '1') ? 'Yes' : 'No';
            $this->export[$i]->resort_tax        = (double) $b->resort_tax;
            $this->export[$i]->accommodation_tax = (double) $b->accomodation_tax;
            $this->export[$i]->discount_total    = (double) $b->discount;
            $this->export[$i]->good_sam_number   = (double) $b->good_sam_number;
            $this->export[$i]->check_in          = Carbon::parse($b->from_date)->format('M d Y');
            $this->export[$i]->check_out         = Carbon::parse($b->to_date)->format('M d Y');

            // Customer Details
            $customer_query   = 'SELECT * FROM wp_wpsc_submited_form_data WHERE log_id = '.$b->order_id;
            $customer_details = $wpdb->get_results($customer_query);

            $this->export[$i]->first_name   = '';
            $this->export[$i]->last_name    = '';
            $this->export[$i]->address      = '';
            $this->export[$i]->city         = '';
            $this->export[$i]->state        = '';
            $this->export[$i]->country      = '';
            $this->export[$i]->zip          = '';
            $this->export[$i]->email        = '';
            $this->export[$i]->phone        = '';
            $this->export[$i]->arrival_time = '';
            $this->export[$i]->comments     = '';

            foreach ($customer_details as $c) {
                if ($c->form_id == 2) {
                    $this->export[$i]->first_name = ucfirst(strtolower(trim($c->value)));
                } elseif ($c->form_id == 3) {
                    $this->export[$i]->last_name = ucfirst(strtolower(trim($c->value)));
                } elseif ($c->form_id == 4) {
                    $this->export[$i]->address = ucwords(strtolower(trim($c->value)));
                } elseif ($c->form_id == 5) {
                    $this->export[$i]->city = ucwords(strtolower(trim($c->value)));
                } elseif ($c->form_id == 6) {
                    $state_number            = $c->value;
                    $state_query             = "SELECT name from wp_wpsc_region_tax WHERE id = $state_number";
                    $state                   = $wpdb->get_var($state_query);
                    $this->export[$i]->state = ucwords(strtolower(trim($state)));
                } elseif ($c->form_id == 7) {
                    $this->export[$i]->country = $c->value;
                } elseif ($c->form_id == 8) {
                    $this->export[$i]->zip = $c->value;
                } elseif ($c->form_id == 9) {
                    $this->export[$i]->email = $c->value;
                } elseif ($c->form_id == 18) {
                    $this->export[$i]->phone = $c->value;
                } elseif ($c->form_id == 20) {
                    $this->export[$i]->arrival_time = $c->value;
                } elseif ($c->form_id == 22) {
                    $this->export[$i]->comments = $c->value;
                }
            }

            $this->export[$i]->customer_details = $customer_details;

            // Filter out bad values before continuing.
            // Had to be this late because Booking details didn't have an obvious flag.
            if ('' == $this->export[$i]->first_name) {
                unset($this->export[$i]);
                continue;
            }

            // Payment Details
            $transaction_query = 'SELECT * FROM wp_wpsc_purchase_logs WHERE id = '.$b->order_id;
            $t                 = $wpdb->get_row($transaction_query);
//            $this->export[$i]->transaction     = $t;
            $this->export[$i]->note            = $t->notes;
            $this->export[$i]->payment_gateway = $t->gateway;
            $this->export[$i]->total_price     = (double) $t->totalprice; // not sure if this is the final price

            // Product Details
            $product_query = 'SELECT * FROM wp_wpsc_cart_contents_orig WHERE id = '.$b->order_item_id;
            $p             = $wpdb->get_row($product_query);
//            $this->export[$i]->product_details = $p;
            $this->export[$i]->product_name = $p->name;
            $this->export[$i]->sku          = Export::slugify($p->name);
            $this->export[$i]->camp_spot    = $p->prodid;

        }

    }

    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

}