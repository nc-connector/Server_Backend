# Changelog

All notable changes to this repository are documented in this file.

The format is based on **Keep a Changelog** and uses a simple version-first structure.

## [1.0.0] - 2026-03-30

Initial public release of the **NC Connector backend** for Nextcloud.

### Added
- Central backend for NC Connector mail add-ons
- App published under the final Nextcloud app id `nc_connector_backend`
- Support for Nextcloud 31–34 and PHP 8.1+
- `Community` and `Pro` operating modes
- Central seat assignment and assigned-seat overview
- Global default policies for Share and Talk behavior
- Group overrides with explicit numeric priority
- User overrides with deterministic precedence over group and default layers
- Runtime policy endpoint for mail clients via `/apps/nc_connector_backend/api/v1/status`
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
- Release helper scripts for packaging and signing under `release/`
