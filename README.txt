=== Plugin Name ===
Contributors: austinginder
Donate link: https://austinginder.com
Tags: stackable, multitenancy, multi-tenant, domain-mapping
Requires at least: 3.0.1
Tested up to: 5.4.2
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Lightning fast ⚡ duplicate copies of your WordPress site.

== Description ==

No need to wait to deploy to separate environments. Simply one click to clone your existing site to a safe sandbox piggybacked onto your live site. Endless possibilities. ☄️

== Installation ==

1. Drag and drop `stackable.zip` on WordPress /wp-admin/ under Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Will Stackable work with my web host =

If your web host providers allows modifications to `wp-config.php` then Stackable should work automatically. Otherwise Stackable will prompt with steps to configure `wp-config.php` manually.

== Changelog ==

= 1.0 =
* Inital release of Stackable. Ability to add or remove stacked sites with database prefix `stacked_#_`.
* Feature: Clone existing site to new database prefix
* Feature: Add new empty site to new database prefix
* Feature: Domain mapping off or on
* Feature: Files shared or dedicated
