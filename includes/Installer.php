<?php
    namespace Ninja\Van\MY;
    
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class Installer
    {
        public function run(){
            $this->add_version();
            $this->add_webhook_security();
            $this->check_status();
        }

        public function upgrade(){
            $this->check_table();
        }

        private function add_version(){
            if (!get_option('Ninja_Van_WooCommerce_installed')) {
                update_option( 'Ninja_Van_WooCommerce_installed', time());
            }
            
            update_option( 'Ninja_Van_WooCommerce_version', Ninja_Van_MY_VERSION);
        }

        private function add_webhook_security(){
            if (!get_option('Ninja_Van_WooCommerce_webhook_security')) {
                update_option( 'Ninja_Van_WooCommerce_webhook_security', wp_generate_uuid4());
            }
        }

        private function check_status(){
            if (!get_option('Ninja_Van_WooCommerce_STATUS')) {
                update_option( 'Ninja_Van_WooCommerce_STATUS', false);
            }else{
                update_option( 'Ninja_Van_WooCommerce_STATUS', true);
            }
        }

        private function check_table(){
            $db_version = get_option('Ninja_Van_WooCommerce_Db_Version');

            // Return if the table is already up to date
            $db_version_ld = version_compare($db_version, Ninja_Van_MY_DB_VERSION);
            if (!empty($db_version) && $db_version_ld === 0) {
                return;
            }
            $db_version_state = $db_version_ld === -1 ? 'update' : 'downgrade';

            global $wpdb;

            $table_name = $wpdb->prefix . Ninja_Van_MY_DB_NAME;
            $charset_collate = $wpdb->get_charset_collate();

            // Make sure the file is sql type
            $file = Ninja_Van_MY_PATH . '/assets/update/update.sql';
            if (file_exists($file)) {

                // Read the sql file
                $sql = file_get_contents($file);

                // Replace `tb_my_address` with the actual table name
                $sql = str_replace('tb_my_address', $table_name, $sql);

                // Replace CHARSET=utf8 with the actual charset
                $sql = str_replace('CHARSET=utf8', $charset_collate, $sql);

                // Execute the sql
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                // Update database version
                update_option('Ninja_Van_WooCommerce_Db_Version', Ninja_Van_MY_DB_VERSION);

                nv_my_log('Database ' . $db_version_state . ' to version ' . Ninja_Van_MY_DB_VERSION);
            } else {
                nv_my_log('Error: Unable to find update.sql');
            }

        }
    }
?>