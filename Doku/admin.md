# Administration Guide — NC Connector Backend

This document is for **administrators** who want to deploy and operate the **NC Connector backend** in Nextcloud.

It explains the admin interface in practical terms:
- where each setting lives
- what it controls technically
- which users it affects
- how defaults, group overrides, and user overrides interact

Related docs:
- `development.md` — developer and maintenance guide
- `endpoints.md` — endpoint reference for mail clients and admin APIs
- `../README.md` — repository overview and product purpose

---

## Table of Contents

- [1. Supported versions & requirements](#1-supported-versions--requirements)
- [2. Where to find the backend settings](#2-where-to-find-the-backend-settings)
- [3. General section (license mode and license state)](#3-general-section-license-mode-and-license-state)
  - [3.1 Operating mode](#31-operating-mode)
  - [3.2 License fields and sync](#32-license-fields-and-sync)
  - [3.3 Reading the status block](#33-reading-the-status-block)
- [4. Group Settings section](#4-group-settings-section)
  - [4.1 Default Settings](#41-default-settings)
  - [4.1.1 Share settings reference](#411-share-settings-reference)
  - [4.1.2 Talk settings reference](#412-talk-settings-reference)
  - [4.1.3 What “Editable in add-on” actually means](#413-what-editable-in-add-on-actually-means)
  - [4.2 Seat assignment](#42-seat-assignment)
  - [4.3 Assigned seats](#43-assigned-seats)
  - [4.4 Group overrides](#44-group-overrides)
  - [4.5 User overrides](#45-user-overrides)
- [5. Effective precedence model](#5-effective-precedence-model)
- [6. What mail clients read from the backend](#6-what-mail-clients-read-from-the-backend)
- [7. Install, disable, remove, keep-data](#7-install-disable-remove-keep-data)
- [8. Operational recommendations](#8-operational-recommendations)

---

## 1. Supported versions & requirements

Nextcloud:
- Supported: **Nextcloud 31–34**

PHP:
- Required: **PHP 8.1+**

App state:
- The app must be enabled:
  - `php occ app:enable nc_connector`

Operational context:
- The backend is the central policy source for the NC Connector mail add-ons.
- One **Seat** always maps to exactly **one Nextcloud user**.

---

## 2. Where to find the backend settings

Path in Nextcloud:
- **Settings → Administration → NC Connector**

The interface is intentionally split into two main areas:

| Area | Purpose |
|---|---|
| `General` | License mode, license credentials, license sync, and seat entitlement state |
| `Group Settings` | Default policies, seat assignment, assigned-seat overview, group overrides, and user overrides |

Important UI note:
- There is currently **no NC Connector app-bar entry** in Nextcloud.
- Administration happens only through the **Administration settings** page.

---

## 3. General section (license mode and license state)

### 3.1 Operating mode

The backend supports two operating modes:

| Mode | Purpose | Operational effect |
|---|---|---|
| `Community` | Small setups, tests, proof of concept | Includes **1 Seat**, does not contact the license backend |
| `Pro` | Team rollout | Seat entitlement comes from the NC Connector license backend |

Practical meaning:
- In **Community**, the backend is fully usable, but seat capacity is intentionally minimal.
- In **Pro**, seat entitlement is synchronized from the license state.

UI behavior:
- The mode selector itself stays compact.
- Explanatory details are shown in tooltips.
- If **Pro** is active and no credentials are stored yet, the page shows an additional note that points admins to `nc-connector.de` for obtaining the license key.

### 3.2 License fields and sync

Relevant controls in `General`:

| UI element | Purpose |
|---|---|
| `License email` | Account identifier for the NC Connector license backend |
| `License key` | License secret used for backend sync |
| `Save credentials` | Stores credentials in Nextcloud and triggers a sync path |
| `Sync now` | Manually refreshes license state and seat entitlement |

Operational note:
- In **Community** mode, the backend does not need a license lookup.
- In **Pro** mode, the license state becomes operationally relevant for seat availability.

### 3.3 Reading the status block

The status block is the first place to check when a seat or entitlement question comes up.

Typical fields shown there:
- current status (`active`, `grace`, `expired`, ...)
- validity window
- grace window
- purchased seats
- available seats
- last sync timestamp

Use this section when the support question is:
- “Why does this instance currently not allow more seats?”
- “Why is a seat paused?”
- “Did the last Pro sync succeed?”

---

## 4. Group Settings section

This area contains the actual policy and rollout logic.

It covers five operational layers:
1. global defaults
2. seat assignment
3. assigned-seat overview
4. group overrides
5. user overrides

### 4.1 Default Settings

Default settings are the baseline policies for **all users with an assigned Seat**.

If there is:
- no matching group override, and
- no user override,

then the default values are the effective policy delivered to the mail add-on.

### 4.1.1 Share settings reference

| Setting | Purpose | Notes |
|---|---|---|
| `Base directory` | Default target folder base path for new shares | Useful for structured server-side storage |
| `Share name` | Default share title | Reduces manual input in the client |
| `Upload/Create` | Default create permission | Delivered to the add-on as part of share policy |
| `Edit` | Default write permission | Controls whether recipients may modify content |
| `Delete` | Default delete permission | Controls whether recipients may remove content |
| `Set password` | Default password toggle for shares | Does not force a password unless the value is forced downstream |
| `Send password separately` | Sends the password in a separate follow-up mail | Built-in default is **enabled** |
| `Expiration (days)` | Default share lifetime | Interpreted in days |
| `Always share attachments via NC Connector` | Forces attachment handling into the NC Connector flow | If active, the size-threshold setting becomes operationally irrelevant |
| `Offer upload for files larger than (MB)` | Threshold that prompts the add-on to offer NC Connector sharing | Comes with its own enable/disable checkbox; when disabled, the field is greyed out and the API value becomes `null` |
| `Language in share HTML block` | Selects the built-in language for generated share text | Built-in default is **English** |
| `Email share template` | Custom HTML template for the main share mail | Only active when the language is set to `custom` |
| `Email password template` | Custom HTML template for the separate password mail | Only active when the language is set to `custom` |

Template details:
- The editor is opened in a modal, not inline.
- The modal offers preview, source-code view, and variable insertion.
- The **Languages** dropdown rewrites the built-in text fragments to supported locales.
- Variables and links stay untouched during translation.
- Runtime image rendering inside the editor uses locally mirrored app files, while the stored template still keeps the original image URL.

Template variables used by Share templates:
- `{URL}`
- `{PASSWORD}`
- `{EXPIRATIONDATE}`
- `{RIGHTS}`
- `{NOTE}`

Important dependency:
- If `Language in share HTML block` is **not** `custom`, the template rows stay visibly inactive.
- In that state, the UI hides the override-mode selector for those rows because the template content is not the active source.

### 4.1.2 Talk settings reference

| Setting | Purpose | Notes |
|---|---|---|
| `Language in Talk description text` | Selects the built-in language for the Talk invitation text | Built-in default is **English** |
| `Talk invitation template` | Custom invitation template | Only active when the language is set to `custom` |
| `Generate password for meetings` | Enables password generation by default for new Talk rooms | Default is add-on editable |
| `Title` | Default room title | Used as prefill in the mail client |
| `Lobby active until start time` | Enables lobby behavior until the start time | Relevant for meeting control |
| `Show in search` | Makes the room searchable in Talk | Controls discoverability |
| `Add users` | Adds internal users by default | Applied by the add-on when invitees are synchronized |
| `Add guests` | Adds external recipients as guests by default | Depends on server-side Talk behavior |
| `Set password` | Enables Talk password protection by default | Separate Talk password dispatch was intentionally removed |
| `Room type` | Selects the Talk room type | Affects room behavior on the Nextcloud side |

Talk template variable:
- `{MEETING_URL}`
- `{PASSWORD}`

Important dependency:
- If `Language in Talk description text` is **not** `custom`, the Talk template row stays visibly inactive.

### 4.1.3 What “Editable in add-on” actually means

This flag is easy to misunderstand, so it is worth being explicit.

It does **not** mean:
- “the backend default is disabled”
- “the setting is optional”
- “the mail client does not receive a backend value”

It **does** mean:
- the backend still sends a concrete effective value
- the mail add-on is allowed to change that value locally for the user interaction

Built-in default behavior:
- All **non-template Share/Talk settings** start with **Editable in add-on = enabled**.
- Template fields intentionally stay backend-controlled.

API consequence:
- Mail clients receive both:
  - `policy` → the effective value
  - `policy_editable` → whether the add-on may change it locally

Operational consequence:
- If a later **forced** group override or user override exists for the same setting, the add-on is no longer allowed to change that setting.
- If that override returns to `inherit`, the lower layer becomes effective again and `policy_editable` follows that lower layer again.

### 4.2 Seat assignment

This section is used to assign NC Connector access to users.

Main controls:
- group filter
- free-text search
- per-user checkbox assignment
- bulk assignment for all filtered users
- bulk removal for all filtered users

Rules that matter operationally:
- **Admin users cannot receive Seats.**
- Seat availability depends on the active mode and license state.
- In **Community**, only one Seat is available.
- In **Pro**, seat entitlement comes from the license backend.

Typical use:
- Filter by department or group
- Assign the relevant seat users
- Verify the result in the **Assigned seats** table directly below

### 4.3 Assigned seats

This is the operational overview for support and audits.

The table shows at least:
- user identity
- assignment timestamp
- assigning admin
- current seat state (`active`, `paused`, ...)
- whether matching **group overrides** currently apply
- whether direct **user overrides** exist

Additional behavior:
- The table is refreshed after seat changes.
- It is also refreshed after group-override and user-override changes, so the overview stays operationally useful.

Tooltip behavior:
- Hovering **Group overrides** shows which matching group overrides currently affect that seat user.
- The group names inside that tooltip are clickable and jump directly to the corresponding **Group overrides** configuration.
- Hovering **User overrides** shows a clickable link that jumps directly to that user’s override configuration.

CSV report:
- The section offers a downloadable CSV report.
- The report lists all current seat users and their effective policy state.
- Raw template HTML is intentionally **not** exported.
- If a custom template is effective, the report shows `Custom` instead of embedding HTML into the file.

### 4.4 Group overrides

This layer exists for team-level deviations from the global default.

Per setting, a group override can be:
- `inherit` → use the global default layer
- `forced` → enforce a group-specific value

Additional group-level field:
- `Priority`

Why priority exists:
- A user can belong to multiple Nextcloud groups.
- If multiple groups define overrides, the backend needs a deterministic winner.

Rule:
- **Lower priority number wins.**

Practical meaning:
- Group overrides can be configured for **any Nextcloud group**.
- They do **not** require that all members already have Seats.
- They apply only to users who:
  - belong to that group, and
  - currently have an assigned Seat

Operational advice:
1. Keep the default layer clean.
2. Use group overrides for department-level exceptions.
3. Use user overrides only when the group layer is still not specific enough.

### 4.5 User overrides

This is the highest policy layer.

Per setting, a user override can be:
- `inherit` → fall back to the next lower layer
- `forced` → enforce a specific user value

Important resolution rule:
- A user override first inherits from the **group layer**.
- Only if no matching group override exists does it fall back to the **default layer**.

Practical effect:
- A forced user override wins over:
  - the global default
  - any matching group override
- A forced user override also disables local editing in the add-on for that setting.

Recommended use:
- actual exceptions
- legal or departmental edge cases
- pilot users with temporarily different settings

Do not use user overrides as a substitute for missing group structure. That becomes hard to maintain quickly.

---

## 5. Effective precedence model

The backend resolves policies in this order:

1. **User override**
2. **Group override**
3. **Default**

This means:
- user `forced` wins over everything below
- group `forced` wins if there is no user `forced`
- `inherit` always falls back one layer down

Concrete fallback behavior:
- user `inherit` → matching group override first, otherwise default
- group `inherit` → default

This same precedence model is reflected in:
- the admin UI behavior
- the seat overview indicators
- the mail-client API payload

---

## 6. What mail clients read from the backend

Mail clients read the effective runtime state from:
- `GET /apps/nc_connector/api/v1/status`

The response contains:

| Block | Meaning |
|---|---|
| `status` | Current seat/license/user access state |
| `policy` | Effective Share and Talk policies after full resolution |
| `policy_editable` | Per-setting information whether the add-on may still change the effective value locally |

Important current behavior:
- The status response no longer contains a `default` block.
- Mail clients should rely only on the effective runtime blocks above.

For the detailed schema, see:
- `endpoints.md`

---

## 7. Install, disable, remove, keep-data

The backend lifecycle is intentionally split into destructive and non-destructive paths.

| Command | Expected behavior |
|---|---|
| `php occ app:enable nc_connector` | Creates missing schema objects if necessary |
| `php occ app:disable nc_connector` | Disables the app **without deleting data** |
| `php occ app:remove --keep-data nc_connector` | Removes the app code **but keeps data** |
| `php occ app:remove nc_connector` | Removes app code **and deletes NC Connector data** |

Deletion on full remove includes:
- settings table
- seats table
- user overrides table
- group overrides table
- local runtime image cache
- background job entry for license sync

This distinction matters for real-world operations:
- `disable` / `enable` is the safe maintenance path
- `remove --keep-data` is the safe reinstall path
- `remove` is the destructive cleanup path

---

## 8. Operational recommendations

Recommended order for a fresh setup:
1. Enable the app.
2. Decide on `Community` vs `Pro`.
3. If `Pro`, store license credentials and run a sync.
4. Define clean global defaults first.
5. Assign Seats.
6. Add group overrides only where departments really differ.
7. Add user overrides only where real exceptions exist.
8. Export the seat report if documentation is required.

Recommended support checklist:
1. Check the license mode and license status block.
2. Check whether the affected user has a Seat.
3. Check whether the seat is active or paused.
4. Check whether a matching group override exists.
5. Check whether a direct user override exists.
6. Check the effective runtime payload via `/api/v1/status`.

If you follow that order, most support cases become straightforward instead of guesswork.
