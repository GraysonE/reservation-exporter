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
    /**
     * @var mixed
     */
    public $b;
    /**
     * @var string
     */
    public $customer_query;
    /**
     * @var string
     */
    public $transaction_query;
    /**
     * @var string
     */
    public $product_query;
    public $bad_data;
    /**
     * @var int
     */
    public $i;
    public $orig;


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

        // wp_wpsc_order_booking_info & wp_wpsc_order_booking_info_orig - Booking and Purchase data
        // wp_parking_places - Camp spots
        // wp_wpsc_meta - sku and cart item
        // wp_wpsc_cart_contents & wp_wpsc_cart_contents_orig - product name and details
        // wp_wpsc_purchase_logs & wp_wpsc_purchase_logs_orig - contains address data, gateway, notes
        // wp_wpsc_submited_form_data - address and customer info


        $this->limit  = 8000;
        $this->offset = 0;

        $this->orig = ''; // don't get data from database tables appended with '_orig'
        $this->get_data();
        $this->orig = '_orig'; // get data from database tables appended with '_orig'
        $this->get_data();

        $booking_csv = new CSV($this->export);
        $csv_url     = $booking_csv->get_file_url();

        wp_send_json_success(['url' => $csv_url, 'export' => $this->export]);
    }

    public function get_data()
    {
        $this->i = 0;
        $orig    = $this->orig;

        $this->booking_query = "SELECT * FROM wp_wpsc_order_booking_info$orig LIMIT ".$this->limit." OFFSET ".$this->offset;

        global $wpdb;
        $bookings = $wpdb->get_results($this->booking_query);

        foreach ($bookings as $i => $b) {

            $this->i = $i;
            $this->b = $b;

            $this->customer_query    = "SELECT * FROM wp_wpsc_submited_form_data WHERE log_id = ".$this->b->order_id;
            $this->transaction_query = "SELECT * FROM wp_wpsc_purchase_logs$orig WHERE id = ".$this->b->order_id;
            $this->product_query     = "SELECT * FROM wp_wpsc_cart_contents$orig WHERE id = ".$this->b->order_item_id;
            $this->set_booking_data();
            $this->set_customer_data();
            $this->set_payment_data();
            $this->set_product_data();

            if ($this->no_first_name && $this->no_total) {
                unset($this->export[$this->i]);
            }

        }

    }


    public function set_booking_data()
    {
        // Booking Details
        $this->export[$this->i]                  = new \stdClass();
        $this->export[$this->i]->booking_details = $this->b;
        $this->export[$this->i]->purchase_id     = $this->b->entity_id.$this->orig;
        $this->export[$this->i]->purchase_date   = Carbon::parse($this->b->created_at)->format('M d Y');
        $this->export[$this->i]->confirmed       = ($this->b->status == '1') ? 'Yes' : 'No';

        $this->export[$this->i]->resort_tax_amount = '$';
        $this->export[$this->i]->resort_tax_amount .= (double) round($this->b->resort_tax, 2);
        $this->export[$this->i]->resort_tax_perc   = ((0 != $this->b->resort_tax) || (0 != $this->b->price)) ? (double) ((double) $this->b->resort_tax / (double) $this->b->price) * 100 : 'N/A';
        $this->export[$this->i]->resort_tax_perc   = round($this->export[$this->i]->resort_tax_perc, 2);
        $this->export[$this->i]->resort_tax_perc   .= '%';

        $this->export[$this->i]->accommodation_tax_amount = '$';
        $this->export[$this->i]->accommodation_tax_amount .= (double) round($this->b->accomodation_tax, 2);
        $this->export[$this->i]->accommodation_tax_perc   = ((0 != $this->b->accomodation_tax) || (0 != $this->b->price)) ? (double) ((double) $this->b->accomodation_tax / (double) $this->b->price) * 100 : 'N/A';
        $this->export[$this->i]->accommodation_tax_perc   = round($this->export[$this->i]->accommodation_tax_perc, 2);
        $this->export[$this->i]->accommodation_tax_perc   .= '%';

        $this->export[$this->i]->discount_total = '$';
        $this->export[$this->i]->discount_total .= (double) $this->b->discount;

        $this->export[$this->i]->good_sam_number = ($this->b->good_sam_number == 0) ? '' : (double) $this->b->good_sam_number;

        $this->export[$this->i]->check_in  = Carbon::parse($this->b->from_date)->format('M d Y');
        $this->export[$this->i]->check_out = Carbon::parse($this->b->to_date)->format('M d Y');
    }

    public function set_customer_data()
    {
        // Customer Details
        global $wpdb;
        $customer_details = $wpdb->get_results($this->customer_query);

        $this->export[$this->i]->first_name   = '';
        $this->export[$this->i]->last_name    = '';
        $this->export[$this->i]->address      = '';
        $this->export[$this->i]->city         = '';
        $this->export[$this->i]->state        = '';
        $this->export[$this->i]->country      = '';
        $this->export[$this->i]->zip          = '';
        $this->export[$this->i]->email        = '';
        $this->export[$this->i]->phone        = '';
        $this->export[$this->i]->arrival_time = '';
        $this->export[$this->i]->comments     = '';

        foreach ($customer_details as $c) {
            if ($c->form_id == 2) {
                $this->export[$this->i]->first_name = ucfirst(strtolower(trim($c->value)));
            } elseif ($c->form_id == 3) {
                $this->export[$this->i]->last_name = ucfirst(strtolower(trim($c->value)));
            } elseif ($c->form_id == 4) {
                $this->export[$this->i]->address = ucwords(strtolower(trim($c->value)));
            } elseif ($c->form_id == 5) {
                $this->export[$this->i]->city = ucwords(strtolower(trim($c->value)));
            } elseif ($c->form_id == 6) {
                $this->export[$this->i]->state = $this->get_state_name($c->value);
            } elseif ($c->form_id == 7) {
                $this->export[$this->i]->country = $c->value;
            } elseif ($c->form_id == 8) {
                if (strlen($c->value) >= 5) {
                    $this->export[$this->i]->zip = $c->value;
                } else {
                    $this->export[$this->i]->zip = 'Not Provided';
                }

                if ('' == $this->export[$this->i]->state) {
                    $this->export[$this->i]->state = $this->zipToState($c->value);
                }

            } elseif ($c->form_id == 9) {
                if (is_numeric($c->value)) {
                    $this->export[$this->i]->state = $this->get_state_name($c->value);
                }

                if (strpos($c->value, '@') !== false) {

                    $this->export[$this->i]->email = $c->value;
                }
            } elseif ($c->form_id == 18) {
                $this->export[$this->i]->phone = $c->value;
            } elseif ($c->form_id == 20) {
                $this->export[$this->i]->arrival_time = $c->value;
            } elseif ($c->form_id == 22) {
                $this->export[$this->i]->comments = $c->value;
            }
        }

//            $this->export[$this->i]->customer_details = $customer_details;

        // Filter out bad values before continuing.
        // Had to be this late because Booking details didn't have an obvious flag.
        $this->no_first_name = (('' == $this->export[$this->i]->first_name) || (null == $this->export[$this->i]->first_name));
    }

    public function set_payment_data()
    {

        // Payment Details
        global $wpdb;
        $t                                       = $wpdb->get_row($this->transaction_query);
        $this->export[$this->i]->transaction     = $t;
        $this->export[$this->i]->note            = $t->notes;
        $this->export[$this->i]->payment_gateway = $t->gateway;
        $this->export[$this->i]->total_price     = '$';
        $this->export[$this->i]->total_price     .= (double) $t->totalprice; // not sure if this is the final price

        $this->no_total = (((double) $t->totalprice == 0) || (null == $t->totalprice));
    }

    public function set_product_data()
    {

        // Product Details
        global $wpdb;
        $p                                       = $wpdb->get_row($this->product_query);
        $this->export[$this->i]->product_details = $p;
        $this->export[$this->i]->product_name    = $p->name;
        $this->export[$this->i]->sku             = Export::slugify($p->name);
        $this->export[$this->i]->camp_spot       = $p->prodid;
    }

    public function get_state_name($value)
    {
        global $wpdb;
        $state_number = $value;
        $state_query  = "SELECT name from wp_wpsc_region_tax WHERE id = $state_number";
        $state        = $wpdb->get_var($state_query);

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