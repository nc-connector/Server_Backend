# Changelog

All notable changes to this repository are documented in this file.

The format is based on **Keep a Changelog** and uses a simple version-first structure.

## [1.2.1] - 2026-07-06

### Added
- Add NC Connector admin delegation with separate permissions for policies, templates, group overrides, and user overrides
- Add a delegation overview for audits and support
- Add automated backend checks for static rules, schema mapping, translations, JavaScript syntax, and service/controller behavior

### Changed
- Update the supported platform target to Nextcloud 32 through 35 and PHP 8.3 or newer
- Sanitize backend template HTML before storage and before stored templates are returned
- Tighten template preview image handling with HTTPS-only fetching, private-network blocking, size limits, content checks, and clearer admin warnings
- Update bundled DOMPurify to 3.4.11
- Split the admin UI code by maintained UI areas while keeping settings behavior unchanged

## [1.2.0] - 2026-06-15

### Added
- Add Nextcloud Secrets mode for separate password delivery
- Add a Pro prompt for Community mode and installations without a valid license
- Add backend update check and version status in the admin UI

### Changed
- Update README and app metadata for the current backend feature set
- Document Secrets password mode in the admin guide

## [1.1.1] - 2026-05-12

### Added
- Add email signature feature details to app metadata
- Add per-user signature values for matching email, mobile phone, and custom fields
- Add explicit `occ` toggle for admin seat assignment

### Changed
- Vendor DOMPurify for the backend template editor
- Sanitize custom template drafts before preview and save in the admin UI

## [1.1.0] - 2026-05-05

### Added
- Add central email signature policy
- Add email signature settings to the admin UI
- Add Talk room deletion policy toggle

### Changed
- Clarify admin seat exclusion in backend UI and docs

### Documentation
- Document central email signature policies
- Update runtime metadata documentation

## [1.0.1] - 2026-04-24

### Changed
- Updated the backend share-base default and i18n hint example to `NC Connector`
- Refined Talk invitation and Sharing wording with full locale coverage across all supported languages

### Fixed
- Updated the Talk guest help URL to the current documentation page
- Removed the deprecated `appinfo/database.xml` from the shipped app and rely on `InstallSchema` as the active schema source

### Removed
- Removed legacy backend JavaScript assets left over from earlier app-id and branding renames

## [1.0.0] - 2026-03-30

Initial public release of the **NC Connector backend** for Nextcloud.

### Added
- Central backend for NC Connector mail add-ons
- App published under the final Nextcloud app id `ncc_backend_4mc`
- Support for Nextcloud 31–34 and PHP 8.1+
- `Community` and `Pro` operating modes
- Central seat assignment and assigned-seat overview
- Global default policies for Share and Talk behavior
- Group overrides with explicit numeric priority
- User overrides with deterministic precedence over group and default layers
- Runtime policy endpoint for mail clients via `/apps/ncc_backend_4mc/api/v1/status`
- Visual template editors for Share and Talk templates
- Template preview, source-code view, variable insertion, and language helper in the editor modal
- Talk invitation output mode with `HTML` or cleaned `Plain Text` runtime delivery
- Runtime API key `policy.talk.event_description_type` with `html` / `plain_text`
- Local runtime image mirroring for external template images in the editor
- CSV export for assigned seats and effective policy documentation
- Consistent server-side logging via `Psr\Log\LoggerInterface`
- Warning logs for denied admin access, invalid payloads, and seat conflicts
- Browser-console logging for admin UI and user-page API/UI failures
- Removal of silent catch paths and suppressed runtime-file deletion in app-owned code
- Admin documentation and development documentation under `Doku/`
