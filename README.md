# Simple WP History

A lightweight WordPress activity history and audit log for site administrators.

Simple WP History records meaningful WordPress admin activity without capturing passwords, typed form values, cookies, authentication tokens, nonces, or full post/page content.

## Features

- WordPress Dashboard summary widget
- Dedicated **History** admin menu
- Successful and failed login logging
- Logout logging
- Post, page, and custom post type activity
- Media upload, update, and deletion activity
- User creation, updates, deletion, and role changes
- Plugin activation and deactivation
- Theme changes
- WordPress, plugin, and theme update events
- Safe WordPress settings-change logging
- Optional meaningful wp-admin button/link activity tracking
- WooCommerce order and product activity when WooCommerce is installed
- Search and filtering by user, event, category, date, and object
- CSV export
- Configurable log retention
- Full, masked, or disabled IP storage
- IP column hidden by default
- Admin click rate limiting and duplicate suppression
- Native WordPress update notifications through GitHub Releases
- Expanded WooCommerce audit events
- JSON export
- Database health information and manual cleanup
- User, role, and event exclusions
- Per-user activity summaries
- Important Events view for high-impact activity
- Login security summaries for recent failed-login patterns

## Security and privacy

The plugin is designed to minimise unnecessary data collection.

It does **not** capture:

- Passwords
- Typed form values
- Authentication cookies
- Authentication tokens
- WordPress nonces
- API secrets
- Full post or page content
- Raw keystrokes

Security measures include:

- WordPress capability checks
- Nonce verification
- Server-side sanitisation
- Output escaping
- Prepared database queries
- CSV formula-injection protection
- Rate limiting
- Duplicate click suppression
- Sensitive option-name filtering
- Query-string removal from tracked admin links
- Cleanup locking
- Administrator-only access to logs, exports, and settings

## Requirements

- WordPress 5.8 or newer
- PHP 7.4 or newer
- Designed to remain compatible with WordPress 6.1.1

## Installation

1. Download the plugin ZIP.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate it.
4. Open **History** from the WordPress admin sidebar.

## Default settings

- Retention: 180 days
- IP storage: Masked
- Meaningful admin action tracking: Enabled

## Data storage

The plugin creates one custom database table:

`{wp_prefix}simple_wp_history`

Deleting or deactivating the plugin does not automatically delete the audit table.

## Website

https://khairulazhar.com/my-plugins-and-tools/

## Author

Khairul Azhar  
https://khairulazhar.com/

## License

GPL-2.0-or-later

## Updates

Installed copies can check the latest public GitHub Release and surface newer versions in WordPress' normal **Plugins** update UI. Release ZIP assets are preferred; GitHub's source archive is used as a fallback.
