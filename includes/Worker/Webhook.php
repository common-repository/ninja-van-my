<?php

    namespace Ninja\Van\MY\Worker;

    /**
     * This class manage webhook subscriptions & callbacks from Ninja Van
     */
    class Webhook extends API
    {
        public $token = '';
        public $events;

        function __construct() {
            parent::__construct();

            $this->events = [
                '1.1' => [
                    'Pending Pickup',
                    'Arrived at Sorting Hub',
                    'On Vehicle for Delivery',
                    'Returned to Sender',
                    'Completed',
                    'Cancelled'
                ],
                '2.0' => [
                    'Pending Pickup',
                    'Arrived at Origin Hub',
                    'On Vehicle for Delivery',
                    'Returned to Sender',
                    'Delivered',
                    'Cancelled',
                ]
            ];
            $this->token = nv_my_token();
        }

        public function get_events($version = '', $case = false){
            if (empty($version)) {
                $array = [];
                foreach ($this->events as $events) {
                    $array = array_merge($array, $events);
                }
                return ($case) ? $array : array_map('strtolower', array_unique($array));
            }

            if (isset($this->events[$version])) {
                return ($case) ? $this->events[$version] : array_map('strtolower', $this->events[$version]);
            }

            return [];
        }

        /**
         * Get Webhooks
         * Version 1.0 is Deprecated. Use Version 2.0 instead
         */
        public function get_webhooks($version = '2.0'){
            if (!in_array($version, ['1.0', '2.0'])) {
                return [
                    'status' => false,
                    'message' => 'Invalid Version!',
                    'data' => []
                ];
            }
            $get_webhooks = ($version == '1.0') ? 'get_webhooks' : 'get_webhooks_v2';
            if (!$this->token) {
                $this->token = nv_my_token(true);
            }

            if (!$this->token) {
                return [
                    'status' => false,
                    'message' => 'Problem With Access Token!',
                    'data' => []
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode][$get_webhooks];
            $request = wp_remote_get( $url,
                array(
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $this->token),
                        'Path' => $this->endpoints[$this->auth_mode][$get_webhooks]
                    ]
                )
            );

            if ( is_wp_error( $request )) {
                return [
                    'status' => false,
                    'message' => $request->get_error_message(),
                    'data' => []
                ];
            } else if (wp_remote_retrieve_response_code( $request ) != 200) {
                return [
                    'status' => false,
                    'message' => 'Webhooks Fetch Failed!',
                    'data' => json_decode(wp_remote_retrieve_body( $request ))
                ];
            }

            return [
                'status' => true,
                'message' => 'Webhooks Fetched!',
                'data' => json_decode(wp_remote_retrieve_body( $request ))
            ];
        }

        /**
         * Create Webhooks
         * Version 1.0 is Deprecated. Use Version 2.0 instead
         */
        public function create_webhook($data, $version = '2.0'){
            if (!in_array($version, ['1.0', '2.0'])) {
                return [
                    'status' => false,
                    'message' => 'Invalid Version!',
                    'data' => []
                ];
            }
            $create_webhooks = ($version == '1.0') ? 'create_webhooks' : 'create_webhooks_v2';
            if (!$this->token) {
                $this->token = nv_my_token(true);
            }

            if (!$this->token) {
                return [
                    'status' => false,
                    'message' => 'Problem With Access Token!',
                    'data' => []
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode][$create_webhooks];
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $this->token),
                        'Path' => $this->endpoints[$this->auth_mode][$create_webhooks]
                    ],
                    'body' => wp_json_encode($data)
                )
            );

            if ( is_wp_error( $request )) {
                return [
                    'status' => false,
                    'message' => $request->get_error_message(),
                    'data' => []
                ];
            } else if (wp_remote_retrieve_response_code( $request ) == 400) {
                $response = json_decode(wp_remote_retrieve_body( $request ));
                if (isset($response->error)) {
                    $error = $response->error;
                    if (isset($error->message)) {
                        return [
                            'status' => false,
                            'message' => $error->message,
                            'data' => $response
                        ];
                    }
                }

                return [
                    'status' => false,
                    'message' => 'Webhooks Creation Failed!',
                    'data' => $response
                ];
            } else if (wp_remote_retrieve_response_code( $request ) != 200) {
                return [
                    'status' => false,
                    'message' => 'Webhooks Creation Failed!',
                    'data' => json_decode(wp_remote_retrieve_body( $request ))
                ];
            }

            return [
                'status' => true,
                'message' => 'Webhooks Created!',
                'data' => json_decode(wp_remote_retrieve_body( $request ))
            ];
        }

        /**
         * Delete Webhooks
         * Version 1.0 is Deprecated. Use Version 2.0 instead
         */
        public function delete_webhook($data, $version = '2.0'){
            if (!in_array($version, ['1.0', '2.0'])) {
                return [
                    'status' => false,
                    'message' => 'Invalid Version!',
                    'data' => []
                ];
            }
            $delete_webhooks = ($version == '1.0') ? 'delete_webhooks' : 'delete_webhooks_v2';
            if (!$this->token) {
                $this->token = nv_my_token(true);
            }

            if (!$this->token) {
                return [
                    'status' => false,
                    'message' => 'Problem With Access Token!',
                    'data' => []
                ];
            }

            if (empty($data)) {
                return [
                    'status' => false,
                    'message' => 'Webhook ID is required!',
                    'data' => []
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode][$delete_webhooks].'/'.$data;
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'DELETE',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $this->token),
                        'Path' => $this->endpoints[$this->auth_mode][$delete_webhooks],
                    ]
                )
            );

            if ( is_wp_error( $request )) {
                return [
                    'status' => false,
                    'message' => $request->get_error_message(),
                    'data' => []
                ];
            } else if (wp_remote_retrieve_response_code( $request ) == 400) {
                $response = json_decode(wp_remote_retrieve_body( $request ));
                if (isset($response->error)) {
                    $error = $response->error;
                    if (isset($error->message)) {
                        return [
                            'status' => false,
                            'message' => $error->message,
                            'data' => $response
                        ];
                    }
                }

                return [
                    'status' => false,
                    'message' => 'Webhooks Deletion Failed!',
                    'data' => $response
                ];

            } else if (wp_remote_retrieve_response_code( $request ) == 404) {
                return [
                    'status' => false,
                    'message' => 'Webhooks Not Found!',
                    'data' => json_decode(wp_remote_retrieve_body( $request ))
                ];
            } else if (wp_remote_retrieve_response_code( $request ) == 500) {
                $response = json_decode(wp_remote_retrieve_body( $request ));
                if (isset($response->error)) {
                    $error = $response->error;
                    if (isset($error->message)) {
                        return [
                            'status' => false,
                            'message' => $error->message,
                            'data' => $response
                        ];
                    }
                }

                return [
                    'status' => false,
                    'message' => 'Webhooks Deletion Failed!',
                    'data' => $response
                ];
            } else if (wp_remote_retrieve_response_code( $request ) != 200) {
                return [
                    'status' => false,
                    'message' => 'Webhooks Deletion Failed!',
                    'data' => json_decode(wp_remote_retrieve_body( $request ))
                ];
            }

            return [
                'status' => true,
                'message' => 'Webhooks Deleted!',
                'data' => json_decode(wp_remote_retrieve_body( $request ))
            ];
        }

        public function callback($order_id = 0, $tracking_number = 0, $ninjavan_status = ''){
            $order = wc_get_order($order_id);

            if (!$order || $order->get_meta('ninja_van_tracking_number') != $tracking_number) {
                $response = [
                    'status' => false,
                    'message' => "Order Not Found!",
                    'data' => []
                ];
                return wp_send_json($response);
            }

            /**
             * Skip if ninja van status is not completed, but order status is completed
             */
            if ((strtolower($ninjavan_status) != 'completed' && strtolower($ninjavan_status) != 'delivered') && $order->get_status() == 'completed') {
                $response = [
                    'status' => false,
                    'message' => "This Order has already been completed!",
                    'data' => []
                ];
                return wp_send_json($response);
            }

            /**
             * Check if this order has already been updated
             */
            $ninjavan_events = unserialize($order->get_meta('_ninja_van_events'));
            $ninjavan_events = is_array($ninjavan_events) ? $ninjavan_events : [];
            if (isset($ninjavan_events[strtolower($ninjavan_status)])) {
                $response = [
                    'status' => false,
                    'message' => "This Order has already been updated!",
                    'data' => []
                ];
                return wp_send_json($response);
            }

            $order->update_meta_data('_ninja_van_last_event_date', gmdate('Y-m-d H:i:s'));
            /**
             * Record the event
             */
            if (in_array(strtolower($ninjavan_status), $this->get_events())) {
                $ninjavan_events[strtolower($ninjavan_status)] = gmdate('Y-m-d H:i:s');
                $order->update_meta_data('_ninja_van_events', serialize($ninjavan_events));
            }

            $order->save();

            $note = 'Shipping Details Has Been Updated!';

            switch (strtolower($ninjavan_status)) {
                case 'pending pickup':
                    $note = 'Ninja Van: This Order will be Pickup shortly! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('nv-pending-pickup');
                    $order->add_order_note($note);
                    break;

                /**
                 * @deprecated
                 */
                case 'arrived at sorting hub':
                    $note = 'Ninja Van: This Order will be Delivered soon! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('nv-in-transit');
                    $order->add_order_note($note);
                    break;

                case 'on vehicle for delivery':
                    $note = 'Ninja Van: Order out for Delivery and will be Delivered shorty! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('nv-out-delivery');
                    $order->add_order_note($note);
                    break;

                /**
                 * @deprecated
                 */
                case 'completed':
                    $note = 'Ninja Van: Order has been Delivered! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('completed');
                    $order->add_order_note($note);
                    break;

                case 'cancelled':
                    $note = 'Ninja Van: Order has been Cancelled! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('cancelled');
                    $order->add_order_note($note);
                    break;

                case 'returned to sender':
                    $note = 'Ninja Van: Delivery of this Order has failed repeatedly! Goods will be returned to Sender. Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('nv-returned');
                    $order->add_order_note($note);
                    break;

                case 'delivered':
                    $note = 'Ninja Van: Order has been Delivered! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('completed');
                    $order->add_order_note($note);
                    break;

                case 'arrived at transit hub':
                    $note = 'Ninja Van: Order has arrived at Transit Hub! Date: '.gmdate('Y-m-d H:i:s');
                    $order->update_status('nv-in-transit');
                    $order->add_order_note($note);
                    break;
                
                default:
                    $response = [
                        'status' => false,
                        'message' => "Nothing to do with this request!",
                        'data' => []
                    ];
                    return wp_send_json($response);
                    break;
            }

            $response = [
                'status' => true,
                'message' => $note,
                'data' => [
                    'order_id' => $order_id,
                    'tracking_number' => $tracking_number,
                    'ninjavan_status' => $ninjavan_status,
                ]
            ];
            return wp_send_json($response);
        }
    }