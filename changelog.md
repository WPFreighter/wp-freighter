# Changelog

## [1.2.0] - 2025-12-05
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

## [1.1.2] - 2023-05-04
### Changed
- Site cloning will copy current source content folder over to new `/content/<id>/`.
- Refresh WP Freighter configs on plugin update.

### Fixed
- PHP 8 warnings and errors.

## [1.1.1] - 2023-03-18
### Fixed
- PHP 8 issues.

## [1.1] - 2022-12-28
### Added
- **Free for everyone:** Removed EDD integration.
- **Automatic Updates:** Integrated with Github release.
- Settings link to plugin page.

## [1.0.2] - 2021-08-21
### Fixed
- Bad logic where configurations weren't always regenerated after sites changed.
- Various PHP warnings.

## [1.0.1] - 2020-09-18
### Added
- Automatic installation of default theme when creating new sites if needed.
- Overlay loader while new sites are installing.
- Fields for domain or label on the new site dialog.

### Changed
- Compatibility for alternative `wp-config.php` location.
- Force HTTPS in urls.

### Fixed
- Inconsistent response of sites array.

## [1.0.0] - 2020-09-10
### Added
- Initial release of WP Freighter.
- Ability to add or remove stacked sites with database prefix `stacked_#_`.
- Clone existing site to new database prefix.
- Add new empty site to new database prefix.
- Domain mapping off or on.
- Files shared or dedicated.