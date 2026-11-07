=== UnivaPayment for WooCommerce ===
Contributors: nahianhasan161
Tags: bkash, nagad, payment gateway, woocommerce, bangladesh
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take bKash and Nagad payments on your own number, checked automatically from the SMS on your phone.

== Description ==

The money goes straight to your own bKash or Nagad number. UnivaPayment checks each payment from the SMS on your Android phone and confirms the WooCommerce order.

If a payment cannot be matched automatically, the order goes on hold until you approve it in your UnivaPayment dashboard or app.

= Features =

* Customers pay with bKash or Nagad on a hosted payment page, in Bangla or English.
* Money goes directly to your own number. UnivaPayment never holds it.
* Orders are confirmed automatically through a signed webhook, and checked again with the API before they are marked paid.
* Works with the classic checkout and the block checkout, and with HPOS order storage.
* A sandbox mode for trying the full flow without real money.

= Requirements =

* A free UnivaPayment account.
* An Android phone that receives the bKash or Nagad SMS for your number, with the UnivaPayment app installed and paired.
* Store currency set to Bangladeshi Taka (BDT).

UnivaPayment is not affiliated with or endorsed by bKash Limited or Nagad. bKash and Nagad are trademarks of their respective owners.

== Installation ==

1. In WordPress, go to Plugins, Add New, Upload Plugin, and choose the zip.
2. Activate it, then open WooCommerce, Settings, Payments, UnivaPayment.
3. In your UnivaPayment dashboard, open Developers and create an API key. Paste it into the plugin.
4. In Developers, set the webhook URL to the address the plugin shows, and paste the secret into the plugin.
5. Enable the method and save. The store currency must be BDT.

Start with a sandbox key (upk_test_) to try it. Those orders complete without real money, so change to a live key before selling.

== External services ==

This plugin connects to the UnivaPayment service to create and check payments. It cannot work without it.

* Service: UnivaPayment, at https://univapayment-web.univapayment.workers.dev (the address can be changed in the plugin settings).
* When a customer places an order with this payment method, the plugin sends the order total, the currency, the order number, the WooCommerce order ID and the order's thank-you page address, together with your API key. No customer name, email, phone number or address is sent.
* When UnivaPayment sends a webhook to your site, and when a customer comes back to the thank-you page, the plugin asks the service for that payment's status, sending only the payment ID and your API key.
* On the hosted payment page, the customer enters the number they pay from. That page belongs to UnivaPayment, not to this plugin.

Terms of use: https://univapayment-web.univapayment.workers.dev/terms
Privacy policy: https://univapayment-web.univapayment.workers.dev/privacy

== Frequently Asked Questions ==

= Do I need a bKash or Nagad merchant account? =

No. A personal or merchant number both work. The money comes to the number you add in your UnivaPayment dashboard.

= Why does it need an Android phone? =

Payments are checked from the SMS bKash and Nagad send to your number. The UnivaPayment app forwards only those payment SMS, and only ones that arrive after you pair the phone.

= What happens if a payment cannot be matched? =

The order goes on hold. You can approve or reject the payment from the dashboard or the app, and the order updates automatically.

= Can I try it without real money? =

Yes. Use an API key that starts with upk_test_. Sandbox orders complete with a test SMS instead of a real payment.

= Why is the payment method not showing at checkout? =

Check that the method is enabled, an API key is saved, and the store currency is BDT.

== Changelog ==

= 1.0.1 =
* Text domain now matches the plugin directory slug, univapayment-for-woocommerce.
* Requires WordPress 6.5, for the WooCommerce dependency header.
* Readme lists the external service, with links to the terms and privacy policy.

= 1.0.0 =
* First release: hosted bKash and Nagad checkout, signed webhooks, block checkout and HPOS support.

== Upgrade Notice ==

= 1.0.1 =
Readme and translation fixes. No change to payments.

= 1.0.0 =
First release.
