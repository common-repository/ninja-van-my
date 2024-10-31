<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = nv_my_get_settings();
$sender = nv_my_sender_address();
$pickup = nv_my_pickup_address();

if (nv_my_get_config('status') && nv_my_get_config('status') == true) {
    $api_status = 1;
}else{
    $api_status = 0;
}

if (nv_my_get_config('type') && nv_my_get_config('type') == true) {
    $api_type = 1;
}else{
    $api_type = 0;
}

if (nv_my_get_config('client_id')) {
    $client_id = nv_my_get_config('client_id');
}else{
    $client_id = '';
}

if (nv_my_get_config('client_secret')) {
    $client_secret = nv_my_get_config('client_secret');
}else{
    $client_secret = '';
}

if (nv_my_get_config('auth_mode')) {
    $auth_mode = nv_my_get_config('auth_mode');
}else{
    $auth_mode = Ninja_Van_MY_OAUTH;
}

if ($auth_mode == 'PLUGIN') {
    $access_code = nv_my_get_config('access_code');
}else{
    $access_code = '';
}

/**
 * If refresh token is available, show it. Otherwise, show error message with link to re-connect
 */
if ($refresh_token = get_transient('ninja_van_refresh_token')) {
    $refresh_token_text = $refresh_token;
}else if ($access_code) {
    $refresh_token_error = get_transient('ninja_van_refresh_token_error');
    $refresh_token_text = ($refresh_token_error) ? $refresh_token_error : "Connection Expired. Please Re-login!";
} else {
    $refresh_token_text = "";
}

if (nv_my_check_cron('ninja_van_refresh_token')) {
    $cron_running = true;
} else {
    $cron_running = false;
}

if ($token = get_transient('ninja_van_token')) {
    $access_token = $token;
}else{
    $access_token = "Token Not Available!";
}

if ($expired_at = get_transient('ninja_van_token_expiry')) {
    $access_token_expired_text = nv_my_gmdate($expired_at, 'validity', true);
} else {
    $access_token_expired_text = "Expiry Date Not Available!";
}

$store_default_country = nv_my_woocommerce_default_country();

if ($store_default_country == 'MY') {
    $store_location = 'MY';
}else {
    $store_location = 'XX';
}

?>

