<?php
    use Ninja\Van\MY\Worker\API;
    use Ninja\Van\MY\Worker\AccessToken;
    use Ninja\Van\MY\Worker\Webhook;
    use Ninja\Van\MY\Worker\CreateBooking;
    use Ninja\Van\MY\Worker\CancelBooking;
    use Ninja\Van\MY\Worker\GenerateAWB;
    use Ninja\Van\MY\Library\TCPDFBarcode as Barcode;
    use Ninja\Van\MY\Library\TCPDF2DBarcode as QRCode;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    function nv_my_info() {
        if (!Ninja_Van_MY_DEVELOPMENT) {
            return [];
        }

        $settings = nv_my_get_settings();
        $configs = [
            'status' => nv_my_get_config('status'),
            'type' => nv_my_get_config('type'),
            'auth_mode' => nv_my_get_config('auth_mode'),
            'client_id' => nv_my_get_config('client_id'),
            'client_secret' => nv_my_get_config('client_secret'),
            'access_code' => nv_my_get_config('access_code'),
            'auth_state' => nv_my_get_config('auth_state'),
            'access_token' => get_transient('ninja_van_token'),
            'refresh_token' => get_transient('ninja_van_refresh_token'),
            'expires_in' => get_transient('ninja_van_token_expiry') ? nv_my_duration(get_transient('ninja_van_token_expiry') - time()) : false,
            'next_refresh' => get_transient('ninja_van_token_expiry') ? gmdate('Y-m-d H:i:s', get_transient('ninja_van_token_expiry')) : false,
            'webhook_events' => nv_my_get_webhook_events()
        ];
        $systems = [
            'webhook_security' => nv_my_get_webhook_security(),
            
        ];
        $crons = [
            'ninja_van_refresh_token' => [
                'status' => wp_next_scheduled( 'ninja_van_refresh_token' ) ? true : false,
                'next_run' => wp_next_scheduled( 'ninja_van_refresh_token' ) ? nv_my_gmdate(wp_next_scheduled( 'ninja_van_refresh_token' )) : false,
                'has_action' => has_action( 'ninja_van_refresh_token', 'nv_my_cron' ) ? true : false
            ],
        ];
        $response = [
            'status' => true,
            'message' => "Callback Received!",
            'data' => [
                'system' => $systems,
                'settings' => $settings,
                'config' => $configs,
                'cron' => $crons,
                'tables' => [
                    'cod' => nv_my_check_table(Ninja_Van_MY_DB_NAME),
                    'version' => get_option('Ninja_Van_WooCommerce_Db_Version')
                ],
            ]
        ];

        return $response;
    }

    function nv_my_cron(){
        $access_token = new AccessToken();
        $access_token->cron_get_access_token();
    }

    function nv_my_pure_digit($text = ''){
        return preg_replace('/\D/', '', $text);
    }

    function nv_my_pure_text($text = ''){
        return preg_replace("~[^a-z]+~i", '', $text);
    }

    function nv_my_hide_text($string = ''){
        return wp_trim_words(preg_replace("/[^\s]+/", "*********", $string), 15);
    }

    function nv_my_duration($time = 0){
        $duration = '';
        $seconds = $time % 60;
        $minutes = floor(($time % 3600) / 60);
        $hours = floor(($time % 86400) / 3600);
        $days = floor($time / 86400);

        if ($days > 0) {
            $duration .= $days . ' day(s) ';
        }
        if ($hours > 0) {
            $duration .= $hours . ' hour(s) ';
        }
        if ($minutes > 0) {
            $duration .= $minutes . ' minute(s) ';
        }
        if ($seconds > 0) {
            $duration .= $seconds . ' second(s)';
        }

        return trim($duration);
    }

    function nv_my_gmdate($timestamp = 0, $preset = 'default', $timezone = false){
        $formats = [
            'default' => 'Y-m-d H:i:s',
            'date' => 'Y-m-d',
            'time' => 'H:i:s',
            'short' => 'd M Y',
            'long' => 'j M Y - h:i:s A',
            'validity' => 'h:i:s A - j M Y'
        ];

        if (!isset($formats[$preset])) {
            $preset = 'default';
        }

        $format = $formats[$preset];

        $date = new DateTime("@$timestamp");
        $date->setTimezone(new DateTimeZone(wp_timezone_string()));
        return $date->format($format) . ($timezone ? ' ' . wp_timezone_string() : '');
    }

    function nv_my_get_file($path = '', $url = false){
        if ($url) {
            return Ninja_Van_MY_URL . $path;
        }else{
            return Ninja_Van_MY_PATH . $path;
        }
    }

    function nv_my_get_media($path = '', $url = false){
        if ($url) {
            return wp_get_upload_dir()['baseurl'] . $path;
        }else{
            return wp_get_upload_dir()['basedir'] . $path;
        }
    }

    function nv_my_get_image($path = '', $base64 = false) {
        // Return the image path
        if (!$base64) {
            return $path;
        }

        $image = 'data:image/{extension};base64,{image}';
        $transparent = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII';
        // Only process if the file exists
        if(file_exists($path)) {
            // Get the file extension
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            // Check if the extension is an image or svg
            if(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                // Read image path, convert to base64 encoding
                $data = base64_encode(file_get_contents($path));

                if ($extension == 'svg') {
                    $extension = 'svg+xml';
                }

                // Format the image SRC:  data:{mime};base64,{data};
                return str_replace(
                    ['{extension}', '{image}'],
                    [$extension, $data],
                    $image
                );
            } else {
                return str_replace(
                    ['{extension}', '{image}'],
                    ['png', $transparent],
                    $image
                );
            }
        } else {
            return str_replace(
                ['{extension}', '{image}'],
                ['png', $transparent],
                $image
            );
        }
    }

    function nv_my_random_string($len = 10, $case = 'generic') {
        // Generate a random string of letters
        $cases = array(
            'upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'lower' => 'abcdefghijklmnopqrstuvwxyz',
            'number' => '0123456789',
            'symbol' => '!@#$%^&*(){}[]?<>'
        );
        if ($case == 'alpha') {
            $seed = str_split($cases['upper'].$cases['lower']);
        } else if ($case == 'alphanumeric') {
            $seed = str_split($cases['upper'].$cases['lower'].$cases['number']);
        } else if ($case == 'number') {
            $seed = str_split($cases['number']);
        } else if ($case == 'symbol') {
            $seed = str_split($cases['symbol']);
        } else if (in_array($case, array_keys($cases))) {
            $seed = str_split($cases[$case]);
        } else {
            $seed = str_split($cases['upper'].$cases['lower'].$cases['number']); // Default
        }

        shuffle($seed);
        $rand = '';
        foreach (array_rand($seed, $len) as $k) $rand .= $seed[$k];

        return $rand;
    }

    function nv_my_log($message = '') {
        if (empty($message)) return;

        $message = 'NV Notice: ' . $message;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }

        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'ninja-van-my'));
        }
    }

    function nv_my_get_timezone() {
        $timezone = get_option('timezone_string');
        if (empty($timezone)) {
            $timezone = 'Asia/Kuala_Lumpur';
        }
        return $timezone;
    }

    function nv_my_rank_levenshtein($word, $words) {
        if (!is_array($words)) {
            $words = array($words);
        }

        if (empty($word)) {
            return $words;
        }

        $ranked = array();

        usort($words, function($a, $b) use ($word, &$ranked) {
            $levA = levenshtein(strtolower($word), strtolower($a));
            $levB = levenshtein(strtolower($word), strtolower($b));
            $ranked[$a] = $levA;
            $ranked[$b] = $levB;
            return $levA <=> $levB;
        });

        return $ranked;
    }

    function nv_my_get_webhook_security(){
        $security = get_option( 'Ninja_Van_WooCommerce_webhook_security');

        if(!empty($security)){
            return $security;
        }
        
        $security = wp_generate_uuid4();
        
        update_option( 'Ninja_Van_WooCommerce_webhook_security', $security);

        return $security;
    }

    function nv_my_get_webhook_url(){
        return get_site_url().'/ninjavan-webhook?security='.nv_my_get_webhook_security();
    }

    /**
     * @since 0.1.1 - Added Plugin API & backward compatibility
     */
    function nv_my_get_config($key = false){
        if (!$key) {
            return '';
        } else if ($key == 'status') {
            return get_option( 'Ninja_Van_WooCommerce_STATUS', 0 );
        } else if ($key == 'type') {
            return get_option( 'Ninja_Van_WooCommerce_TYPE', 1 );
        } else if ($key == 'client_id') {
            if (nv_my_get_config('auth_mode') == 'PLUGIN') {
                return Ninja_Van_MY_PLUGIN_AUTH[nv_my_get_config('type')]['client_id'];
            }
            return get_option( 'Ninja_Van_WooCommerce_CLIENT_ID', '' );
        } else if ($key == 'client_secret') {
            if (nv_my_get_config('auth_mode') == 'PLUGIN') {
                return Ninja_Van_MY_PLUGIN_AUTH[nv_my_get_config('type')]['client_secret'];
            }
            return get_option( 'Ninja_Van_WooCommerce_CLIENT_SECRET', '' );
        } else if ($key == 'auth_mode') {
            return get_option( 'Ninja_Van_WooCommerce_AUTH_MODE', Ninja_Van_MY_OAUTH );
        } else if ($key == 'auth_state') {
            return get_option( 'Ninja_Van_WooCommerce_AUTH_STATE', 0 );
        } else if ($key == 'access_code') {
            return get_option( 'Ninja_Van_WooCommerce_ACCESS_CODE', '' );
        } else {
            return '';
        }
    }

    function nv_my_get_settings($key = false){
        $default = array(
            'version' => Ninja_Van_MY::version,
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

        $shipping_countries = nv_my_supported_countries();
        foreach ($shipping_countries as $code => $name) {
            $default['exchange_rates'][$code] = "1.00";
        }

        $settings = unserialize(get_option( 'Ninja_Van_WooCommerce_SETTINGS', serialize(array())));

        $response = (object) array_merge($default, $settings);

        /**
         * @since 1.1.0 - Removed exchange_rate_sg
         */
        if (isset($response->exchange_rate_sg)) {
            $response->exchange_rates['SG'] = $response->exchange_rate_sg;
            unset($response->exchange_rate_sg);
        }

        if (!$key) {
            return $response;
        }elseif ($key == 'version') {
            return $response->version;
        }elseif ($key == 'service_type') {
            return $response->service_type;
        }elseif ($key == 'service_level') {
            return $response->service_level;
        }elseif ($key == 'service_code') {
            return $response->service_code;
        }elseif ($key == 'pickup_required') {
            return $response->pickup_required;
        }elseif ($key == 'pickup_service_type') {
            return $response->pickup_service_type;
        }elseif ($key == 'pickup_service_level') {
            return $response->pickup_service_level;
        }elseif ($key == 'pickup_time_slot') {
            return $response->pickup_time_slot;
        }elseif ($key == 'push_to_ninja') {
            return $response->push_to_ninja;
        }elseif ($key == 'push_when') {
            return $response->push_when;
        }elseif ($key == 'awb_printer_type') {
            return $response->awb_printer_type;
        }elseif ($key == 'awb_seller_information') {
            return $response->awb_seller_information;
        }elseif ($key == 'awb_order_note') {
            return $response->awb_order_note;
        }elseif ($key == 'awb_order_item') {
            return $response->awb_order_item;
        }elseif ($key == 'awb_order_item_sku') {
            return $response->awb_order_item_sku;
        }elseif ($key == 'awb_phone_number') {
            return $response->awb_phone_number;
        }elseif ($key == 'awb_channel_url') {
            return $response->awb_channel_url;
        }elseif ($key == 'awb_seller_logo') {
            return $response->awb_seller_logo;
        }elseif ($key == 'awb_order_text_limit') {
            return $response->awb_order_text_limit;
        }elseif ($key == 'tracking_prefix') {
            return mb_substr(nv_my_pure_text($response->tracking_prefix), 0, 3);
        }elseif ($key == 'tracking_suffix') {
            return mb_substr(nv_my_pure_text($response->tracking_suffix), 0, 1);
        }elseif ($key == 'exchange_rates') {
            return $response->exchange_rates;
        }elseif ($key == 'cash_on_delivery') {
            return $response->cash_on_delivery;
        }elseif ($key == 'validate_cod') {
            return $response->validate_cod;
        }elseif ($key == 'domestic_shipment') {
            return $response->domestic_shipment;
        }elseif ($key == 'international_shipment') {
            return $response->international_shipment;
        }elseif ($key == 'pickup_location') {
            return $response->pickup_location;
        }else{
            return '';
        }
    }
    
    function nv_my_validate_address($country = 'MY', $address = array()){

        $requirements = ['first_name'];
        if (empty($country) || empty($address) || !is_object($address)){
            return ['Country', 'Address 1', 'City', 'Postcode', 'State'];
        }

        $missing = [];
        switch ($country) {
            case 'MY':
                $requirements = array_merge($requirements, ['address_1', 'city', 'postcode', 'state']);
                break;
            case 'SG':
                $requirements = array_merge($requirements, ['address_1', 'postcode']);
                break;
            case 'ID':
                $requirements = array_merge($requirements, ['address_1']);
                break;
            case 'TH':
                $requirements = array_merge($requirements, ['address_1', 'postcode']);
                break;
            case 'VN':
                $requirements = array_merge($requirements, ['address_1']);
                break;
            case 'PH':
                $requirements = array_merge($requirements, ['address_1']);
                break;
            case 'MM':
                $requirements = array_merge($requirements, ['address_1', 'postcode']);
                break;
            default:
                break;
        }

        foreach ($requirements as $key) {
            if (!isset($address->$key) || empty($address->$key)){
                $missing[] = ucwords(str_replace('_', ' ', $key));
                break;
            }
        }

        return $missing;
    }

    function nv_my_get_exchange_rates($country = 'MY'){
        $exchange_rates = nv_my_get_settings('exchange_rates');

        if (isset($exchange_rates[strtolower($country)])) {
            return $exchange_rates[strtolower($country)];
        }

        return 1;
    }

    function nv_my_get_shipping_cost($weight){
        $price = nv_my_get_settings('shipping_charge_first');

        $next_kg = nv_my_get_settings('shipping_charge_after_first');

        if ($weight > 1) {
            $price += (($weight - 1) * $next_kg);
        }

        return (double) $price;
    }

    function nv_my_get_default_address(){
        if (nv_my_get_settings('pickup_location') == 1) {
            return nv_my_pickup_address(true);
        }else{
            return nv_my_sender_address(true);
        }
    }

    function nv_my_sender_address($full_state = false){

        $address = [];
        $default = [
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

        if (get_option( 'Ninja_Van_WooCommerce_ADDRESS_SENDER')) {
            $address = unserialize(get_option( 'Ninja_Van_WooCommerce_ADDRESS_SENDER'));
            // return $address['address']['state'];
            if ($full_state) {
                $address['address']['state'] = nv_my_get_states($address['address']['state']);
            }
        }

        return array_merge($default, $address);
    }

    function nv_my_pickup_address($full_state = false){

        $address = [];
        $default = [
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

        if (get_option( 'Ninja_Van_WooCommerce_ADDRESS_PICKUP')) {
            $address = unserialize(get_option( 'Ninja_Van_WooCommerce_ADDRESS_PICKUP'));
            if ($full_state) {
                $address['address']['state'] = nv_my_get_states($address['address']['state']);
            }
        }

        return array_merge($default, $address);
    }

    function nv_my_amount_with_currency($amount = 0, $country = 'MY'){
        $currency = nv_my_currency($country, true);

        return $currency .' '. number_format($amount, 2);
    }

    function nv_my_currency($country_code, $sign = false){
        if (!isset($country_code) || empty($country_code)) {
            $country_code = nv_my_woocommerce_default_country();           
        }

        $currencies_by_country =  [
            "BD" => array(
                "sign" => "৳",
                "code" => "BDT",
            ), 
            "BE" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "BF" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "BG" => array(
                "sign" => "лв",
                "code" => "BGN",
            ), 
            "BA" => array(
                "sign" => "KM",
                "code" => "BAM",
            ), 
            "BB" => array(
                "sign" => "$",
                "code" => "BBD",
            ), 
            "WF" => array(
                "sign" => "₣",
                "code" => "XPF",
            ), 
            "BL" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "BM" => array(
                "sign" => "$",
                "code" => "BMD",
            ), 
            "BN" => array(
                "sign" => "$",
                "code" => "BND",
            ), 
            "BO" => array(
                "sign" => "Bs.",
                "code" => "BOB",
            ), 
            "BH" => array(
                "sign" => ".د.ب",
                "code" => "BHD",
            ), 
            "BI" => array(
                "sign" => "FBu",
                "code" => "BIF",
            ), 
            "BJ" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "BT" => array(
                "sign" => "Nu.",
                "code" => "BTN",
            ), 
            "JM" => array(
                "sign" => "J$",
                "code" => "JMD",
            ), 
            "BV" => array(
                "sign" => "kr",
                "code" => "NOK",
            ), 
            "BW" => array(
                "sign" => "P",
                "code" => "BWP",
            ), 
            "WS" => array(
                "sign" => "WS$",
                "code" => "WST",
            ), 
            "BQ" => array(
                "sign" => "$",
                "code" => "USD",
            ), 
            "BR" => array(
                "sign" => "R$",
                "code" => "BRL",
            ), 
            "BS" => array(
                "sign" => "$",
                "code" => "BSD",
            ), 
            "JE" => array(
                "sign" => "£",
                "code" => "GBP",
            ), 
            "BY" => array(
                "sign" => "Br",
                "code" => "BYR",
            ), 
            "BZ" => array(
                "sign" => "BZ$",
                "code" => "BZD",
            ), 
            "RU" => array(
                "sign" => "₽",
                "code" => "RUB",
            ), 
            "RW" => array(
                "sign" => "FRw",
                "code" => "RWF",
            ), 
            "RS" => array(
                "sign" => "din.",
                "code" => "RSD",
            ), 
            "TL" => array(
                "sign" => "$",
                "code" => "USD",
            ), 
            "RE" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "TM" => array(
                "sign" => "m",
                "code" => "TMT",
            ), 
            "TJ" => array(
                "sign" => "ЅМ",
                "code" => "TJS",
            ), 
            "RO" => array(
                "sign" => "lei",
                "code" => "RON",
            ), 
            "TK" => array(
                "sign" => "$",
                "code" => "NZD",
            ), 
            "GW" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "GU" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "GT" => array(
                "sign" => "Q",
                "code" => "GTQ",
            ), 
            "GS" => array(
                "sign" => "£",
                "code" => "GBP",
            ),
            "GR" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "GQ" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "GP" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "JP" => array(
                "sign" => "¥",
                "code" => "JPY",
            ), 
            "GY" => array(
                "sign" => "$",
                "code" => "GYD",
            ), 
            "GG" => array(
                "sign" => "£",
                "code" => "GBP",
            ),
            "GF" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "GE" => array(
                "sign" => "₾",
                "code" => "GEL",
            ), 
            "GD" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "GB" => array(
                "sign" => "£",
                "code" => "GBP",
            ),
            "GA" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "SV" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "GN" => array(
                "sign" => "FG",
                "code" => "GNF",
            ), 
            "GM" => array(
                "sign" => "D",
                "code" => "GMD",
            ), 
            "GL" => array(
                "sign" => "kr",
                "code" => "DKK",
            ), 
            "GI" => array(
                "sign" => "£",
                "code" => "GIP",
            ), 
            "GH" => array(
                "sign" => "GH₵",
                "code" => "GHS",
            ), 
            "OM" => array(
                "sign" => "ر.ع.",
                "code" => "OMR",
            ), 
            "TN" => array(
                "sign" => "د.ت",
                "code" => "TND",
            ), 
            "JO" => array(
                "sign" => "د.ا",
                "code" => "JOD",
            ), 
            "HR" => array(
                "sign" => "kn",
                "code" => "HRK",
            ), 
            "HT" => array(
                "sign" => "G",
                "code" => "HTG",
            ), 
            "HU" => array(
                "sign" => "Ft",
                "code" => "HUF",
            ), 
            "HK" => array(
                "sign" => "$",
                "code" => "HKD",
            ), 
            "HN" => array(
                "sign" => "L",
                "code" => "HNL",
            ), 
            "HM" => array(
                "sign" => "$",
                "code" => "AUD",
            ),
            "VE" => array(
                "sign" => "Bs",
                "code" => "VEF",
            ), 
            "PR" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "PS" => array(
                "sign" => "₪",
                "code" => "ILS",
            ), 
            "PW" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "PT" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "SJ" => array(
                "sign" => "kr",
                "code" => "NOK",
            ), 
            "PY" => array(
                "sign" => "₲",
                "code" => "PYG",
            ), 
            "IQ" => array(
                "sign" => "ع.د",
                "code" => "IQD",
            ), 
            "PA" => array(
                "sign" => "B/.",
                "code" => "PAB",
            ), 
            "PF" => array(
                "sign" => "₣",
                "code" => "XPF",
            ), 
            "PG" => array(
                "sign" => "K",
                "code" => "PGK",
            ), 
            "PE" => array(
                "sign" => "S/.",
                "code" => "PEN",
            ), 
            "PK" => array(
                "sign" => "Rs",
                "code" => "PKR",
            ), 
            "PH" => array(
                "sign" => "₱",
                "code" => "PHP",
            ), 
            "PN" => array(
                "sign" => "$",
                "code" => "NZD",
            ), 
            "PL" => array(
                "sign" => "zł",
                "code" => "PLN",
            ), 
            "PM" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "ZM" => array(
                "sign" => "ZK",
                "code" => "ZMK",
            ), 
            "EH" => array(
                "sign" => "د.م.",
                "code" => "MAD",
            ), 
            "EE" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "EG" => array(
                "sign" => "E£",
                "code" => "EGP",
            ), 
            "ZA" => array(
                "sign" => "R",
                "code" => "ZAR",
            ), 
            "EC" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "IT" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "VN" => array(
                "sign" => "₫",
                "code" => "VND",
            ), 
            "SB" => array(
                "sign" => "SI$",
                "code" => "SBD",
            ), 
            "ET" => array(
                "sign" => "Br",
                "code" => "ETB",
            ), 
            "SO" => array(
                "sign" => "S",
                "code" => "SOS",
            ), 
            "ZW" => array(
                "sign" => "Z$",
                "code" => "ZWL",
            ), 
            "SA" => array(
                "sign" => "ر.س",
                "code" => "SAR",
            ), 
            "ES" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "ER" => array(
                "sign" => "Nfk",
                "code" => "ERN",
            ), 
            "ME" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "MD" => array(
                "sign" => "L",
                "code" => "MDL",
            ), 
            "MG" => array(
                "sign" => "Ar",
                "code" => "MGA",
            ), 
            "MF" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "MA" => array(
                "sign" => "د.م.",
                "code" => "MAD",
            ), 
            "MC" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "UZ" => array(
                "sign" => "лв",
                "code" => "UZS",
            ), 
            "MM" => array(
                "sign" => "K",
                "code" => "MMK",
            ), 
            "ML" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "MO" => array(
                "sign" => "MOP$",
                "code" => "MOP",
            ), 
            "MN" => array(
                "sign" => "₮",
                "code" => "MNT",
            ), 
            "MH" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "MK" => array(
                "sign" => "ден",
                "code" => "MKD",
            ), 
            "MU" => array(
                "sign" => "₨",
                "code" => "MUR",
            ), 
            "MT" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "MW" => array(
                "sign" => "MK",
                "code" => "MWK",
            ), 
            "MV" => array(
                "sign" => "Rf",
                "code" => "MVR",
            ), 
            "MQ" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "MP" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "MS" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "MR" => array(
                "sign" => "UM",
                "code" => "MRO",
            ), 
            "IM" => array(
                "sign" => "£",
                "code" => "GBP",
            ),
            "UG" => array(
                "sign" => "USh",
                "code" => "UGX",
            ), 
            "TZ" => array(
                "sign" => "TSh",
                "code" => "TZS",
            ), 
            "MY" => array(
                "sign" => "RM",
                "code" => "MYR",
            ), 
            "MX" => array(
                "sign" => "$",
                "code" => "MXN",
            ), 
            "IL" => array(
                "sign" => "₪",
                "code" => "ILS",
            ), 
            "FR" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "IO" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "SH" => array(
                "sign" => "£",
                "code" => "SHP",
            ), 
            "FI" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "FJ" => array(
                "sign" => "$",
                "code" => "FJD",
            ), 
            "FK" => array(
                "sign" => "£",
                "code" => "FKP",
            ), 
            "FM" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "FO" => array(
                "sign" => "kr",
                "code" => "DKK",
            ), 
            "NI" => array(
                "sign" => "C$",
                "code" => "NIO",
            ), 
            "NL" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "NO" => array(
                "sign" => "kr",
                "code" => "NOK",
            ), 
            "NA" => array(
                "sign" => "N$",
                "code" => "NAD",
            ), 
            "VU" => array(
                "sign" => "VT",
                "code" => "VUV",
            ), 
            "NC" => array(
                "sign" => "₣",
                "code" => "XPF",
            ), 
            "NE" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "NF" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "NG" => array(
                "sign" => "₦",
                "code" => "NGN",
            ), 
            "NZ" => array(
                "sign" => "$",
                "code" => "NZD",
            ), 
            "NP" => array(
                "sign" => "₨",
                "code" => "NPR",
            ), 
            "NR" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "NU" => array(
                "sign" => "$",
                "code" => "NZD",
            ), 
            "CK" => array(
                "sign" => "$",
                "code" => "NZD",
            ), 
            "XK" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "CI" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "CH" => array(
                "sign" => "CHF",
                "code" => "CHF",
            ), 
            "CO" => array(
                "sign" => "$",
                "code" => "COP",
            ), 
            "CN" => array(
                "sign" => "¥",
                "code" => "CNY",
            ), 
            "CM" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "CL" => array(
                "sign" => "$",
                "code" => "CLP",
            ), 
            "CC" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "CA" => array(
                "sign" => "$",
                "code" => "CAD",
            ), 
            "CG" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "CF" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "CD" => array(
                "sign" => "FC",
                "code" => "CDF",
            ), 
            "CZ" => array(
                "sign" => "Kč",
                "code" => "CZK",
            ), 
            "CY" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "CX" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "CR" => array(
                "sign" => "₡",
                "code" => "CRC",
            ), 
            "CW" => array(
                "sign" => "ƒ",
                "code" => "ANG",
            ), 
            "CV" => array(
                "sign" => "$",
                "code" => "CVE",
            ), 
            "CU" => array(
                "sign" => "$",
                "code" => "CUP",
            ), 
            "SZ" => array(
                "sign" => "L",
                "code" => "SZL",
            ), 
            "SY" => array(
                "sign" => "£",
                "code" => "SYP",
            ), 
            "SX" => array(
                "sign" => "ƒ",
                "code" => "ANG",
            ), 
            "KG" => array(
                "sign" => "лв",
                "code" => "KGS",
            ), 
            "KE" => array(
                "sign" => "KSh",
                "code" => "KES",
            ), 
            "SS" => array(
                "sign" => "£",
                "code" => "SSP",
            ), 
            "SR" => array(
                "sign" => "$",
                "code" => "SRD",
            ), 
            "KI" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "KH" => array(
                "sign" => "៛",
                "code" => "KHR",
            ), 
            "KN" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "KM" => array(
                "sign" => "CF",
                "code" => "KMF",
            ), 
            "ST" => array(
                "sign" => "Db",
                "code" => "STD",
            ), 
            "SK" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "KR" => array(
                "sign" => "₩",
                "code" => "KRW",
            ), 
            "SI" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "KP" => array(
                "sign" => "₩",
                "code" => "KPW",
            ), 
            "KW" => array(
                "sign" => "د.ك",
                "code" => "KWD",
            ), 
            "SN" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "SM" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "SL" => array(
                "sign" => "Le",
                "code" => "SLL",
            ), 
            "SC" => array(
                "sign" => "₨",
                "code" => "SCR",
            ), 
            "KZ" => array(
                "sign" => "лв",
                "code" => "KZT",
            ), 
            "KY" => array(
                "sign" => "$",
                "code" => "KYD",
            ), 
            "SG" => array(
                "sign" => "S$",
                "code" => "SGD",
            ), 
            "SE" => array(
                "sign" => "kr",
                "code" => "SEK",
            ), 
            "SD" => array(
                "sign" => "SDG",
                "code" => "SDG",
            ), 
            "DO" => array(
                "sign" => "RD$",
                "code" => "DOP",
            ), 
            "DM" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "DJ" => array(
                "sign" => "Fdj",
                "code" => "DJF",
            ), 
            "DK" => array(
                "sign" => "kr",
                "code" => "DKK",
            ), 
            "VG" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "DE" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "YE" => array(
                "sign" => "﷼",
                "code" => "YER",
            ), 
            "DZ" => array(
                "sign" => "د.ج",
                "code" => "DZD",
            ), 
            "US" => array(
                "sign" => "$",
                "code" => "USD",
            ), 
            "UY" => array(
                "sign" => "$",
                "code" => "UYU",
            ), 
            "YT" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "UM" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "LB" => array(
                "sign" => "ل.ل",
                "code" => "LBP",
            ), 
            "LC" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "LA" => array(
                "sign" => "₭",
                "code" => "LAK",
            ), 
            "TV" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "TW" => array(
                "sign" => "NT$",
                "code" => "TWD",
            ), 
            "TT" => array(
                "sign" => "TT$",
                "code" => "TTD",
            ), 
            "TR" => array(
                "sign" => "₺",
                "code" => "TRY",
            ), 
            "LK" => array(
                "sign" => "Rs",
                "code" => "LKR",
            ), 
            "LI" => array(
                "sign" => "CHF",
                "code" => "CHF",
            ), 
            "LV" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "TO" => array(
                "sign" => "T$",
                "code" => "TOP",
            ), 
            "LT" => array(
                "sign" => "LTL",
                "code" => "LTL",
            ), 
            "LU" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "LR" => array(
                "sign" => "L$",
                "code" => "LRD",
            ), 
            "LS" => array(
                "sign" => "L",
                "code" => "LSL",
            ), 
            "TH" => array(
                "sign" => "฿",
                "code" => "THB",
            ), 
            "TF" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "TG" => array(
                "sign" => "CFA",
                "code" => "XOF",
            ), 
            "TD" => array(
                "sign" => "CFA",
                "code" => "XAF",
            ), 
            "TC" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "LY" => array(
                "sign" => "ل.د",
                "code" => "LYD",
            ), 
            "VA" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "VC" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "AE" => array(
                "sign" => "د.إ",
                "code" => "AED",
            ), 
            "AD" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "AG" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "AF" => array(
                "sign" => "؋",
                "code" => "AFN",
            ), 
            "AI" => array(
                "sign" => "$",
                "code" => "XCD",
            ), 
            "VI" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "IS" => array(
                "sign" => "kr",
                "code" => "ISK",
            ), 
            "IR" => array(
                "sign" => "﷼",
                "code" => "IRR",
            ), 
            "AM" => array(
                "sign" => "֏",
                "code" => "AMD",
            ), 
            "AL" => array(
                "sign" => "L",
                "code" => "ALL",
            ), 
            "AO" => array(
                "sign" => "Kz",
                "code" => "AOA",
            ),
            "AS" => array(
                "sign" => "$",
                "code" => "USD",
            ),
            "AR" => array(
                "sign" => "$",
                "code" => "ARS",
            ), 
            "AU" => array(
                "sign" => "$",
                "code" => "AUD",
            ), 
            "AT" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "AW" => array(
                "sign" => "ƒ",
                "code" => "AWG",
            ), 
            "IN" => array(
                "sign" => "₹",
                "code" => "INR",
            ), 
            "AX" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "AZ" => array(
                "sign" => "₼",
                "code" => "AZN",
            ), 
            "IE" => array(
                "sign" => "€",
                "code" => "EUR",
            ), 
            "ID" => array(
                "sign" => "Rp",
                "code" => "IDR",
            ), 
            "UA" => array(
                "sign" => "₴",
                "code" => "UAH",
            ), 
            "QA" => array(
                "sign" => "ر.ق",
                "code" => "QAR",
            ), 
            "MZ" => array(
                "sign" => "MT",
                "code" => "MZN",
            ) 
         ];

        if (array_key_exists($country_code, $currencies_by_country)) {
            $key = $sign ? 'sign' : 'code';
            return $currencies_by_country[$country_code][$key];
        }else{
            return $sign ? 'RM' : 'MYR';
        }
    }

    function nv_my_woocommerce_default_country(){
        if ( class_exists( 'WC_Countries' ) ) {
            $wc_countries = new WC_Countries();
            return $wc_countries->get_base_country();
        }else{
            return 'XX';
        }
    }

    function nv_my_supported_countries(){
        return array(
            "SG" => "Singapore",
            "MY" => "Malaysia",
            "ID" => "Indonesia",
            "TH" => "Thailand",
            "VN" => "Vietnam",
            "PH" => "Philippines",
            "MM" => "Myanmar",
        );
    }

    function nv_my_get_woocommerce_international_shipping(){
        $countries = nv_my_supported_countries();

        $wc_countries = new WC_Countries();
        $wc_base_country = $wc_countries->get_base_country();
        $allowed_countries = $wc_countries->get_allowed_countries();

        foreach ($countries as $code => $name) {
            if (!array_key_exists($code, $allowed_countries)) {
                unset($countries[$code]);
            } else if ($code == $wc_base_country) {
                unset($countries[$code]);
            }
        }

        return $countries;
        
    }

    function nv_my_seller_logo($url = true){
        $logo = nv_my_get_settings('awb_seller_logo');

        if ($url) {
            $logo = nv_my_get_media($logo, true);
            $is_valid_url = filter_var($logo, FILTER_VALIDATE_URL);
            if ($is_valid_url) {
                return $logo;
            }
        }

        if (file_exists(nv_my_get_media($logo))) {
            return nv_my_get_media($logo);
        }

        return false;
    }

    function nv_my_barcode($text = ''){
        // set the barcode content and type
        $barcode = new Barcode($text, 'C128');

        // output the barcode as PNG image
        return base64_encode($barcode->getBarcodePngData(3, 100, array(0,0,0)));
    }

    function nv_my_qrcode($text = ''){

        // set the qrcode content and type
        $qrcode = new QRCode($text, 'QRCODE,H');

        // output the qrcode as PNG image
        return base64_encode($qrcode->getBarcodePngData(10, 10, array(0,0,0)));        
    }

    function nv_my_page_break($count = 0){
        if($count % 2 == 0){
            return '<div class="page-break"></div>';
        }
    }

    function nv_my_get_awb(array $order_ids = []){
        $awb = new GenerateAWB();
        return $awb->get_awb($order_ids);
    }

    function nv_my_assets(){
        return Ninja_Van_MY_ASSETS;
    }

    function nv_my_time($time = 'now', $format = 'Y-m-d H:i:s'){
        $datetime = new DateTime($time, new DateTimeZone('Asia/Kuala_Lumpur'));
        return $datetime->format($format);
    }

    function nv_my_parcel_size($kg = 0){
        if ($kg > 3 && $kg <= 5) {
            return 'Medium / &#60; 5kg';
        }elseif ($kg > 5 && $kg <= 10) {
            return 'Large / &#60; 10kg';
        }elseif ($kg > 10 && $kg <= 30) {
            return 'Extra Large / &#60; 30kg';
        }else{
            return 'Small / &#60; 3kg';
        }
    }

    /**
     * Get WooCommerce Weight Unit
     *
     * @return numeric
     */
    function nv_my_get_weight($weight = 0){

        $wc_weight_unit = get_option('woocommerce_weight_unit');

        if ($wc_weight_unit == 'g') {
            return ($weight / 1000);
        }else if ($wc_weight_unit == 'kg') {
            return $weight;
        }else{
            return 1;
        }

    }

    function nv_my_order_status($text = ''){
        $text = preg_replace('~[^0-9a-z\\s]~i', ' ', $text);

        return strtoupper($text);
    }

    function nv_my_track_order_link($tracking_number = '', $link = false){
        if ($link) {
            return esc_url('https://www.ninjavan.co/en-my/tracking?id='.$tracking_number);
        }else{
            return '<a target="blank" href="'.esc_url("https://www.ninjavan.co/en-my/tracking?id=".$tracking_number).'">'.esc_html($tracking_number).'</a>';
        }
        
    }

    function nv_my_check_table($tb_name){
        global $wpdb;
        $tb_name = $wpdb->prefix . $tb_name;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tb_name)) == $tb_name;
    }

    /**
     * Check for Cash on Delivery support
     * @param string $postcode Filter by postcode
     * @param string $address (Optional) Use Levenshtein to find the closest address. Strict option
     * @return array status, message, flag, score
     */
    function nv_my_address_has_cod($postcode, $address=''){
        // Get validate_cod settings
        $validate_cod = nv_my_get_settings('validate_cod');
        if (!$validate_cod) {
            return array(
                'status' => true,
                'message' => 'Cash on Delivery is available (Inactive)',
                'flag' => -1,
                'score' => -1
            );
        }
        if (nv_my_check_table(Ninja_Van_MY_DB_NAME)) {
            global $wpdb;
            $tb_name = $wpdb->prefix . Ninja_Van_MY_DB_NAME;
            $address_by_postcode = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $tb_name WHERE postcode = %s",
                    $postcode
                ),
                ARRAY_A
            );

            if (empty($address_by_postcode)) {
                // Default behavior
                return array(
                    'status' => false,
                    'message' => 'Area not supported for Cash on Delivery',
                    'flag' => -1,
                    'score' => -1
                );
            }

            /**
             * Use Levenshtein to find the closest address
             * @behavior: Find the closest address and check if cod is available
             */
            if (!empty($address)) {
                // Get array of street_names
                $addresses = array_column($address_by_postcode, 'street_name');
                $ranked_addresses = nv_my_rank_levenshtein($address, $addresses);

                // Rank address_by_postcode by ranked_addresses
                $ranked_address_by_postcode = [];
                foreach ($ranked_addresses as $ranked_address => $score) {
                    foreach ($address_by_postcode as $address) {
                        if ($address['street_name'] == $ranked_address) {
                            // Add score to the address
                            $address['score'] = $score;
                            $ranked_address_by_postcode[] = $address;
                        }
                    }
                }

                // Sort by score
                usort($ranked_address_by_postcode, function($a, $b) {
                    return $a['score'] <=> $b['score'];
                });

                // Check if cod of first address is available
                if ($ranked_address_by_postcode[0]['cod'] == 1) {
                    return array(
                        'status' => true,
                        'message' => 'Cash on Delivery is available for ' . $ranked_address_by_postcode[0]['street_name'] . ' (' . $ranked_address_by_postcode[0]['postcode'] . ')',
                        'flag' => -1,
                        'score' => $ranked_address_by_postcode[0]['score']
                    );
                } else {
                    return array(
                        'status' => false,
                        'message' => 'Cash on Delivery is not available for ' . $ranked_address_by_postcode[0]['street_name'] . ' (' . $ranked_address_by_postcode[0]['postcode'] . ')',
                        'flag' => -1,
                        'score' => $ranked_address_by_postcode[0]['score']
                    );
                }

            } 
            /**
             * Simple Cash on Delivery check
             * @behavior: If at least one address has cod, then it is available, else not available
             */
            else {
                // Count how many addresses does not have cod
                $unsupported_cod = 0;
                foreach ($address_by_postcode as $address) {
                    if ($address['cod'] == 0) {
                        $unsupported_cod++;
                    }
                }

                if ($unsupported_cod == count($address_by_postcode)) {
                    return array(
                        'status' => false,
                        'message' => 'Cash on Delivery is not available',
                        'flag' => 1,
                        'score' => -1
                    );
                } else {
                    return array(
                        'status' => true,
                        'message' => 'Cash on Delivery ' . ($unsupported_cod == 0 ? 'is' : 'might not be' ) . ' available',
                        'flag' => ($unsupported_cod == 0) ? 1 : -1,
                        'score' => -1
                    );
                }
                
            }
        } else {
            // Default behaviour
            return array(
                'status' => true,
                'message' => 'Unable to check for Cash on Delivery support. (Reference not available)',
                'flag' => -1,
                'score' => -1
            );
        }
    }

    /**
     * @use Plugin API
     */
    function nv_my_get_authorize_url(){

        $client_api = new API();

        if ($client_api->auth_mode != 'PLUGIN') {
            return '#';
        }

        $scopes = $client_api->get_scopes();
        
        $required_scopes = ['create_order', 'cancel_order', 'webhook_subscriptions']; 
        $permissions = [];
        foreach ($required_scopes as $scope) {
            if (isset($scopes[$scope])) {
                $permissions = array_merge($permissions, $scopes[$scope]);
            }
        } 
        $permissions = implode('%20', $permissions);

        // Get the full site URL
        $site_url = get_site_url();

        // Parse the URL to extract components
        $parsed_url = parse_url($site_url);

        // Combine the host and path to preserve the subdirectory
        $hostname = $parsed_url['host'] . (isset($parsed_url['path']) ? $parsed_url['path'] : '');

        // Ensure the path is normalized without a trailing slash
        $hostname = rtrim($hostname, '/');
        
        /**
         * State should be the hostname of the site
         */
        $url = str_replace('api', 'dashboard', $client_api->base_url) . $client_api->endpoints[$client_api->auth_mode]['login'] .'?client_id='.$client_api->client_id.'&scopes='.$permissions.'&state='.$hostname;

        return $url;
    }

    /**
     * @use Plugin API
     * @url https://api-docs.ninjavan.co/en#tag/Plugin-APIs/paths/~1global~1aaa~11.0~1logout/post
     */
    function nv_my_get_disconnect_url(){

        $client_api = new API();

        if ($client_api->auth_mode != 'PLUGIN') {
            return '#';
        }

        $url = get_site_url() . '/ninjavan-logout?security='.nv_my_get_webhook_security();
        
        return $url;
    }

    function nv_my_token($reset = false){
        $access_token = new AccessToken();

        if ($reset) {
            $access_token->refresh_token();
        }

        $response = (object) $access_token->get_access_token();

        if ($response->status) {
            return $response->token;
        }

        return false;
    }

    function nv_my_logout($legacy = false){
        $access_token = new AccessToken();
        if ($legacy) {
            $access_token->disconnect();
            nv_my_log('Disconnecting from Ninja Van Legacy API');
            return;
        }
        
        $response = (object) $access_token->logout();
        nv_my_log('Logging out from Ninja Van API: '.$response->message);
        return $response;
    }

    function nv_my_check_cron($name){
        if (wp_next_scheduled( $name )) {
            return true;
        }
        return false;
    }

    function nv_my_get_webhook_events($version = '', $case = false) {
        $client = new Webhook();
        return $client->get_events($version, $case);
    }

    /**
     * @use Plugin API
     */
    function nv_my_get_webhooks(){
        $client = new Webhook();
        $response = $client->get_webhooks();
        $webhooks = [];

        if ($response['status']) {
            $hostname = wp_parse_url(site_url(), PHP_URL_HOST);

            // Filter data by site url
            foreach ($response['data'] as $key => $value) {
                if (strpos($value->uri, $hostname) !== false) {
                    $webhooks[] = $value;
                }
            }
            
        }

        return $webhooks;
    }

    /**
     * @use Plugin API
     */
    function nv_my_create_webhook_bulk($version = '2.0'){
        $client = new Webhook();
        $events = $client->events[$version];

        $webhooks = [];
        
        if (empty($events)) {
            return $webhooks;
        }
        foreach ($events as $event_id => $event) {
            $response = nv_my_create_webhook($event, $version);
            if ($response !== false) {
                $webhooks[] = $event;
            }
        }
        return $webhooks;
    }

    /**
     * @use Plugin API
     * Version 1.0 is deprecated. Use 2.0 instead
     */
    function nv_my_create_webhook($event, $version = '2.0'){

        if (empty($event)) {
            return false;
        }

        $client = new Webhook();

        if ($version == '2.0') {
            if ($event == 'Delivered') {
                // Accepts `Delivered, Received by Customer`, `Delivered, Collected by Customer`, `Delivered, Left at Doorstep`
                return false;
            }
            $data = [
                'event' => $event,
                'version' => $version,
                'uri' => nv_my_get_webhook_url(),
            ];
        } else {
            $data = [
                'event' => $event,
                'version' => $version,
                'internal' => false,
                'protocol' => 'HTTP',
                'method' => 'POST',
                'uri' => nv_my_get_webhook_url(),
                'enable_custom_webhook' => false,
                'hooks_enabled' => false,
                'transformer_class' => '',
                'on_request_generate_hook' => '',
                'on_response_received_hook' => '',
            ];
        }


        $response = $client->create_webhook($data);

        if ($response['status']) {
            $webhook = $response['data'];
            if (isset($webhook->id) && isset($webhook->event)) {
                return $webhook->id;
            } else {
                nv_my_log('(Fatal) No Webhook ID / Event found: ' . json_encode($webhook));
            }
        } else {
            nv_my_log('(Fatal) Webhook Creation Failed: ' . $response['message']);
            nv_my_log('(Fatal) Webhook Creation Failed: ' . json_encode($response));
        }

        return false;
    }

    /**
     * @use Plugin API
     * On logout, delete all webhooks since it was a user action
     */
    function nv_my_delete_webhook($webhook_id){
        $client = new Webhook();
        $response = $client->delete_webhook($webhook_id);

        if (!$response['status']) {
            nv_my_log('(Fatal) Webhook Deletion Failed: ' . $response['message']);
            nv_my_log('(Fatal) Webhook Deletion Failed: ' . json_encode($response));
        }
        return $response;
    }

    /**
     * @use Plugin API
     * Version 1.0 is deprecated. Use 2.0 instead
     */
    function nv_my_sync_webhooks($is_native = false){
        // Check if nonce is set
        if ($is_native !== true && !isset($_POST['nonce'])) {
            wp_send_json_error('Invalid request');
            return;
        }

        // Verify nonce
        if ($is_native !== true && !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'my_ajax_nonce')) {
            wp_send_json_error('Bad nonce');
            return;
        }

        $version = '2.0';
        $webhooks = nv_my_get_webhooks();
        $event_statuses = nv_my_get_webhook_events($version, true);
        if ($version == '2.0') {
            // Remove `Delivered` and add `Delivered, Received by Customer`, `Delivered, Collected by Customer`, `Delivered, Left at Doorstep`
            $event_statuses = array_diff($event_statuses, ['Delivered']);
            $event_statuses = array_merge($event_statuses, ['Delivered, Received by Customer', 'Delivered, Collected by Customer', 'Delivered, Left at Doorstep']);
        } 
        $valid_statuses = [];

        // For each webhook, check the query parameter of security in key `uri` and compare it against the security code
        // For v2.0, some events have additional subevents. I.e `Delivered, Received by Customer` or `Delivered, Collected by Customer`
        foreach ($webhooks as $webhook) {
            if (isset($webhook->uri) && isset($webhook->id) && isset($webhook->event)) {
                $uri = wp_parse_url($webhook->uri);
                if (isset($uri['query'])) {
                    $query = wp_parse_args($uri['query']);
                    // Remove the webhook if the security code is not the same or the event is not in the list of supported events
                    if ((isset($query['security']) && $query['security'] != nv_my_get_webhook_security()) || !in_array($webhook->event, $event_statuses)) {
                        $deleted = nv_my_delete_webhook($webhook->id);
                    } else {
                        $valid_statuses[] = $webhook->event;
                    }
                }
            }
        }
        
        $success = [];
        foreach ($event_statuses as $status) {
            if (!in_array($status, $valid_statuses)) {
                $create_response = nv_my_create_webhook($status);
                if ($create_response !== false) {
                    $success[] = $status;
                }
            }
        }

        if (!$is_native) {
            count($success) == 0 ?
            wp_send_json_error('Webhook already synced')
            : wp_send_json_success('('.count($success).') new webhooks created');
            return;
        }

        return array_diff($event_statuses, $valid_statuses);
    }

    function nv_my_process_webhook($order_id = 0, $tracking_number = 0, $ninjavan_status = '') {
        $client = new Webhook();
        return $client->callback($order_id, $tracking_number, $ninjavan_status);
    }

    function nv_my_get_order_items($order, $skus = false, $separator = ','){
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            if ($product->is_type('variation')) {
                $attributes = $product->get_variation_attributes();
                $attribute_summary = array();

                foreach ($attributes as $attribute => $value) {
                    $taxonomy = str_replace('attribute_', '', $attribute);
                    $attribute_summary[] = wc_attribute_label($taxonomy) . ': ' . $value;
                }

                $attribute_summary = implode(', ', $attribute_summary);

                if (!empty($attribute_summary)) {
                    $item_details[] = $item->get_quantity() . 'x ' . $product->get_title() . ' (' . $attribute_summary . ')';
                }
            }else{
                $item_details[] = $item->get_quantity().'x '.$product->get_name();
            }
            
            $item_details_sku[] = $item->get_quantity().'x '.$product->get_sku();
        }

        if ($skus && !empty($item_details_sku)) {
            return strtoupper(implode ($separator." ", $item_details_sku));
        }
        
        if (!$skus && !empty($item_details)) {
            return implode ($separator." ", $item_details);
        }

        return '';
    }

    function nv_my_get_order_items_data($order){
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $items[] = [
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'weight' => $product->get_weight(),
                'price' => $product->get_price(),
                'total' => $product->get_price() * $item->get_quantity(),
            ];
        }

        return $items;
    }

    function nv_my_get_order_weights($order, $toArray = false){
        $weights = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $weights[] = ($product->get_weight()) ? ($product->get_weight() * $item->get_quantity()) : 0;
        }

        if ($toArray) {
            return $weights;
        }

        return nv_my_get_weight((double) array_sum($weights));
    }

    function nv_my_short_text($text, $overrideLimit = 0){
        $limit = (int) nv_my_get_settings('awb_order_text_limit');

        if ($limit < 20) {
            $limit = 20;
        }elseif ($limit > 250) {
            $limit = 250;
        }

        if ($overrideLimit > 0) {
            $limit = (int) $overrideLimit;
        }

        return mb_strimwidth($text, 0, $limit, "...");
    }

    function nv_my_create_booking(int $order_id){
        $create_booking = new CreateBooking();
        return $create_booking->by_order_id($order_id);
    }

    function nv_my_cancel_booking(int $order_id){
        $cancel_booking = new CancelBooking();
        return $cancel_booking->by_order_id($order_id);
    }

    function nv_my_get_states($state_code = false){

        if ($state_code) {
            $state_code = strtolower($state_code);
        }
    
        $stateList = array(
            'jhr' => 'Johor',
            'kdh' => 'Kedah',
            'ktn' => 'Kelantan',
            'kul' => 'Kuala Lumpur',
            'lbn' => 'Labuan',
            'mlk' => 'Malacca',
            'nsn' => 'Negeri Sembilan',
            'phg' => 'Pahang',
            'png' => 'Penang',
            'prk' => 'Perak',
            'pls' => 'Perlis',
            'pjy' => 'Putrajaya',
            'sbh' => 'Sabah',
            'swk' => 'Sarawak',
            'sgr' => 'Selangor',
            'trg' => 'Terengganu',
        );

        if (isset($state_code) && $state_code !== false) {
            if (array_key_exists($state_code, $stateList)) {
                return $stateList[$state_code];
            }else{
                $stateList[''] = '';
            }
        }else{
            return $stateList;
        }
    }

    /**
     * Get Ninja Van Origin Countries
     * @note: Only Malaysia is supported for now
     */
    function nv_my_get_origin_countries(){
        return array(
            'MY' => 'Malaysia',
            'XX' => 'Not Available',
        );
    }

    function nv_my_get_international_service_code($country_code){
        $client_api = new API();

        if ($client_api->auth_mode != 'PLUGIN') {
            return '';
        }

        $service_codes = $client_api->get_international_service_code();
        if (!$country_code) {
            return $service_codes;
        }

        if (!isset($service_codes[$country_code])) {
            return '';
        }

        return $service_codes[$country_code];
    }

    function nv_my_settings_banner(){
        $logo_url = esc_url(nv_my_assets() . '/images/logo.svg');
        $version = esc_html(nv_my_get_settings('version'));

        return '
        <img src="' . $logo_url . '" alt="" style="width: 90%; max-width: 220px">
        <div>
            <span class="text-info text-title-small">Version - ' . $version . '</span>
        </div>
        ';
    }

    function is_using_wp_cron() {
        // Path to wp-config.php - adjust the path as necessary
        $wp_config_path = ABSPATH . 'wp-config.php';
        
        if (file_exists($wp_config_path)) {
            $wp_config_contents = file_get_contents($wp_config_path);
            
            // Check if DISABLE_WP_CRON is defined and set to true
            if (strpos($wp_config_contents, "define('DISABLE_WP_CRON', true)") !== false) {
                return false;
            } else {
                return true;
            }
        } else {
            nv_my_log('Could not find wp-config.php. Please ensure the path is correct.');
            return true;
        }
    }

    function nv_my_reset($hard = false){
        $access_token = new AccessToken();
        $access_token->disconnect();

        if ($hard) {
            delete_option('Ninja_Van_WooCommerce_installed');
            delete_option('Ninja_Van_WooCommerce_version');
            delete_option('Ninja_Van_WooCommerce_webhook_security');
            delete_option('Ninja_Van_WooCommerce_Db_Version');
        }
        delete_option('Ninja_Van_WooCommerce_STATUS');
        delete_option('Ninja_Van_WooCommerce_TYPE');
        delete_option('Ninja_Van_WooCommerce_CLIENT_ID');
        delete_option('Ninja_Van_WooCommerce_CLIENT_SECRET');
        delete_option('Ninja_Van_WooCommerce_AUTH_MODE');
        delete_option('Ninja_Van_WooCommerce_AUTH_STATE');
        delete_option('Ninja_Van_WooCommerce_ACCESS_CODE');
        delete_option('Ninja_Van_WooCommerce_SETTINGS');
        delete_option('Ninja_Van_WooCommerce_ADDRESS_SENDER');
        delete_option('Ninja_Van_WooCommerce_ADDRESS_PICKUP');
    }

?>