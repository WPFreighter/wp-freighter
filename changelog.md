# Changelog

## **1.5.0** - 2025-12-10
### Added
- **Dark Mode Support:** Added full support for dark mode. The interface now automatically respects system preferences (`prefers-color-scheme`) and includes a manual toggle in the toolbar to switch between light and dark themes.
- **Vue 3 & Vuetify 3:** Completely upgraded the admin dashboard dependencies to Vue.js 3.5.22 and Vuetify 3.10.5. This modernization improves rendering performance and ensures long-term compatibility.
- **Self-Healing Logic:** Implemented `Site::ensure_freighter()`, which automatically restores the plugin files and forces activation on a tenant site before context switching. This prevents errors if the plugin was manually deleted or deactivated in a child environment.
- **New CLI Command:** Added `wp freighter regenerate`. This utility allows administrators to manually trigger a refresh of the `wp-content/freighter.php` bootstrap file and `wp-config.php` adjustments via the command line.

### Changed
- **Terminology:** Updated UI and CLI text to refer to sites as "Tenant Sites" instead of "Stacked Sites" for better clarity.
- **UI Visuals:** Refined input fields, tables, and overlays to use transparent backgrounds, allowing the plugin interface to blend seamlessly with the native WordPress Admin color scheme.
- **Cache Busting:** Introduced `WP_FREIGHTER_VERSION` constant to ensure admin assets are properly refreshed in browser caches immediately after an update.

### Fixed
- **Bootstrap Location:** Updated the detection logic for `freighter.php` to use `ABSPATH` instead of `WP_CONTENT_DIR`, resolving path resolution issues on specific hosting configurations.

## **1.4.0** - 2025-12-09
### Added
- **Bootstrap Architecture:** Introduced `wp-content/freighter.php` to handle site loading logic. This significantly reduces the footprint within `wp-config.php` to a single `require_once` line, making the configuration more robust and easier to read.
- **Local Dependencies:** Removed reliance on external CDNs (delivr.net) for frontend assets. Vue.js, Vuetify, and Axios are now bundled locally within the plugin, improving privacy, compliance (GDPR), and offline development capabilities.
- **Manual Configuration UI:** Added a dedicated interface for environments with strict file permissions. If the plugin cannot write to `wp-config.php` or create the bootstrap file, it now provides the exact code snippets and a "Copy to Clipboard" feature for manual installation.
- **Session Gatekeeper:** Implemented stricter security logic for cookie-based context switching. The plugin now actively verifies that a user is authenticated or in the process of logging in before allowing access to a tenant site via cookies.
- **"Login to Main" Button:** Added a quick-access button in the toolbar to return to the main parent site dashboard when viewing a tenant site with domain mapping enabled.

### Changed
- **Context Switching:** Refactored the context switching logic to force an immediate header reload when setting the `stacked_site_id` cookie. This resolves issues where the context switch would occasionally fail on the first attempt.
- **Asset Management:** Added a developer utility (`WPFreighter\Dev\AssetFetcher`) to programmatically fetch and update frontend vendor libraries.

### Fixed
- **Cookie Reliability:** Fixed an issue where the site ID cookie wasn't being set early enough during the request lifecycle for specific hosting configurations.

## **1.3.0** - 2025-12-07
### Added
- **Hybrid Mode:** Introduced a new "Hybrid" file mode that shares plugins and themes across all sites while keeping the `uploads` folder unique for each site. Perfect for agencies managing standardized stacks with different media libraries.
- **WP-CLI Integration:** Added robust CLI commands (`wp freighter`) to manage sites, clone environments, toggle file modes, and handle domain mapping via the terminal.
- **Developer API:** Introduced the `WPFreighter\Site` class, allowing developers to programmatically create, clone, login, and delete tenant sites.
- **Environment Support:** Added support for the `STACKED_SITE_ID` environment variable to enable context switching in CLI and server environments.
- **Object Cache Compatibility:** Now modifies `WP_CACHE_KEY_SALT` in `wp-config.php` to ensure unique object caching for every tenant site.
- **Storage Stats:** Added directory size calculation to the delete site dialog, providing warnings for dedicated content deletion.

### Changed
- **Architecture Refactor:** Moved core logic from `Run.php` into dedicated `Site` and `CLI` models for better maintainability.
- **Admin Assets:** Migrated inline Vue.js logic from the PHP template to a dedicated `admin-app.js` file.
- **Kinsta Compatibility:** Enhanced support for copying Kinsta-specific `mu-plugins` when creating sites in dedicated mode.

### Fixed
- **Database Hardening:** Implemented `$wpdb->prepare()` across all database write operations to prevent SQL injection vulnerabilities.
- **Input Sanitization:** Added strict input sanitization (`sanitize_text_field`, `sanitize_user`) to all REST API endpoints.

## **v1.2.0** - 2025-12-05
### Added
- **REST API Implementation:** Completely replaced legacy `admin-ajax` calls with secure WordPress REST API endpoints (`wp-freighter/v1`) for better stability and permission handling.
- **Clone Specific Sites:** Added functionality to clone any specific tenant site directly from the site list, not just the main site.
- **Enhanced Clone Dialog:** New UI dialog allowing users to define the Label or Domain immediately when cloning a site.
- **Smart Defaults:** The "New Site" form now pre-fills the current user's email and username, and automatically generates a secure random password.
- **Manifest Support:** Added `manifest.json` for standardized update checking.

### Changed
- **Frontend Dependencies:** Updated Vue.js to v2.7.16, Vuetify to v2.6.13, and Axios to v1.13.2.
- **Updater Logic:** Migrated the update checker to pull release data directly from the GitHub repository (`raw.githubusercontent.com`) instead of the previous proprietary endpoint.
- **Admin Bar:** The "Exit WP Freighter" button now utilizes a Javascript-based REST API call for a smoother exit transition.

### Fixed
- **Theme Upgrader Noise:** Implemented a silent skin for the `Theme_Upgrader` to prevent HTML output from breaking JSON responses when installing default themes on new sites.
- **Fallback Logic:** The updater now falls back to a local manifest file if the remote check fails.

## **v1.1.2** - 2023-05-04
### Changed
- Site cloning will copy current source content folder over to new `/content/<id>/`.
- Refresh WP Freighter configs on plugin update.

### Fixed
- PHP 8 warnings and errors.

## **v1.1.1** - 2023-03-18
### Fixed
- PHP 8 issues.

## **v1.1** - 2022-12-28
### Added
- **Free for everyone:** Removed EDD integration.
- **Automatic Updates:** Integrated with Github release.
- Settings link to plugin page.

## **v1.0.2** - 2021-08-21
### Fixed
- Bad logic where configurations weren't always regenerated after sites changed.
- Various PHP warnings.

## **v1.0.1** - 2020-09-18
### Added
- Automatic installation of default theme when creating new sites if needed.
- Overlay loader while new sites are installing.
- Fields for domain or label on the new site dialog.

### Changed
- Compatibility for alternative `wp-config.php` location.
- Force HTTPS in urls.

### Fixed
- Inconsistent response of sites array.

## **v1.0.0** - 2020-09-10
### Added
- Initial release of WP Freighter.
- Ability to add or remove tenant sites with database prefix `stacked_#_`.
- Clone existing site to new database prefix.
- Add new empty site to new database prefix.
- Domain mapping off or on.
- Files shared or dedicated.