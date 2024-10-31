=== Ninja Van (MY) ===
Contributors: kyraoki, ninjavanmy
Tags: ninja van, ninjavan
Requires at least: 5.6
Tested up to: 6.6.1
Requires PHP: 7.2
Stable tag: 1.1.4
License: GPL-3.0-or-later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Easily connect your WooCommerce store to your Ninja Van Dashboard for automatic tracking order creation and updates — no manual entry required!

== Description ==

The Ninja Van (MY) plugin allows you to seamlessly integrate your WooCommerce store with the Ninja Van Dashboard. With this plugin, you can automate the process of tracking order creation, updates and cancellation, eliminating the need for manual data entry. 

Simply install the plugin, configure the settings, and enjoy the convenience of automatic order tracking. This plugin is perfect for businesses using Ninja Van MY services in Malaysia.

## Use of 3rd Party Service

Ninja Van (MY) uses an external service to connect this plugin to the Ninja Van Official dashboard. 

This enables us to manage your WooCommerce orders straight to your Ninja Van dashboard. 

By connecting to this external service, we are able to create and cancel tracking number for respective order and configure your dashboard webhook to receive order status update automatically.

In addition, the tracking number displayed are linked to Ninja Van Official Track & Trace.

For transparency, we use the following endpoints:

* https://api.ninjavan.co/
* https://api-sandbox.ninjavan.co/
* https://www.ninjavan.co/en-my/tracking

Learn about [how this plugin works](https://sites.google.com/ninjavan.co/woocommerce/connect?authuser=0) and [our privacy policy](https://www.ninjavan.co/en-my/privacy-policy)

## Privacy Policy

The use of Ninja Van (MY) plugin adhere to our privacy policy, which can be found at [https://www.ninjavan.co/en-my/privacy-policy](https://www.ninjavan.co/en-my/privacy-policy).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ninja-van-my` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Ninja Van MY screen to configure the plugin.

== Frequently Asked Questions ==

Q: *Can I use Next Day & Same Day Delivery through the plugin?*
A: Not at the moment, probably will be included in the next built update

Q: *I have more than 100 orders to fulfill daily but I can only select 20 orders at a time, can I increase the number?*
A: You can do the following:
1. You can increase your Website Admin Page Order Limit view to more than 20 orders per page
2. Or you can use the "Auto Push Order to Ninja Van" Feature to ease your Shipping Order Creation Process

== Changelog ==

= 1.0.0 =
Initial release
### What's New
* Support for High-Performance Order Storage (HPOS) for WooCommerce
* Added support for Webhook V2
* Added Cancel Order
* Added option to add logo in AWB
* Added Cash on Delivery (COD) Address Validation

### Changes
* Better UI
* Removed support for Webhook V1 and V1.1
* Fix shipping postcode priority issue. Going forward, we will prefer shipping postcode when filled over billing
* Fix order status updates for orders that already completed

= 1.0.1 =
Bug Updates
### Changes
* Fix an issue where AWB did not use shipping address if first or last name was empty. This new fix would only checks for first name
* Fix an issue with order creation error when shipping field is empty

= 1.0.2 =
Bug Updates
### Changes
* Fix an issue where AWB is unable to download due to redirect behavior of HPOS enabled shop page upon executing bulk action

= 1.0.3 =
Bug Updates
### Changes
* Fix Create Booking error for international shipping

= 1.0.4 =
Bug Updates
### Changes
* Fix Create Booking -> Cash on Deliver (COD) error for international shipping

= 1.0.5 =
Bug Updates
### Changes
* Add address field validation per country for Create Booking
* Fix an issue where AWB does not start download from edit order & setting pages

= 1.0.6 =
Bug Updates
### Changes
* Fix many `wc_doing_it_wrong` issues
* Fix an issue on some website that are unable to render url image

= 1.0.7 =
Small Updates
### Changes
* Improve error description for Ninja Van order page column
* Other very tiny improvements

= 1.1.0 =
Major Release
### What's New
* Added an OAuth 2.0 authentication (Beta) to seamlessly connect your Ninja Van account. Switch over to the new authentication method in the settings.

### Changes
* Added Exchange Rate field for more Ninja Van supported countries
* More optimizations!

= 1.1.1. =
Small Updates
### Changes
* Small bug fixes that prevents user from be able to save settings
* Tips and tricks

= 1.1.2 =
Bug Updates
### Changes
* Fix an issue where Cash on Delivery were blocked for international shipping

= 1.1.3 =
Bug Updates
### What's New
* Added an option to force validate Cash on Delivery orders
* Service Code(s) for international shipment
* Cash on Delivery order coverage has been updated to the latest

### Changes
* Callback URL does not work with subdirectory installations. We have implemented a fix to address this issue
* Plugin API is not the default authentication
* Once authenticated using Plugin API, webhooks are automatically synced
* Status "Arrived at Transit Hub" is replaced by "Arrived at Origin Hub"

= 1.1.4 =
### Changes
* Small optimizations
* Order item that cost zero (0) does not included in the parcel job

== Screenshots ==

1. General Settings
2. Shipping & Air Waybill (AWB) Settings
3. Pickup Address Settings
4. Miscellaneous Settings
5. Order History
6. Bulk Actions (Push to Ninja Van, Generate AWBs & Cancel Orders)

== Upgrade Notice ==

= 1.0.0 =
This version creates a Cash on Delivery (COD) addresses table which are used to verify COD orders. Upon uninstallation of this plugin, the table will be removed.

= 1.1.3 =
Update Cash on Delivery (COD) address coverage to the latest version