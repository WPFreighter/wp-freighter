
WP Freighter allows you to efficiently run many WordPress sites from a single WordPress installation.

## Description

Lightning Fast ⚡ - No need to wait to deploy to separate environments. Simply one click to clone your existing site or add a new WordPress site. Only database tables are cloned or created.

Lightweight Management - WordPress is unlocked with domain mapping and unique wp-content folders. Run many, completely unique, WordPress sites from a single WordPress installation.

Painless Maintenance Sandbox - With only WordPress administrator access, easily clone your live site to a safe sandbox piggybacked onto your live site. Switch into the sandbox and begin troubleshooting maintenance related issues. When done, exit back to the live site.

Watch an overview of WP Freighter: https://vimeo.com/456300437. Endless possibilities ☄️.

## Installation

1. Drag and drop `wp-freighter.zip` on WordPress /wp-admin/ under Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress

## Known Limitations ⚠️

- **Requires customization to wp-config.php for compatibility.**

  In order for WP Freighter to work, it needs to modify the wp-config.php file. This will happen automatically however if the file is locked down then WP Freighter will provide the necessary configurations for you to provide to your web host. If modifying wp-confg.php is not allowed by the web host provider then WP Freighter won’t be able to run.

- **Root level files are shared.**

  WP Freighter makes minimal changes to wp-config.php in order to allow for many WordPress sites on a single WordPress installation. Unfortunately that means root level files are shared between all sites. If root level files are needed I recommend coping files from the root `/` to `/content/<stacked-id>/` and configure Redirection plugin to handle the redirection.
