# WP Freighter

**Multi-tenant mode for WordPress.**

WP Freighter allows you to efficiently run many WordPress sites from a single WordPress installation. It modifies your WordPress configuration to support multiple database prefixes and content directories, allowing for instant provisioning and lightweight management.

## Features

*   **Lightning Fast Provisioning ⚡** - Spin up new environments in seconds. Only database tables are created or cloned.
*   **Flexible File Isolation** - Choose how your sites share files:
    *   **Shared:** All sites share plugins, themes, and uploads (Single `/wp-content/`).
    *   **Hybrid:** Shared plugins and themes, but unique uploads for every site (Standardized stack, unique content).
    *   **Dedicated:** Completely unique `/wp-content/` directory for every site (Full isolation).
*   **One-Click Cloning** - Clone your main site or *any* existing tenant site to a new environment. Perfect for staging or testing.
*   **Domain Mapping** - Map unique custom domains to specific tenant sites or use the parent domain for easy access.
*   **Magic Login** - Generate one-time auto-login links to jump between site dashboards instantly.
*   **Zero-Config Sandbox** - Safely troubleshoot maintenance issues by cloning your live site to a sandbox piggybacked onto your existing installation.
*   **Secure Context Switching** - Intelligent session management ensures admins can move between sites securely.

---

## Installation

1.  Download the `wp-freighter.zip` release.
2.  Upload to your WordPress installation via **Plugins -> Add New -> Upload Plugin**.
3.  Activate the plugin.
4.  Navigate to **Tools -> WP Freighter** to configure your environment.

### Configuration Note
WP Freighter attempts to create a bootstrap file at `/wp-content/freighter.php` and modify your `wp-config.php` to function. 

If your host restricts file permissions:
1.  The plugin will provide the exact code snippets you need.
2.  You will need to manually create the bootstrap file and/or edit `wp-config.php`.

---

## WP-CLI Integration

WP Freighter includes a robust CLI interface for managing sites via the terminal.

### Global Management
```bash
# View system info, current mode, and site count
wp freighter info

# List all tenant sites
wp freighter list

# Update file storage mode (shared|hybrid|dedicated)
wp freighter files set dedicated

# Toggle domain mapping (on|off)
wp freighter domain set on
```

### Site Management
```bash
# Create a new empty site
wp freighter create --title="My New Site" --name="Client A" --domain="client-a.test"

# Clone the main site to a new staging environment
wp freighter clone main --name="Staging"

# Clone a specific tenant site (ID 2) to a new site
wp freighter clone 2 --name="Dev Copy"

# Generate a magic login URL for Site ID 3
wp freighter login 3

# Delete a site (Confirmation required)
wp freighter delete 4
```

---

## Developer API (`WPFreighter\Site`)

You can programmatically manage tenant sites using the `WPFreighter\Site` class.

### Create a Site
```php
$args = [
    'title'    => 'New Project',
    'name'     => 'Project Alpha',     // Internal label
    'domain'   => 'project-alpha.com', // Optional
    'username' => 'admin',
    'email'    => 'admin@example.com',
    'password' => 'secure_password_123' // Optional, auto-generated if omitted
];

$site = \WPFreighter\Site::create( $args );

if ( is_wp_error( $site ) ) {
    // Handle error
} else {
    echo "Created site ID: " . $site['stacked_site_id'];
}
```

### Clone a Site
```php
// Clone Main Site
$staging = \WPFreighter\Site::clone( 'main', [ 'name' => 'Staging Environment' ] );

// Clone Tenant Site ID 5
$copy = \WPFreighter\Site::clone( 5, [ 'name' => 'Copy of Site 5' ] );
```

### Generate Login Link
```php
// Get a one-time login URL for Site ID 2
$login_url = \WPFreighter\Site::login( 2 );

// Redirect to a specific page after login
$edit_url = \WPFreighter\Site::login( 2, 'post-new.php' );
```

### Delete a Site
```php
\WPFreighter\Site::delete( 4 );
```

---

## Architecture & Modes

WP Freighter works by dynamically swapping the `$table_prefix` and directory constants based on the requested domain or a secure admin cookie. It offers three distinct file modes to suit your workflow:

### 1. Shared Mode
*   **Structure:** Single `/wp-content/` directory.
*   **Behavior:** All sites share the exact same plugins, themes, and media library.
*   **Best for:** Multilingual networks or brand variations using the exact same assets.

### 2. Hybrid Mode
*   **Structure:** Shared `/plugins/` and `/themes/`. Unique uploads stored in `/content/<id>/uploads/`.
*   **Behavior:** You manage one set of plugins for all sites, but every site has its own media library.
*   **Best for:** Agencies managing multiple client sites with a standardized software stack but unique content.

### 3. Dedicated Mode
*   **Structure:** Completely unique `/wp-content/` directory stored in `/content/<id>/`.
*   **Behavior:** Each site has its own plugins, themes, and uploads.
*   **Best for:** True multi-tenancy, snapshots, and distinct staging environments where you need to test plugin updates in isolation.

## Known Limitations ⚠️

*   **`wp-config.php` Access:** The plugin requires write access to `wp-config.php` and `wp-content/`. If your host prevents this, manual configuration is required.
*   **Root Files:** Files in the root directory (like `robots.txt` or `.htaccess`) are shared across all sites.
*   **Cron Jobs:** WP-Cron relies on traffic to trigger. For low-traffic tenant sites, consider setting up system cron jobs triggered via WP-CLI.

## License

MIT License