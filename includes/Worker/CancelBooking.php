<?php
    namespace Ninja\Van\MY\Worker;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class handles order cancellation from Ninja Van
     */
    class CancelBooking extends API
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

            $cancel_item = $this->prepare($order_id);

            if (!$cancel_item || !is_array($cancel_item)) {
                $this->update_order($order_id, new \stdClass(), 'Tracking Number Not Found!');

                return [
                    'status' => false,
                    'message' => 'Tracking Number Not Found!',
                    'data' => []
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode]['cancel_order'].'/'.$cancel_item['trackingNo'];
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'DELETE',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $this->token),
                    ]
                )
            );

            /**
             * @todo save() is relatively an expensive operation. We should only call it if we need to.
             */
            $this->update_meta_data($order_id, '_ninja_van_console_log', wp_remote_retrieve_body( $request ));

            if ( is_wp_error( $request )) {
                $this->update_order($order_id, new \stdClass(), $request->get_error_message());
                return [
                    'status' => false,
                    'message' => $request->get_error_message(),
                    'data' => []
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 400) {
                $message = 'Error while trying to request for order cancel!';

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
                    'message' => 'Error while trying to request for order cancel!',
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
            }elseif (wp_remote_retrieve_response_code($request) == 404) {
                $this->update_order($order_id, new \stdClass(), 'Order Not Found!');

                return [
                    'status' => false,
                    'message' => 'Order Not Found!',
                    'data' => []
                ];
            }elseif (wp_remote_retrieve_response_code($request) == 200) {
                $response = json_decode(wp_remote_retrieve_body( $request ));
                if (!isset($response->trackingId)) {
                    $message = 'Unable to cancel order. Bad Response!';
                } else {
                    $message = 'Order was cancelled';
                }
                $this->update_order($order_id, $response, $message);
                return [
                    'status' => true,
                    'message' => 'Order successfully cancelled!',
                    'data' => $response
                ];
            }else{
                $this->update_order($order_id, new \stdClass(), 'Unable to cancel order!');

                return [
                    'status' => false,
                    'message' => 'Unable to cancel order!',
                    'data' => []
                ];
            }
        }

        private function prepare(int $order_id){
            $order = wc_get_order( $order_id );

            if (!$order->get_meta('ninja_van_tracking_number')) {
                return false;
            }

            $cancel_item = [
                'trackingNo' => $order->get_meta('ninja_van_tracking_number'),
            ];

            return $cancel_item;
        }

        private function update_order($order_id, $response, $message = 'Order was cancelled'){

            $order = wc_get_order( $order_id );

            $order->update_meta_data( '_ninja_van_cancelled_payload', serialize($response) );

            $order->update_meta_data( '_ninja_van_console_log', $message );

            $order->save();
        }

        private function update_meta_data($order_id, $key, $value){
            $order = wc_get_order( $order_id );

            $order->update_meta_data( $key, $value );

            $order->save();
        }
       
    }