<?php

    namespace Ninja\Van\MY\Worker;

    use Ninja\Van\MY\Worker\AccessToken;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class handles callback from Ninja Van
     * Scopes: Webhook, Auth Redirect, and Logout
     */
    class Callback
    {
        private $routes = [
            '/ninjavan-webhook',
            '/ninjavan-auth',
            '/ninjavan-logout',
            '/ninjavan-term'
        ];

        function __construct() {
            /**
             * Update $routes as per status
             */
            if (nv_my_get_config('status') == false){
                if ((nv_my_get_config('auth_mode') == 'PLUGIN')) {
                    $this->routes = [
                        '/ninjavan-auth',
                    ];
                }
                $this->routes[] = '/ninjavan-term';
            }
            add_action( 'wp_loaded', [$this, 'listen_callback'] );
        }

        /**
         * Listen to callback from Ninja Van
         * @path [POST] ninjavan-webhook is the path that we have set in the Ninja Van Dashboard
         * @path [GET] ninjavan-auth is redirect url for authentication
         * @path [GET] ninjavan-logout is redirect url for logout
         */
        public function listen_callback(){
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
                $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                $url = esc_url_raw($url);

                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return;
                }

                $webhook = wp_parse_url($url);

                if (!isset($webhook['path'])) {
                    return;
                }

                $route = '/' . basename($webhook['path']);

                if (!in_array($route, $this->routes)) {
                    return;
                }

                switch ($route) {
                    case '/ninjavan-webhook':
                        $response = [
                            'status' => false,
                            'message' => "Bad Request!",
                            'data' => []
                        ];
                        return wp_send_json($response);
                        break;
                    case '/ninjavan-auth':
                        // Get parameters from request
                        $code = isset($_REQUEST['code']) ? sanitize_text_field($_REQUEST['code']) : NULL;
                        $state = isset($_REQUEST['state']) ? sanitize_text_field($_REQUEST['state']) : NULL;

                        if (!$code || !$state) {
                            $response = [
                                'status' => false,
                                'message' => "Bad Request!",
                                'data' => []
                            ];
        
                            return wp_send_json($response);
                        }

                        if ($state != 'authorized') {
                            $response = [
                                'status' => false,
                                'message' => "Hmm! Something went wrong!",
                                'data' => []
                            ];
        
                            return wp_send_json($response);
                        }

                        $request = [
                            'code' => $code,
                            'state' => $state,
                        ];

                        return $this->callback_oauth($request);
                        break;
                    case '/ninjavan-logout':
                        if (isset($_REQUEST['security']) && sanitize_text_field($_REQUEST['security']) == nv_my_get_webhook_security()) {
                            return $this->callback_logout();
                        }
                        $response = [
                            'status' => false,
                            'message' => "Invalid Security Code!",
                            'data' => []
                        ];

                        return wp_send_json($response);
                        break;
                    case '/ninjavan-term':
                        if (Ninja_Van_MY_DEVELOPMENT) {
                            // Do not process the whole output. Only take what's needed
                            $request = [
                                'info' => isset($_REQUEST['info']) ? sanitize_text_field($_REQUEST['info']) : NULL,
                                'reset' => isset($_REQUEST['reset']) ? sanitize_text_field($_REQUEST['reset']) : NULL,
                                'webhooks' => isset($_REQUEST['webhooks']) ? sanitize_text_field($_REQUEST['webhooks']) : NULL,
                                'security' => isset($_REQUEST['security']) ? sanitize_text_field($_REQUEST['security']) : NULL,
                                'hard' => isset($_REQUEST['hard']) ? sanitize_text_field($_REQUEST['hard']) : NULL,
                                'event_name' => isset($_REQUEST['event_name']) ? sanitize_text_field($_REQUEST['event_name']) : NULL,
                                'event_id' => isset($_REQUEST['event_id']) ? sanitize_text_field($_REQUEST['event_id']) : NULL,
                            ];
                            return $this->callback_term($request);
                        }
                        break;

                    default:
                        # code...
                        break;
                }
            }
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
                $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                $url = esc_url_raw($url);

                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                }
                
                $webhook = wp_parse_url($url);

                if (!isset($webhook['path'])) {
                    return;
                }

                $route = '/' . basename($webhook['path']);

                if (!in_array($route, $this->routes)) {
                    return;
                }

                switch ($route) {
                    case '/ninjavan-webhook':
                        if (isset($_REQUEST['security']) && sanitize_text_field($_REQUEST['security']) == nv_my_get_webhook_security()) {
                            $security_token = NULL;
                            foreach (getallheaders() as $header_name => $header_value) {
                                if (strtolower($header_name) == 'x-ninjavan-hmac-sha256') {
                                    $security_token = $header_value;
                                }
                            }
                            
                            if (!$security_token) {
                                $response = [
                                    'status' => false,
                                    'message' => "Invalid Token!",
                                    'data' => []
                                ];
        
                                return wp_send_json($response);
                            }

                            $request = json_decode(file_get_contents('php://input'));
                            
                            if (!$request) {
                                $response = [
                                    'status' => false,
                                    'message' => "Invalid Request!",
                                    'data' => []
                                ];
        
                                return wp_send_json($response);
                            }
        
                            return $this->callback_webhook($request);
                        }else {
                            $response = [
                                'status' => false,
                                'message' => "Invalid Security Code!",
                                'data' => []
                            ];
        
                            return wp_send_json($response);
                        }
                        break;

                    case '/ninjavan-auth':
                        $response = [
                            'status' => false,
                            'message' => "Bad request!",
                            'data' => []
                        ];
                        return wp_send_json($response);
                        break;

                    case '/ninjavan-logout':
                        $response = [
                            'status' => false,
                            'message' => "Bad request!",
                            'data' => []
                        ];
                        return wp_send_json($response);
                        break;

                    case '/ninjavan-term':
                        $response = [
                            'status' => false,
                            'message' => "Bad request!",
                            'data' => []
                        ];
                        return wp_send_json($response);
                        break;
                    
                    default:
                        # code...
                        break;
                }
            }        
        }

        public function callback_webhook($request) {
            if (!isset($request->shipper_order_ref_no) || !isset($request->tracking_id) || !isset($request->status)) {
                $response = [
                    'status' => false,
                    'message' => "Invalid Request: Order Reference, Tracking ID and Status is required!",
                    'data' => []
                ];

                return wp_send_json($response);
            }

            $order_id = (int) nv_my_pure_digit(sanitize_text_field($request->shipper_order_ref_no));
            $tracking_number = sanitize_text_field($request->tracking_id);
            $ninjavan_status = sanitize_text_field($request->status);

            return nv_my_process_webhook($order_id, $tracking_number, $ninjavan_status);
        }

        /**
         * Plugin API Authentication Callback
         */
        public function callback_oauth($request) {
            update_option('Ninja_Van_WooCommerce_ACCESS_CODE', $request['code']);
            update_option('Ninja_Van_WooCommerce_STATUS', true);
            update_option('Ninja_Van_WooCommerce_AUTH_STATE', 0);

            $access_token = nv_my_token();

            if (!$access_token) {
                $state = 'false';
            } else {
                $state = 'true';
            }

            // Sync webhooks
            try {
                $webhooks = nv_my_sync_webhooks(true);
            } catch (\Exception $e) {
                nv_my_log($e->getMessage());
            }

            print('<script>window.location.href = "'.esc_url(admin_url('admin.php?page=ninja-van-my&login='.$state)).'";</script>');
            return;
        }

        /**
         * Plugin API Logout Callback
         */
        public function callback_logout() {

            if (nv_my_get_config('auth_mode') != 'PLUGIN') {
                print('<script>window.location.href = "'.esc_url(admin_url('admin.php?page=ninja-van-my&logout=true')).'";</script>');
                return;
            }

            // Delete all webhooks
            $webhooks = nv_my_get_webhooks();
            if (!empty($webhooks)) {
                foreach ($webhooks as $webhook) {
                    nv_my_delete_webhook($webhook->id);
                }
            }
            $response = nv_my_logout();

            if (!$response->status) {
                nv_my_log($response->message);
                print('<script>window.location.href = "'.esc_url(admin_url('admin.php?page=ninja-van-my&logout=false')).'";</script>');
                return;
            }

            nv_my_log('Logging out from Ninja Van API: '.$response->message);

            print('<script>window.location.href = "'.esc_url(admin_url('admin.php?page=ninja-van-my&logout=true')).'";</script>');
            return;
        }

        private function callback_term($request = []) {
            // Only allow admin to access this page
            if (!current_user_can('manage_options')) {
                $response = [
                    'status' => false,
                    'message' => "Ah! Ah! Ah! You didn't say the magic word!",
                    'data' => []
                ];
                return wp_send_json($response);
            }
            if (isset($request['info'])) {
                $response = nv_my_info();
            } 
            
            else if (isset($request['reset'])) {
                if (isset($request['security']) && $request['security'] == nv_my_get_webhook_security()) {
                    nv_my_reset(isset($request['hard']) ? true : false);
                    $response = [
                        'status' => true,
                        'message' => "Reset Completed!",
                        'data' => []
                    ];
                } else {
                    $response = [
                        'status' => false,
                        'message' => "What are you trying to do?",
                        'data' => []
                    ];
                }
            } 

            else if (isset($request['webhooks'])) {
                if (isset($request['security']) && $request['security'] == nv_my_get_webhook_security()) {
                    if ($request['webhooks'] == 'get') {
                        $webhooks = nv_my_get_webhooks();
                        if (empty($webhooks)) {
                            $response = [
                                'status' => false,
                                'message' => "Webhooks Not Found!",
                                'data' => []
                            ];
                        } else {
                            $response = [
                                'status' => true,
                                'message' => "Webhooks Fetched!",
                                'data' => $webhooks
                            ];
                        }
                    } else if ($request['webhooks'] == 'create') {
                        if (isset($request['event_name']) && !empty($request['event_name'])) {
                            $created = nv_my_create_webhook($request['event_name']);
                            if ($created) {
                                $response = [
                                    'status' => true,
                                    'message' => "Webhook Created!",
                                    'data' => $created
                                ];
                            } else {
                                $response = [
                                    'status' => false,
                                    'message' => "Webhook Creation Failed!",
                                    'data' => []
                                ];
                            }
                        } else {
                            $response = [
                                'status' => false,
                                'message' => "Event Name is required!",
                                'data' => []
                            ];
                        }
                    } else if ($request['webhooks'] == 'delete') {
                        if (isset($request['event_id']) && !empty($request['event_id'])) {
                            $deleted = nv_my_delete_webhook($request['event_id']);
                            if ($deleted) {
                                $response = [
                                    'status' => true,
                                    'message' => "Webhook Deleted!",
                                    'data' => []
                                ];
                            } else {
                                $response = [
                                    'status' => false,
                                    'message' => "Webhook Not Found!",
                                    'data' => []
                                ];
                            }
                        } else {
                            $response = [
                                'status' => false,
                                'message' => "Webhook ID is required!",
                                'data' => []
                            ];
                        }
                    } else if ($request['webhooks'] == 'sync') {
                        $response = [
                            'status' => true,
                            'message' => "Webhooks Synced!",
                            'data' => nv_my_sync_webhooks(true)
                        ];
                    } else {
                        $response = [
                            'status' => false,
                            'message' => "Ah! Ah! Ah! You didn't say the magic word!",
                            'data' => []
                        ];
                    }
                } else {
                    $response = [
                        'status' => false,
                        'message' => "What are you trying to do bro?",
                        'data' => []
                    ];
                }

            }
            
            else {
                $response = [
                    'status' => true,
                    'message' => "Hello world!",
                    'data' => []
                ];
            }
            

            return wp_send_json($response);
        }
    }

?>