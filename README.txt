=== Plugin Name ===
Contributors: austinginder
Donate link: https://austinginder.com
Tags: multitenancy, multi-tenant, domain-mapping
Requires at least: 3.0.1
Tested up to: 5.4.2
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Efficiently run many WordPress sies from a single WordPress installation.

== Description ==

No need to wait to deploy to separate environments. Simply one click to clone your existing site to a safe sandbox piggybacked onto your live site. Endless possibilities. ☄️

== Installation ==

1. Drag and drop `wp-freighter.zip` on WordPress /wp-admin/ under Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Will WP Freighter work with my web host =

If your web host providers allows modifications to `wp-config.php` then WP Freighter should work automatically. Otherwise WP Freighter will prompt with steps to configure `wp-config.php` manually.

== Changelog ==


= 1.0.1 = 
* Feature: Will automatically install default theme when creating new sites if needed.
* Improvement: Shows overlay loader while new sites are installing.
* Improvement: Added fields for domain or label on the new site dialog.
* Improvement: Compatibility for alternative wp-config.php location.
* Improvement: Force HTTPS in urls.
* Fixed: Inconsistent response of sites array.

= 1.0 =
* Initial release of WP Freighter. Ability to add or remove stacked sites with database prefix `stacked_#_`.
* Feature: Clone existing site to new database prefix
* Feature: Add new empty site to new database prefix
* Feature: Domain mapping off or on
* Feature: Files shared or dedicated
