# Development Guide — NC Connector Backend

This document is the **single source of truth** for developers maintaining or extending the **NC Connector backend** for Nextcloud.

It is written to answer three practical questions:
- where the relevant code lives
- how policy resolution works end to end
- which lifecycle and packaging rules must not be broken

Related docs:
- `admin.md` — admin-facing behavior and operational guide
- `endpoints.md` — API reference for mail clients and admin UI
- `../README.md` — repository overview and product purpose

---

## Table of Contents

- [1. Scope & goals](#1-scope--goals)
- [2. Supported versions & prerequisites](#2-supported-versions--prerequisites)
- [3. Repository layout](#3-repository-layout)
- [4. Architecture overview](#4-architecture-overview)
  - [4.1 Admin UI layer](#41-admin-ui-layer)
  - [4.2 Controller layer](#42-controller-layer)
  - [4.3 Service layer](#43-service-layer)
  - [4.4 Persistence layer](#44-persistence-layer)
- [5. Database model](#5-database-model)
- [6. Policy resolution model](#6-policy-resolution-model)
  - [6.1 Default, group, user](#61-default-group-user)
  - [6.2 `inherit` vs `forced`](#62-inherit-vs-forced)
  - [6.3 `policy` vs `policy_editable`](#63-policy-vs-policy_editable)
- [7. Template editor and runtime image handling](#7-template-editor-and-runtime-image-handling)
- [8. Endpoint inventory](#8-endpoint-inventory)
  - [8.1 Mail-client endpoint](#81-mail-client-endpoint)
  - [8.2 Admin endpoints](#82-admin-endpoints)
  - [8.3 Direct page route](#83-direct-page-route)
- [9. Install, disable, remove, keep-data](#9-install-disable-remove-keep-data)
- [10. Internationalization (i18n / l10n)](#10-internationalization-i18n--l10n)
- [11. Local development and validation](#11-local-development-and-validation)
  - [11.1 Logging and error-handling contract](#111-logging-and-error-handling-contract)
- [12. Smoke-test checklist](#12-smoke-test-checklist)
- [13. Packaging and release notes](#13-packaging-and-release-notes)

---

## 1. Scope & goals

Goals for this backend:
- provide a central policy source for the NC Connector mail add-ons
- manage seat assignment and seat availability
- resolve effective client policies through a deterministic precedence model
- expose a clean runtime API to mail clients
- keep admin operations understandable and auditable

What this backend is responsible for:
- license mode and license synchronization
- seat assignment
- default settings
- group overrides
- user overrides
- template editing and rendering support for Share and Talk content

What this backend is **not** responsible for:
- rendering the mail-client UI itself
- implementing Outlook/Thunderbird compose or calendar logic
- storing raw mail-client-local preferences

---

## 2. Supported versions & prerequisites

Nextcloud:
- Supported: **31–34**

PHP:
- Required: **8.1+**

Background job:
- `OCA\NcConnector\Cron\LicenseSyncJob`

App registration:
- metadata, dependencies, repair steps, and admin settings registration live in:
  - `nc_connector_backend/appinfo/info.xml`

Important practical point:
- The repository root is **not** the app root.
- The actual Nextcloud app lives in:
  - `nc_connector_backend/`

In other words:
- repo root: docs, release helpers, repo-level files
- app root: installable Nextcloud app content

---

## 3. Repository layout

Top-level inside this repository:

| Path | Purpose |
|---|---|
| `nc_connector_backend/` | Actual Nextcloud app |
| `Doku/` | Project documentation |
| `release/` | Packaging and signing helpers |
| `README.md` | Repository overview |

Key paths inside the app folder:

| Path | Purpose |
|---|---|
| `nc_connector_backend/appinfo/info.xml` | App metadata, dependencies, repair steps, background jobs |
| `nc_connector_backend/appinfo/database.xml` | Schema definition for all NC Connector tables |
| `nc_connector_backend/appinfo/routes.php` | Explicit route registration for the query-based group-override endpoints |
| `nc_connector_backend/lib/AppInfo/Application.php` | App bootstrapping and registration |
| `nc_connector_backend/lib/Controller/*` | Admin and runtime HTTP controllers |
| `nc_connector_backend/lib/Service/*` | Business logic, policy resolution, license logic, seat logic |
| `nc_connector_backend/lib/Db/*` | Entity and mapper classes |
| `nc_connector_backend/lib/Setup/*` | Install and uninstall repair steps |
| `nc_connector_backend/lib/Settings/*` | Nextcloud admin-settings integration |
| `nc_connector_backend/js/nc_connector-adminSettings.js` | Main admin UI logic |
| `nc_connector_backend/js/nc_connector-main.js` | Direct page UI under `/apps/nc_connector_backend` |
| `nc_connector_backend/css/*` | Admin and direct-page styling |
| `nc_connector_backend/templates/*` | Nextcloud-rendered PHP templates |
| `nc_connector_backend/l10n/*.json` | Source translations |
| `nc_connector_backend/l10n/*.js` | Browser-loaded translation files |
| `nc_connector_backend/img/runtime/` | Local runtime image mirror for the editor |

---

## 4. Architecture overview

The backend is deliberately split into four layers:
1. admin UI
2. controllers
3. services
4. persistence

That split is what keeps policy logic understandable.

### 4.1 Admin UI layer

Main files:
- `nc_connector_backend/js/nc_connector-adminSettings.js`
- `nc_connector_backend/css/adminSettings.css`
- `nc_connector_backend/templates/adminSettings.php`

Responsibilities:
- render the admin tabs
- call the admin endpoints
- manage modal editor state
- show seat overview, tooltips, and CSV export
- handle translations in the browser

Important UI behaviors currently implemented there:
- modal-based template editor
- preview and source-code dialogs layered correctly above the modal
- runtime image refresh for newly inserted image URLs
- clickable tooltip links from seat overview to group/user overrides
- CSV export for assigned seats
- language dropdown in the editor modal for built-in template translation

### 4.2 Controller layer

Main controllers:

| Controller | Responsibility |
|---|---|
| `AdminLicenseController` | License mode, credentials, sync |
| `AdminDirectoryController` | Group and user lookup for admin UI |
| `AdminSeatController` | Seat assignment and assigned-seat overview |
| `AdminClientSettingsController` | Defaults, user overrides, group overrides |
| `StatusController` | Effective runtime API for mail clients |
| `PageController` | Direct page under `/apps/nc_connector_backend` |

Design intent:
- Controllers should stay thin.
- Resolution logic belongs in services, not in endpoint methods.

### 4.3 Service layer

Core services:

| Service | Responsibility |
|---|---|
| `LicenseService` | License mode, credentials, sync, entitlement state |
| `SeatService` | Seat assignment and seat-limit enforcement |
| `ClientSettingsService` | Default values, group/user overrides, effective policy resolution, template normalization, runtime image cache |
| `AccessService` | Access checks for direct page visibility and user-facing runtime state |

Most important service in day-to-day maintenance:
- `ClientSettingsService.php`

That file is the core of the backend because it owns:
- setting definitions
- built-in defaults
- override modes
- precedence resolution
- template activation rules
- editor asset handling
- seat-overview helper data for matching overrides

Logging rule for service/controller work:
- server-side logging uses `Psr\Log\LoggerInterface`

### 4.4 Persistence layer

Entity / mapper pairs exist for:
- settings
- seats
- user overrides
- group overrides

Files:
- `nc_connector_backend/lib/Db/Setting.php`
- `nc_connector_backend/lib/Db/SettingMapper.php`
- `nc_connector_backend/lib/Db/Seat.php`
- `nc_connector_backend/lib/Db/SeatMapper.php`
- `nc_connector_backend/lib/Db/ClientOverride.php`
- `nc_connector_backend/lib/Db/ClientOverrideMapper.php`
- `nc_connector_backend/lib/Db/GroupOverride.php`
- `nc_connector_backend/lib/Db/GroupOverrideMapper.php`

These classes map the Nextcloud database rows into the service layer.

---

## 5. Database model

Current schema objects:

| Table | Purpose |
|---|---|
| `*dbprefix*nccb_settings` | Global backend settings and defaults |
| `*dbprefix*nccb_seats` | Assigned seats per user |
| `*dbprefix*nccb_client_overrides` | User-specific override rows |
| `*dbprefix*nccb_group_overrides` | Group-specific override rows including priority |

Important schema characteristics:
- settings are keyed by `config_key`
- seats are unique per `user_id`
- overrides are unique per `(target, setting_key)`
- group overrides additionally carry a numeric `priority`

Schema source of truth:
- `nc_connector_backend/appinfo/database.xml`

Repair/install logic:
- `OCA\NcConnector\Setup\InstallSchema`

Important maintenance rule:
- If future schema changes are introduced, do not rely only on table recreation.
- Add proper migration classes under `lib/Migration/` for upgrade safety.

---

## 6. Policy resolution model

This is the most important conceptual part of the backend.

### 6.1 Default, group, user

Effective policy precedence is:
1. **user override**
2. **group override**
3. **default**

That rule is applied consistently in:
- runtime API resolution
- admin seat overview indicators
- editability calculation
- CSV export logic

### 6.2 `inherit` vs `forced`

Every group/user override row is mode-based.

| Mode | Meaning |
|---|---|
| `inherit` | Do not enforce a value here; fall back to the next lower layer |
| `forced` | Enforce the concrete value stored in that row |

Fallback behavior:
- group `inherit` → default
- user `inherit` → group first, otherwise default

This matters because user overrides are intentionally layered on top of group overrides, not directly on top of defaults.

### 6.3 `policy` vs `policy_editable`

The runtime API intentionally separates two questions:
1. What is the effective value?
2. May the add-on still change it locally?

Those map to:
- `policy`
- `policy_editable`

Current rule set:
- all non-template Share/Talk defaults start as **editable in add-on**
- templates remain backend-controlled
- any **forced** override disables local add-on editing for that specific setting
- in the admin defaults table, `user_choice` is rendered as a checked **Editable in add-on** box and the corresponding backend value field is disabled for clarity
- `attachments_min_size_mb` is nullable by design:
  - `null` means the threshold feature is disabled
  - a numeric value means the threshold feature is enabled
  - `attachments_always_via_ncconnector = true` also forces the runtime value to `null`

Important current API behavior:
- `/api/v1/status` no longer exposes a `default` block
- clients should use only the effective runtime values

---

## 7. Template editor and runtime image handling

The editor path deserves its own section because it is more than simple form storage.

Relevant responsibilities in `ClientSettingsService` and `nc_connector-adminSettings.js`:
- detect whether a template row is active at all
- treat template rows as active only when the corresponding language is `custom`
- mirror external image URLs into local runtime files for editor rendering
- keep the original external image URL in stored template HTML
- refresh draft images immediately inside the open modal editor
- discard unsaved modal changes on close
- translate built-in text fragments via the editor’s **Languages** dropdown

Current implementation model:
- Editor rendering uses only local app image files.
- Stored template HTML still keeps the original source URL.
- New image URLs inserted in the modal are refreshed into the runtime cache immediately for the current draft.
- Saving the modal remains the only real commit path.

Export/report behavior:
- Template HTML is never exported as raw HTML in the seat CSV.
- If a custom template applies, the CSV uses `Custom`.

---

## 8. Endpoint inventory

### 8.1 Mail-client endpoint

Main runtime endpoint:
- `GET /apps/nc_connector_backend/api/v1/status`

Purpose:
- deliver effective access state and effective policies to the mail add-on

Current high-level response blocks:
- `status`
- `policy`
- `policy_editable`

Internal admin use:
- the assigned-seats CSV export reuses the same status endpoint with `user_id` filtering when building effective per-user policy rows

### 8.2 Admin endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/apps/nc_connector_backend/api/v1/admin/license` | Read current license state |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/license/credentials` | Store license credentials |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/license/mode` | Switch `Community` / `Pro` |
| `POST` | `/apps/nc_connector_backend/api/v1/admin/license/sync` | Trigger manual sync |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/groups` | Read available Nextcloud groups |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/users` | Read users, optionally filtered |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/seats` | Read assigned seats overview |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/seats/{targetUserId}` | Assign or remove seat |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/client-settings/schema` | Read admin settings schema and defaults metadata |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/client-settings/defaults` | Save global defaults |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/client-settings/users/{targetUserId}` | Read user overrides |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/client-settings/users/{targetUserId}` | Save user overrides |
| `GET` | `/apps/nc_connector_backend/api/v1/admin/client-settings/groups?group_id=...` | Read group overrides |
| `PUT` | `/apps/nc_connector_backend/api/v1/admin/client-settings/groups` | Save group overrides |

Important implementation detail:
- Group override endpoints intentionally use the query-based route in `appinfo/routes.php`.
- That path was chosen because it behaves deterministically with group identifiers and avoids the earlier path-segment routing problem.

### 8.3 Direct page route

Direct route:
- `GET /apps/nc_connector_backend/`

Purpose:
- direct page view without an app-bar entry

Status:
- The route still exists.
- The app-bar icon was intentionally removed.

---

## 9. Install, disable, remove, keep-data

Lifecycle behavior must stay stable. This was a source of regressions earlier and should not be changed casually.

Install path:
- `php occ app:enable nc_connector_backend`
- creates missing schema via `InstallSchema`

Disable path:
- `php occ app:disable nc_connector_backend`
- must **not** delete data

Remove but keep data:
- `php occ app:remove --keep-data nc_connector_backend`
- removes app code only
- keeps NC Connector data intact

Remove including data:
- `php occ app:remove nc_connector_backend`
- deletes:
  - settings table
  - seats table
  - user overrides table
  - group overrides table
  - runtime image cache
  - `LicenseSyncJob` entry from `oc_jobs`

Implementation pieces:
- install repair step: `OCA\NcConnector\Setup\InstallSchema`
- uninstall repair step: `OCA\NcConnector\Setup\UninstallCleanup`

Maintenance rule:
- `disable` and `remove --keep-data` are non-destructive paths.
- `remove` is the destructive path.
- Do not merge these semantics accidentally.

---

## 10. Internationalization (i18n / l10n)

Translation model:
- source translations live in `nc_connector_backend/l10n/*.json`
- browser-loaded translation files live in `nc_connector_backend/l10n/*.js`

Current behavior:
- All visible JS UI strings go through the translation helper.
- Tooltip texts are translated too.
- English is the fallback language.
- The editor modal includes a **Languages** dropdown for built-in template text fragments.

Important distinction:
- UI translation and template-language translation are different concerns.
- The UI follows Nextcloud translation loading.
- The template language dropdown rewrites the built-in template fragments only.

Current template-language rules:
- `share_html_block_template` and `share_password_template` are relevant only when `language_share_html_block = custom`
- `talk_invitation_template` is relevant only when `language_talk_description = custom`
- `talk_invitation_template_format` is relevant only when `language_talk_description = custom`
- `talk_invitation_template_format = html` returns stored editor HTML to the runtime API
- `talk_invitation_template_format = plain_text` converts the stored HTML to cleaned plain text while preserving link targets as raw URLs
- the runtime API additionally derives `event_description_type = html | plain_text` for clients that only need the final rendering mode
- otherwise those template values are effectively inactive for runtime use

Maintenance rule:
- If you add visible UI strings, update both:
  - `l10n/*.json`
  - generated `l10n/*.js`

---

## 11. Local development and validation

Typical local checks used in this repository:
- JS syntax:
  - `node --check nc_connector_backend/nc_connector_backend/js/nc_connector-adminSettings.js`
- translation JS syntax:
  - syntax-check `nc_connector_backend/nc_connector_backend/l10n/*.js`
- XML sanity:
  - validate `appinfo/info.xml`
  - validate `appinfo/database.xml`

Environment note from this workspace:
- `php` is not available in the local PATH here.
- Real PHP linting and `occ`-based verification must therefore happen on the Nextcloud instance.

### 11.1 Logging and error-handling contract

Current logging contract:
- do not silently swallow backend exceptions
- do not use suppressed filesystem operators such as `@unlink`
- do not keep silent JSON/API parse failures in app-owned JavaScript
- log expected admin misuse / invalid input as `warning`
- log unexpected backend failures as `error` with exception context

Concrete implementation rules:
- Controllers use `warning` for:
  - denied admin access
  - invalid payloads
  - missing users / groups
  - seat-conflict situations
- Services use `error` when an operation that should succeed fails unexpectedly
- JavaScript uses `console.error(...)` for UI/API failures and parse issues

Practical audit checks:
- `rg -n "@unlink|@rmdir|@mkdir|@file|@copy|@rename|catch \\{" nc_connector_backend/nc_connector_backend -g '!js/vendor/**' -g '!l10n/**'`
- `rg -n "catch \\(" nc_connector_backend/nc_connector_backend -g '!js/vendor/**' -g '!l10n/**'`

Good practical validation order:
1. JS syntax
2. translation syntax
3. install / enable on Nextcloud
4. admin UI smoke test
5. runtime `/api/v1/status` check
6. logging audit grep checks

---

## 12. Smoke-test checklist

A pragmatic smoke test for this backend should cover the actual operational risks.

1. `php occ app:enable nc_connector_backend`
2. Open the admin settings page
3. Switch to `Community`
4. Assign one Seat to a non-admin user
5. Verify the assigned user appears in **Assigned seats**
6. Check `GET /apps/nc_connector_backend/api/v1/status` as that seat user
7. Save default Share/Talk settings
8. Verify that non-template settings default to `policy_editable = true`
9. Configure a group override and confirm the seat overview marks group overrides as active for matching users
10. Configure a user override and confirm it wins over the group layer
11. Change a template, open the editor modal, preview it, and verify image rendering
12. Use the editor **Languages** dropdown and confirm variables/links remain untouched
13. Download the seat CSV report and confirm effective policy data is present while template HTML is shown as `Custom`
14. Switch to `Pro`, save credentials, run `Sync now`, and verify the status block updates
15. Test lifecycle commands:
    - `php occ app:disable nc_connector_backend`
    - `php occ app:enable nc_connector_backend`
    - `php occ app:remove --keep-data nc_connector_backend`
    - `php occ app:remove nc_connector_backend`

If any of these fail, fix the underlying lifecycle or resolution logic before touching surface-level UI behavior.

---

## 13. Packaging and release notes

Repository-level release helpers exist under:
- `release/`

Current release model:
- the installable app package must contain only the actual app folder content
- final store packages require `appinfo/signature.json`
- signing depends on a Nextcloud-issued certificate and a signing environment with `occ integrity:sign-app`

Practical rule:
- Build release archives from the **app folder**, not from the entire repository root.

That keeps docs, local helpers, and private signing material out of the shipped app package.
