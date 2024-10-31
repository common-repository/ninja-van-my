<?php
    namespace Ninja\Van\MY\Admin;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class Settings
    {
        public function index_page(){
            $template = __DIR__ . '/views/settings/view.php';

            if (file_exists($template)) {
                include $template;
            }
        }
        
        /**
         * Set Configuration
         *
         * @return void
         */
        static public function nv_settings_form_handlers(): void{

            if (!is_user_logged_in()) {
                return;
            }

            if (!is_admin()) {
                return;
            }

            if (!isset( $_POST['save_settings'])) {
                return;
            }

            if (! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['_wpnonce'])), 'save_settings')) {
                wp_die( 'Hey Boy! Cheating ah?' );
            }

            $settings = [
                'service_level' => (isset($_POST['service_level'])) ? sanitize_text_field( $_POST['service_level'] ) : NULL,
                'service_code' => (isset($_POST['service_code'])) ? sanitize_text_field( $_POST['service_code'] ) : NULL,
                'pickup_required' => (isset($_POST['pickup_required'])) ? sanitize_text_field( $_POST['pickup_required'] ) : NULL,
                'pickup_location' => (isset($_POST['pickup_location'])) ? sanitize_text_field( $_POST['pickup_location'] ) : NULL,
                'pickup_service_level' => (isset($_POST['pickup_service_level'])) ? sanitize_text_field( $_POST['pickup_service_level'] ) : NULL,
                'pickup_time_slot' => (isset($_POST['pickup_time_slot'])) ? sanitize_text_field( $_POST['pickup_time_slot'] ) : NULL,
                'push_to_ninja' => (isset($_POST['push_to_ninja'])) ? sanitize_text_field( $_POST['push_to_ninja'] ) : NULL,
                'push_when' => (isset($_POST['push_when'])) ? sanitize_text_field( $_POST['push_when'] ) : NULL,
                'awb_printer_type' => (isset($_POST['awb_printer_type'])) ? sanitize_text_field( $_POST['awb_printer_type'] ) : NULL,
                'awb_seller_information' => (isset($_POST['awb_seller_information'])) ? sanitize_text_field( $_POST['awb_seller_information'] ) : NULL,
                'awb_order_note' => (isset($_POST['awb_order_note'])) ? sanitize_text_field( $_POST['awb_order_note'] ) : NULL,
                'awb_order_item' => (isset($_POST['awb_order_item'])) ? sanitize_text_field( $_POST['awb_order_item'] ) : NULL,
                'awb_order_item_sku' => (isset($_POST['awb_order_item_sku'])) ? sanitize_text_field( $_POST['awb_order_item_sku'] ) : NULL,
                'awb_phone_number' => (isset($_POST['awb_phone_number'])) ? sanitize_text_field( $_POST['awb_phone_number'] ) : NULL,
                'awb_channel_url' => (isset($_POST['awb_channel_url'])) ? esc_url_raw( $_POST['awb_channel_url'] ) : NULL,
                'awb_seller_logo' => (isset($_POST['awb_seller_logo'])) ? esc_url_raw( $_POST['awb_seller_logo'] ) : NULL,
                'awb_order_text_limit' => (isset($_POST['awb_order_text_limit'])) ? sanitize_text_field( $_POST['awb_order_text_limit'] ) : NULL,
                'tracking_prefix' => (isset($_POST['tracking_prefix'])) ? sanitize_text_field( $_POST['tracking_prefix'] ) : NULL,
                'tracking_suffix' => (isset($_POST['tracking_suffix'])) ? sanitize_text_field( $_POST['tracking_suffix'] ) : NULL,
                'cash_on_delivery' => (isset($_POST['cash_on_delivery'])) ? sanitize_text_field( $_POST['cash_on_delivery'] ) : NULL,
                'validate_cod' => (isset($_POST['validate_cod'])) ? sanitize_text_field( $_POST['validate_cod'] ) : NULL,
                'domestic_shipment' => (isset($_POST['domestic_shipment'])) ? sanitize_text_field( $_POST['domestic_shipment'] ) : NULL,
                'international_shipment' => (isset($_POST['international_shipment'])) ? sanitize_text_field( $_POST['international_shipment'] ) : NULL,
                'plugin_type' => (isset($_POST['type'])) ? sanitize_text_field( $_POST['type'] ) : NULL,
                'plugin_auth_mode' => (isset($_POST['auth_mode'])) ? sanitize_text_field( $_POST['auth_mode'] ) : NULL,
                'plugin_status' => (isset($_POST['status'])) ? sanitize_text_field( $_POST['status'] ) : NULL,
                'plugin_client_id' => (isset($_POST['client_id'])) ? sanitize_text_field( $_POST['client_id'] ) : NULL,
                'plugin_client_secret' => (isset($_POST['client_secret'])) ? sanitize_text_field( $_POST['client_secret'] ) : NULL,
                'exchange_rates' => []
            ];

            $supported_countries = nv_my_supported_countries();
            foreach ($supported_countries as $country_code => $country_name) {
                $country_code = strtolower($country_code);
                $exchange_rate = (isset($_POST['exchange_rate_' . $country_code])) ? number_format( $_POST['exchange_rate_' . $country_code], 2 ) : 0;
                $exchange_rate = ($exchange_rate <= 0) ? 0.01 : $exchange_rate;
                $settings['exchange_rates'][strtoupper($country_code)] = $exchange_rate;
            }

            $addresses = [
                'sender_name' => (isset($_POST['sender_name'])) ? sanitize_text_field( $_POST['sender_name'] ) : NULL,
                'sender_phone' => (isset($_POST['sender_phone'])) ? sanitize_text_field( $_POST['sender_phone'] ) : NULL,
                'sender_email' => (isset($_POST['sender_email'])) ? sanitize_text_field( $_POST['sender_email'] ) : NULL,
                'sender_address1' => (isset($_POST['sender_address1'])) ? sanitize_text_field( $_POST['sender_address1'] ) : NULL,
                'sender_address2' => (isset($_POST['sender_address2'])) ? sanitize_text_field( $_POST['sender_address2'] ) : NULL,
                'sender_city' => (isset($_POST['sender_city'])) ? sanitize_text_field( $_POST['sender_city'] ) : NULL,
                'sender_state' => (isset($_POST['sender_state'])) ? sanitize_text_field( $_POST['sender_state'] ) : NULL,
                'sender_postcode' => (isset($_POST['sender_postcode'])) ? sanitize_text_field( $_POST['sender_postcode'] ) : NULL,
                'pickup_name' => (isset($_POST['pickup_name'])) ? sanitize_text_field( $_POST['pickup_name'] ) : NULL,
                'pickup_phone' => (isset($_POST['pickup_phone'])) ? sanitize_text_field( $_POST['pickup_phone'] ) : NULL,
                'pickup_email' => (isset($_POST['pickup_email'])) ? sanitize_text_field( $_POST['pickup_email'] ) : NULL,
                'pickup_address1' => (isset($_POST['pickup_address1'])) ? sanitize_text_field( $_POST['pickup_address1'] ) : NULL,
                'pickup_address2' => (isset($_POST['pickup_address2'])) ? sanitize_text_field( $_POST['pickup_address2'] ) : NULL,
                'pickup_city' => (isset($_POST['pickup_city'])) ? sanitize_text_field( $_POST['pickup_city'] ) : NULL,
                'pickup_state' => (isset($_POST['pickup_state'])) ? sanitize_text_field( $_POST['pickup_state'] ) : NULL,
                'pickup_postcode' => (isset($_POST['pickup_postcode'])) ? sanitize_text_field( $_POST['pickup_postcode'] ) : NULL,
            ];

            self::nv_settings_save($settings);
            self::nv_address_save($addresses);

            wp_redirect( admin_url( 'admin.php?page=ninja-van-my&save=true'));
        }

        /**
         * Save Settings
         * Sanitization done on the input level
         * @todo If there are error, redirect with error code. `admin.php?page=ninja-van-my-settings&save={error_code}` with exit()
         */
        private static function nv_settings_save($form): void{
            $settings = array(
                'service_type' => 'parcel',
                'service_level' => 'standard',
                'service_code' => '',
                'pickup_required' => true,
                'pickup_service_type' => 'scheduled',
                'pickup_service_level' => 'standard',
                'pickup_time_slot' => '12:00|15:00',
                'push_to_ninja' => 'manual',
                'push_when' => 'processing',
                'awb_printer_type' => 2,
                'awb_seller_information' => true,
                'awb_order_note' => true,
                'awb_order_item' => true,
                'awb_order_item_sku' => true,
                'awb_phone_number' => true,
                'awb_channel_url' => true,
                'awb_seller_logo' => '',
                'awb_order_text_limit' => 160,
                'tracking_prefix' => nv_my_random_string(3, 'upper'),
                'tracking_suffix' => '',
                'exchange_rates' => array(),
                'cash_on_delivery' => true,
                'validate_cod' => false,
                'domestic_shipment' => true,
                'international_shipment' => true,
                'pickup_location' => 0
            );

            extract($form);

            /**
             * @deprecated hidden by default
             */
            $settings['service_type'] = sanitize_text_field( 'parcel' );

            if (isset($service_level)) {
                $settings['service_level'] = $service_level;
            }

            /**
             * @since 0.1.1
             */
            if (isset($service_code)) {
                $settings['service_code'] = $service_code;
            }

            $settings['pickup_required'] = (isset($pickup_required)) ? true : false;

            /**
             * @since 0.1.1
             */
            if (isset($pickup_location) && $pickup_location == 1) {
                $settings['pickup_location'] = 1; // Pickup at pickup location
            } else {
                $settings['pickup_location'] = 0;
            }

            /**
             * @deprecated hidden by default
             */
            $settings['pickup_service_type'] = sanitize_text_field( 'scheduled' );


            if (isset($pickup_service_level)) {
                $settings['pickup_service_level'] = $pickup_service_level;
            }

            if (isset($pickup_time_slot)) {
                $settings['pickup_time_slot'] = $pickup_time_slot;
            }
            
            /**
             * @changed `select` to `checkbox`: push_to_ninja
             */
            $settings['push_to_ninja'] = (isset($push_to_ninja)) ? 'automatic' : 'manual';

            if (isset($push_when)) {
                $settings['push_when'] = $push_when;
            }

            if (isset($awb_printer_type) && $awb_printer_type == 1) {
                $settings['awb_printer_type'] = 1;
            }else{
                $settings['awb_printer_type'] = 2;
            }

            /**
             * @changed `select` to `checkbox`: awb_seller_information, awb_order_note, awb_order_item, awb_order_item_sku, awb_phone_number
             */
            $settings['awb_seller_information'] = (isset($awb_seller_information)) ? true : false;
            $settings['awb_order_note'] = (isset($awb_order_note)) ? true : false;
            $settings['awb_order_item'] = (isset($awb_order_item)) ? true : false;
            $settings['awb_order_item_sku'] = (isset($awb_order_item_sku)) ? true : false;
            $settings['awb_channel_url'] = (isset($awb_channel_url)) ? true : false;
            $settings['awb_phone_number'] = (isset($awb_phone_number)) ? true : false;

            /**
             * @since 0.1.1
             */
            if (isset($awb_seller_logo)) {
                // Check if $awb_seller_logo is a valid URL
                if (filter_var($awb_seller_logo, FILTER_VALIDATE_URL)) {
                    // Get the upload directory
                    $upload_dir = wp_get_upload_dir();
            
                    // Get the path of the image relative to the upload URL
                    $relative_path = str_replace($upload_dir['baseurl'], '', $awb_seller_logo);
            
                    // Save path instead of URL
                    $settings['awb_seller_logo'] = sanitize_text_field($relative_path);
                } else {
                    nv_my_log('Invalid URL for Seller Logo: ' . $awb_seller_logo, 'error');
                }
            }

            $settings['awb_order_text_limit'] = isset($awb_order_text_limit) ? (int) $awb_order_text_limit : 250;
            
            if (isset($exchange_rates)) {
                $settings['exchange_rates'] = $exchange_rates;
            }

            if (isset($tracking_prefix)) {
                // Limit to 3 characters
                $settings['tracking_prefix'] =  mb_substr(nv_my_pure_text($tracking_prefix ), 0, 3);
            }

            /**
             * @deprecated empty by default
             */
            $settings['tracking_suffix'] = nv_my_pure_text(sanitize_text_field( '' ));

            /**
             * @since 0.1.1
             */
            $settings['cash_on_delivery'] = (isset($cash_on_delivery)) ? true : false;
            $settings['domestic_shipment'] = (isset($domestic_shipment)) ? true : false;
            $settings['international_shipment'] = (isset($international_shipment)) ? true : false;

            /**
             * @since 1.1.3
             */
            $settings['validate_cod'] = (isset($validate_cod)) ? true : false;
            

            update_option( 'Ninja_Van_WooCommerce_SETTINGS', serialize($settings));

            /**
             * Plugin Settings
             */
            $type = isset($plugin_type) && $plugin_type == 1 ? true : false; 
            $auth_mode = isset($plugin_auth_mode) ? 'PLUGIN' : 'DIRECT';
            $status = isset($plugin_status) ? true : false;
            $client_id = isset($plugin_client_id) ? $plugin_client_id : '';
            $client_secret = isset($plugin_client_secret) ? $plugin_client_secret : '';

            if ($type === true) {
                update_option( 'Ninja_Van_WooCommerce_TYPE', true);
            } else {
                update_option( 'Ninja_Van_WooCommerce_TYPE', false);
            }


            if ($status == true && !empty($client_id) && !empty($client_secret)) {
                if (get_option('Ninja_Van_WooCommerce_CLIENT_ID') != $client_id || get_option('Ninja_Van_WooCommerce_CLIENT_SECRET') != $client_secret) {
                    delete_transient('ninja_van_token');
                    delete_transient('ninja_van_refresh_token');
                    delete_transient('ninja_van_token_expiry');
                    delete_transient('ninja_van_token_error');
                    delete_transient('ninja_van_refresh_token_error');
                }

                update_option( 'Ninja_Van_WooCommerce_STATUS', true);
            } else if ($status == true && $auth_mode == 'PLUGIN' && nv_my_get_config('access_code') != '') {
                update_option( 'Ninja_Van_WooCommerce_STATUS', true);
            } else {
                update_option( 'Ninja_Van_WooCommerce_STATUS', false);
            }

            /**
             * If auth mode is changed, reset credentials
             */
            $reset_credentials = false;
            if ($auth_mode != nv_my_get_config('auth_mode')) {
                nv_my_logout(!(nv_my_get_config('auth_mode') == 'PLUGIN' && !empty(nv_my_get_config('access_code'))));
                $reset_credentials = true;
            }

            update_option( 'Ninja_Van_WooCommerce_AUTH_MODE', $auth_mode);

            if ($reset_credentials) {
                $client_id = '';
                $client_secret = '';
            }

            update_option( 'Ninja_Van_WooCommerce_CLIENT_ID', $client_id);
            update_option( 'Ninja_Van_WooCommerce_CLIENT_SECRET', $client_secret);

            
        }

        /**
         * Save Addresses
         * @todo If there are error, redirect with error code. `admin.php?page=ninja-van-my-settings&save={error_code}` with exit()
         */
        private static function nv_address_save($form): void{
            $get_address_template = function(): array {
                return [
                    'name' => '',
                    'phone_number' => '',
                    'email' => '',
                    'address' => [
                        'address1' => '',
                        'address2' => '',
                        'area' => '',
                        'city' => '',
                        'state' => '',
                        'country' => 'MY',
                        'postcode' => '',
                        'address_type' => 'Office',
                    ]
                ];
            };

            $address = $get_address_template();

            extract($form);
            
            // Use the same address for sender if sender address is empty
            if (isset($sender_name)) {
                if (empty($sender_name) && isset($pickup_name)) {
                    $sender_name = $pickup_name;
                }
                $address['name'] = $sender_name;
            }

            if (isset($sender_phone)) {
                if (empty($sender_phone) && isset($pickup_phone)) {
                    $sender_phone = $pickup_phone;
                }
                $address['phone_number'] = $sender_phone;
            }

            if (isset($sender_email)) {
                if (empty($sender_email) && isset($pickup_email)) {
                    $sender_email = $pickup_email;
                }
                $address['email'] = $sender_email;
            }

            if (isset($sender_address1)) {
                if (empty($sender_address1) && isset($pickup_address1)) {
                    $sender_address1 = $pickup_address1;
                }
                $address['address']['address1'] = $sender_address1;
            }

            if (isset($sender_address2)) {
                if (empty($sender_address2) && isset($pickup_address2)) {
                    $sender_address2 = $pickup_address2;
                }
                $address['address']['address2'] = $sender_address2;
            }

            if (isset($sender_city)) {
                if (empty($sender_city) && isset($pickup_city)) {
                    $sender_city = $pickup_city;
                }
                $address['address']['city'] = $sender_city;
                $address['address']['area'] = $sender_city;
            }

            if (isset($sender_postcode)) {
                if (empty($sender_postcode) && isset($pickup_postcode)) {
                    $sender_postcode = $pickup_postcode;
                }
                $address['address']['postcode'] = $sender_postcode;
            }
            if (isset($sender_state)) {
                if (empty($sender_state) && isset($pickup_state)) {
                    $sender_state = $pickup_state;
                }
                $address['address']['state'] = $sender_state;
            }

            update_option( 'Ninja_Van_WooCommerce_ADDRESS_SENDER', serialize($address));

            // Reset $address
            $address = $get_address_template();

            // Use the same address for pickup if pickup address is empty
            if (isset($pickup_name)) {
                if (empty($pickup_name) && isset($sender_name)) {
                    $pickup_name = $sender_name;
                }
                $address['name'] = $pickup_name;
            }

            if (isset($pickup_phone)) {
                if (empty($pickup_phone) && isset($sender_phone)) {
                    $pickup_phone = $sender_phone;
                }
                $address['phone_number'] = $pickup_phone;
            }

            if (isset($pickup_email)) {
                if (empty($pickup_email) && isset($sender_email)) {
                    $pickup_email = $sender_email;
                }
                $address['email'] = $pickup_email;
            }

            if (isset($pickup_address1)) {
                if (empty($pickup_address1) && isset($sender_address1)) {
                    $pickup_address1 = $sender_address1;
                }
                $address['address']['address1'] = $pickup_address1;
            }

            if (isset($pickup_address2)) {
                if (empty($pickup_address2) && isset($sender_address2)) {
                    $pickup_address2 = $sender_address2;
                }
                $address['address']['address2'] = $pickup_address2;
            }

            if (isset($pickup_city)) {
                if (empty($pickup_city) && isset($sender_city)) {
                    $pickup_city = $sender_city;
                }
                $address['address']['city'] = $pickup_city;
                $address['address']['area'] = $pickup_city;
            }

            if (isset($pickup_postcode)) {
                if (empty($pickup_postcode) && isset($sender_postcode)) {
                    $pickup_postcode = $sender_postcode;
                }
                $address['address']['postcode'] = $pickup_postcode;
            }
            if (isset($pickup_state)) {
                if (empty($pickup_state) && isset($sender_state)) {
                    $pickup_state = $sender_state;
                }
                $address['address']['state'] = $pickup_state;
            }

            update_option( 'Ninja_Van_WooCommerce_ADDRESS_PICKUP', serialize($address));


        }

        /**
         * Handle Download AWB Requests
         *
         * @return void
         */
        static public function nv_download_awb(): void{
            // Check if our nonce is set and valid.
            if (!isset($_REQUEST['_wpnonce']) || !(wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-posts') || wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'nv-order-awb') || wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'nv-settings-awb'))) {
                return;
            }
            if (is_user_logged_in() && is_admin()) {
                $download_awb = sanitize_text_field($_REQUEST['download_awb']);
                if (isset($_REQUEST['download_awb']) && is_numeric($download_awb)) {
                    nv_my_get_awb([$download_awb]);
                }
            }
        }
    }
    
?>