# Development Guide — NC Connector Backend

This is the technical guide for contributors and maintainers of NC Connector Backend. It covers source layout, architecture, data flow, persistence, security boundaries, tests, and release work.

Deployment, configuration, policy rollout, update, rollback, backup, monitoring, and support procedures belong in [admin.md](admin.md). HTTP routes and payload fields belong in [endpoints.md](endpoints.md).

---

## Table of Contents

- [1. Purpose and boundaries](#1-purpose-and-boundaries)
- [2. Developer prerequisites and quick start](#2-developer-prerequisites-and-quick-start)
  - [2.1 Toolchain](#21-toolchain)
  - [2.2 First checkout](#22-first-checkout)
  - [2.3 Local validation](#23-local-validation)
- [3. Repository layout](#3-repository-layout)
- [4. Architecture](#4-architecture)
  - [4.1 Bootstrap and Nextcloud integration](#41-bootstrap-and-nextcloud-integration)
  - [4.2 Admin UI](#42-admin-ui)
  - [4.3 Controllers](#43-controllers)
  - [4.4 Services](#44-services)
  - [4.5 Persistence and background jobs](#45-persistence-and-background-jobs)
- [5. End-to-end flows](#5-end-to-end-flows)
  - [5.1 Runtime policy request](#51-runtime-policy-request)
  - [5.2 Admin settings request](#52-admin-settings-request)
  - [5.3 Template edit and preview](#53-template-edit-and-preview)
  - [5.4 Scheduled update and license checks](#54-scheduled-update-and-license-checks)
- [6. Database and lifecycle](#6-database-and-lifecycle)
  - [6.1 Tables](#61-tables)
  - [6.2 Install and migration rules](#62-install-and-migration-rules)
  - [6.3 Disable and removal behavior](#63-disable-and-removal-behavior)
- [7. Policy resolution](#7-policy-resolution)
  - [7.1 Precedence](#71-precedence)
  - [7.2 Editable client values](#72-editable-client-values)
  - [7.3 Runtime dependencies](#73-runtime-dependencies)
- [8. Template and signature processing](#8-template-and-signature-processing)
  - [8.1 Sanitizing](#81-sanitizing)
  - [8.2 External image cache](#82-external-image-cache)
  - [8.3 Share compatibility output](#83-share-compatibility-output)
  - [8.4 Talk rendering](#84-talk-rendering)
  - [8.5 Email signature rendering](#85-email-signature-rendering)
- [9. Routes, authentication, and permissions](#9-routes-authentication-and-permissions)
- [10. Localization](#10-localization)
- [11. Logging and error handling](#11-logging-and-error-handling)
- [12. Tests and continuous integration](#12-tests-and-continuous-integration)
  - [12.1 Local commands](#121-local-commands)
  - [12.2 CI matrix](#122-ci-matrix)
  - [12.3 Nextcloud acceptance](#123-nextcloud-acceptance)
- [13. Packaging and release](#13-packaging-and-release)
- [14. Change checklists](#14-change-checklists)
  - [14.1 Add or change a setting](#141-add-or-change-a-setting)
  - [14.2 Add or change an endpoint](#142-add-or-change-an-endpoint)
  - [14.3 Change persistence](#143-change-persistence)
  - [14.4 Change templates or sanitizing](#144-change-templates-or-sanitizing)

---

## 1. Purpose and boundaries

The backend provides:

- Seat and license state
- central Share, Talk, and email signature policies
- default, group, and user policy layers
- managed templates and signature rendering
- scoped administration for delegated users
- one authenticated runtime interface for mail clients

The backend does not implement Thunderbird or Outlook compose, attachment, calendar, or sender-identity behavior. Client-specific behavior belongs in the matching client repository.

Documentation ownership:

| Document | Primary content |
|---|---|
| `README.md` | Product overview and repository entry points |
| `Doku/admin.md` | Deployment and operations |
| `Doku/development.md` | Source and maintenance |
| `Doku/endpoints.md` | Route and payload reference |
| `CHANGELOG.md` | Repository release history |
| `ncc_backend_4mc/CHANGELOG.md` | App-package release history |
| `VENDOR.md` | Bundled third-party components |

The repository root is not the Nextcloud app root. The installable app is `ncc_backend_4mc/`.

---

## 2. Developer prerequisites and quick start

### 2.1 Toolchain

Use:

- PHP 8.3 or newer
- Composer 2
- Node.js 24 for CI parity
- PHP DOM, libxml, and SimpleXML extensions
- Git
- a Nextcloud 32–35 test instance for integration checks

The PHP and Nextcloud support range is declared in `ncc_backend_4mc/appinfo/info.xml`.

### 2.2 First checkout

From the repository root:

```powershell
composer install
composer run check
```

`composer run check` runs static, schema, localization, JavaScript, and PHPUnit checks. It does not run PHP syntax over every application file.

The application can be installed into a test Nextcloud instance by placing or linking `ncc_backend_4mc/` in a configured apps directory and running:

```text
php occ app:enable ncc_backend_4mc
```

Use only a disposable or backed-up instance for lifecycle and destructive-removal tests.

### 2.3 Local validation

Recommended order:

1. PHP syntax for every `ncc_backend_4mc/**/*.php` file
2. backend static checks
3. database-schema checks
4. localization checks
5. JavaScript syntax checks
6. PHPUnit
7. install or enable on Nextcloud
8. admin and client acceptance checks
9. log review

Automated commands are listed in [12. Tests and continuous integration](#12-tests-and-continuous-integration).

---

## 3. Repository layout

| Path | Purpose |
|---|---|
| `ncc_backend_4mc/` | Installable Nextcloud app |
| `ncc_backend_4mc/appinfo/` | Metadata and explicit routes |
| `ncc_backend_4mc/lib/AppInfo/` | Bootstrap and registration |
| `ncc_backend_4mc/lib/Controller/` | HTTP entry points |
| `ncc_backend_4mc/lib/Service/` | Policy, license, template, access, and Seat behavior |
| `ncc_backend_4mc/lib/Db/` | Entities and mappers |
| `ncc_backend_4mc/lib/Setup/` | Install and uninstall repair steps |
| `ncc_backend_4mc/lib/Settings/` | Nextcloud admin and delegated personal settings integration |
| `ncc_backend_4mc/lib/Cron/` | Timed background jobs |
| `ncc_backend_4mc/js/` | Admin and direct-page browser code |
| `ncc_backend_4mc/css/` | Admin and direct-page styles |
| `ncc_backend_4mc/templates/` | Nextcloud-rendered PHP views |
| `ncc_backend_4mc/l10n/` | Browser and server translations |
| `ncc_backend_4mc/img/runtime/` | Regenerable external-image preview cache |
| `tests/` | PHPUnit tests and Nextcloud test doubles |
| `scripts/` | Static, schema, localization, and JavaScript checks |
| `Doku/` | Administration, development, and endpoint documentation |

Key app metadata:

- `appinfo/info.xml` owns app id, version, support range, background jobs, commands, repair steps, and settings registration.
- `appinfo/routes.php` owns routes that require explicit registration across the supported Nextcloud versions.

---

## 4. Architecture

The application uses five main layers:

1. Nextcloud registration and settings entry points
2. browser UI
3. controllers
4. services
5. persistence

Controllers coordinate requests. Services own business rules. Mappers own database access.

### 4.1 Bootstrap and Nextcloud integration

`lib/AppInfo/Application.php` registers application services and delegated personal settings.

`lib/Settings/` contains:

- the administration section
- the full-admin settings page
- the delegated personal settings page

Full admins open the administration page. Delegated admins receive the personal settings entry only when an active NC Connector delegation applies.

Metadata in `appinfo/info.xml` registers:

- app dependencies
- both background jobs
- the admin Seat command
- install and uninstall repair steps
- the full-admin settings section

### 4.2 Admin UI

The main entry point is `js/ncc_backend_4mc-adminSettings.js`. New responsibilities should go into the existing area module where they belong.

| Module | Ownership |
|---|---|
| `adminApi.js` | Admin HTTP calls |
| `adminSettingsMeta.js` | Setting labels, enum labels, template fragments, and documentation URLs |
| `adminSettingsPayload.js` | Save payloads for default, group, and user layers |
| `adminOverridesUi.js` | Group and user override selection and tables |
| `adminSeatUi.js` | Seat assignment and paging |
| `adminSeatReport.js` | Assigned-Seat table and CSV export |
| `adminGeneralStatusUi.js` | License, update, and recommended-app status |
| `adminPermissions.js` | Browser-side delegated-scope mapping |
| `adminDelegationUi.js` | Delegation editor and overview |
| `adminTemplateEditor.js` | Editor modal and TinyMCE lifecycle |
| `adminTemplateImages.js` | Template image rewriting and browser sanitizing |
| `adminTemplateAssetRefresh.js` | Preview-asset refresh requests |
| `adminTemplatePreview.js` | Preview document and Talk plain-text preview |
| `adminTabs.js` | Shared tab activation |
| `adminVisibility.js` | Permission-based row and tab visibility |

Style files follow the same UI areas. Shared layout stays in `adminSettings.css`; status, Seats, templates, and delegation use their matching files.

Do not add new `fetch(...)` wrappers to the main entry point. Use `adminApi.js`.

Default rows remain separate from override rows:

- defaults use **Editable in add-on**
- group and user layers use `inherit` or `forced`

These are different state models and should not share one implicit mode.

### 4.3 Controllers

| Controller | Ownership |
|---|---|
| `StatusController` | Mail-client runtime response |
| `AdminLicenseController` | Mode, credentials, and manual license sync |
| `AdminUpdateController` | Cached backend update state |
| `AdminDirectoryController` | User and group lookup |
| `AdminSeatController` | Seat assignment and report data |
| `AdminClientSettingsController` | Schema, defaults, and overrides |
| `AdminDelegationController` | Delegated-admin management |
| `PageController` | Direct app page |

Controller rules:

- authenticate and authorize first
- parse request values through the definition or service layer
- return one response shape for expected admin warnings
- keep policy resolution and persistence out of controller methods
- log denied access and invalid admin input as warnings

`AdminWarningResponseTrait` provides the shared warning response used by admin controllers.

### 4.4 Services

| Service | Ownership |
|---|---|
| `LicenseService` | Community/Pro mode, encrypted credentials, entitlement, grace state, and sync |
| `SeatService` | Assignment, capacity, active/paused state, and admin-seat override |
| `ClientSettingsDefinitionService` | Setting schema, defaults, parsing, serialization, and classification |
| `ClientSettingsService` | Stored layers, precedence, effective values, and template activation |
| `ClientPolicyRuntimeService` | Final runtime dependencies and output shaping |
| `AccessService` | User-facing access state |
| `AdminPermissionService` | Mapping of admin operations to delegated scopes |
| `AdminDelegationService` | Delegation storage and normalization |
| `TemplateSanitizerService` | Server-side HTML allowlist |
| `TemplateAssetService` | Safe external-image preview cache |
| `TalkTemplateRuntimeService` | HTML or plain-text Talk output |
| `EmailSignatureRuntimeService` | Profile variables and signature rendering |
| `UpdateCheckService` | Daily stable-version lookup and cached state |

Keep each rule in one service. Controllers and UI modules should consume the result instead of reproducing the rule.

### 4.5 Persistence and background jobs

Each stored object uses an entity and mapper under `lib/Db/`.

Background jobs:

| Job | Interval | Behavior |
|---|---|---|
| `LicenseSyncJob` | 24 hours | Runs only when Pro mode and complete credentials permit a license request |
| `UpdateCheckJob` | 6 hours | Calls the update service; the service performs at most one successful request per UTC day |

Both jobs resolve their services through the application container when Nextcloud executes them.

---

## 5. End-to-end flows

### 5.1 Runtime policy request

High-level flow:

```text
Authenticated request
  -> StatusController
  -> access, license, and Seat evaluation
  -> ClientSettingsService layer resolution
  -> ClientPolicyRuntimeService dependency processing
  -> template and signature rendering
  -> status + policy + policy_editable response
```

`StatusController` also supports an internal `user_id` query for admin reporting. Normal mail clients use the authenticated user and do not supply that parameter.

When access is not available, the response keeps status information while policy groups become unavailable. The exact fields are defined in [endpoints.md](endpoints.md).

### 5.2 Admin settings request

Read flow:

```text
Admin request
  -> permission check
  -> setting schema and stored layer
  -> availability and template-asset processing
  -> admin response
```

Save flow:

```text
Admin payload
  -> permission check
  -> setting definition normalization
  -> template sanitizing where applicable
  -> layer-specific persistence
  -> refreshed layer and warnings
```

The browser collects values in `adminSettingsPayload.js`. Server services re-check every permission and value. Browser state never grants authority.

### 5.3 Template edit and preview

The editor maintains a draft until the modal is saved.

Flow:

1. Load the effective template and cached preview assets.
2. Replace safe external image sources with local preview paths.
3. Edit and sanitize the browser draft.
4. Refresh new image sources through the server.
5. Preview HTML or Talk plain text.
6. Save the modal draft into the settings form.
7. Save the settings layer.
8. Sanitize again on the server before persistence.

Closing the editor without saving discards the draft.

### 5.4 Scheduled update and license checks

Update flow:

1. `UpdateCheckJob` starts every six hours.
2. `UpdateCheckService` reads the installed version from `appinfo/info.xml`.
3. A cached successful result from the same UTC day is reused.
4. Otherwise the service requests stable update metadata.
5. Payload, timestamp, or error are stored in `nccb_settings`.

License flow:

1. `LicenseSyncJob` starts every 24 hours.
2. Community mode or incomplete credentials stop the flow before an external request.
3. Pro credentials are decrypted through Nextcloud crypto.
4. The service requests entitlement state.
5. Seat count, status, expiry, timestamp, or error are stored.

Manual **Sync now** uses the same license service.

---

## 6. Database and lifecycle

### 6.1 Tables

The configured Nextcloud prefix is prepended to every table name.

| Suffix | Content | Key rule |
|---|---|---|
| `nccb_settings` | License, update, and default-setting values | Unique `config_key` |
| `nccb_seats` | Seat ownership and assignment metadata | Unique `user_id` |
| `nccb_client_overrides` | User setting overrides | Unique `user_id + setting_key` |
| `nccb_group_overrides` | Group setting overrides and priority | Unique `group_id + setting_key` |
| `nccb_admin_delegations` | Delegated-admin scopes | Unique `user_id` |

`lib/Setup/InstallSchema.php` describes the current schema.

### 6.2 Install and migration rules

`InstallSchema` runs as:

- pre-migration repair step
- install repair step

It creates missing tables and indexes. It is not a substitute for versioned migrations once an existing column or stored format changes.

For a future schema change:

1. add a migration under `lib/Migration/`
2. keep fresh-install schema current
3. test upgrade from the previous released schema
4. test a fresh install
5. test rollback through backup restoration

Do not delete or recreate production tables to apply a normal upgrade.

### 6.3 Disable and removal behavior

The operational commands are documented in [admin.md](admin.md#95-disable-reinstall-or-remove).

Implementation rules:

- disable does not call destructive cleanup
- `app:remove --keep-data` leaves app-owned tables intact
- plain `app:remove` invokes `UninstallCleanup`

Full cleanup drops all five app tables, deletes `LicenseSyncJob` and `UpdateCheckJob` entries, and removes the runtime image cache.

`UninstallCleanup` checks the actual `occ app:remove ncc_backend_4mc` command line before changing data. Keep this guard when changing repair registration.

---

## 7. Policy resolution

### 7.1 Precedence

Resolution order:

1. user override
2. matching group override
3. default

Only `forced` override rows contribute a value. `inherit` continues to the lower layer.

Group selection:

- collect the user's matching groups
- ignore inherited rows
- compare numeric priority
- use the lowest priority number

The resolved value, source layer, policy mode, and add-on editability are carried together so the runtime response and assigned-Seat report use the same result.

### 7.2 Editable client values

`ClientSettingsDefinitionService::isAddonControllableSetting()` classifies values that may remain locally editable.

Default behavior:

- non-template Share, Talk, and signature settings start add-on editable
- template values remain backend-managed
- user-only signature values remain backend-managed
- a forced group or user value sets add-on editability to false

The runtime response separates:

- the effective value in `policy`
- local editability in `policy_editable`

When editability is true, clients may store a local choice. They do not write it to the backend runtime endpoint.

### 7.3 Runtime dependencies

`ClientPolicyRuntimeService` applies dependencies after layer resolution.

Share:

- `attachments_always_via_ncconnector = true` clears the threshold value
- missing Secrets support clears and locks Secrets-dependent runtime values
- non-custom Share language clears custom template values
- `attachment_link_target` accepts `zip_download` or `share_page`
- absent stored link target uses the built-in ZIP default

Talk:

- non-custom language clears custom invitation and format
- custom format is normalized to HTML or plain text
- `event_description_type` is derived for clients

Email signature:

- disabled and locked compose clears reply, forward, and template output
- disabled but add-on-editable compose keeps dependent values available
- template rendering occurs after dependency checks
- user-only source values are removed from the public settings map after rendering

Keep dependency rules in the runtime service rather than client-specific branches.

---

## 8. Template and signature processing

### 8.1 Sanitizing

Template HTML is checked twice:

1. bundled DOMPurify cleans the admin draft before preview and save
2. `TemplateSanitizerService` applies the server allowlist before storage and when stored templates are read

Rendered email signatures pass through the server sanitizer after profile values are inserted.

When changing allowed elements or attributes:

- update browser and server behavior together
- preserve template placeholders through DOM parsing
- add sanitizer tests for allowed and rejected input
- update `VENDOR.md` when the bundled sanitizer changes

### 8.2 External image cache

`TemplateAssetService` mirrors external editor images under `img/runtime`.

It accepts only:

- HTTPS
- public destinations
- a limited redirect chain
- files up to 4 MB
- supported image content types
- image bytes matching the declared type

It rejects private and reserved network targets before downloading. It removes stale cache files for the same source key before writing a replacement.

The stored template keeps the original external URL. The cached file is editor-only and can be regenerated.

Failures return `template_asset_warnings` and create a warning or error log entry. Do not turn an image-cache failure into a silent preview omission.

### 8.3 Share compatibility output

The admin edits one canonical Share template.

Current clients receive:

- `share_html_block_template_v2`, which keeps `{LINK_INTRO}` and `{LINK_LABEL}`
- `share_html_block_effective_language`, which tells clients how to localize generated labels and notices

Older clients receive:

- `share_html_block_template`, where link-intro and label placeholders are replaced with generic wording

Internal compatibility metadata is removed from both outputs.

Existing stored customer templates are not rewritten to add new variables. A template without the mode-aware variables produces compatible output in both fields.

`attachment_link_target` is a normal policy value, not another template version. Manual shares remain standard share-page links; attachment clients select ZIP or share-page wording from the effective target.

### 8.4 Talk rendering

Talk custom output supports:

- HTML
- cleaned plain text

Plain-text conversion exists in two environments:

- `adminTemplatePreview.js` for the admin preview
- `TalkTemplateRuntimeService` for mail-client output

Keep both paths aligned for:

- visible text
- raw link targets
- block-level line breaks
- final whitespace normalization

### 8.5 Email signature rendering

`EmailSignatureRuntimeService` resolves:

- Nextcloud display name and profile fields
- forced signature email override
- mobile phone and custom user values
- HTML escaping
- multiline `{ABOUT}` conversion
- empty line or table-row removal

Supported variables:

- `{NAME}`
- `{EMAIL}`
- `{PHONE}`
- `{PHONE_MOBILE}`
- `{ABOUT}`
- `{FUNCTION}`
- `{ORGANISATION}`
- `{CUSTOM1}`
- `{CUSTOM2}`

The resolved email is returned separately for client sender-identity matching.

Built-in template changes affect only the schema fallback. Never rewrite stored customer templates during a default-template update.

---

## 9. Routes, authentication, and permissions

The complete route list and response fields live in [endpoints.md](endpoints.md).

Public mail-client interface:

- authenticated `GET /apps/ncc_backend_4mc/api/v1/status`
- front-controller variant with `/index.php`
- optional internal `user_id` only for authorized admin reporting

Admin interface:

- full Nextcloud admins may use every admin action
- delegated admins are limited to active NC Connector scopes
- delegation management itself remains full-admin only
- Seat assignment and license settings remain full-admin only

`AdminPermissionService` maps settings and actions to scopes. Browser mapping in `adminPermissions.js` controls visibility but does not replace the server check.

Signature scope details:

- compose, reply, and forward activation use signature-policy permission
- signature template and signature user values use signature-template permission

Route registration:

- group override routes remain explicit in `appinfo/routes.php` because query-based group identifiers work across supported versions
- delegation routes remain explicit for the same compatibility range
- attribute routes cover the remaining controllers where supported

Do not add a second user-directory endpoint. Delegation selection reuses the existing admin users endpoint.

---

## 10. Localization

Source translations:

- `ncc_backend_4mc/l10n/*.json`

Browser-loaded translations:

- `ncc_backend_4mc/l10n/*.js`

Every visible UI string must exist in both forms for every supported locale.

Rules:

- use the translation helper for labels, messages, buttons, tooltips, and errors
- do not add an English-only fallback key to non-English files
- keep JSON and JavaScript keys aligned
- run the localization checker after every visible text change

UI language and template language are separate:

- UI language follows Nextcloud localization
- the editor language selector rewrites built-in Share and Talk text fragments
- signature templates have no language selector

Template translation must preserve variables, links, and language metadata.

---

## 11. Logging and error handling

Server code uses `Psr\Log\LoggerInterface`.

Severity:

| Level | Use |
|---|---|
| `debug` | Scheduled-job start and other opt-in trace context |
| `warning` | Denied admin access, invalid input, missing directory objects, unavailable integrations, and recoverable preview failures |
| `error` | Unexpected persistence, network, crypto, file, or lifecycle failures |

Browser code uses `console.error(...)` for failed admin API calls, parse failures, and UI operations that cannot continue.

Rules:

- do not swallow exceptions without recording or returning the failure
- do not use suppressed file operations such as `@unlink`
- include the exception object in server error context
- do not log license keys, app passwords, tokens, cookies, or template customer data
- keep expected admin misuse at warning level
- return actionable messages without exposing secrets

Useful source audits:

```text
rg -n "@unlink|@rmdir|@mkdir|@file|@copy|@rename|catch \{" ncc_backend_4mc -g "!js/vendor/**" -g "!l10n/**"
rg -n "catch \(" ncc_backend_4mc -g "!js/vendor/**" -g "!l10n/**"
```

Operational collection steps live in [admin.md](admin.md#11-logs-and-support-data).

---

## 12. Tests and continuous integration

### 12.1 Local commands

Install tools:

```powershell
composer install
```

Run all Composer checks:

```powershell
composer run check
```

Individual checks:

```powershell
composer run check:static
composer run check:schema
composer run check:l10n
composer run check:js
composer test
```

PHP syntax is separate. On a POSIX shell:

```bash
find ncc_backend_4mc -name "*.php" -print0 | xargs -0 -n1 php -l
```

On PowerShell:

```powershell
Get-ChildItem .\ncc_backend_4mc -Filter *.php -File -Recurse |
  ForEach-Object {
    php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax failed: $($_.FullName)" }
  }
```

### 12.2 CI matrix

The repository workflow runs:

| Check | Runtime |
|---|---|
| PHP syntax | PHP 8.3, 8.4, and 8.5 |
| Backend static checks | PHP 8.3 |
| Database-schema checks | PHP 8.3 |
| Localization checks | Node.js 24 |
| JavaScript syntax | Node.js 24 |
| PHPUnit | PHP 8.3, 8.4, and 8.5 |

The matrix produces ten executions across six jobs.

Test coverage includes:

- setting definitions and value normalization
- runtime policy dependencies
- template and signature rendering
- sanitizer behavior
- template image restrictions
- delegated-admin permissions
- Seat and directory boundaries
- endpoint response fields
- schema-to-mapper alignment
- localization and JavaScript syntax

### 12.3 Nextcloud acceptance

Automated tests do not replace a real Nextcloud check.

After a lifecycle, policy, permission, template, or route change:

1. install or update on a supported Nextcloud instance
2. run the pilot acceptance table in [admin.md](admin.md#34-pilot-acceptance)
3. run the relevant troubleshooting-free operational checks
4. review server and browser logs

Test destructive removal only on a disposable instance or after a verified backup.

---

## 13. Packaging and release

The App Store archive must contain the app directory as:

```text
ncc_backend_4mc/
  appinfo/
  lib/
  js/
  css/
  templates/
  l10n/
  img/
  CHANGELOG.md
  LICENSE.txt
```

Do not package the repository root, test tools, documentation source directory, dependency caches, or local signing material.

A store release requires:

- matching version in `ncc_backend_4mc/appinfo/info.xml`
- matching release entry in `CHANGELOG.md`
- byte-identical release entry in `ncc_backend_4mc/CHANGELOG.md`
- a clean staged copy of `ncc_backend_4mc/`
- `appinfo/signature.json` generated through the Nextcloud app-signing process
- a signed archive from that staged copy

Private signing keys never belong in Git or the release archive.

Release checklist:

1. classify the delta from the previous release tag
2. update both Changelogs and the app version
3. keep both Changelogs byte-identical
4. run the full local checks
5. verify every CI job is still wired
6. run Nextcloud pilot acceptance
7. build and sign from a clean staged app directory
8. inspect archive paths and excluded files
9. commit with `Release X.Y.Z`
10. create a tag or push only as a separate release action

Documentation-only changes still run the project checks before the release commit is amended.

---

## 14. Change checklists

### 14.1 Add or change a setting

1. Add or update the definition in `ClientSettingsDefinitionService`.
2. Decide whether the setting is add-on controllable, template-managed, backend-only, or user-only.
3. Add admin metadata and the correct default or override UI.
4. Map delegated permissions in `AdminPermissionService` and browser visibility.
5. Add every locale key to JSON and JavaScript files.
6. Add runtime dependency processing only when the setting affects another value.
7. Update [admin.md](admin.md) for operator behavior.
8. Update [endpoints.md](endpoints.md) for response fields.
9. Add definition, permission, runtime, controller, and response tests.
10. Run the full test matrix.

Generic settings use the existing key/value tables and do not need a database migration. A new column, index, table, or stored format does.

### 14.2 Add or change an endpoint

1. Reuse an existing controller when the responsibility matches.
2. Define authentication and permission before request parsing.
3. Keep business rules in services.
4. Reuse existing directory and settings endpoints instead of adding parallel paths.
5. Add explicit routing only where supported-version behavior requires it.
6. Document method, path, parameters, response, and failure states in [endpoints.md](endpoints.md).
7. Add permission and response tests.
8. Run Pretty URL and `/index.php` path checks on Nextcloud.

### 14.3 Change persistence

1. Add a versioned migration.
2. Update fresh-install schema.
3. Update entity and mapper code.
4. Test upgrade from the last release.
5. Test fresh install.
6. Test keep-data reinstall.
7. Test full destructive removal.
8. Verify backup and rollback instructions remain accurate.

### 14.4 Change templates or sanitizing

1. Keep browser and server sanitizer rules aligned.
2. Preserve supported placeholders through parsing and rendering.
3. Keep Share legacy and current outputs compatible.
4. Keep Talk browser preview and server plain-text rendering aligned.
5. Sanitize signatures after profile substitution.
6. Test unsafe HTML, URLs, redirects, file limits, content types, and empty variables.
7. Update template authoring guidance in [admin.md](admin.md#56-template-operation).
8. Update `VENDOR.md` for bundled dependency changes.
