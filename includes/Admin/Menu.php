<?php

namespace Ninja\Van\MY\Admin;
    
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menu {
    /**
     * Initialize the class
     */
    function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function admin_menu() {
        $parent_slug = 'ninja-van-my';
        $capability = 'manage_options';

        $icon = nv_my_assets().'/images/favicon.ico';

        add_menu_page(
            __( 'Ninja Van (MY)', 'ninja-van-my' ),
            __( 'Ninja Van (MY)', 'ninja-van-my' ),
            $capability,
            $parent_slug,
            [$this, 'load_settings'],
            $icon
        );
    }
    
    public function load_settings() {
        $settings = new Settings();
        return $settings->index_page();
    }
}

?>