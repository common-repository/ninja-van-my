<?php

namespace Ninja\Van\MY;
    
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin
{
    function __construct() {
        $this->dispatch_actions();
        new Admin\Menu();
    }

    public function dispatch_actions(){
        $settings = new Admin\Settings();
        add_action( 'admin_init', [$settings, 'nv_settings_form_handlers'] );
        add_action( 'admin_init', [$settings, 'nv_download_awb'] );
        add_action( 'admin_enqueue_scripts', [$this, 'nv_admin_style'] );
        add_action( 'admin_enqueue_scripts', [$this, 'nv_admin_js'] );
        add_action( 'admin_enqueue_scripts', [$this, 'nv_wp_media_files'] );
        add_action( 'wp_ajax_nv_my_sync_webhooks', 'nv_my_sync_webhooks' );
    }

    function nv_admin_style() {
        wp_enqueue_style( 'data-tables-style', nv_my_assets().'/lib/datatables.min.css', array(), '2.0.3' );
        wp_enqueue_style( 'ninja-van-my-admin-style', nv_my_assets().'/admin/nv-style.css', array(), Ninja_Van_MY_VERSION );
    }

    function nv_admin_js() {
        wp_enqueue_script( 'data-tables-js', nv_my_assets().'/lib/datatables.min.js', array(), '2.0.3' );
        wp_enqueue_script( 'ninja-van-my-admin-js', nv_my_assets().'/admin/nv-ui.js', array(), Ninja_Van_MY_VERSION );
        wp_localize_script('ninja-van-my-admin-js', 'nv_my_admin_js', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('my_ajax_nonce'),
        ));
    }

    function nv_wp_media_files() {
        wp_enqueue_media();
    }
}

?>