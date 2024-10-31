<?php
    namespace Ninja\Van\MY\Worker;

    use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
    use Automattic\WooCommerce\Utilities\OrderUtil;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class WooCommerceOption
    {
        public $success;
        public $failed;
        function __construct($refresh = false) {
            $this->load_options();
        }

        /**
         * HPOS Compatibility
         * @link https://stackoverflow.com/questions/77366037/filtering-orders-list-in-woocommerce-with-hpos#:~:text=For%20others%20with%20similar%20low%20reading%20comprehension%2C%20the%20updated%20hooks%20for%20the%20admin%20order%20page%20are
         */
        public function load_options() {
            add_action( 'init', [$this, 'register_ninja_van_order_status'] );
            add_action( 'wc_order_statuses', [$this, 'register_ninja_van_order_statuses'] );

            $this->add_shop_order_actions();
            $this->add_shop_order_filters();

            add_action( 'add_meta_boxes', [$this, 'register_ninja_van_dashboard'] );

            add_action( 'admin_notices', [$this, 'admin_notice'] );
        }

        private function add_shop_order_actions() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                add_filter( 'bulk_actions-woocommerce_page_wc-orders', [$this, 'set_ninja_van_actions'] );
                add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_ninja_van_actions'], 10, 3 );
            } else {
                add_filter( 'bulk_actions-edit-shop_order', [$this, 'set_ninja_van_actions'] );
                add_filter( 'handle_bulk_actions-edit-shop_order', [$this, 'handle_ninja_van_actions'], 10, 3 );
            }
        }

        private function add_shop_order_filters() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                add_action( 'woocommerce_order_list_table_restrict_manage_orders', [$this, 'display_admin_shop_order_language_filter'] );
                add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', [$this, 'process_admin_shop_order_filter'] );
            } else {
                add_action( 'restrict_manage_posts', [$this, 'display_admin_shop_order_language_filter'] );
                add_action( 'pre_get_posts', [$this, 'process_admin_shop_order_filter'] );
            }
        }
        

        // Register new status
        public function register_ninja_van_order_status() {
            register_post_status( 'wc-nv-pending-pickup', array(
                'label'                     => 'NV Pending Pickup',
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'NV Pending Pickup (%s)', 'NV Pending Pickups (%s)', 'ninja-van-my' )
            ) );

            register_post_status( 'wc-nv-in-transit', array(
                'label'                     => 'NV In Transit',
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'NV In Transit (%s)', 'NV In Transit (%s)', 'ninja-van-my' )
            ) );

            register_post_status( 'wc-nv-out-delivery', array(
                'label'                     => 'NV Out For Delivery',
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'NV Out For Delivery (%s)', 'NV Out For Deliveries (%s)', 'ninja-van-my' )
            ) );

            register_post_status( 'wc-nv-returned', array(
                'label'                     => 'NV Return',
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'NV Returned (%s)', 'NV Returned (%s)', 'ninja-van-my')
            ) );
        }

        public function register_ninja_van_order_statuses( $order_statuses ) {
 
            $new_order_statuses = array();
            
            // add new order status after processing
            foreach ( $order_statuses as $key => $status ) {
         
                $new_order_statuses[ $key ] = $status;

                if ( 'wc-processing' === $key ) {
                    $new_order_statuses['wc-nv-pending-pickup'] = 'NV Pending Pickup';
                    $new_order_statuses['wc-nv-in-transit'] = 'NV In Transit';
                    $new_order_statuses['wc-nv-out-delivery'] = 'NV Out For Delivery';
                }

                if ( 'wc-cancelled' === $key ) {
                    $new_order_statuses['wc-nv-returned'] = 'NV Returned';
                }
            }
         
            return $new_order_statuses;
        }

        public function set_ninja_van_actions ( $actions ) {

            $actions['push_to_ninja_van'] = __( 'NV: Push Order(s)', 'ninja-van-my' );
            $actions['generate_airway_bill'] = __( 'NV: Generate AWB', 'ninja-van-my' );
            $actions['cancel_order_ninja_van'] = __( 'NV: Cancel Order(s)', 'ninja-van-my' );

            return $actions;
        }

        public function handle_ninja_van_actions ( $redirect_to, $action, $post_ids ) {

            if ( ! in_array($action, array('push_to_ninja_van', 'generate_airway_bill', 'cancel_order_ninja_van'))) {
                return $redirect_to;
            }

            if ($action == 'push_to_ninja_van') {
                foreach ($post_ids as $order) {
                    $response = (object) nv_my_create_booking($order);

                    if ($response->status) {
                        ++$this->success;
                    }else{
                        ++$this->failed;
                    } 
                }

                return $redirect_to = add_query_arg( array(
                    'success_count' => $this->success,
                    'failed_count' => $this->failed,
                    'ninja_action' => 'push'
                ), $redirect_to );
            }

            if ($action == 'cancel_order_ninja_van') {
                foreach ($post_ids as $order) {
                    $response = (object) nv_my_cancel_booking($order);

                    if ($response->status) {
                        ++$this->success;
                    }else{
                        ++$this->failed;
                    } 
                }

                return $redirect_to = add_query_arg( array(
                    'success_count' => $this->success,
                    'failed_count' => $this->failed,
                    'ninja_action' => 'cancel'
                ), $redirect_to );
            }

            if ($action == 'generate_airway_bill') {
                return nv_my_get_awb($post_ids);
            }

            return $redirect_to;
        }

        public function display_admin_shop_order_language_filter(){
            global $pagenow, $post_type;

            if( ('shop_order' === $post_type && 'edit.php' === $pagenow) || ( isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wc-orders' ) ) {
                $languages = array(
                    'pushed' => __('Only With Tracking Number', 'ninja-van-my')
                );
                $current   = isset($_GET['ninja_van_filter'])? sanitize_text_field($_GET['ninja_van_filter']) : '';

                echo '<select name="ninja_van_filter">
                <option value="">' . esc_html__('All Orders ', 'ninja-van-my') . '</option>';

                foreach ( $languages as $value => $name ) {
                    printf( '<option value="%s"%s>%s</option>', esc_html($value), 
                        $value === $current ? '" selected="selected"' : '', esc_html($name) );
                }
                echo '</select>';
            }
        }

        public function process_admin_shop_order_filter( $query ) {
            global $pagenow;

            if ( is_admin() && $pagenow == 'edit.php' && isset( $_GET['ninja_van_filter'] ) 
                && sanitize_text_field($_GET['ninja_van_filter']) == 'pushed' && (sanitize_text_field($_GET['post_type']) == 'shop_order') ) {

                $query->set( 'meta_key', 'ninja_van_tracking_number' ); // Set the new "meta query"
                $query->set( 'meta_compare', 'EXISTS' ); // Set the new "meta query"

                $query->set( 'posts_per_page', 10 ); // Set "posts per page"

                $query->set( 'paged', ( get_query_var('paged') ? get_query_var('paged') : 1 ) ); // Set "paged"
            } else if ( is_admin() && isset( $_GET['page'] ) && sanitize_text_field($_GET['page']) == 'wc-orders' 
                && isset( $_GET['ninja_van_filter'] ) && sanitize_text_field($_GET['ninja_van_filter']) == 'pushed' ) {
                
                $meta_query = array(
                    array(
                        'key' => 'ninja_van_tracking_number',
                        'compare' => 'EXISTS'
                        )
                    );
                    
                $query['meta_query'] = array_merge( $query, $meta_query );
                
                $query['limit'] = 10;
            }
            return $query;
        }

        function register_ninja_van_dashboard() {
            $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                    ? wc_get_page_screen_id( 'shop-order' )
                    : 'shop_order';
            add_meta_box( 'ninja_van_dashboard', __( 'Ninja Van - Dashboard', 'ninja-van-my' ), [$this, 'ninja_van_dashboard'], $screen, 'advanced', 'high' );
        }
    
        function ninja_van_dashboard( $post ) {
            $order_id = ( $post instanceof WP_Post ) ? $post->get_id() : $post->ID;
            $order = wc_get_order( $order_id );

            $render = '<table class="table ninja_van_dashboard wp-list-table widefat striped">';
    
            $render .= '<tr><th colspan="2" style="padding: 2.5em 1.5em; text-align: center;"><img src="'.esc_url(nv_my_assets().'/images/logo.svg').'" alt="" style="width: 90%; max-width: 220px"></th></tr>';
    
            $render .= '<tr><th>Order ID</th><td><strong>'.esc_html($order_id).'</strong></td></tr>';


            if ($tracking_number = $order->get_meta( 'ninja_van_tracking_number', true )) {
                $render .= '<tr><th>'.esc_html__('Tracking Number', 'ninja-van-my').'</th><td><a target="_blank" href="'.esc_url(nv_my_track_order_link($tracking_number, true)).'" type="button">'.esc_html($tracking_number).'</a></td></tr>';

                $awb_note = '';
                if ($awb_generated = $order->get_meta( '_ninja_van_awb_generated', true )) {
                    $awb_note = '<span style="font-style: italic;margin-left: 5px;">('.sprintf( esc_html__('Printed %s times', 'ninja-van-my'), number_format($awb_generated) ).')</span>';
                }

                $render .= '<tr><th>'.esc_html__('Airway Bill','ninja-van-my').'</th><td><a href="'.esc_url(wp_nonce_url(home_url( add_query_arg( 'download_awb', $order_id ) ), 'nv-order-awb')).'" type="button" class="button">Download AWB</a>'.$awb_note.'</td></tr>';
            }

            if ($last_update = $order->get_meta( '_ninja_van_last_event_date', true )) {
                $render .= '<tr><th>'.esc_html__('Last Webhook Call','ninja-van-my').'</th><td>'.esc_html($last_update).'</td></tr>';
            }

            if ($console = $order->get_meta( '_ninja_van_console_log', true )) {
                $render .= '<tr><th>Console Log</th><td>'.esc_html($console).'</td></tr>';
            }
    
            $render .= '</table>';
    
            echo wp_kses_post(trim($render));
        }

        function admin_notice() {
            if ((isset($_REQUEST['post_type']) && sanitize_text_field($_REQUEST['post_type']) != 'shop_order') && (isset($_REQUEST['page']) && sanitize_text_field($_REQUEST['page']) != 'wc-orders')) {
                return;
            }
            
            if (isset($_REQUEST['success_count'])) {
                $success = (int) sanitize_text_field($_REQUEST['success_count']);
                if ($success > 0) {
                    $message = (isset($_REQUEST['ninja_action']) && sanitize_text_field($_REQUEST['ninja_action']) == 'cancel') 
                        ? sprintf(esc_html(_n( '%d Order has been Cancelled from Ninja Van', '%d Orders has been Cancelled from Ninja Van', $success, 'ninja-van-my')), number_format($success)) 
                        : sprintf(esc_html(_n( '%d Order has been Pushed to Ninja Van', '%d Orders has been Pushed to Ninja Van', $success, 'ninja-van-my')), number_format($success));
                    printf( '<div id="message" class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message) );
                }
            }
            
            if (isset($_REQUEST['failed_count'])) {
                $failed = (int) sanitize_text_field($_REQUEST['failed_count']);
                if ($failed > 0) {
                    $message = (isset($_REQUEST['ninja_action']) && sanitize_text_field($_REQUEST['ninja_action']) == 'cancel') 
                        ? sprintf(esc_html(_n( '%d Order was Failed to Cancel from Ninja Van', '%d Orders were Failed to Cancel from Ninja Van', $failed, 'ninja-van-my'), number_format($failed))) 
                        : sprintf(esc_html(_n( '%d Order was Failed to Push to Ninja Van', '%d Orders were Failed to Push to Ninja Van', $failed, 'ninja-van-my')), number_format($failed));
                    printf( '<div id="message" class="notice notice-error is-dismissible"><p>%s</p></div>',esc_html($message) );
                }
            }
        }
    }