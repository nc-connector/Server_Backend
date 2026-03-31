# Administration Guide — NC Connector Backend

This document is written for **administrators**, including administrators who have **never worked with Nextcloud before**.

Its purpose is practical:
- explain what this backend actually does
- explain how to enable and configure it in Nextcloud
- explain how Seats, defaults, group overrides, and user overrides interact
- explain what mail clients finally read from the backend

Related docs:
- `development.md` — developer and maintenance guide
- `endpoints.md` — endpoint reference for mail clients and admin APIs
- `../README.md` — repository overview and product purpose

---

## Table of Contents

- [1. Audience, prerequisites, and scope](#1-audience-prerequisites-and-scope)
- [2. First-time setup for admins new to Nextcloud](#2-first-time-setup-for-admins-new-to-nextcloud)
  - [2.1 What you need beforehand](#21-what-you-need-beforehand)
  - [2.2 Nextcloud terms used in this guide](#22-nextcloud-terms-used-in-this-guide)
  - [2.3 First rollout walkthrough](#23-first-rollout-walkthrough)
- [3. Where to find the backend settings](#3-where-to-find-the-backend-settings)
- [4. General section](#4-general-section)
  - [4.1 Operating mode](#41-operating-mode)
  - [4.2 License fields and sync](#42-license-fields-and-sync)
  - [4.3 Reading the status block](#43-reading-the-status-block)
- [5. Group Settings section](#5-group-settings-section)
  - [5.1 Default Settings](#51-default-settings)
    - [5.1.1 Share settings reference](#511-share-settings-reference)
    - [5.1.2 Talk settings reference](#512-talk-settings-reference)
    - [5.1.3 What “Editable in add-on” actually means](#513-what-editable-in-add-on-actually-means)
    - [5.1.4 Thunderbird attachment automation prerequisite: disable competing compose features](#thunderbird-attachment-automation-prerequisite)
  - [5.2 Seat assignment](#52-seat-assignment)
  - [5.3 Assigned seats](#53-assigned-seats)
  - [5.4 Group overrides](#54-group-overrides)
  - [5.5 User overrides](#55-user-overrides)
- [6. Effective precedence model](#6-effective-precedence-model)
- [7. What mail clients read from the backend](#7-what-mail-clients-read-from-the-backend)
- [8. Install, disable, remove, keep-data](#8-install-disable-remove-keep-data)
- [9. Operational recommendations](#9-operational-recommendations)

---

## 1. Audience, prerequisites, and scope

Supported versions:
- **Nextcloud 31–34**
- **PHP 8.1+**

Operational scope:
- The backend is the central policy source for the NC Connector mail add-ons.
- One **Seat** always maps to exactly **one Nextcloud user**.
- The backend controls:
  - license mode and license synchronization
  - seat assignment
  - default policies
  - group overrides
  - user overrides
  - template control for Share and Talk

This backend does **not** replace mail-client deployment.
You still need to deploy the Thunderbird or Outlook add-on separately.

---

## 2. First-time setup for admins new to Nextcloud

### 2.1 What you need beforehand

Before you configure NC Connector, make sure you have:
- a working Nextcloud instance
- a Nextcloud account with **administrator** rights
- the NC Connector backend app installed or ready to install
- if you want `Pro` mode:
  - a license email
  - a license key

You can enable the app in two common ways:

**Nextcloud web UI**
- Open **Apps**
- search for **NC Connector**
- click **Download and enable** or **Enable**

**Nextcloud command line**
- `php occ app:enable nc_connector_backend`

If you are new to Nextcloud and do not have shell access, the web UI path is usually enough.

### 2.2 Nextcloud terms used in this guide

These terms are important:

| Term | Meaning |
|---|---|
| `User` | A normal Nextcloud account |
| `Group` | A built-in Nextcloud group such as `Sales`, `HR`, or `Project-A` |
| `Administrator` | A Nextcloud admin account; admins can configure NC Connector but cannot receive Seats |
| `Seat` | A license slot in NC Connector; exactly one Seat belongs to one Nextcloud user |
| `Default` | The baseline policy for all Seat users |
| `Group override` | A group-specific policy layer above the defaults |
| `User override` | A user-specific policy layer above group and default |
| `occ` | Nextcloud’s command-line tool |

The most important conceptual point is:
- **NC Connector does not create its own user directory**
- it reuses **existing Nextcloud users and groups**

### 2.3 First rollout walkthrough

If you want the shortest possible path to a working setup, do this in order:

1. Enable the app.
2. Open **Settings → Administration → NC Connector**.
3. Decide whether the instance should run in `Community` or `Pro`.
4. If `Pro`, store the license credentials and run a sync.
5. Define clean **Default Settings** first.
6. Assign Seats to the users who should use NC Connector.
7. Test one real user with the mail add-on.
8. Add **Group overrides** only if departments need different defaults.
9. Add **User overrides** only for real exceptions.

That order keeps the rollout understandable and avoids unnecessary exception handling too early.

---

## 3. Where to find the backend settings

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

## 4. General section

### 4.1 Operating mode

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

### 4.2 License fields and sync

Relevant controls in `General`:

| UI element | Purpose |
|---|---|
| `License email` | Account identifier for the NC Connector license backend |
| `License key` | License secret used for backend sync |
| `Save license data` | Stores credentials in Nextcloud and triggers a sync path |
| `Sync now` | Manually refreshes license state and seat entitlement |

Operational note:
- In **Community** mode, the backend does not need a license lookup.
- In **Pro** mode, the license state becomes operationally relevant for seat availability.

### 4.3 Reading the status block

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

## 5. Group Settings section

This area contains the actual policy and rollout logic.

It covers five operational layers:
1. global defaults
2. seat assignment
3. assigned-seat overview
4. group overrides
5. user overrides

### 5.1 Default Settings

Default settings are the baseline policies for **all users with an assigned Seat**.

If there is:
- no matching group override, and
- no user override,

then the default values are the effective policy delivered to the mail add-on.

### 5.1.1 Share settings reference

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

### 5.1.2 Talk settings reference

| Setting | Purpose | Notes |
|---|---|---|
| `Language in Talk description text` | Selects the built-in language for the Talk invitation text | Built-in default is **English** |
| `Talk invitation output` | Controls whether the custom Talk template is delivered as HTML or as cleaned plain text | Only relevant when the language is set to `custom` |
| `Talk invitation template` | Custom invitation template | Only active when the language is set to `custom` |
| `Generate password for meetings` | Enables password generation by default for new Talk rooms | Default is add-on editable |
| `Title` | Default room title | Used as prefill in the mail client |
| `Lobby active until start time` | Enables lobby behavior until the start time | Relevant for meeting control |
| `Show in search` | Makes the room searchable in Talk | Controls discoverability |
| `Add users` | Adds internal users by default | Applied by the add-on when invitees are synchronized |
| `Add guests` | Adds external recipients as guests by default | Depends on server-side Talk behavior |
| `Set password` | Enables Talk password protection by default | Separate Talk password dispatch was intentionally removed |
| `Room type` | Selects the Talk room type | Affects room behavior on the Nextcloud side |

Talk template variables:
- `{MEETING_URL}`
- `{PASSWORD}`

Important dependency:
- If `Language in Talk description text` is **not** `custom`, the Talk template row stays visibly inactive.
- If `Language in Talk description text` is `custom`, the Talk template row additionally shows an `HTML | Plain Text` output selector.
- `HTML` returns the stored editor HTML unchanged through the runtime API.
- `Plain Text` strips the HTML markup for runtime delivery while preserving visible URLs, including meeting and help links.

### 5.1.3 What “Editable in add-on” actually means

This flag is easy to misunderstand, so it is worth being explicit.

It does **not** mean:
- “the backend default is disabled”
- “the setting is optional”
- “the mail client does not receive a backend value”

It **does** mean:
- the backend still sends a concrete effective value
- the mail add-on is allowed to change that value locally for the user interaction
- in the admin UI, the stored backend value stays visible but the value field is greyed out and locked as soon as **Editable in add-on** is enabled

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

<a id="thunderbird-attachment-automation-prerequisite"></a>
### 5.1.4 Thunderbird attachment automation prerequisite: disable competing compose features

This section matters **only for Thunderbird**.
It does **not** apply to Outlook.

If you want **NC Connector attachment automation** to be the only active compose flow, administrators should also disable Thunderbird’s own competing compose prompts centrally.

Why this is necessary:
- NC Connector can route attachments into its own sharing flow (`always` or `offer above threshold`).
- Thunderbird itself still has native compose features for:
  - **Check for missing attachments**
  - **Upload for files larger than ...**
- Per reviewer constraints and the add-on’s limited experiment scope, **NC Connector must not change these Thunderbird-wide compose settings itself**.
- Therefore, if you want a deterministic admin-managed rollout, disable and lock these Thunderbird settings via `policies.json`.

Relevant Thunderbird preferences:
- `mail.compose.attachment_reminder`
  - controls **Check for missing attachments**
- `mail.compose.big_attachments.notify`
  - controls **Upload for files larger than ...**
- `mail.compose.big_attachments.threshold_kb`
  - controls the native Thunderbird threshold value in **KB**

Recommended lock state when NC Connector attachment automation should own the workflow:
- `mail.compose.attachment_reminder` => `false` / `locked`
- `mail.compose.big_attachments.notify` => `false` / `locked`
- `mail.compose.big_attachments.threshold_kb` => `5120` / `locked`

Notes:
- `5120` KB is Thunderbird’s default threshold value (5 MB).
- Once `mail.compose.big_attachments.notify=false`, the threshold is effectively inactive, but keeping it explicitly locked avoids drift and makes the admin intent visible.
- Merge the example below into your existing `policies.json`; do not create a second policy file.

Official references:
- Thunderbird Enterprise Policies — `Preferences` policy:
  - `https://thunderbird.github.io/policy-templates/templates/esr140/#preferences`
- Thunderbird compose preferences source:
  - `https://searchfox.org/comm-central/source/mail/components/preferences/compose.inc.xhtml`

Example `policies.json` snippet:

```json
{
  "policies": {
    "Preferences": {
      "mail.compose.attachment_reminder": {
        "Value": false,
        "Status": "locked"
      },
      "mail.compose.big_attachments.notify": {
        "Value": false,
        "Status": "locked"
      },
      "mail.compose.big_attachments.threshold_kb": {
        "Value": 5120,
        "Status": "locked"
      }
    }
  }
}
```

Example merged `policies.json` (force-install NC Connector + lock Thunderbird native attachment prompts):

```json
{
  "policies": {
    "ExtensionSettings": {
      "*": {
        "installation_mode": "allowed"
      },
      "{4a35421f-0906-439c-bff2-8eef39e2baee}": {
        "installation_mode": "force_installed",
        "install_url": "https://services.addons.thunderbird.net/thunderbird/downloads/latest/nc4tb/addon-989342-latest.xpi",
        "updates_disabled": false
      }
    },
    "Preferences": {
      "mail.compose.attachment_reminder": {
        "Value": false,
        "Status": "locked"
      },
      "mail.compose.big_attachments.notify": {
        "Value": false,
        "Status": "locked"
      },
      "mail.compose.big_attachments.threshold_kb": {
        "Value": 5120,
        "Status": "locked"
      }
    }
  }
}
```

### 5.2 Seat assignment

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

### 5.3 Assigned seats

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

### 5.4 Group overrides

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

### 5.5 User overrides

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

## 6. Effective precedence model

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

## 7. What mail clients read from the backend

Mail clients read the effective runtime state from:
- `GET /apps/nc_connector_backend/api/v1/status`

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

## 8. Install, disable, remove, keep-data

The backend lifecycle is intentionally split into destructive and non-destructive paths.

| Command | Expected behavior |
|---|---|
| `php occ app:enable nc_connector_backend` | Creates missing schema objects if necessary |
| `php occ app:disable nc_connector_backend` | Disables the app **without deleting data** |
| `php occ app:remove --keep-data nc_connector_backend` | Removes the app code **but keeps data** |
| `php occ app:remove nc_connector_backend` | Removes app code **and deletes NC Connector data** |

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

## 9. Operational recommendations

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
7. Check the Nextcloud log for `warning` / `error` entries from `nc_connector_backend`.

Logging note:
- The backend now logs denied admin access, invalid admin payloads, and unexpected backend failures consistently.
- Admin support should therefore treat the Nextcloud log as part of the normal troubleshooting workflow, not as an optional last resort.
- Browser-side admin UI failures are additionally written to the browser console.

If you follow that order, most support cases become straightforward instead of guesswork.
