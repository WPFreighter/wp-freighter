=== Plugin Name ===
Contributors: austinginder
Donate link: https://austinginder.com
Tags: multitenancy, multi-tenant, domain-mapping
Requires at least: 3.0.1
Tested up to: 5.5.1
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Efficiently run many WordPress sites from a single WordPress installation.

== Description ==

Lightning Fast ⚡ - No need to wait to deploy to separate environments. Simply one click to clone your existing site or add a new WordPress site. Only database tables are cloned or created.

Lightweight Management - WordPress is unlocked with domain mapping and unique wp-content folders. Run many, completely unique, WordPress sites from a single WordPress installation.

Painless Maintenance Sandbox - With only WordPress administrator access, easily clone your live site to a safe sandbox piggybacked onto your live site. Switch into the sandbox and begin troubleshooting maintenance related issues. When done, exit back to the live site.

Watch an overview of WP Freighter: https://vimeo.com/456300437. Endless possibilities ☄️.

== Installation ==

1. Drag and drop `wp-freighter.zip` on WordPress /wp-admin/ under Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress

== Known Limitations ⚠️ ==

= Requires customization to wp-config.php for web host compatibility. =
In order for WP Freighter to work, it needs to modify the wp-config.php file. This will happen automatically however if the file is locked down then WP Freighter will provide the necessary configurations for you to provide to your web host. If modifying wp-confg.php is not allowed by the web host provider then WP Freighter won’t be able to run.

= Root level files are shared. =
WP Freighter makes minimal changes to wp-config.php in order to allow for many WordPress sites on a single WordPress installation. Unfortunately that means root level files are shared between all sites. If root level files are needed I recommend coping files from the root / to /content/<stacked-id>/ and configure Redirection plugin to handle the redirection.

== Changelog ==

= 1.0.1 = 
* Feature: Will automatically install default theme when creating new sites if needed.
* Improvement: Shows overlay loader while new sites are installing.
* Improvement: Added fields for domain or label on the new site dialog.
* Improvement: Compatibility for alternative wp-config.php location.
* Improvement: Force HTTPS in urls.
* Fixed: Inconsistent response of sites array.

= 1.0.0 =
* Initial release of WP Freighter. Ability to add or remove stacked sites with database prefix `stacked_#_`.
* Feature: Clone existing site to new database prefix
* Feature: Add new empty site to new database prefix
* Feature: Domain mapping off or on
* Feature: Files shared or dedicated
