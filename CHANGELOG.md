# Changelog

## 1.1

Major audit visibility and maintenance update.

### Added

- Login security summary with recent failed-login patterns by username and stored IP pattern
- Important Events view for high-impact audit activity
- Per-user activity summary with last login, last action, total activity and content-change count
- Richer content-change metadata for author, parent, template and publish-date changes
- Log exclusions for selected users, roles and event types
- Dashboard alert cards for failed logins, plugin changes and user changes
- Database health information including row count, approximate table size, oldest log and retention status
- Manual cleanup for logs older than 30, 90 or 180 days
- JSON export alongside CSV
- Expanded WooCommerce auditing for product publish/unpublish, coupons and WooCommerce setting changes

### Improved

- GitHub updater version parsing
- Force-check handling so WordPress' "Check again" can refresh the GitHub release cache
- Retention/security time windows now follow the WordPress site timezone

## 1.0.1

Maintenance release to verify the GitHub-to-WordPress update flow.

### Changed

- Refined admin interface wording
- No database schema changes
- No settings reset

## 1.0

Initial public release.

### Included

- Activity and audit history
- Dashboard widget
- Admin history screen
- Search and filters
- CSV export
- Retention settings
- IP privacy controls
- Hidden-by-default IP column
- Meaningful wp-admin action tracking
- Content metadata change tracking
- WooCommerce activity support
- Security hardening including capability checks, nonces, sanitisation, escaping, prepared queries, rate limiting, duplicate suppression, and CSV formula-injection protection
- Native WordPress update notifications sourced from GitHub Releases
