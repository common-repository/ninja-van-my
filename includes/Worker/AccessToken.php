<?php
    namespace Ninja\Van\MY\Worker;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class handles access token generation for Ninja Van
     */
    class AccessToken extends API
    {

        function __construct($refresh = false) {
            parent::__construct();

            if ($this->auth_mode == 'PLUGIN') {
                $state = nv_my_get_config('auth_state');
            } else {
                $state = 1;
            }

            if (!$state && nv_my_get_config('access_code')) {
                nv_my_log('Getting Refresh Token for the first time...');
                $response = $this->get_refresh_token();
                nv_my_log($response['message']);
            }else if (!$refresh) {
                $this->get_access_token();
            }else{
                $this->refresh_token();
            }            
        }

        /**
         * Get refresh token for the first time
         * @scope Plugin API
         * @note Standard expiration time is 86400 seconds (24 hours)
         */
        public function get_refresh_token(){
            $refresh_token = get_transient('ninja_van_refresh_token');

            // Shouldn't happen but just in case...
            if ($refresh_token && !empty($refresh_token)) {
                nv_my_log('Refresh Token already available!');
                return [
                    'status' => false,
                    'message' => 'Refresh Token Available',
                    'token' => $refresh_token,
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode]['token'];

            /**
             * Plugin API requires a different server endpoint
             */
            if ($this->auth_mode == 'PLUGIN') {
                $url = str_replace('https://api', 'https://aaa', $url);
            }
            
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    'body'        => wp_json_encode([
                        'client_id' => $this->client_id,
                        'client_secret' => $this->client_secret,
                        'grant_type' => 'authorization_code',
                        'code' => nv_my_get_config('access_code')
                    ]),
                )
            );

            // Delete Old Access Token From Cache If Available (in case of a plugin auth mode change)
            delete_transient('ninja_van_token');
            delete_transient('ninja_van_token_expiry');
            delete_transient('ninja_van_token_error');
            delete_transient('ninja_van_refresh_token_error');
             
               
            if ( is_wp_error( $request )) {
                set_transient( 'ninja_van_refresh_token_error', $request->get_error_message());
                
                return [
                    'status' => false,
                    'message' => 'Failed To Generate Refresh Token! '.$request->get_error_message(),
                    'token' => null,
                ];
            } else if (wp_remote_retrieve_response_code($request) == 400) {
                $response = json_decode($request['body']);

                $message = 'Failed To Generate Refresh Token! Bad request!';

                if ($response->error && $response->error->message) {
                    $message = $response->error->message;
                }

                set_transient( 'ninja_van_refresh_token_error', $message);

                return [
                    'status' => false,
                    'message' => $message,
                    'token' => null,
                ];
            } else if (wp_remote_retrieve_response_code($request) == 429) {
                $response = json_decode($request['body']);

                $message = 'Failed To Generate Access Token! API rate limit exceeded!';

                if ($response->message) {
                    $message = $response->message;
                }

                set_transient( 'ninja_van_refresh_token_error', $message);
                
                return [
                    'status' => false,
                    'message' => $message,
                    'token' => null,
                ];
            } else if (wp_remote_retrieve_response_code($request) == 200) {
                $response = json_decode($request['body']);

                // If both expires_in and expires are not available, set expires_in to 43200 seconds (12 hours)
                if (!$response->expires_in && !$response->expires) {
                    nv_my_log('Refresh Token Response does not contain expires_in or expires. Setting approriate expiry time... (12 hours)');
                    $response->expires = time() + 43200;
                    $response->expires_in = 43200;
                }

                // Get number of seconds from epoch ($response->expires) if ($response->expires_in) is not available
                if (!$response->expires_in) {
                    nv_my_log('Refresh Token Response does not contain expires_in. Using expires to calculate expires_in time...');
                    $response->expires_in = $response->expires - time();
                }

                // Get the time when the refresh token expires from time remaning ($response->expires_in) if ($response->expires) is not available
                if (!$response->expires) {
                    nv_my_log('Refresh Token Response does not contain expires. Using expires_in to calculate expires time...');
                    $response->expires = time() + $response->expires_in;
                }

                if (!$response->refresh_token) {
                    set_transient( 'ninja_van_refresh_token_error', 'Failed to obtain Refresh Token! (Bad Payload)' );
                    return [
                        'status' => false,
                        'message' => 'Failed To Obtain Refresh Token! Please Contact Ninja Van!',
                        'token' => null,
                    ];
                }

                set_transient( 'ninja_van_token', $response->access_token, ($response->expires_in - 600) );
                set_transient( 'ninja_van_token_expiry', $response->expires);
                set_transient( 'ninja_van_refresh_token', $response->refresh_token, $response->expires_in );
                update_option( 'Ninja_Van_WooCommerce_AUTH_STATE', 1 ); // Connected
                $this->schedule_cron();

                return [
                    'status' => true,
                    'message' => 'A New Refresh Token Has Been Generated!',
                    'token' => $response->access_token,
                ];
            }else{
                $wp_body = wp_remote_retrieve_body($request);
                if (is_array($wp_body) || is_object($wp_body)) {
                    $wp_body = json_encode($wp_body);
                }
                nv_my_log('Failed To Generate Refresh Token with status code ('.wp_remote_retrieve_response_code($request).') and message ('.$wp_body.')');
                
                set_transient( 'ninja_van_refresh_token_error', $wp_body);

                return [
                    'status' => false,
                    'message' => 'Failed To Generate Refresh Token! Please Contact Ninja Van!',
                    'token' => null,
                ];
            }

        }

        /**
         * Get access token
         * @scope Plugin API & Direct API
         * @note Standard expiration time is 86400 seconds (24 hours)
         */
        public function get_access_token(){
            $token = get_transient('ninja_van_token');
            $refresh_token = get_transient('ninja_van_refresh_token');

            if ($token && !empty($token)) {
                return [
                    'status' => true,
                    'message' => 'Access Token Available',
                    'token' => $token,
                ];
            }

            /**
             * If refresh token is not available, connection is expired (for PLUGIN API)
             */
            if ($this->auth_mode == 'PLUGIN' && ( !$refresh_token || empty($refresh_token) )) {
                set_transient( 'ninja_van_refresh_token_error', 'Session Expired! Please Login Again!');
                $this->schedule_cron(true);
                return [
                    'status' => false,
                    'message' => 'Session Expired! Please Login Again!',
                    'token' => null,
                ];
            }

            $url = $this->url.$this->endpoints[$this->auth_mode]['token'];

            /**
             * Plugin API requires a different server endpoint
             */
            if ($this->auth_mode == 'PLUGIN') {
                $url = str_replace('https://api', 'https://aaa', $url);
            }
            
            $request = wp_remote_post( $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    'body'        => wp_json_encode([
                        'client_id' => $this->client_id,
                        'client_secret' => $this->client_secret,
                        'grant_type' => ($this->auth_mode == 'PLUGIN') ? 'refresh_token' : 'client_credentials',
                        'refresh_token' => $refresh_token
                    ]),
                )
            );

            // Delete Old Access Token From Cache If Available
            delete_transient('ninja_van_token');
             
               
            if ( is_wp_error( $request )) {
                set_transient( 'ninja_van_token_error', $request->get_error_message());
                
                return [
                    'status' => false,
                    'message' => 'Failed To Generate Access Token! '.$request->get_error_message(),
                    'token' => null,
                ];
            } else if (wp_remote_retrieve_response_code($request) == 429) {
                $response = json_decode($request['body']);

                $message = 'Failed To Generate Access Token! API rate limit exceeded!';

                if ($response->message) {
                    $message = $response->message;
                }

                return [
                    'status' => false,
                    'message' => $message,
                    'token' => null,
                ];
            } else if (wp_remote_retrieve_response_code($request) == 200) {
                $response = json_decode($request['body']);

                // If both expires_in and expires are not available, set expires_in to 43200 seconds (12 hours)
                if (!$response->expires_in && !$response->expires) {
                    nv_my_log('Refresh Token Response does not contain expires_in or expires. Setting approriate expiry time... (12 hours)');
                    $response->expires = time() + 43200;
                    $response->expires_in = 43200;
                }

                // Get number of seconds from epoch ($response->expires) if ($response->expires_in) is not available
                if (!$response->expires_in) {
                    nv_my_log('Refresh Token Response does not contain expires_in. Using expires to calculate expires_in time...');
                    $response->expires_in = $response->expires - time();
                }

                // Get the time when the refresh token expires from time remaning ($response->expires_in) if ($response->expires) is not available
                if (!$response->expires) {
                    nv_my_log('Refresh Token Response does not contain expires. Using expires_in to calculate expires time...');
                    $response->expires = time() + $response->expires_in;
                }

                if (nv_my_get_config('auth_state') && !$response->refresh_token) {
                    set_transient( 'ninja_van_refresh_token_error', 'Failed to obtain Refresh Token! (Bad Payload)' );
                } else {
                    $response->refresh_token = $refresh_token;
                }

                set_transient( 'ninja_van_token', $response->access_token, ($response->expires_in - 600));
                set_transient( 'ninja_van_token_expiry', $response->expires);
                set_transient( 'ninja_van_refresh_token', $response->refresh_token, $response->expires_in );

                return [
                    'status' => true,
                    'message' => 'A New Access Token Has Been Generated!',
                    'token' => $response->access_token,
                ];
            }else{
                $wp_body = wp_remote_retrieve_body($request);
                if (is_array($wp_body) || is_object($wp_body)) {
                    $wp_body = json_encode($wp_body);
                }
                nv_my_log('Failed To Generate Refresh Token with status code ('.wp_remote_retrieve_response_code($request).') and message ('.$wp_body.')');

                set_transient( 'ninja_van_token_error', $wp_body);

                return [
                    'status' => false,
                    'message' => 'Failed To Generate Access Token! Please Contact Ninja Van!',
                    'token' => null,
                ];
            }
        }

        /**
         * Refresh access token
         * @scope Plugin API & Direct API
         */
        public function refresh_token(){
            delete_transient('ninja_van_token');
            delete_transient('ninja_van_token_expiry');
            delete_transient('ninja_van_token_error');
            delete_transient('ninja_van_refresh_token_error');

            return $this->get_access_token();
        }

        /**
         * Logout from Ninja Van API
         * @scope Plugin API
         */
        public function logout(){
            $url = $this->url.$this->endpoints['PLUGIN']['logout'];

            $access_token = (object) $this->get_access_token();

            if (!$access_token->status) {
                return [
                    'status' => false,
                    'message' => 'Failed To Logout! '.$access_token->message,
                    'token' => null,
                ];
            }

            $access_token = $access_token->token;

            $request = wp_remote_post( $url,
                array(
                    'method'      => 'POST',
                    'timeout'     => Ninja_Van_MY_cURL_TIMEOUT,
                    'headers'     => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => trim('Bearer ' . $access_token),
                    ]
                )
            );

            if ( is_wp_error( $request )) {
                set_transient( 'ninja_van_refresh_token_error', $request->get_error_message());

                return [
                    'status' => false,
                    'message' => 'Failed To Logout! '.$request->get_error_message(),
                    'token' => null,
                ];
            }

            $this->disconnect();

            return [
                'status' => true,
                'message' => 'Successfully Logged Out!',
                'token' => null,
            ];
        }

        /**
         * Schedule cron job for token refresh
         * @scope Plugin API
         */
        public function schedule_cron($remove = false){
            if ($remove) {
                wp_clear_scheduled_hook( 'ninja_van_refresh_token' );
                return;
            }
            if (!wp_next_scheduled( 'ninja_van_refresh_token' )) {
                wp_schedule_event( time(), 'every_5_minutes', 'ninja_van_refresh_token' );
            }
        }

        /**
         * Disconnect from Ninja Van
         * @scope Plugin API & Direct API
         */
        public function disconnect(){
            $this->schedule_cron(true);
            
            delete_transient('ninja_van_token');
            delete_transient('ninja_van_refresh_token');
            delete_transient('ninja_van_token_expiry');
            delete_transient('ninja_van_token_error');
            delete_transient('ninja_van_refresh_token_error');
            
            update_option('Ninja_Van_WooCommerce_STATUS', false);
            update_option('Ninja_Van_WooCommerce_ACCESS_CODE', '');
            update_option('Ninja_Van_WooCommerce_AUTH_STATE', 0); 

        }

        public function cron_get_access_token(){
            nv_my_log('Running cron job to refresh access token...');
            $response = $this->get_access_token();
            nv_my_log($response['message']);
        }

        /**
         * Get scopes for Plugin API
         * @scope Plugin API
         */
        public static function plugin_api_scopes(){
            return [
                'create_order' => ['SHIPPER_PUBLIC_APIS_CREATE_ORDER'],
                'generate_awb' => ['SHIPPER_PUBLIC_APIS_GET_AWB'],
                'cancel_order' => ['SHIPPER_PUBLIC_APIS_CANCEL_ORDER'],
                'webhook_subscriptions' => ['SHIPPER_PUBLIC_APIS_GET_SUBSCRIPTIONS', 'SHIPPER_PUBLIC_APIS_CREATE_SUBSCRIPTION', 'SHIPPER_PUBLIC_APIS_DELETE_SUBSCRIPTION'],
                'shipper_settings' => ['SHIPPER_PUBLIC_APIS_GET_SHIPPER_SETTINGS']
            ];
        }
    }