=== CODWarden: Stop Fake Cash on Delivery Orders for WooCommerce ===
Contributors: iqbal511138
Tags: cash on delivery, cod, fake orders, woocommerce, fraud
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stops fake and repeat-refused cash on delivery orders with phone checks, order limits, a duplicate guard and a refuser list.

== Description ==

Cash on delivery is how most people pay in Bangladesh, India, Pakistan, the Gulf and much of Africa. It is also where stores lose the most money: prank orders with fake numbers, the same customer ordering five times, and parcels that come back because the buyer never meant to pay. Every one of those costs you courier fees and blocks stock.

CODWarden checks every cash on delivery order before WooCommerce accepts it.

= What it does =

* **Real phone numbers only.** The billing phone must be a real mobile number for the customer's country, so the courier can call. Built-in rules for Bangladesh, India, Pakistan, UAE, Saudi Arabia, Egypt, Morocco, Nigeria, Kenya, Nepal and Sri Lanka, and a sensible length check everywhere else.
* **Order limits.** At most a few cash on delivery orders from the same phone or the same IP address in a time window you choose.
* **Duplicate guard.** The same cart from the same phone within minutes is stopped with a friendly message.
* **Refuser list.** Adds a "Refused" order status. Mark a returned parcel as Refused and the phone gets a strike; after the number of strikes you choose, cash on delivery is turned off for that phone. Add or remove numbers by hand too.
* **Amount rules.** Hide cash on delivery above an order total, or for first-time customers above a smaller total.
* **Clear dashboard.** Cash on delivery orders this week, what was stopped and why, and the phones on your refuser list.
* Works with the classic checkout and the block checkout, and with High-Performance Order Storage.
* Returning customers with a completed order and shop managers are never checked.
* Other payment methods are never touched.

= Privacy =

Phone numbers and IP addresses are stored only as keyed fingerprints plus a shortened label such as +8801712xxx678. CODWarden makes no external requests.

== Installation ==

1. Install and activate CODWarden. WooCommerce must be active, with the Cash on delivery payment method enabled.
2. Open CODWarden in the admin menu. The defaults are safe for most stores.
3. When a parcel comes back, set that order's status to "Refused".

== Frequently Asked Questions ==

= Will real customers be blocked? =

Only when a rule says so, and they always see a clear message and can pick another payment method. Logged-in customers who already received a parcel are never checked.

= My country is not in the phone list. =

CODWarden still requires 7 to 15 digits. You can turn the phone check off in Settings.

= Does it work behind Cloudflare? =

CODWarden reads the visitor address from the server. Developers can change that with the `codwarden_client_ip` filter.

= Does it send data anywhere? =

No.

== Screenshots ==

1. Overview.
2. Settings.
3. Phone list.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
