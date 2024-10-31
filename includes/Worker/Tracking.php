<?php
    namespace Ninja\Van\MY\Worker;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class Tracking
    {
        function __construct() {
            add_action( 'woocommerce_my_account_my_orders_actions', [$this, 'tracking_button_in_my_account_order_actions'], 10, 2 );
            add_action( 'woocommerce_view_order', [$this, 'tracking_detail_in_view_order'], 10);
        }

        public function tracking_button_in_my_account_order_actions($actions, $order){
            $order = wc_get_order( $order->get_id() );

            $shipping_tracking = $order->get_meta( 'ninja_van_tracking_number', true );;
    
            if ($shipping_tracking) {
                $url = "https://www.ninjavan.co/en-my/tracking?id=".$shipping_tracking;
                    
                $actions['track'] = array(
                    'url'  => $url,
                    'name' => __( 'Track Order', 'ninja-van-my' ),
                );
                return $actions;
            }else{
                return $actions;
            }  
        }
    
        public function tracking_detail_in_view_order($order){
            $order = wc_get_order( $order );
            
            $shipping_tracking = $order->get_meta( 'ninja_van_tracking_number', true );
        
            if (!empty($shipping_tracking)) {
                $shipping_tracking = sanitize_text_field($shipping_tracking);
                echo '<h2 class="woocommerce-order-details__title">' . esc_html__('Shipping Information', 'ninja-van-my') . '</h2>';
                echo '<table class="woocommerce-table shop_table shipping_information"><tbody>';
                    echo '<tr><th>' . esc_html__('Shipping Provider', 'ninja-van-my') . '</th><td>' . esc_html__('Ninja Van', 'ninja-van-my') . '</td></tr>';
                    echo '<tr><th>' . esc_html__('Tracking Number', 'ninja-van-my') . '</th><td><a target="_blank" href="' . esc_url('https://www.ninjavan.co/en-my/tracking?id=' . $shipping_tracking) . '">' . esc_html($shipping_tracking) . '</a></td></tr>';
                echo '</tbody></table>';
            }
        }
    }
?>