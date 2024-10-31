<?php
    namespace Ninja\Van\MY\Worker;
    use Dompdf\Dompdf;
    use Dompdf\Options;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class GenerateAWB
    {
        public function get_awb(array $order_ids = []){
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);

            if (nv_my_get_settings('awb_printer_type') == 2) {
                $dompdf->loadHtml($this->prepare_A6($order_ids), 'UTF-8');
                $dompdf->setPaper('A6', 'portrait');
            }else{
                $dompdf->loadHtml($this->prepare_A4($order_ids), 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
            }

            $dompdf->render();

            $file_name = 'AWB-'.gmdate('Ymd').'-'.wp_rand(1000,9999).'-'.wp_rand(1000,9999).'.pdf';
            $dompdf->stream($file_name, array("Attachment" => true));

            exit(0);
        }

        private function prepare_A4($order_ids){
            $template = '<!DOCTYPE html>
            <html lang="en">
                <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title>AWB</title>
                    <style>
                        *{
                            margin: 0;
                            padding: 0;
                            font-family: Arial, Helvetica, sans-serif;
                        }
                        @page {
                            margin: 0cm 0cm;
                        }
                        body {
                            margin-top: 0.75cm;
                            margin-left: 0.75cm;
                            margin-right: 0.75cm;
                            margin-bottom: 0.75cm;
                            font-size: 11px !important;
                            color: #111111;
                            background-color: #ffffff;
                        }
                        .page-break {
                            page-break-after: always;
                        }
                        table{
                            width: 100%;
                        }
                        table th, table td{
                            padding: 5px;
                            vertical-align: middle;
                        }
                        table .table-equal th,
                        table .table-equal td{
                            width: 33.33%;
                        }
                        table.table-border-2{
                            border: 2px solid #111111;
                            
                        }
                        table.block-wrap{
                            border: 1px solid #111111;
                        }
                        table.block-wrap th{
                            border-bottom: 1px solid #111111;
                        }
                        table.block-wrap td{
                            display: inline-block;
                            min-height: 150px;
                        }
                        table.non-block td{
                            display: table-cell;
                            
                        }
                        table.collapse{
                            border: 1px solid #111111;
                            border-collapse: collapse;
                        }
                        .text-left{
                            text-align: left;
                        }
                        .text-center{
                            text-align: center;
                        }
                        .text-right{
                            text-align: right;
                        }
                        .text-size-12{
                            font-size: 12px;
                        }
                        .text-color-gray{
                            color: #c3c3c3;
                        }
                        .align-top{
                            vertical-align: top;
                        }
                        .align-middle{
                            vertical-align: middle;
                        }
                        .align-bottom{
                            vertical-align: bottom;
                        }
                        .img-fluid{
                            max-width: 100%;
                        }
                        .img-fluid-w100{
                            max-width: 100px;
                        }
                        .img-fluid-w75{
                            max-width: 75px;
                        }
                        .img-fluid-w50{
                            max-width: 50px;
                        }
                        .m-0{
                            margin: 0;
                        }
                        .mt-0{
                            margin-top: 0;
                        }
                        .5{
                            margin-bottom: 3em;
                        }
                        .mb-5{
                            margin-bottom: 5em;
                        }
                        .mb-10{
                            margin-bottom: 10em;
                        }
                        .{
                            margin-bottom: 0;
                        }
                        .p-0{
                            padding: 0;
                        }
                        .p-5{
                            padding: 5px !important;
                        }
                        .pt-5{
                            padding-top: 0;
                        }
                        .pr-5{
                            padding-right: 0;
                        }
                        .pb-5{
                            padding-bottom: 0;
                        }
                        .pl-5{
                            padding-left: 0;
                        }
                        .thick-border{
                            border: 1.5px solid #222222;
                            width: 100%;
                            padding: 5px;
                            overflow: hidden;
                            text-align: left;
                        }
                        .thin-border{
                            border: 1px solid #111111;
                            width: 100%;
                            padding: 5px;
                        }
                        .letter-space{
                            letter-spacing: 2px;;
                        }
                    </style>
                </head>
                <body>
                    <div class="awb">';
                    $count = 1;
                    foreach($order_ids as $order_id):
                        $order = $this->get_order_A4($order_id);
                        if (!is_array($order)) {
                            $template .= $order;
                            $template .= nv_my_page_break($count);
                            $count++;
                        }
                    endforeach;
                    
                    '</div>
                </body>
            </html>';

            return $template;
        }

        private function get_order_A4($order_id){
            $order = wc_get_order( $order_id );
            if (!$order) {
                $response = [
                    'status' => false,
                    'message' => 'Order Not Found!'
                ];
                return $response;
            }

            if (!$order->get_meta('ninja_van_tracking_number')) {
                $response = [
                    'status' => false,
                    'message' => 'Tracking Number not available!'
                ];
                return $response;
            }

            if (!$order->get_meta('_ninja_van_payload')) {
                $response = [
                    'status' => false,
                    'message' => 'Tracking Number not available!'
                ];
                return $response;
            }

            $ninjavan = unserialize($order->get_meta('_ninja_van_payload'));

            $sender_address = (object) nv_my_sender_address();

            $billing_address = (object) $order->get_address();

            if ($address = (object) $order->get_address('shipping')) {
                if (empty($address->first_name) || empty($address->postcode)) {
                    $address = $billing_address;
                }
            }

            $cash_on_delivery = (isset($ninjavan->parcel_job->cash_on_delivery)) ? $ninjavan->parcel_job->cash_on_delivery : 0;

            $from_phone_number = $ninjavan->from->phone_number;
            
            $sender = $ninjavan->from->address;

            $from_address = '';

            if (isset($sender->address1)) {
                $from_address .= $sender->address1.', ';
            }
            if (isset($sender->address2)) {
                $from_address .= $sender->address2.', ';
            }
            if (isset($sender->country)) {
                $from_address .= $sender->country.', ';
            }
            if (isset($sender_address)) {
                $from_address .= $sender_address->address['postcode'];
            }

            if (!nv_my_get_settings('awb_seller_information')) {
                $from_phone_number = nv_my_hide_text($from_phone_number);
                $from_address = nv_my_hide_text($from_address);
            }

            $awb_seller_logo_td = '';
            if ($awb_seller_logo = nv_my_seller_logo(false)) {
                // max dimension is 100 x 100
                $awb_seller_logo_td = '
                    <td width="20%">
                        <img src="'.nv_my_get_image($awb_seller_logo, true).'" alt="Seller Logo" class="img-fluid-w100">
                    </td>
                ';
            }

            $response = '<table class="table-border-2 p-5 mb-5">
                    <tr>
                        <td colspan="2">
                            <table style="width: 100%;">
                                <tr class="text-center">
                                    <td width="20%">
                                        <img src="data:image/png;base64,'.esc_html(nv_my_qrcode($ninjavan->tracking_number)).'" alt="'.esc_html($ninjavan->tracking_number).'" class="img-fluid-w75">
                                    </td>
                                    '.$awb_seller_logo_td.'
                                    <td width="35%">
                                        <div style="text-align: center !important;">
                                            <img src="'.nv_my_get_image(nv_my_get_file("/assets/logo.png"),true).'" style="width: 100%; max-width: 120px" alt="">
                                        </div>
                                        <h1 class="m-0 mb-0 letter-space" style="margin-top: 5px">AIRWAY BILL</h1>
                                        <p class="m-0 mb-0 text-size-12">' . esc_url('www.ninjavan.co') . '</p>
                                    </td>
                                    <td width="45%">
                                        <p>'.$ninjavan->tracking_number.'</p>
                                        <img src="data:image/png;base64,'.esc_html(nv_my_barcode($ninjavan->tracking_number)).'" alt="'.esc_html($ninjavan->tracking_number).'" style="margin: 5px 0 5px 0; auto; display: block; width: 90%; height: 40px; overflow: hidden;">
                                        <p>Size/Weight: '.esc_html(nv_my_parcel_size($ninjavan->parcel_job->dimensions->weight)).'</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; vertical-align: top">
                            <table class="text-left" style="width: 100%; border: 1px solid #111111;">
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111">FROM (SENDER)</th>
                                </tr>
                                <tr>
                                    <td>
                                        <table class="non-block">
                                            <tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/contact.png"),true).'" alt="Sender" width="16px">
                                                </td>
                                                <td><strong>'.esc_html($ninjavan->from->name).'</strong></td>
                                            </tr>';
                                            if (nv_my_get_settings('awb_phone_number')) {
                                                $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/phone.png"),true).'" alt="Phone" width="12px">
                                                </td>
                                                <td>'.esc_html($from_phone_number).'</td>
                                            </tr>';
                                            }

                                            $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/address.png"),true).'" alt="Location" width="16px">
                                                </td>
                                                <td>'.esc_html($from_address).'</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%; vertical-align: top">
                            <table class="text-left" style="width: 100%; border: 1px solid #111111;">
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111">TO (ADDRESSEE)</th>
                                </tr>
                                <tr>
                                    <td>
                                        <table class="non-block">
                                            <tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/contact.png"),true).'" alt="Receiver" width="16px">
                                                </td>
                                                <td><strong>'.esc_html($ninjavan->to->name).'</strong></td>
                                            </tr>';
                                            if (nv_my_get_settings('awb_phone_number')) {
                                                $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/phone.png"),true).'" alt="Phone" width="12px">
                                                </td>
                                                <td>'.esc_html($ninjavan->to->phone_number).'</td>
                                            </tr>';
                                            }
                                            $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/address.png"),true).'" alt="Location" width="16px">
                                                </td>
                                                
                                                <td>';
                                                    $to = $ninjavan->to->address;

                                                    if (isset($to->address1)) {
                                                        $response .= esc_html($to->address1).', ';
                                                    }
                                                    if (isset($to->address2)) {
                                                        $response .= esc_html($to->address2).', ';
                                                    }
                                                    if (isset($to->country)) {
                                                        $response .= esc_html($to->country).', ';
                                                    }
                                                    if (isset($address->postcode)) {
                                                        $response .= esc_html($address->postcode);
                                                    }
                                                $response .= '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <table style="text-align: left; border: 1px solid #111111">
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111">COD: '.esc_html(nv_my_amount_with_currency($cash_on_delivery, $to->country)).'</th>
                                </tr>';

                                if(nv_my_get_settings('awb_order_note') && !empty($order->get_customer_note())){
                                    $response .= '<tr>
                                        <td style="text-align: left">
                                            <strong>Note:</strong> '.esc_html(nv_my_short_text($order->get_customer_note())).'
                                        </td>
                                    </tr>';
                                }

                                if(nv_my_get_settings('awb_order_item') && !empty($ninjavan->parcel_job->delivery_instructions)){
                                    $response .= '<tr>
                                        <td style="text-align: left">
                                            <strong>Items:</strong> '.esc_html(nv_my_short_text($ninjavan->parcel_job->delivery_instructions)).'
                                        </td>
                                    </tr>';
                                }

                                if(nv_my_get_settings('awb_order_item_sku') && !empty($skus = nv_my_get_order_items($order, true))){
                                    $response .= '<tr>
                                        <td style="text-align: left">
                                            <strong>SKUs:</strong> '.esc_html(nv_my_short_text($skus)).'
                                        </td>
                                    </tr>';
                                }

                                $response .= '
                            </table>
                        </td>
                    </tr>
                    {{footer}}
                </table>';

                if (nv_my_get_settings('awb_channel_url')) {
                    $response = str_replace('{{footer}}', '<tr><td colspan="2"><p class="text-center text-size-12 text-color-gray">'.esc_url(get_site_url()).'</p></td></tr>', $response);
                } else {
                    $response = str_replace('{{footer}}', '', $response);
                }

                $this->add_stats($order);
                $order->save();

                return $response;
        }

        private function prepare_A6($order_ids){
            $template = '<!DOCTYPE html>
            <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                    <title>AWB</title>
                    <style>
                        *{
                            margin: 0;
                            padding: 0;
                            font-family: Arial, Helvetica, sans-serif;
                        }
                        @page {
                            margin: 0cm 0cm;
                        }
                        body {
                            margin-top: 10px;
                            margin-left: 10px;
                            margin-right: 10px;
                            margin-bottom: 10px;
                            font-size: 11px !important;
                            color: #111111;
                            background-color: #ffffff;
                        }
                        .page-break {
                            page-break-after: always;
                        }
                        table{
                            width: 100%;
                        }
                        table th, table td{
                            padding: 3px;
                            vertical-align: middle;
                        }
                        table .table-equal th,
                        table .table-equal td{
                            width: 33.33%;
                        }
                        table.table-border-2{
                            border: 2px solid #111111;
                            
                        }
                        table.block-wrap{
                            border: 1px solid #111111;
                        }
                        table.block-wrap th{
                            border-bottom: 1px solid #111111;
                        }
                        table.block-wrap td{
                            display: inline-block;
                            min-height: 150px;
                        }
                        table.non-block td{
                            display: table-cell;
                        }
                        table.collapse{
                            border: 1px solid #111111;
                            border-collapse: collapse;
                        }
                        .text-left{
                            text-align: left;
                        }
                        .text-center{
                            text-align: center;
                        }
                        .text-right{
                            text-align: right;
                        }
                        .text-size-12{
                            font-size: 12px;
                        }
                        .text-color-gray{
                            color: #c3c3c3;
                        }
                        .align-top{
                            vertical-align: top;
                        }
                        .align-middle{
                            vertical-align: middle;
                        }
                        .align-bottom{
                            vertical-align: bottom;
                        }
                        .img-fluid{
                            max-width: 100%;
                        }
                        .img-fluid-w100{
                            max-width: 100px;
                        }
                        .img-fluid-w75{
                            max-width: 75px;
                        }
                        .img-fluid-w50{
                            max-width: 50px;
                        }
                        .m-0{
                            margin: 0;
                        }
                        .mt-0{
                            margin-top: 0;
                        }
                        .5{
                            margin-bottom: 3em;
                        }
                        .mb-5{
                            margin-bottom: 5em;
                        }
                        .mb-10{
                            margin-bottom: 10em;
                        }
                        .p-0{
                            padding: 0;
                        }
                        .p-5{
                            padding: 5px !important;
                        }
                        .pt-5{
                            padding-top: 0;
                        }
                        .pr-5{
                            padding-right: 0;
                        }
                        .pb-5{
                            padding-bottom: 0;
                        }
                        .pl-5{
                            padding-left: 0;
                        }
                        .thick-border{
                            border: 1.5px solid #222222;
                            width: 100%;
                            padding: 5px;
                            overflow: hidden;
                            text-align: left;
                        }
                        .thin-border{
                            border: 1px solid #111111;
                            width: 100%;
                            padding: 5px;
                        }
                        .letter-space{
                            letter-spacing: 2px;;
                        }
                    </style>
                </head>
                <body>
                    <div class="awb">';
                    foreach($order_ids as $order_id):
                        $order = $this->get_order_A6($order_id);
                        if (!is_array($order)) {
                            $template .= $order;
                            $template .= '<div class="page-break"></div>';
                        }
                    endforeach;
                    
                    '</div>
                </body>
            </html>';

            return $template;
        }

        private function get_order_A6($order_id){
            $order = wc_get_order( $order_id );
            if (!$order) {
                $response = [
                    'status' => false,
                    'message' => 'Order Not Found!'
                ];
                return $response;
            }

            if (!$order->get_meta('ninja_van_tracking_number')) {
                $response = [
                    'status' => false,
                    'message' => 'Tracking Number not available!'
                ];
                return $response;
            }

            if (!$order->get_meta('_ninja_van_payload')) {
                $response = [
                    'status' => false,
                    'message' => 'Tracking Number not available!'
                ];
                return $response;
            }

            $ninjavan = unserialize($order->get_meta('_ninja_van_payload'));

            $sender_address = (object) nv_my_sender_address();

            $billing_address = (object) $order->get_address();

            if ($address = (object) $order->get_address('shipping')) {
                if (empty($address->first_name) || empty($address->postcode)) {
                    $address = $billing_address;
                }
            }

            $cash_on_delivery = (isset($ninjavan->parcel_job->cash_on_delivery)) ? $ninjavan->parcel_job->cash_on_delivery : 0;

            $from_phone_number = $ninjavan->from->phone_number;
            
            $sender = $ninjavan->from->address;

            $from_address = '';

            if (isset($sender->address1) && !empty($sender->address1)) {
                $from_address .= $sender->address1.', ';
            }
            if (isset($sender->address2) && !empty($sender->address2)) {
                $from_address .= $sender->address2.', ';
            }
            if (isset($sender->country) && !empty($sender->country)) {
                $from_address .= $sender->country.', ';
            }
            if (isset( $sender_address->address['postcode']) && !empty( $sender_address->address['postcode'])) {
                $from_address .= $sender_address->address['postcode'];
            }

            if (!nv_my_get_settings('awb_seller_information')) {
                $from_phone_number = nv_my_hide_text($from_phone_number);
                $from_address = nv_my_hide_text($from_address);
            }

            $awb_seller_logo_td = '';
            if ($awb_seller_logo = nv_my_seller_logo(false)) {

                $awb_seller_logo_td = '
                    <td>
                        <div class="awb_logo" style="text-align: center">
                            <img src="'.nv_my_get_image($awb_seller_logo,true).'" alt="Seller Logo" class="img-fluid-w75">
                        </div>
                    </td>
                ';
            }

            $response = '
                <table>
                    <tr>
                        <td>
                            <div class="qr-code" style="text-align: left; width: 80px;">
                                <img src="data:image/png;base64,'.esc_html(nv_my_qrcode($ninjavan->tracking_number)).'" alt="'.esc_html($ninjavan->tracking_number).'" style="width: 100%; max-width: 70px; height: auto">
                            </div>
                        </td>
                        '.$awb_seller_logo_td.'
                        <td>
                            <div style="text-align: right">
                                <img src="'.nv_my_get_image(nv_my_get_file("/assets/logo.png"),true).'" style="width: 100%; max-width: 115px" alt="">
                                <h3 class="m-0 mb-0 letter-space" style="margin-top: 5px; margin-right: -3px">AIRWAY BILL</h3>
                                <p class="m-0 mb-0 text-size-12">www.ninjavan.co</p>
                            </div>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>
                            <div class="barcode" style="text-align: center">
                                <p style="letter-spacing: 2px;"><strong>'.esc_html($ninjavan->tracking_number).'</strong></p>
                                <img src="data:image/png;base64,'.esc_html(nv_my_barcode($ninjavan->tracking_number)).'" style="margin: 5px 0 0 0; display: block; width: 96.5%; height: 30px; overflow: hidden;">
                            </div>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td style="vertical-align: top">
                            <table class="text-left" style="width: 100%; border: 1px solid #111111;">
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111">FROM (SENDER)</th>
                                </tr>
                                <tr>
                                    <td>
                                        <table class="non-block">
                                            <tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/contact.png"),true).'" alt="Sender" width="16px">
                                                </td>
                                                <td><strong>'.esc_html($ninjavan->from->name).'</strong></td>
                                            </tr>';
                                            if (nv_my_get_settings('awb_seller_information')) {
                                                if (nv_my_get_settings('awb_phone_number')) {
                                                    $response .= '<tr>
                                                        <td width="10px">
                                                            <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/phone.png"),true).'" alt="Phone" width="12px">
                                                        </td>
                                                        <td>'.esc_html($from_phone_number).'</td>
                                                    </tr>';
                                                }
                                               $response .= '<tr>
                                                    <td width="10px">
                                                        <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/address.png"),true).'" alt="Location" width="16px">
                                                    </td>
                                                    <td>'.esc_html($from_address).'</td>
                                                </tr>';
                                            }
                                        $response .= '</table>
                                    </td>
                                </tr>
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111">TO (ADDRESSEE)</th>
                                </tr>
                                <tr>
                                    <td>
                                        <table class="non-block">
                                            <tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/contact.png"),true).'" alt="Receiver" width="16px">
                                                </td>
                                                <td><strong>'.esc_html($ninjavan->to->name).'</strong></td>
                                            </tr>';
                                            if (nv_my_get_settings('awb_phone_number')) {
                                                $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/phone.png"),true).'" alt="Phone" width="12px">
                                                </td>
                                                <td>'.esc_html($ninjavan->to->phone_number).'</td>
                                            </tr>';
                                            }
                                            $response .= '<tr>
                                                <td width="10px">
                                                    <img src="'.nv_my_get_image(nv_my_get_file("/assets/images/awb/address.png"),true).'" alt="Location" width="16px">
                                                </td>
                                                
                                                <td>';
                                                    $to = $ninjavan->to->address;

                                                    if (isset($to->address1)) {
                                                        $response .= esc_html($to->address1).', ';
                                                    }
                                                    if (isset($to->address2)) {
                                                        $response .= esc_html($to->address2).', ';
                                                    }
                                                    if (isset($to->country)) {
                                                        $response .= esc_html($to->country).', ';
                                                    }
                                                    if (isset($address->postcode)) {
                                                        $response .= esc_html($address->postcode);
                                                    }
                                                $response .= '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <th style="text-align: left; border-bottom: 1px solid #111111"></th>
                                </tr>
                                <tr>
                                    <td>
                                        <table class="non-block">
                                            <tr>
                                                <td>
                                                    <strong>COD: </strong> '.nv_my_amount_with_currency($cash_on_delivery, $to->country).'
                                                </td>
                                            </tr>';
                                            if(nv_my_get_settings('awb_order_note') && !empty($order->get_customer_note())){
                                                $response .= '<tr>
                                                    <td style="text-align: left">
                                                        <strong>Note:</strong> '.esc_html(nv_my_short_text($order->get_customer_note())).'
                                                    </td>
                                                </tr>';
                                            }
            
                                            if(nv_my_get_settings('awb_order_item') && !empty($ninjavan->parcel_job->delivery_instructions)){
                                                $response .= '<tr>
                                                    <td style="text-align: left">
                                                        <strong>Items:</strong> '.esc_html(nv_my_short_text($ninjavan->parcel_job->delivery_instructions)).'
                                                    </td>
                                                </tr>';
                                            }

                                            if(nv_my_get_settings('awb_order_item_sku') && !empty($skus = nv_my_get_order_items($order, true))){
                                                $response .= '<tr>
                                                    <td style="text-align: left">
                                                        <strong>SKUs:</strong> '.esc_html(nv_my_short_text($skus)).'
                                                    </td>
                                                </tr>';
                                            }
                                        $response .= '</table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {{footer}}
                </table>';

                if (nv_my_get_settings('awb_channel_url')) {
                    $response = str_replace('{{footer}}', '<tr><td><p class="text-center text-size-12 text-color-gray">'.esc_url(get_site_url()).'</p></td></tr>', $response);
                } else {
                    $response = str_replace('{{footer}}', '', $response);
                }


                $this->add_stats($order);
                $order->save();

                return $response;
        }

        /**
         * Increment the number of AWB generated
         * 
         * @param \WC_Order $order
         * @return void
         */
        private function add_stats(\WC_Order $order) {
            // Get the current number of AWB generated in order meta
            $awb_generated = $order->get_meta('_ninja_van_awb_generated');

            // If the meta is not set, set it to 1, else increment it by 1
            $awb_generated = $awb_generated ? $awb_generated + 1 : 1;

            // Update the meta
            $order->update_meta_data('_ninja_van_awb_generated', esc_html($awb_generated));
        }
    }