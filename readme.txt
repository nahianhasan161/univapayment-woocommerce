=== UnivaPayment for WooCommerce ===
Tags: bkash, nagad, payment, woocommerce, bangladesh
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Take bKash and Nagad payments on your own number, checked automatically from the SMS on your phone.

== Description ==

The money goes straight to your own bKash or Nagad number. UnivaPayment checks each payment from the SMS on your Android phone and confirms the WooCommerce order.

If a payment cannot be matched automatically, the order goes on hold until you approve it in your UnivaPayment dashboard or app.

== Installation ==

1. In WordPress, go to Plugins, Add New, Upload Plugin, and choose the zip.
2. Activate it, then open WooCommerce, Settings, Payments, UnivaPayment.
3. In your UnivaPayment dashboard, open Developers and create an API key. Paste it into the plugin.
4. In Developers, set the webhook URL to the address the plugin shows, and paste the secret into the plugin.
5. Enable the method and save. The store currency must be BDT.

Start with a sandbox key (upk_test_) to try it. Those orders complete without real money, so change to a live key before selling.
