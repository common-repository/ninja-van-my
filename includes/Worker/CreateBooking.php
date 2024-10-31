<?php
    namespace Ninja\Van\MY\Worker;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class handles order booking from Ninja Van
     */
    class CreateBooking extends API
    {
        public $token = '';

        function __construct() {
            parent::__construct();

            $this->token = nv_my_token();
        }

        public function by_order_id(int $order_id){
            if (!$this->token) {
                $this->token = nv_my_token(true);
            }

            if (!$this->token) {
                $this->update_order($order_id, new \stdClass(), 'Problem With Access Token!');

                return [
                    'status' => false,
                    'message' => 'Problem With Access Token!',
                    'data' => []
                ];
            }

            /**
             * @todo prepare() should appropriately return false with a message if there is an error.
             */
            $booking = $this->prepare($order_id);
            $booking_status = $booking['status'];
            $booking_message = $booking['message'];
            $booking_item = $booking['item'];

            if (!$booking_status) {
                if (!isset($booking['flag'])) {
                    $this->update_order($order_id, new \stdClass(), $booking_message);
                }
                return [
                    'status' => false,
                    'message' => $booking_message,
                    'data' => []
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode]['create_order'];
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $this->token),
                    ],
                    'body'        => wp_json_encode($booking_item),
                )
            );

            if ( is_wp_error( $request )) {
                $this->update_order($order_id, new \stdClass(), $request->get_error_message());
                return [
                    'status' => false,
                    'message' => $request->get_error_message(),
                    'data' => []
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 400) {
                $message = 'Validation Error! Check Customer Address And Try Again!';

                $ninja_van = json_decode(wp_remote_retrieve_body( $request ));

                if (isset($ninja_van->error)) {
                    if ($ninja_van->error->title) {
                        $message = $ninja_van->error->title;
        
                        if (isset($ninja_van->error->message)) {
                            $message .= '. '.$ninja_van->error->message;
                        }
                    }

                    if (is_array($ninja_van->error->details) && !empty($ninja_van->error->details)) {
                        foreach ($ninja_van->error->details as $key => $details) {
                            if (isset($details->message)) {
                                $message .= '. '.$details->reason.': ('.$details->field.') '.$details->message;
                            }
                        }
                    }
                }

                $this->update_order($order_id, new \stdClass(), $message);

                return [
                    'status' => false,
                    'message' => 'Validation Error! Check Customer Address And Try Again!',
                    'data' => json_decode(wp_remote_retrieve_body( $request ))
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 401) {
                $this->update_order($order_id, new \stdClass(), 'Access Token Expired Or Authentication Failed! Try Again!');

                $this->token = nv_my_token(true);

                return [
                    'status' => false,
                    'message' => 'Authentication Failed! Try Again!',
                    'data' => []
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 200) {
                $response = json_decode(wp_remote_retrieve_body( $request ));
                $this->update_order($order_id, $response);
                return [
                    'status' => true,
                    'message' => 'Successfully Pushed to Ninja Van!',
                    'data' => $response
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 403) {
                $this->update_order($order_id, new \stdClass(), 'Access Denied!');

                return [
                    'status' => false,
                    'message' => 'Access Denied!',
                    'data' => []
                ];
            }else{
                $this->update_order($order_id, new \stdClass(), 'Unable to push tracking to '.$url.' ('.wp_remote_retrieve_response_code($request).')');

                return [
                    'status' => false,
                    'message' => 'Unable to push tracking due to an error!',
                    'data' => []
                ];
            }            
        }

        private function prepare(int $order_id){
            $order = wc_get_order( $order_id );

            if (!$order) {
                return [
                    'status' => false,
                    'message' => 'Order not found!',
                    'item' => []
                ];
            }

            if ($order->get_meta('ninja_van_tracking_number')) {
                return [
                    'status' => false,
                    'message' => 'Already Pushed!',
                    'item' => [],
                    'flag' => 1
                ];
            }

            /**
             * Check service type of `International` and given service code
             */
            if ($this->s_type() == 'International' && empty($this->s_code())) {
                return [
                    'status' => false,
                    'message' => 'Service Code Is Required For International Service Type!',
                    'item' => []
                ];
            }

            $billing_address = (object) $order->get_address();

            /**
             * If the billing address is not complete, we should not proceed.
             */
            if ($address = (object) $order->get_address('shipping')) {
                if (!$address->phone || empty($address->phone)) {
                    $address->phone = $order->get_meta('_shipping_phone');
                }
                if (count(nv_my_validate_address($address->country, $address)) > 0) {
                    $address = $billing_address;
                }
            }

            $required_fields = nv_my_validate_address($address->country, $address);
            if (count($required_fields) > 0) {
                return [
                    'status' => false,
                    'message' => implode(', ', $required_fields).' is required!',
                    'item' => []
                ];
            }

            $origin_country = nv_my_woocommerce_default_country();
            /**
             * Check if domestic shipment is enabled for origin and destination country
             */
            if (($origin_country == $address->country) && !nv_my_get_settings('domestic_shipment')) {
                return [
                    'status' => false,
                    'message' => 'Domestic Shipment Is Not Enabled!',
                    'item' => []
                ];
            }

            /**
             * Check if international shipment is enabled for origin and destination country
             */
            if (($origin_country != $address->country) && !nv_my_get_settings('international_shipment')) {
                return [
                    'status' => false,
                    'message' => 'International Shipment Is Not Enabled!',
                    'item' => []
                ];
            }
            
            if (!$address->phone || empty($address->phone)) {
                $address->phone = $billing_address->phone;
            }

            $instructions = nv_my_short_text(nv_my_get_order_items($order), 250);

            $weight = nv_my_get_order_weights($order);

            $weight = ($weight > 0) ? $weight : 1;

            // Get COD Amount Start
            if ($order->get_payment_method() == 'cod') {
                // If COD is enabled
                if (!nv_my_get_settings('cash_on_delivery')) return [
                    'status' => false,
                    'message' => 'Cash On Delivery (COD) Is Not Enabled!',
                    'item' => []
                ];
                // Check if address is supported for COD
                if ($origin_country == $address->country ) {
                    $cod_available = nv_my_address_has_cod($address->postcode);
                    // Add order note
                    if (isset($cod_available['message']) && !empty($cod_available['message'])) $order->add_order_note('Ninja Van: '.$cod_available['message']);
    
                    if (isset($cod_available['status']) && !$cod_available['status']) {
                        return [
                            'status' => false,
                            'message' => $cod_available['message'],
                            'item' => []
                        ];
                    }
                    if (isset($cod_available['flag']) && $cod_available['flag'] == -1) {
                        $order->update_meta_data( '_ninja_van_cod_may_not_support', true );
                        $order->save();
                    }
                }

                $cash_on_delivery = (double) $order->get_total();
                $cash_on_delivery_currency = $order->get_currency();

                // If destination country is not equal to origin country and COD is enabled, convert the COD amount to the origin country currency
                if ($origin_country != $address->country && $cash_on_delivery > 0) {
                    $exchange_rates = nv_my_get_settings('exchange_rates');
                    $exchange_rate_amt = (double) $exchange_rates[$address->country]; 
                    $cash_on_delivery_currency = nv_my_currency($address->country);
                    $cash_on_delivery = (double) round($cash_on_delivery * $exchange_rate_amt, 2);
                }
            }else{
                $cash_on_delivery = 0;
            }
            // Get COD Amount End

            $tracking_number = trim(nv_my_get_settings('tracking_prefix').$order->get_id().nv_my_get_settings('tracking_suffix'));

            $prepare = [
                'service_type' => $this->s_type(),
                'service_level' => $this->s_level(),
                'requested_tracking_number' => $tracking_number,
                'reference' => [
                    'merchant_order_number' => 'WC-EX-'.$order->get_id()
                ],
                'from' => $this->sender(),
                'to' => [
                    'name' => trim($address->first_name.' '.$address->last_name),
                    'phone_number' => $address->phone,
                    'email' => $billing_address->email ?? null,
                    'address' => [
                        'address1' => trim($address->company .' '. $address->address_1),
                        'address2' => $address->address_2,
                        'city' => $address->city,
                        'state' => ($address->country == 'MY') ? nv_my_get_states($address->state) : null,
                        'country'  => $address->country,
                        'postcode' => $address->postcode
                    ]
                ],
                'parcel_job' => [
                    'cash_on_delivery' => (double) $cash_on_delivery,
                    'cash_on_delivery_currency' => $cash_on_delivery_currency,
                    'is_pickup_required' => $this->is_pickup(),
                    'pickup_service_type' => $this->ps_type(),
                    'pickup_service_level' => $this->ps_level(),
                    'pickup_address' => $this->pickup(),
                    'pickup_date' => nv_my_time('now', 'Y-m-d'),
                    'pickup_timeslot'  => [
                        'start_time'  => $this->pickup_time('from'),
                        'end_time' => $this->pickup_time('to'),
                        'timezone' => 'Asia/Kuala_Lumpur'
                    ],
                    'pickup_approx_volume' => 'Less than 3 Parcels',
                    'pickup_instruction' => 'Pickup with care!',

                    'delivery_instruction' => $instructions,
                    'delivery_start_date' => nv_my_time('now', 'Y-m-d'),
                    'delivery_timeslot' => [
                        'start_time' => '09:00',
                        'end_time' => '22:00',
                        'timezone' => 'Asia/Kuala_Lumpur'
                    ],

                    'dimensions' => [
                        'size' => '',
                        'weight' => (double) number_format($weight, 2, '.', ''),
                        'length' => '',
                        'width' => '',
                        'height' => ''
                    ]
                ]
            ];

            if ($address->country == 'SG') {
                $prepare['service_type'] = 'International';

                $prepare['international'] = [
                    'portation' => 'Export',
                    'service_code' => $this->s_code($address->country),
                ];

                $prepare['customs_declaration'] = [
                    'goods_currency' => nv_my_currency($address->country),
                ];
                
                $order_items = nv_my_get_order_items_data($order);

                $prepare['parcel_job']['items'] = [];
                foreach ($order_items as $key => $item) {
                    // Skip if price is 0
                    if (floatval($item['price']) == 0) continue;
                    $prepare['parcel_job']['items'][] = [
                        'item_description' => $item['name'] . ' x ' . $item['quantity'],
                        'unit_value' => floatval($item['price']),
                    ];
                }
            }

            return [
                'status' => true,
                'message' => 'Successfully Prepared!',
                'item' => $prepare
            ];
        }

        private function sender(){
            return nv_my_sender_address(true);
        }

        private function s_type(){
            return nv_my_get_settings('service_type');
        }

        private function s_level(){
            return nv_my_get_settings('service_level');
        }

        private function s_code($country){
            // Use built-in service code, only override if it is not empty
            $s_code = nv_my_get_settings('service_code');
            if (!empty($s_code)) return $s_code;
            if (empty($country)) return '';

            // Get order country
            return nv_my_get_international_service_code($country);
        }

        private function pickup(){
            return nv_my_get_default_address();
        }

        private function is_pickup(){
            return nv_my_get_settings('pickup_required');
        }

        private function ps_type(){
            return nv_my_get_settings('pickup_service_type');
        }

        private function ps_level(){
            return nv_my_get_settings('pickup_service_level');
        }

        private function pickup_time($type = 'from'){
            $pickup_time = explode('|', nv_my_get_settings('pickup_time_slot'));

            if ($type == 'from') {
                return $pickup_time[0];
            }elseif ($type == 'to') {
                return $pickup_time[1];
            }
        }

        private function update_order($order_id, $response, $message = 'Successfully Pushed to Ninja Van!'){

            $order = wc_get_order( $order_id );

            $order->update_meta_data( '_ninja_van_payload', serialize($response) );

            if (isset($response->tracking_number)) {
                $order->update_meta_data( 'ninja_van_tracking_number', trim($response->tracking_number) );
            }

            $order->update_meta_data( '_ninja_van_console_log', $message );

            $order->save();
        }

        private function update_meta_data($order_id, $key, $value){
            $order = wc_get_order( $order_id );

            $order->update_meta_data( $key, $value );

            $order->save();
        }
    }