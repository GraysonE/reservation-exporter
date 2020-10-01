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
        // wp_wpsc_order_booking_info & wp_wpsc_order_booking_info_orig - Booking and Purchase data
        // wp_parking_places - Camp spots
        // wp_wpsc_meta - sku and cart item
        // wp_wpsc_cart_contents & wp_wpsc_cart_contents_orig - product name and details
        // wp_wpsc_purchase_logs & wp_wpsc_purchase_logs_orig - contains address data, gateway, notes
        // wp_wpsc_submited_form_data - address and customer info

        global $wpdb;
        $limit         = 300;
        $booking_query = "SELECT * FROM wp_wpsc_order_booking_info, wp_wpsc_order_booking_info_orig LIMIT $limit";
//        $booking_query = "SELECT * FROM wp_wpsc_order_booking_info, wp_wpsc_order_booking_info_orig";
        $bookings = $wpdb->get_results($booking_query);

        foreach ($bookings as $i => $b) {

            // Booking Details
            $this->export[$i] = new \stdClass();
//            $this->export[$i]->booking_details          = $b;
            $this->export[$i]->purchase_id   = $b->entity_id;
            $this->export[$i]->purchase_date = Carbon::parse($b->created_at)->format('M d Y');
            $this->export[$i]->confirmed     = ($b->status == '1') ? 'Yes' : 'No';

            $this->export[$i]->resort_tax_amount = '$';
            $this->export[$i]->resort_tax_amount .= (double) round($b->resort_tax, 2);
            $this->export[$i]->resort_tax_perc   = ((0 != $b->resort_tax) || (0 != $b->price)) ? (double) ((double) $b->resort_tax / (double) $b->price) * 100 : 'N/A';
            $this->export[$i]->resort_tax_perc   = round($this->export[$i]->resort_tax_perc, 2);
            $this->export[$i]->resort_tax_perc   .= '%';

            $this->export[$i]->accommodation_tax_amount = '$';
            $this->export[$i]->accommodation_tax_amount .= (double) round($b->accomodation_tax, 2);
            $this->export[$i]->accommodation_tax_perc   = ((0 != $b->accomodation_tax) || (0 != $b->price)) ? (double) ((double) $b->accomodation_tax / (double) $b->price) * 100 : 'N/A';
            $this->export[$i]->accommodation_tax_perc   = round($this->export[$i]->accommodation_tax_perc, 2);
            $this->export[$i]->accommodation_tax_perc   .= '%';

            $this->export[$i]->discount_total = '$';
            $this->export[$i]->discount_total .= (double) $b->discount;

            $this->export[$i]->good_sam_number = ($b->good_sam_number == 0) ? '' : (double) $b->good_sam_number;

            $this->export[$i]->check_in  = Carbon::parse($b->from_date)->format('M d Y');
            $this->export[$i]->check_out = Carbon::parse($b->to_date)->format('M d Y');

            // Customer Details
            $customer_query   = 'SELECT * FROM wp_wpsc_submited_form_data WHERE log_id = {$b->order_id}';
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
                    $this->export[$i]->state = $this->get_state_name($c->value);
                } elseif ($c->form_id == 7) {
                    $this->export[$i]->country = $c->value;
                } elseif ($c->form_id == 8) {
                    if (strlen($c->value) >= 5) {
                        $this->export[$i]->zip = $c->value;
                    } else {
                        $this->export[$i]->zip = 'Not Provided';
                    }

                    if ('' == $this->export[$i]->state) {
                        $this->export[$i]->state = $this->zipToState($c->value);
                    }

                } elseif ($c->form_id == 9) {
                    if (is_numeric($c->value)) {
                        $this->export[$i]->state = $this->get_state_name($c->value);
                    }

                    if (strpos($c->value, '@') !== false) {

                        $this->export[$i]->email = $c->value;
                    }
                } elseif ($c->form_id == 18) {
                    $this->export[$i]->phone = $c->value;
                } elseif ($c->form_id == 20) {
                    $this->export[$i]->arrival_time = $c->value;
                } elseif ($c->form_id == 22) {
                    $this->export[$i]->comments = $c->value;
                }
            }

