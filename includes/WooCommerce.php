<?php

namespace Ninja\Van\MY;

use Automattic\WooCommerce\Utilities\OrderUtil;
    
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerce
{
    function __construct() {
        $this->woocommerce_order_status_controller();
    }

    public function woocommerce_order_status_controller(){
        if (nv_my_get_settings('push_to_ninja') == 'automatic') {
            if (nv_my_get_settings('push_when') == 'completed') {
                add_action( 'woocommerce_order_status_completed', [$this, 'automatic_push_to_ninjavan']);
            }elseif (nv_my_get_settings('push_when') == 'processing') {
                add_action( 'woocommerce_order_status_processing', [$this, 'automatic_push_to_ninjavan']);
            }elseif (nv_my_get_settings('push_when') == 'on-hold') {
                add_action( 'woocommerce_order_status_on-hold', [$this, 'automatic_push_to_ninjavan']);
            }elseif (nv_my_get_settings('push_when') == 'pending') {
                add_action( 'woocommerce_order_status_pending', [$this, 'automatic_push_to_ninjavan']);
            }
        }

        $this->add_shop_order_columns();
    }

    private function add_shop_order_columns() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            add_filter( 'manage_woocommerce_page_wc-orders_columns', [$this, 'add_ninja_van_column'], 10, 1 );
            add_filter( 'manage_woocommerce_page_wc-orders_custom_column', [$this, 'add_ninja_van_column_contents'], 10, 2 );
        } else {
            add_filter( 'manage_edit-shop_order_columns', [$this, 'add_ninja_van_column'], 10, 1 );
            add_filter( 'manage_shop_order_posts_custom_column', [$this, 'add_ninja_van_column_contents'], 10, 2 );
        }
    }

    public function automatic_push_to_ninjavan( $order_id = 0 ) {
        if ($order_id) {
            return nv_my_create_booking($order_id);
        }
    }

    public function add_ninja_van_column($columns) {
        $columns['ninja'] = __('Ninja Van', 'ninja-van-my');
        return $columns;
    }

    public function add_ninja_van_column_contents( $column, $post_id ) {
        if ( 'ninja' === $column ){
            $order = ( $post_id instanceof \WC_Order ) ? $post_id : wc_get_order( $post_id );
            $tracking_number = $order->get_meta( 'ninja_van_tracking_number', true );
            $awb_generated = $order->get_meta( '_ninja_van_awb_generated', true );

            if ($tracking_number) {
                echo nv_my_track_order_link($tracking_number);
                if ($awb_generated) {
                    echo '<br><span class="nv-awb-generated">'.esc_html__('AWB Printed: ', 'ninja-van-my').number_format($awb_generated).' time(s)</span>';
                }
                // (o) tracking number & (o) _ninja_van_cancelled_payload & _ninja_van_console_log != 'Order was cancelled' : Error while trying to cancel booking
                $log = $order->get_meta( '_ninja_van_console_log', true );
                if ($order->get_meta( '_ninja_van_cancelled_payload', true ) && isset($log) && $log != 'Order was cancelled') {
                    echo '<br><span class="nv-error"><b>'.esc_html__('Cancel Error: ', 'ninja-van-my').'</b>'.esc_html($log).'</span>';
                } 
                if ($order->get_meta( '_ninja_van_cod_may_not_support', true)) {
                    echo '<br><span class="nv-error"><b>'.esc_html__('COD Warning: ', 'ninja-van-my').'</b>'.esc_html__('Area may not be covered', 'ninja-van-my').'</span>';
                }
            }else{
                // (x) tracking number & (o) _ninja_van_payload (o) & _ninja_van_console_log != 'Successfully Pushed To Ninja Van!' : Error while trying to create booking
                $log = $order->get_meta( '_ninja_van_console_log', true );
                if ($order->get_meta( '_ninja_van_payload', true ) && isset($log) && $log != 'Successfully Pushed To Ninja Van!') {
                    echo '<span class="nv-error"><b>'.esc_html__('Booking Error: ', 'ninja-van-my').'</b>'.esc_html($log).'</span>';
                } else {
                    echo 'Not Available';
                }
            }
        }
    }
}