# Changelog

## **1.3.0** - 2025-12-07
### Added
- **Hybrid Mode:** Introduced a new "Hybrid" file mode that shares plugins and themes across all sites while keeping the `uploads` folder unique for each site. Perfect for agencies managing standardized stacks with different media libraries.
- **WP-CLI Integration:** Added robust CLI commands (`wp freighter`) to manage sites, clone environments, toggle file modes, and handle domain mapping via the terminal.
- **Developer API:** Introduced the `WPFreighter\Site` class, allowing developers to programmatically create, clone, login, and delete stacked sites.
- **Environment Support:** Added support for the `STACKED_SITE_ID` environment variable to enable context switching in CLI and server environments.
- **Object Cache Compatibility:** Now modifies `WP_CACHE_KEY_SALT` in `wp-config.php` to ensure unique object caching for every stacked site.
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
- **Clone Specific Sites:** Added functionality to clone any specific stacked site directly from the site list, not just the main site.
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
- Ability to add or remove stacked sites with database prefix `stacked_#_`.
- Clone existing site to new database prefix.
- Add new empty site to new database prefix.
- Domain mapping off or on.
- Files shared or dedicated.