<?php

    namespace Ninja\Van\MY\Worker;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class handles API initialization for Ninja Van
     */
    class API 
    {
        public $client_id;
        public $client_secret;
        public $auth_mode;
        public $endpoints = [
            "DIRECT" => [
                "token" => "/2.0/oauth/access_token",
                "create_order" => "/4.2/orders",
                "cancel_order" => "/2.2/orders",
            ],
            "PLUGIN" => [
                "token" => "/1.0/oauth/token",
                "create_order" => "/plugins/4.2/orders",
                "cancel_order" => "/plugins/2.2/orders",
                "login" => "/oauth/login",
                "logout" => "/global/aaa/1.0/logout",
                "get_webhooks" => "/plugins/2.0/shippers/event-subscriptions/webhook",
                "create_webhooks" => "/plugins/2.0/shippers/event-subscriptions/webhook",
                "delete_webhooks" => "/plugins/2.0/shippers/event-subscriptions",
                "get_webhooks_v2" => "/plugins/2.1/shippers/webhooks",
                "create_webhooks_v2" => "/plugins/2.1/shippers/webhooks",
                "delete_webhooks_v2" => "/plugins/2.1/shippers/webhooks"
            ]
        ];

        public $base_url = 'https://api-sandbox.ninjavan.co';
        public $url;

        function __construct() {
            $this->client_id = nv_my_get_config('client_id');
            $this->client_secret = nv_my_get_config('client_secret');
            $this->auth_mode = nv_my_get_config('auth_mode');

            if (!in_array($this->auth_mode, ['DIRECT', 'PLUGIN'])) $this->auth_mode =  nv_my_get_config('auth_mode');
            $this->url = $this->base_url . '/SG';
            if (nv_my_get_config('type') == 1) {
                $this->base_url = 'https://api.ninjavan.co';
                $this->url = $this->base_url . '/MY';
            }
        }

        /**
         * Get scopes for Plugin API
         */
        function get_scopes() {
            if ($this->auth_mode == 'DIRECT') return [];
            return [
                'create_order' => ['SHIPPER_PUBLIC_APIS_CREATE_ORDER'],
                'generate_awb' => ['SHIPPER_PUBLIC_APIS_GET_AWB'],
                'cancel_order' => ['SHIPPER_PUBLIC_APIS_CANCEL_ORDER'],
                'webhook_subscriptions' => ['SHIPPER_PUBLIC_APIS_GET_SUBSCRIPTIONS', 'SHIPPER_PUBLIC_APIS_CREATE_SUBSCRIPTIONS', 'SHIPPER_PUBLIC_APIS_DELETE_SUBSCRIPTIONS'],
                'shipper_settings' => ['SHIPPER_PUBLIC_APIS_GET_SHIPPER_SETTINGS']
            ];
        }

        /**
         * Get International Service Code
         */
        function get_international_service_code(){
            return array(
                'SG' => 'MYSG-A-S-1',
                'PH' => 'MYPH-A-S-1',
                'ID' => 'MYID-A-S-1',
            );
        }

    }