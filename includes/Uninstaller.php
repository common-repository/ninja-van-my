<?php
    namespace Ninja\Van\MY;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class Uninstaller
    {
        public function run(){
            $this->remove_tables();
        }

        private function remove_tables(){
            global $wpdb;
            $table_name = $wpdb->prefix . Ninja_Van_MY_DB_NAME;
            $sql = "DROP TABLE IF EXISTS $table_name";
            $wpdb->query($sql);

            // Remove DB version
            delete_option( 'Ninja_Van_WooCommerce_Db_Version');
        }

        private function remove_version(){
            delete_option( 'Ninja_Van_WooCommerce_installed');
            delete_option( 'Ninja_Van_WooCommerce_version');
        }

        private function remove_webhook_security(){
            delete_option( 'Ninja_Van_WooCommerce_webhook_security');
        }

        private function remove_status(){
            delete_option( 'Ninja_Van_WooCommerce_STATUS');
        }

        private function remove_options(){
            delete_option( 'Ninja_Van_WooCommerce_settings');
        }
    }