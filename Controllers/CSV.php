<?php


namespace GraysonErhard\ReservationExporter\Controllers;

use Carbon\Carbon;

class CSV
{

    public $export;
    public $csv = [];

    public function __construct($export)
    {
        $this->export     = $export;
        $this->upload_dir = wp_upload_dir();
        $this->set();
        $this->create_file();
    }

    public function set_headers()
    {

        $this->csv[0] = [
            'Purchase ID', 'Confirmed', 'Resort Tax Amount', 'Resort Tax Percent', 'Accommodation Tax Amount', 'Accommodation Tax Percent', 'Purchase Total', 'Discount',
            'Good Sam Number', 'Billing First Name', 'Billing Last Name', 'Billing Address', 'Billing City',
            'Billing State', 'Billing Zip', 'Billing Country', 'Billing Phone', 'Billing Email', 'Payment Gateway',
            'Purchase Date', 'Arrival Time', 'Check In', 'Check Out', 'Comments', 'Notes', 'Product Name', 'SKU'
        ];

    }

    public function set()
    {

        $this->set_headers();

        $i=0;

        foreach ($this->export as $b) {

            $i++;

            $this->csv[$i] = [
                $b->purchase_id,
                $b->confirmed,
                $b->resort_tax_amount,
                $b->resort_tax_perc,
                $b->accommodation_tax_amount,
                $b->accommodation_tax_perc,
                $b->total_price,
                $b->discount_total,
                $b->good_sam_number,
                $b->first_name,
                $b->last_name,
                $b->address,
                $b->city,
                $b->state,
                $b->zip,
                $b->country,
                $b->phone,
                $b->email,
                $b->payment_gateway,
                $b->purchase_date,
                $b->arrival_time,
                $b->check_in,
                $b->check_out,
                $b->comments,
                $b->notes,
                $b->product_name,
                $b->sku,
            ];



        }

    }

    public function create_file()
    {
        $filename = $this->upload_dir['path'].'/Buffalo_Crossing_Bookings_Export.csv';

        $fh = fopen($filename, "w");

        foreach ($this->csv as $line) {
            fputcsv($fh, $line, ',');
        }

        fclose($fh);


        file_put_contents($filename, array_unique(file($filename)));
    }

    public function get_file_url()
    {
        return $this->upload_dir['url'].'/Buffalo_Crossing_Bookings_Export.csv';
    }


}