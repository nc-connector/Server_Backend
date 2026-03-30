# Changelog

All notable changes to this repository are documented in this file.

The format is based on **Keep a Changelog** and uses a simple version-first structure.

## [1.0.0] - 2026-03-30

Initial public release of the **NC Connector backend** for Nextcloud.

### Added
- Central backend for NC Connector mail add-ons
- Support for Nextcloud 31–34 and PHP 8.1+
- `Community` and `Pro` operating modes
- Central seat assignment and assigned-seat overview
- Global default policies for Share and Talk behavior
- Group overrides with explicit numeric priority
- User overrides with deterministic precedence over group and default layers
- Runtime policy endpoint for mail clients via `/apps/nc_connector/api/v1/status`
- Visual template editors for Share and Talk templates
- Template preview, source-code view, variable insertion, and language helper in the editor modal
- Local runtime image mirroring for external template images in the editor
- CSV export for assigned seats and effective policy documentation
- Admin documentation and development documentation under `Doku/`
- Release helper scripts for packaging and signing under `release/`