//            $this->export[$i]->customer_details = $customer_details;

            // Filter out bad values before continuing.
            // Had to be this late because Booking details didn't have an obvious flag.
            if ('' == $this->export[$i]->first_name) {
                unset($this->export[$i]);
                continue;
            }

            // Payment Details
            $transaction_query                 = 'SELECT * FROM wp_wpsc_purchase_logs, wp_wpsc_purchase_logs_orig WHERE id = {$b->order_id}';
            $t                                 = $wpdb->get_row($transaction_query);
            $this->export[$i]->transaction     = $t;
            $this->export[$i]->note            = $t->notes;
            $this->export[$i]->payment_gateway = $t->gateway;
            $this->export[$i]->total_price     = '$';
            $this->export[$i]->total_price     .= (double) $t->totalprice; // not sure if this is the final price

            // Product Details
            $product_query                     = 'SELECT * FROM wp_wpsc_cart_contents, wp_wpsc_cart_contents_orig WHERE id = {$b->order_item_id}';
            $p                                 = $wpdb->get_row($product_query);
            $this->export[$i]->product_details = $p;
            $this->export[$i]->product_name    = $p->name;
            $this->export[$i]->sku             = Export::slugify($p->name);
            $this->export[$i]->camp_spot       = $p->prodid;

        }

    }

    public function get_state_name($value)
    {
        global $wpdb;
        $state_number = $value;
        $state_query  = "SELECT name from wp_wpsc_region_tax WHERE id = $state_number";
        $state        = $wpdb->get_var($state_query);

//        if (($state_number != 12345) && ($state_number != 12)) {
//            var_dump($state_number, $state_query, $state);
//            die();
//        }

        return ucwords(strtolower(trim($state)));
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

    public function zipToState($zipcode)
    {
        /* 000 to 999 */
        $zip_by_state = [
            '--', '--', '--', '--', '--', 'NY', 'PR', 'PR', 'VI', 'PR', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA',
            'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'MA', 'RI', 'RI', 'NH', 'NH', 'NH', 'NH', 'NH', 'NH',
            'NH', 'NH', 'NH', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'ME', 'VT', 'VT', 'VT', 'VT',
            'VT', 'MA', 'VT', 'VT', 'VT', 'VT', 'CT', 'CT', 'CT', 'CT', 'CT', 'CT', 'CT', 'CT', 'CT', 'CT', 'NJ', 'NJ',
            'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ', 'NJ',
            'AE', 'AE', 'AE', 'AE', 'AE', 'AE', 'AE', 'AE', 'AE', '--', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY',
            'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY',
            'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'NY',
            'NY', 'NY', 'NY', 'NY', 'NY', 'NY', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA',
            'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA',
            'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', 'PA', '--', 'PA', 'PA', 'PA', 'PA', 'DE',
            'DE', 'DE', 'DC', 'VA', 'DC', 'DC', 'DC', 'DC', 'MD', 'MD', 'MD', 'MD', 'MD', 'MD', 'MD', '--', 'MD', 'MD',
            'MD', 'MD', 'MD', 'MD', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA',
            'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'VA', 'WV', 'WV', 'WV', 'WV', 'WV',
            'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', 'WV', '--',
            'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC', 'NC',
            'NC', 'NC', 'SC', 'SC', 'SC', 'SC', 'SC', 'SC', 'SC', 'SC', 'SC', 'SC', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA',
            'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'GA', 'FL', 'FL', 'FL', 'FL',
            'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'FL', 'AA', 'FL',
            'FL', '--', 'FL', '--', 'FL', 'FL', '--', 'FL', 'AL', 'AL', 'AL', '--', 'AL', 'AL', 'AL', 'AL', 'AL', 'AL',
            'AL', 'AL', 'AL', 'AL', 'AL', 'AL', 'AL', 'AL', 'AL', 'AL', 'TN', 'TN', 'TN', 'TN', 'TN', 'TN', 'TN', 'TN',
            'TN', 'TN', 'TN', 'TN', 'TN', 'TN', 'TN', 'TN', 'MS', 'MS', 'MS', 'MS', 'MS', 'MS', 'MS', 'MS', 'MS', 'MS',
            'MS', 'MS', 'GA', '--', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY',
            'KY', 'KY', 'KY', 'KY', 'KY', '--', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', 'KY', '--', '--', 'OH', 'OH',
            'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH',
            'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', 'OH', '--', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN',
            'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'IN', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI',
            'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'MI', 'IA', 'IA', 'IA', 'IA',
            'IA', 'IA', 'IA', 'IA', 'IA', '--', 'IA', 'IA', 'IA', 'IA', 'IA', 'IA', 'IA', '--', '--', '--', 'IA', 'IA',
            'IA', 'IA', 'IA', 'IA', 'IA', 'IA', 'IA', '--', 'WI', 'WI', 'WI', '--', 'WI', 'WI', '--', 'WI', 'WI', 'WI',
            'WI', 'WI', 'WI', 'WI', 'WI', 'WI', 'WI', 'WI', 'WI', 'WI', 'MN', 'MN', '--', 'MN', 'MN', 'MN', 'MN', 'MN',
            'MN', 'MN', 'MN', 'MN', 'MN', 'MN', 'MN', 'MN', 'MN', 'MN', '--', 'DC', 'SD', 'SD', 'SD', 'SD', 'SD', 'SD',
            'SD', 'SD', '--', '--', 'ND', 'ND', 'ND', 'ND', 'ND', 'ND', 'ND', 'ND', 'ND', '--', 'MT', 'MT', 'MT', 'MT',
            'MT', 'MT', 'MT', 'MT', 'MT', 'MT', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL',
            'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', '--', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL', 'IL',
            'MO', 'MO', '--', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', '--', '--', 'MO', 'MO', 'MO', 'MO',
            'MO', '--', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', 'MO', '--', 'KS', 'KS', 'KS', '--', 'KS', 'KS',
            'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'KS', 'NE', 'NE', '--', 'NE',
            'NE', 'NE', 'NE', 'NE', 'NE', 'NE', 'NE', 'NE', 'NE', 'NE', '--', '--', '--', '--', '--', '--', 'LA', 'LA',
            '--', 'LA', 'LA', 'LA', 'LA', 'LA', 'LA', '--', 'LA', 'LA', 'LA', 'LA', 'LA', '--', 'AR', 'AR', 'AR', 'AR',
            'AR', 'AR', 'AR', 'AR', 'AR', 'AR', 'AR', 'AR', 'AR', 'AR', 'OK', 'OK', '--', 'TX', 'OK', 'OK', 'OK', 'OK',
            'OK', 'OK', 'OK', 'OK', '--', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'OK', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX',
            'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX',
            'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX',
            'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'TX', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO',
            'CO', 'CO', 'CO', 'CO', 'CO', 'CO', 'CO', '--', '--', '--', 'WY', 'WY', 'WY', 'WY', 'WY', 'WY', 'WY', 'WY',
            'WY', 'WY', 'WY', 'WY', 'ID', 'ID', 'ID', 'ID', 'ID', 'ID', 'ID', '--', 'UT', 'UT', '--', 'UT', 'UT', 'UT',
            'UT', 'UT', '--', '--', 'AZ', 'AZ', 'AZ', 'AZ', '--', 'AZ', 'AZ', 'AZ', '--', 'AZ', 'AZ', '--', '--', 'AZ',
            'AZ', 'AZ', '--', '--', '--', '--', 'NM', 'NM', '--', 'NM', 'NM', 'NM', '--', 'NM', 'NM', 'NM', 'NM', 'NM',
            'NM', 'NM', 'NM', 'NM', '--', '--', '--', '--', 'NV', 'NV', '--', 'NV', 'NV', 'NV', '--', 'NV', 'NV', '--',
            'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', '--', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA',
            'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', '--', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA',
            'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA',
            'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'CA', 'AP', 'AP', 'AP', 'AP', 'AP', 'HI', 'HI', 'GU', 'OR', 'OR',
            'OR', 'OR', 'OR', 'OR', 'OR', 'OR', 'OR', 'OR', 'WA', 'WA', 'WA', 'WA', 'WA', 'WA', 'WA', '--', 'WA', 'WA',
            'WA', 'WA', 'WA', 'WA', 'WA', 'AK', 'AK', 'AK', 'AK', 'AK'
        ];

        $prefix = substr($zipcode, 0, 3);
        $index  = intval($prefix); /* converts prefix to integer */

        return $this->convertState($zip_by_state[$index]);
    }

    /* -----------------------------------
     * CONVERT STATE NAMES!
     * Goes both ways. e.g.
     * $name = 'Orgegon' -> returns "OR"
     * $name = 'OR' -> returns "Oregon"
     * ----------------------------------- */
    public function convertState($name)
    {
        $states = array(
            array('name' => 'Alabama', 'abbr' => 'AL'), array('name' => 'Alaska', 'abbr' => 'AK'),
            array('name' => 'Arizona', 'abbr' => 'AZ'), array('name' => 'Arkansas', 'abbr' => 'AR'),
            array('name' => 'California', 'abbr' => 'CA'), array('name' => 'Colorado', 'abbr' => 'CO'),
            array('name' => 'Connecticut', 'abbr' => 'CT'), array('name' => 'Delaware', 'abbr' => 'DE'),
            array('name' => 'Florida', 'abbr' => 'FL'), array('name' => 'Georgia', 'abbr' => 'GA'),
            array('name' => 'Hawaii', 'abbr' => 'HI'), array('name' => 'Idaho', 'abbr' => 'ID'),
            array('name' => 'Illinois', 'abbr' => 'IL'), array('name' => 'Indiana', 'abbr' => 'IN'),
            array('name' => 'Iowa', 'abbr' => 'IA'), array('name' => 'Kansas', 'abbr' => 'KS'),
            array('name' => 'Kentucky', 'abbr' => 'KY'), array('name' => 'Louisiana', 'abbr' => 'LA'),
            array('name' => 'Maine', 'abbr' => 'ME'), array('name' => 'Maryland', 'abbr' => 'MD'),
            array('name' => 'Massachusetts', 'abbr' => 'MA'), array('name' => 'Michigan', 'abbr' => 'MI'),
            array('name' => 'Minnesota', 'abbr' => 'MN'), array('name' => 'Mississippi', 'abbr' => 'MS'),
            array('name' => 'Missouri', 'abbr' => 'MO'), array('name' => 'Montana', 'abbr' => 'MT'),
            array('name' => 'Nebraska', 'abbr' => 'NE'), array('name' => 'Nevada', 'abbr' => 'NV'),
            array('name' => 'New Hampshire', 'abbr' => 'NH'), array('name' => 'New Jersey', 'abbr' => 'NJ'),
            array('name' => 'New Mexico', 'abbr' => 'NM'), array('name' => 'New York', 'abbr' => 'NY'),
            array('name' => 'North Carolina', 'abbr' => 'NC'), array('name' => 'North Dakota', 'abbr' => 'ND'),
            array('name' => 'Ohio', 'abbr' => 'OH'), array('name' => 'Oklahoma', 'abbr' => 'OK'),
            array('name' => 'Oregon', 'abbr' => 'OR'), array('name' => 'Pennsylvania', 'abbr' => 'PA'),
            array('name' => 'Rhode Island', 'abbr' => 'RI'), array('name' => 'South Carolina', 'abbr' => 'SC'),
            array('name' => 'South Dakota', 'abbr' => 'SD'), array('name' => 'Tennessee', 'abbr' => 'TN'),
            array('name' => 'Texas', 'abbr' => 'TX'), array('name' => 'Utah', 'abbr' => 'UT'),
            array('name' => 'Vermont', 'abbr' => 'VT'), array('name' => 'Virginia', 'abbr' => 'VA'),
            array('name' => 'Washington', 'abbr' => 'WA'), array('name' => 'West Virginia', 'abbr' => 'WV'),
            array('name' => 'Wisconsin', 'abbr' => 'WI'), array('name' => 'Wyoming', 'abbr' => 'WY'),
            array('name' => 'Virgin Islands', 'abbr' => 'V.I.'), array('name' => 'Guam', 'abbr' => 'GU'),
            array('name' => 'Puerto Rico', 'abbr' => 'PR')
        );

        $return = false;
        $strlen = strlen($name);

        foreach ($states as $state) :
            if ($strlen < 2) {
                return false;
            } elseif ($strlen == 2) {
                if (strtolower($state['abbr']) == strtolower($name)) {
                    $return = $state['name'];
                    break;
                }
            } else {
                if (strtolower($state['name']) == strtolower($name)) {
                    $return = strtoupper($state['abbr']);
                    break;
                }
            }
        endforeach;

        return $return;
    } // end function convertState()

}