<div class="wrap">
    <h1 class="wp-heading"><?php esc_html_e('Settings', 'ninja-van-my'); ?> - <?php esc_html_e('Ninja Van', 'ninja-van-my'); ?></h1>
    <hr class="wp-header-end">
    <?php if(isset($_GET['save']) && $_GET['save'] == true): ?>
        <div id="message" class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings Saved Successfully!', 'ninja-van-my'); ?></p></div>
    <?php elseif(isset($_GET['logout']) && $_GET['logout'] == true): ?>
        <div id="message" class="notice notice-success is-dismissible"><p><?php esc_html_e('Logout Successfully!', 'ninja-van-my'); ?></p></div>
    <?php elseif(isset($_GET['login']) && $_GET['login'] == true): ?>
        <div id="message" class="notice notice-success is-dismissible"><p><?php esc_html_e('Authorization Successful!', 'ninja-van-my'); ?></p></div>
    <?php endif ?>
    <form id="ninja_van_form_submit" action="" method="POST">
        <!-- Tabs -->
        <h2 class="nav-tab-wrapper nv-setting-tabs">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php echo esc_html__('General', 'ninja-van-my'); ?></a>
            <a href="#shipping" class="nav-tab" data-tab="shipping"><?php echo esc_html__('Shipping & AWB', 'ninja-van-my'); ?></a>
            <a href="#address" class="nav-tab" data-tab="address"><?php echo esc_html__('Pickup Address', 'ninja-van-my'); ?></a>
            <a href="#misc" class="nav-tab" data-tab="misc"><?php echo esc_html__('Miscellaneous', 'ninja-van-my'); ?></a>
            <a href="#history" class="nav-tab" data-tab="history"><?php echo esc_html__('Order History', 'ninja-van-my'); ?></a>
        </h2>
        <!-- General Table -->
        <div class="ninja_van_form ninja_van_form_mw" id="form-general">
            <table class="form-table">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title" style="text-decoration:none">
                            <?php echo wp_kses_post(nv_my_settings_banner()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('General Settings', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="name"><?php esc_html_e('Status', 'ninja-van-my'); ?> <span style="color: red">*</span></label>
                        </th>
                        <td>
                            <?php
                                $options = array(
                                    1 => __('Enable', 'ninja-van-my'),
                                    0 => __('Disable', 'ninja-van-my')
                                );
                            ?>
                            <select name="status" id="status">
                                <?php foreach($options as $value => $name): ?>
                                    <?php if ($value == $api_status): ?>
                                        <option selected value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="name"><?php esc_html_e('Environment', 'ninja-van-my'); ?> <span style="color: red">*</span></label>
                        </th>
                        <td>
                            <?php
                                $options = array(
                                    1 => __('Production (LIVE)', 'ninja-van-my'),
                                    0 => __('Sandbox (TEST)', 'ninja-van-my')
                                );

                                if ($auth_mode == 'PLUGIN' && Ninja_Van_MY_DEVELOPMENT == false) {
                                    // Remove Sandbox option for Plugin API
                                    unset($options[0]);
                                }
                            ?>
                            <select name="type" id="type">
                                <?php foreach($options as $value => $name): ?>
                                    <?php if ($value == $api_type): ?>
                                        <option selected value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="store_location"><?php esc_html_e('Origin Country', 'ninja-van-my'); ?></label>
                        </th>
                        <?php 
                            $options = nv_my_get_origin_countries();
                        ?>
                        <td>
                            <select name="store_location" id="store_location" disabled>
                                <?php foreach($options as $value => $name): ?>
                                    <?php if ($value == $store_location): ?>
                                        <option selected value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <hr>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('API Settings', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <?php if ($auth_mode == 'PLUGIN'): ?>
                    <!-- Plugin API -->
                    <tr>
                        <th scope="row">
                            <label for="client_auth"><?php esc_html_e('Authorize', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <a href="#" class="button button-primary nv-authorize"><?php echo empty($access_code) ? esc_html__('Connect', 'ninja-van-my') : ((!$refresh_token) ? esc_html__('Re-connect', 'ninja-van-my') : esc_html__('Disconnect', 'ninja-van-my')); ?></a>
                            <input type="hidden" name="client_auth" id="client_auth" class="regular-text" value="<?php echo esc_url((!empty($access_code) && $refresh_token) ? nv_my_get_disconnect_url() : nv_my_get_authorize_url()); ?>" readonly>
                            <div class="text-center">
                                <?php if (!empty($access_code)): ?>
                                    <p class="text-info" style="text-align: left !important;"><?php echo empty($refresh_token) ? esc_html__('Failed to establish connection. Please re-connect', 'ninja-van-my') : esc_html__('To re-connect, disconnect current session', 'ninja-van-my'); ?></p>
                                <?php else: ?>
                                    <p class="text-danger" style="text-align: left !important;"><?php esc_html_e('Authorization required to continue', 'ninja-van-my'); ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="access_code"><?php esc_html_e('Status', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                if (!empty($access_code) && !$refresh_token) {
                                    $plugin_status = $refresh_token_text ?? 'Connection Expired';
                                } else if (!empty($access_code)) {
                                    $plugin_status = 'Connected to NV ID ' . $access_code;
                                } else {
                                    $plugin_status = 'Not Connected';
                                }
                            ?>
                            <input type="text" name="access_code" id="access_code" class="regular-text" value="<?php echo esc_attr($plugin_status); ?>" readonly>
                            <?php if (!empty($access_code) && !$refresh_token && is_using_wp_cron()) : ?>
                            <div class="text-center ninja_van_tooltip">
                                <?php printf('<a href="%s" target="_blank" class="text-warning">%s</a>', esc_url('https://www.siteground.com/tutorials/wordpress/real-cron-job/') , esc_html(__('Had to reconnect many times?', 'ninja-van-my'))); ?><span class="ninja_van_tooltip_text"><?php esc_html_e(__('The Ninja Van plugin requires Cron for a stable connection. WordPress Cron depends on site visits, which can be unreliable for sites with few visitors. For consistent performance, configure a real Cron Job.', 'ninja-van-my')); ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <!-- Direct API -->
                    <tr>
                        <th scope="row">
                            <label for="client_id"><?php esc_html_e('Client ID', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="client_id" id="client_id" class="regular-text" value="<?php echo esc_attr($client_id) ?>" <?php echo ($auth_mode == 'PLUGIN' ? esc_attr('readonly') : ''); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="client_secret"><?php esc_html_e('Client Key', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="client_secret" id="client_secret" class="regular-text" value="<?php echo esc_attr($client_secret) ?>" <?php echo ($auth_mode == 'PLUGIN' ? esc_attr('readonly') : ''); ?>>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Access Token', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" value="<?php echo esc_attr($access_token); ?>" readonly>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Token Expired In', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" value="<?php echo esc_attr($access_token_expired_text); ?>" readonly>
                            <?php if (!empty($access_code)): ?>
                            <div class="text-center">
                            <?php printf('<p class="text-%s">%s</p>', esc_attr($cron_running ? 'info' : 'danger'), esc_html(sprintf(__('Background Refresh: %s', 'ninja-van-my'), $cron_running ? 'Active' : 'Inactive'))); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <hr>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('Advanced Settings', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Cash On Delivery (COD)', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="cash_on_delivery" name="cash_on_delivery" <?php if($settings->cash_on_delivery == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                            <div class="text-center">
                                <p class="text-info" style="text-align: left !important;"><?php esc_html_e('Enable Cash On Delivery shipments', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <!-- When COD is enabled -->
                    <tr class="cod_required">
                        <th scope="row">
                            <label><?php esc_html_e('COD Coverage Validation', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="validate_cod" name="validate_cod" <?php if($settings->validate_cod == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                            <div class="text-center">
                                <p class="text-info" style="text-align: left !important;"><?php esc_html_e('Enabling this will decline address(es) that does not support COD', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Domestic Shipment', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="domestic_shipment" name="domestic_shipment" <?php if($settings->domestic_shipment == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                            <div class="text-center">
                                <p class="text-info" style="text-align: left !important;"><?php esc_html_e('Enable local delivery between states/districts', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('International Shipment', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="international_shipment" name="international_shipment" <?php if($settings->international_shipment == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                            <div class="text-center">
                                <p class="text-info" style="text-align: left !important;"><?php esc_html_e('Enable international delivery', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Automatic Push to Ninja Van', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <div class="slider">
                                <input type="checkbox" id="push_to_ninja" name="push_to_ninja" <?php if($settings->push_to_ninja == 'automatic'): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr class="push_to_ninja_required">
                        <th scope="row">
                            <label><?php esc_html_e('Push to Ninja Van when Order Status', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                $push_when = array(
                                    'pending' => __('Pending', 'ninja-van-my'),
                                    'processing' => __('Processing (default)', 'ninja-van-my'),
                                    'on-hold' => __('On Hold', 'ninja-van-my'),
                                    'completed' => __('Completed', 'ninja-van-my'),
                                );
                            ?>
                            <select name="push_when" id="push_when">
                                <?php foreach($push_when as $type => $name): ?>
                                    <?php if ($type == $settings->push_when): ?>
                                        <option selected value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php if ($auth_mode == 'DIRECT' ): ?>
                    <!-- Hide for Plugin API -->
                    <tr>
                        <th scope="row">
                            <label for="webhook_url"><?php esc_html_e('Webhook URL (Endpoint)', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <textarea name="" id="" cols="30" rows="3" readonly><?php echo esc_url(nv_my_get_webhook_url()); ?></textarea>
                            <div class="text-center">
                                <p class="text-info">
                                    <?php
                                    echo wp_kses(
                                        __('Webhook URL for <strong>Pending Pickup</strong>, <strong>Arrived at Transit Hub</strong>, <strong>On Vehicle For Delivery</strong>, <strong>Cancelled</strong>, <strong>Returned To Sender</strong>, and <strong>Delivered</strong> Events.', 'ninja-van-my'),
                                        array('strong' => array())
                                    );
                                    ?>
                                </p>
                                <p class="text-info"><strong><?php esc_html_e('Preferred Version: 2.0', 'ninja-van-my'); ?></strong></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($access_code)) : ?>
                    <tr>
                        <th scope="row">
                            <label for="webhook_sync"><?php esc_html_e('Webhook Sync', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <a href="#" class="button button-primary" id="nv-my-webhook-sync" <?php echo (!empty($access_code) && !$refresh_token) ? esc_attr('disabled') : ''; ?>><?php esc_html_e('Sync Webhook', 'ninja-van-my'); ?></a>
                            <div class="text-center">
                                <p class="text-info" style="text-align:left !important">
                                    <?php 
                                    echo wp_kses(
                                        __('Use <strong>Webhook Sync</strong> to configure webhook automatically. Hassle free!', 'ninja-van-my'),
                                        array('strong' => array())
                                    );
                                    ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="ninja_van_footer">
                <div class="ninja_van_footer_text">
                    <?php esc_html_e("Want to notify your customer? Send order status update and tracking number through WhatsApp.", "ninja-van-my"); ?> <a href="https://wasapbot.my/" target="_blank"><?php esc_html_e("Click Here", 'ninja-van-my'); ?></a>
                </div>
            </div>
        </div>
        <!-- API Table -->
        <div class="ninja_van_form ninja_van_form_mw" id="form-shipping">
            <table class="form-table">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title" style="text-decoration:none">
                            <?php echo wp_kses_post(nv_my_settings_banner()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('Shipping Settings', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Service Level', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                $service_levels = array(
                                    'standard' => __('Standard (default)', 'ninja-van-my'),
                                );
                            ?>
                            <select name="service_level" id="service_level">
                                <?php foreach($service_levels as $type => $name): ?>
                                    <?php if ($type == $settings->service_level): ?>
                                        <option selected value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="international_shipment_required">
                        <th scope="row">
                            <label for="service_code"><?php esc_html_e('Service Code', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="service_code" id="service_code" class="regular-text" value="<?php echo esc_attr($settings->service_code) ?>">
                            <div class="text-center">
                                <p class="text-info"><?php echo esc_html__('For international shipments. By default, Ninja Van uses built-in service code available for each countries. This will override the default', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Pickup Required', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Only exists in POST data when checked: Use isset() -->
                            <div class="slider">
                                <input type="checkbox" id="pickup_required" name="pickup_required" <?php if($settings->pickup_required == true): ?> checked <?php endif; ?>>
                                <span></span> <!-- This is the actual slider -->
                            </div>
                        </td>
                    </tr>
                    <tr class="pickup_required">
                        <th scope="row">
                            <label for="pickup_location"><?php esc_html_e('Default Pickup Location', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                $options = array(
                                    1 => __('Pickup Address', 'ninja-van-my'),
                                    0 => __('Sender Address (default)', 'ninja-van-my')
                                );
                            ?>
                            <select name="pickup_location" id="pickup_location">
                                <?php foreach($options as $value => $name): ?>
                                    <?php if ($value == $settings->pickup_location): ?>
                                        <option selected value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="text-center">
                                <p class="text-info"><?php echo esc_html__('Select the default pickup location for shipments', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr class="pickup_required">
                        <th scope="row">
                            <label><?php esc_html_e('Pickup Service Level', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <select name="pickup_service_level" id="pickup_service_level">
                                <option <?php if($settings->pickup_service_level == 'standard'): ?> selected <?php endif; ?> value="standard"><?php echo esc_html__('Standard (default)', 'ninja-van-my'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="pickup_required">
                        <th scope="row">
                            <label><?php esc_html_e('Pickup Time Slots', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                $pickup_time_slots = array(
                                    '09:00|12:00' => '09:00 to 12:00',
                                    '09:00|18:00' => '09:00 to 18:00',
                                    '09:00|22:00' => '09:00 to 22:00',
                                    '12:00|15:00' => '12:00 to 15:00 (default)',
                                    '15:00|18:00' => '15:00 to 18:00',
                                    '18:00|22:00' => '18:00 to 22:00',
                                );
                            ?>
                            <select name="pickup_time_slot" id="pickup_time_slot">
                                <?php foreach($pickup_time_slots as $type => $name): ?>
                                    <?php if ($type == $settings->pickup_time_slot): ?>
                                        <option selected value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php else: ?>
                                        <option  value="<?php echo esc_attr($type); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <!-- @modified Compulsory. Max 3 chars. Auto generate if not specified -->
                    <tr>
                        <th scope="row">
                            <label for="tracking_prefix"><?php esc_html_e('Tracking Prefix', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input maxlength="3" type="text" name="tracking_prefix" id="tracking_prefix" class="regular-text" value="<?php echo esc_attr($settings->tracking_prefix); ?>">
                            <div class="text-center">
                                <p class="text-info"><?php echo esc_html__('Limit: 3 Character(s). Remaining characters will be removed', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <hr>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('AWB Settings', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('AWB: Printer Type', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <?php
                                $options = array(
                                    1 => 'A4',
                                    2 => __('A6 - Thermal (default)', 'ninja-van-my')
                                );
                            ?>
                        <select name="awb_printer_type" id="awb_printer_type">
                            <?php foreach($options as $value => $name): ?>
                                <?php if ($value == $settings->awb_printer_type): ?>
                                    <option selected value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                <?php else: ?>
                                    <option  value="<?php echo esc_attr($value); ?>"><?php echo esc_html($name); ?></option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Seller Information', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_seller_information" name="awb_seller_information" <?php if($settings->awb_seller_information == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Phone Number', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show: 1 | Hide (Default): 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_phone_number" name="awb_phone_number" <?php if($settings->awb_phone_number == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Order Note', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show: 1 | Hide (Default): 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_order_note" name="awb_order_note" <?php if($settings->awb_order_note == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Order Items', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_order_item" name="awb_order_item" <?php if($settings->awb_order_item == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Order Item SKUs', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show: 1 | Hide (Default): 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_order_item_sku" name="awb_order_item_sku" <?php if($settings->awb_order_item_sku == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Channel URL', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show: 1 | Hide (Default): 0 -->
                            <div class="slider">
                                <input type="checkbox" id="awb_channel_url" name="awb_channel_url" <?php if($settings->awb_channel_url == true): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Display Seller Logo', 'ninja-van-my') ?></label>
                        </th>
                        <td>
                            <?php
                                $image_url = nv_my_get_media($settings->awb_seller_logo, true);
                                $is_valid_url = (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL));
                                $is_valid_image_extension = in_array(pathinfo($image_url, PATHINFO_EXTENSION), array('jpg', 'jpeg', 'png'));
                                $image = ($is_valid_url && $is_valid_image_extension) ? $image_url : '';
                            ?>
                            <a href="#" class="button" id="upload_image_button" style="display:<?php echo esc_attr(($is_valid_url && !empty($image)) ? 'none' : 'block') ?>;">Upload image</a>
                            <div id="image_container" style="display: <?php echo esc_attr(($is_valid_url && !empty($image)) ? 'block' : 'none') ?>;">
                                <img id="image_preview" src="<?php echo esc_url($image) ?>" width="100" height="100" style="display: block">
                                <a href="#" id="remove_image_button" style="display:block"><?php esc_html_e('Remove image', 'ninja-van-my'); ?></a>
                            </div>
                            <input type="hidden" id="awb_seller_logo" name="awb_seller_logo" value="<?php echo esc_url($image) ?>">
                            <div class="text-center">
                                <p class="text-info"><?php esc_html_e('Recommended image size: 100px x 100px. Image will be resized to 100px x 100px.', 'ninja-van-my'); ?></p>
                                <p class="text-warning awb_seller_logo_notice" style="display: <?php echo esc_attr((!$is_valid_url && !empty($image)) ? 'block' : 'none') ?>"><?php esc_html_e('Invalid image type. Please upload a JPG, JPEG, or PNG image.', 'ninja-van-my') ?></p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pickup Addreses -->
        <div class="ninja_van_form ninja_van_form_mw" id="form-address">
            <table class="form-table">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title" style="text-decoration:none">
                            <?php echo wp_kses_post(nv_my_settings_banner()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('Sender Pickup Details', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                </tbody>
            </table>
            <h2 class="nav-tab-wrapper nv-setting-child-tabs">
                <a href="#address1" class="nav-tab nav-tab-active" data-tab="address1"><?php echo esc_html__('Sender Address', 'ninja-van-my'); ?></a>
                <a href="#address2" class="nav-tab" data-tab="address2"><?php echo esc_html__('Pickup Address', 'ninja-van-my'); ?></a>
            </h2>
            <!-- Address 1: Sender Address -->
            <table class="form-table pickup_address_form" id="table-address1" style="display: table;">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title">
                            <div>
                                <span class="text-info text-title-small"><?php esc_html_e('Sender Address', 'ninja-van-my'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_name"><?php esc_html_e('Name', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_name" id="sender_name" class="regular-text" value="<?php echo esc_attr($sender['name']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_phone"><?php esc_html_e('Phone Number', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_phone" id="sender_phone" class="regular-text" value="<?php echo esc_attr($sender['phone_number']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_email"><?php esc_html_e('Email Address', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_email" id="sender_email" class="regular-text" value="<?php echo esc_attr($sender['email']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_address1"><?php esc_html_e('Address', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_address1" id="sender_address1" class="regular-text" value="<?php echo esc_attr($sender['address']['address1']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_address2"><?php esc_html_e('Street Address', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="sender_address2" id="sender_address2" class="regular-text" value="<?php echo esc_attr($sender['address']['address2']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_city"><?php esc_html_e('City', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_city" id="sender_city" class="regular-text" value="<?php echo esc_attr($sender['address']['city']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_postcode"><?php esc_html_e('Postcode', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_postcode" id="sender_postcode" class="regular-text" value="<?php echo esc_attr($sender['address']['postcode']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_state"><?php esc_html_e('State', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <select name="sender_state" id="sender_state">
                                <?php foreach (nv_my_get_states() as $code => $name): ?>
                                    <?php if ($sender['address']['state'] == $code): ?>
                                        <option selected value="<?php echo esc_attr($code) ?>"><?php echo esc_html($name) ?></option>
                                    <?php else: ?>
                                        <option value="<?php echo esc_attr($code) ?>"><?php echo esc_html($name) ?></option>
                                    <?php endif; ?>
                                    
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sender_country"><?php esc_html_e('Country', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="sender_country" id="sender_country" class="regular-text" value="Malaysia" readonly>
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- Address 2: Pickup Address -->
            <table class="form-table pickup_address_form" id="table-address2" style="display: none;">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title">
                            <div>
                                <span class="text-info text-title-small"><?php esc_html_e('Pickup Address', 'ninja-van-my'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_name"><?php esc_html_e('Name', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_name" id="pickup_name" class="regular-text" value="<?php echo esc_attr($pickup['name']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_phone"><?php esc_html_e('Phone Number', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_phone" id="pickup_phone" class="regular-text" value="<?php echo esc_attr($pickup['phone_number']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_email"><?php esc_html_e('Email Address', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_email" id="pickup_email" class="regular-text" value="<?php echo esc_attr($pickup['email']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_address1"><?php esc_html_e('Address', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_address1" id="pickup_address1" class="regular-text" value="<?php echo esc_attr($pickup['address']['address1']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_address2"><?php esc_html_e('Street Address', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_address2" id="pickup_address2" class="regular-text" value="<?php echo esc_attr($pickup['address']['address2']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_city"><?php esc_html_e('City', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_city" id="pickup_city" class="regular-text" value="<?php echo esc_attr($pickup['address']['city']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_postcode"><?php esc_html_e('Postcode', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_postcode" id="pickup_postcode" class="regular-text" value="<?php echo esc_attr($pickup['address']['postcode']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_state"><?php esc_html_e('State', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <select name="pickup_state" id="pickup_state">
                                <?php foreach (nv_my_get_states() as $code => $name): ?>
                                    <?php if ($pickup['address']['state'] == $code): ?>
                                        <option selected value="<?php echo esc_attr($code) ?>"><?php echo esc_html($name) ?></option>
                                    <?php else: ?>
                                        <option value="<?php echo esc_attr($code) ?>"><?php echo esc_html($name) ?></option>
                                    <?php endif; ?>
                                    
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pickup_country"><?php esc_html_e('Country', 'ninja-van-my'); ?> <span class="text-danger">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="pickup_country" id="pickup_country" class="regular-text" value="Malaysia" readonly>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Misc -->
        <div class="ninja_van_form ninja_van_form_mw" id="form-misc">
            <table class="form-table">
                <tbody>
                    <tr>
                        <td colspan="2" class="text-title" style="text-decoration:none">
                            <?php echo wp_kses_post(nv_my_settings_banner()); ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-center">
                            <h3 class="text-title">
                                <?php esc_html_e('Miscellaneous', 'ninja-van-my'); ?>
                            </h3>
                        </th>
                    </tr>
                    <!-- Exchange Rates -->
                    <?php 
                        $shipping_countries = nv_my_get_woocommerce_international_shipping();
                        if (!empty($shipping_countries)):
                        foreach ($shipping_countries as $country_code => $country_name):
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="exchange_rate_sg"><?php printf(esc_html__('Exchange Rate %s (%s)', 'ninja-van-my'), esc_html($country_code), esc_html(nv_my_currency($country_code, true))); ?></label>
                        </th>
                        <td>
                            <input type="number" step="0.01" name="<?php echo esc_html('exchange_rate_' . strtolower($country_code)); ?>" id="<?php echo esc_html('exchange_rate_' . strtolower($country_code)); ?>" class="regular-text" value="<?php echo number_format($settings->exchange_rates[$country_code], 2, '.', ''); ?>">
                            <div class="text-center">
                                <p class="text-info"><?php printf(esc_html__('%s to %s Exchange Rate for COD collection from %s', 'ninja-van-my'), esc_html(nv_my_currency($store_default_country)), esc_html(nv_my_currency($country_code)), esc_html($country_name)); ?> </p>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <th scope="row">
                                <label for="exchange_rate_sg"><?php esc_html_e('Exchange Rate SG (S$)', 'ninja-van-my'); ?></label>
                            </th>
                            <td>
                                <input type="number" step="0.01" name="exchange_rate_sg" id="exchange_rate_sg" class="regular-text" value="<?php echo number_format($settings->exchange_rates['SG'], 2, '.', ''); ?>">
                                <div class="text-center">
                                    <p class="text-info"><?php esc_html_e('MYR to SGD Exchange Rate for COD collection from Singapore', 'ninja-van-my'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <!-- Switch to OAuth API (Display on Override) -->
                    <?php if ($auth_mode != 'PLUGIN' || Ninja_Van_MY_LEGACY_OVERRIDE): ?>
                    <tr>
                        <th colspan="2">
                            <hr>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Switch to OAuth API', 'ninja-van-my'); ?></label>
                        </th>
                        <td>
                            <!-- Show (Default): 1 | Hide: 0 -->
                            <div class="slider">
                                <input type="checkbox" id="auth_mode" name="auth_mode" <?php if($auth_mode == 'PLUGIN'): ?> checked <?php endif; ?>>
                                <span></span>
                            </div>
                            <!-- Static -->
                            <input type="hidden" id="auth_mode_static" name="auth_mode_static" value="<?php echo esc_attr($auth_mode); ?>">
                            <div class="text-center">
                                <p class="text-info" style="text-align: left !important;"><?php echo esc_html__('Use a brand new OAuth API to seamlessly connect to your Ninja Van account!', 'ninja-van-my'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Order History -->
        <div class="ninja_van_form ninja_van_form_mw ninja_van_order_history" id="form-history">
            <table class="form-table">
                <thead>
                    <tr>
                        <th colspan="8" class="text-title" style="text-decoration:none">
                            <?php echo wp_kses_post(nv_my_settings_banner()); ?>
                        </th>
                    </tr>
                </thead>
            </table>
            <div class="ninja_van_order_history_table">
                <table class="wp-list-table widefat fixed striped posts" style="width: 100%" id="orders-history-paged">
                    <thead>
                        <tr>
                            <th><strong><?php esc_html_e('WC Order ID', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('WC Status', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('Customer Name', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('Amount', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('COD', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('Tracking Number', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('Date', 'ninja-van-my'); ?></strong></th>
                            <th><strong><?php esc_html_e('Action', 'ninja-van-my'); ?></strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $args = array(
                                'meta_key'     => 'ninja_van_tracking_number',
                                'meta_compare' => 'EXISTS',
                                'orderby' => 'date',
                                'order' => 'DESC',
                                'posts_per_page' => 100
                            );
                            $orders = wc_get_orders( $args );
                        ?> 
                        <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php 
                            $address = (object) $order->get_address();
                            $status = $order->get_status();
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url(get_edit_post_link($order->get_id())) ?>"><strong><?php echo esc_html($order->get_id()) ?></strong></a></td>
                                <td><div class="<?php echo esc_attr('nv-status status-' . $status); ?>"><span><?php echo esc_html(nv_my_order_status($status)) ?></span></div></td>
                                <td><strong><?php echo esc_html(trim($address->first_name.' '.$address->last_name)); ?></strong></td>
                                <?php 
                                    $escape_formatted_order_total = array(
                                        'span' => array(
                                            'class' => array(),
                                        ),
                                        'bdi' => array(),
                                        // Add other tags and attributes you want to allow here.
                                    );
                                ?>
                                <td><?php echo wp_kses($order->get_formatted_order_total(), $escape_formatted_order_total); ?></td>
                                <td>
                                    <?php if ($order->get_payment_method() == 'cod'): ?>
                                        <span style="color: green"><?php esc_html_e('YES', 'ninja-van-my') ?></span>
                                    <?php else: ?>
                                        <span style="color: red"><?php esc_html_e('NO', 'ninja-van-my') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $escape_tracking_number = array(
                                            'a' => array(
                                                'href' => array(),
                                                'title' => array(),
                                                'target' => array(),
                                            )
                                            // Add other tags and attributes you want to allow here.
                                        );
                                        echo wp_kses(nv_my_track_order_link($order->get_meta('ninja_van_tracking_number')), $escape_tracking_number);
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $date =  $order->get_date_created();
                                    echo esc_html($date->date("F j, Y, g:i:s A"))
                                    ?>
                                </td>
                                <td>
                                    <div>
                                        <a title="Track Order" target="blank" href="<?php echo esc_url(nv_my_track_order_link($order->get_meta('ninja_van_tracking_number'), true)) ?>">
                                            <span class="nv-status nv-status-button"><span class="dashicons dashicons-airplane"></span></span>
                                        </a>
                                    </div>
                                    <div>
                                        <a title="Download AWB" href="<?php echo esc_url(wp_nonce_url(home_url( add_query_arg( 'download_awb', $order->get_id() ) ), 'nv-settings-awb')); ?>">
                                            <span class="nv-status nv-status-button"><span class="dashicons dashicons-printer"></span></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <th colspan="8">
                                    <img src="<?php echo esc_url(nv_my_assets().'/images/404.png'); ?>" alt="" style="width: 90%; max-width: 240px; margin: 21px 0">
                                    <h3><?php esc_html_e('Order History Not Found!', 'ninja-van-my'); ?></h3>
                                </th>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
            wp_nonce_field( 'save_settings' );
            submit_button( __('Save Settings', 'ninja-van-my'), 'primary', 'save_settings' );
        ?>
    </form>
</div>