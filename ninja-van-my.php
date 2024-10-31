<?php
/**
 * Plugin Name: Ninja Van (MY)
 * Plugin URI: https://sites.google.com/ninjavan.co/woocommerce/
 * Description: Easily connect your WooCommerce store to your Ninja Van Dashboard for automatic tracking order creation and updates — no manual entry required!
 * Version: 1.1.4
 * Author: Ninja Van Logistics Sdn Bhd
 * Author URI: https://www.ninjavan.co/
 * Text Domain: ninja-van-my
 * Requires PHP: 7.2
 * Requires at least: 5.6
 * Tested up to: 6.6.1
 * WC requires at least: 6.6
 * WC tested up to: 9.1
 * License: GPL-3.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/vendor/autoload.php';

final class Ninja_Van_MY {

    CONST version = '1.1.4';

    private function __construct() {
        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            add_action( 'admin_notices', [$this, 'check_woocommerce_installation']) ;

            return false;
        }

        $this->define_constants();

        register_activation_hook( __FILE__, [$this, 'activate'] );

        register_uninstall_hook( __FILE__, [__CLASS__, 'uninstall'] );

        add_action( 'plugins_loaded', [$this, 'init_plugin'] );
        add_action( 'plugins_loaded', [$this, 'upgrade'] );

    }

    /**
     * Plugin Initiate
     *
     * @return \Ninja_Van_MY
     */
    public static function init() {
        static $instance = false;

        if (!$instance){
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define Constants
     *
     * @return void
     */
    public function define_constants() {
        define('Ninja_Van_MY_VERSION', self::version);
        define('Ninja_Van_MY_FILE', __FILE__);
        define('Ninja_Van_MY_PATH', __DIR__);
        define('Ninja_Van_MY_URL', plugins_url( '', Ninja_Van_MY_FILE ));
        define('Ninja_Van_MY_ASSETS', Ninja_Van_MY_URL . '/assets');
        define('Ninja_Van_MY_DB_VERSION', '1.1.3');
        define('Ninja_Van_MY_DB_NAME', 'ninja_van_my_address');
        define('Ninja_Van_MY_OAUTH', 'PLUGIN'); // DIRECT or PLUGIN API
        define('Ninja_Van_MY_LEGACY_OVERRIDE', false);
        define('Ninja_Van_MY_PLUGIN_AUTH', array(
            array(
                'type' => 'sandbox',
                'client_id' => '787RE9BFKUiucXUa4Wima7SGeKIZhlAF',
                'client_secret' => 'OFOF7ngfv1VMY7Iw4eUD4EcE2FK23IjS'
            ),
            array(
                'type' => 'production',
                'client_id' => 'Xxjsa61Czl02b9tWVGDwFvITkcIGRMCK',
                'client_secret' => 'LQqJPzGqFEkB4ROU1toLscYZ7C4K7Uon'
            )
        ));
        define('Ninja_Van_MY_LEGACY_SLUG', 'ninja-van-woocommerce/ninja-van-woocommerce.php');
        define('Ninja_Van_MY_cURL_TIMEOUT', 30);
        define('Ninja_Van_MY_DEVELOPMENT', false);
    }

    public function check_woocommerce_installation(){
        if (is_user_logged_in() && is_admin()) {
            if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
                echo '<div class="notice notice-warning"><p><strong>' . esc_html__('WARNING:', 'ninja-van-my') . '</strong> ' . esc_html__('Ninja Van will only work if WooCoommerce is installed and Activated!', 'ninja-van-my') . '</p></div>';
            }
        }
    }

    public function check_extensions(){
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__('WARNING:', 'ninja-van-my') . '</strong> ' . esc_html__('Ninja Van recommend you to have', 'ninja-van-my') . ' <a href="'. esc_url('https://www.php.net/manual/en/book.image.php') . '">GD</a> or <a href="' . esc_url('https://www.php.net/manual/en/book.imagick.php') . '">Imagick</a> ' . esc_html__('library installed on your server to Generate AWB!', 'ninja-van-my') . '</p></div>';
        }
    }

    public function check_permalinks(){
        if (get_option('permalink_structure') == '') {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__('WARNING:', 'ninja-van-my') . '</strong> ' . esc_html__('Ninja Van recommend you to have', 'ninja-van-my') . ' <a href="'.esc_url(admin_url('options-permalink.php')).'">Permalinks</a> ' . esc_html__('changed in order for webhooks to work properly!', 'ninja-van-my') . '</p></div>';
        }
    }

    public function check_legacy_plugin(){
        if (is_user_logged_in() && is_admin()) {
            // Check if Ninja Van for WooCommerce is installed using get_plugins().
            $plugins = get_plugins();
            if (isset($plugins[Ninja_Van_MY_LEGACY_SLUG])) {
                echo '<div class="notice notice-warning"><p><strong>' . esc_html__('WARNING: An outdated version of', 'ninja-van-my') . ' <a href="'.esc_url(admin_url('plugins.php?s=Ninja%20Van%20for%20WooCommerce&plugin_status=all')).'">Ninja Van for WooCommerce</a> ' . esc_html__('is detected and deactivated to avoid conflicts.', 'ninja-van-my') . '</strong> '. esc_html__('Please remove this plugin as it no longer receive any future update!', 'ninja-van-my') . '</p></div>';
                // Deactivate if plugin is activated
                if (is_plugin_active(Ninja_Van_MY_LEGACY_SLUG) || class_exists('Ninja_Van_WooCommerce')) {
                    deactivate_plugins(Ninja_Van_MY_LEGACY_SLUG);
                }
            }
        }
    }

    public function cron_schedules( $schedules ) {
        $schedules['every_5_minutes'] = array(
            'interval' => 5*60,
            'display' => __( 'Every 5 Minutes', 'ninja-van-my' )
        );
        return $schedules;
    }

    public function init_plugin() {

        // Generate custom cron interval
        
        if (is_user_logged_in() && is_admin()) {
            add_action('admin_notices', [$this, 'check_woocommerce_installation']);
            add_action('admin_notices', [$this, 'check_extensions']);
            add_action('admin_notices', [$this, 'check_permalinks']);
            add_action('admin_notices', [$this, 'check_legacy_plugin']);
            new Ninja\Van\MY\Admin();
        }
        
        if (nv_my_get_config('auth_mode') == 'PLUGIN') {
            add_filter( 'cron_schedules', [$this, 'cron_schedules'] );
            add_action( 'ninja_van_refresh_token', 'nv_my_cron' );
        }

        if (get_option('Ninja_Van_WooCommerce_STATUS') == true) {
            new Ninja\Van\MY\WooCommerce();
            new Ninja\Van\MY\Worker\WooCommerceOption();
            new Ninja\Van\MY\Worker\Tracking();
            new Ninja\Van\MY\Worker\Callback();
        } else {
            new Ninja\Van\MY\Worker\Callback();
        }

        if (class_exists('WooCommerce')) {
            /**
             * Declare compatibility with WooCommerce HPOS
             * @link https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
             */
            add_action( 'before_woocommerce_init', function() {
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'ninja-van-my/ninja-van-my.php', true );
                }
            });
        }
    }

    public function activate() {
        $installer = new Ninja\Van\MY\Installer();
        $installer->run();

        // Deactivate if Ninja Van for WooCommerce is installed
        if (is_plugin_active(Ninja_Van_MY_LEGACY_SLUG) || class_exists('Ninja_Van_WooCommerce')) {
            deactivate_plugins(Ninja_Van_MY_LEGACY_SLUG);
        }
    }

    public function upgrade() {
        $installer = new Ninja\Van\MY\Installer();
        $installer->upgrade();
    }

    public static function uninstall() {
        $installer = new Ninja\Van\MY\Uninstaller();
        $installer->run();
    }
}

/**
 * Initialize the main plugin
 *
 * @return \Ninja_Van_MY
 */
function Ninja_Van_MY() {
    return Ninja_Van_MY::init();
}

/**
 * Run Ninja Van
 */
Ninja_Van_MY();