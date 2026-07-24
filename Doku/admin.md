# Administration Guide — NC Connector Backend

This is the operating guide for administrators and operations teams. It covers deployment, configuration, policy rollout, maintenance, monitoring, recovery, and support.

Source layout, architecture, implementation details, builds, and tests belong in [development.md](development.md). The complete HTTP interface is documented in [endpoints.md](endpoints.md).

---

## Table of Contents

- [1. Scope and responsibilities](#1-scope-and-responsibilities)
- [2. Requirements and network access](#2-requirements-and-network-access)
  - [2.1 Supported platform](#21-supported-platform)
  - [2.2 Nextcloud apps](#22-nextcloud-apps)
  - [2.3 Administrative access](#23-administrative-access)
  - [2.4 Network paths](#24-network-paths)
  - [2.5 Pre-deployment checklist](#25-pre-deployment-checklist)
- [3. Installation and first rollout](#3-installation-and-first-rollout)
  - [3.1 Install from the Nextcloud App Store](#31-install-from-the-nextcloud-app-store)
  - [3.2 Install a signed release archive](#32-install-a-signed-release-archive)
  - [3.3 Configure the first Seat](#33-configure-the-first-seat)
  - [3.4 Pilot acceptance](#34-pilot-acceptance)
- [4. General operation](#4-general-operation)
  - [4.1 Open the administration page](#41-open-the-administration-page)
  - [4.2 Community and Pro mode](#42-community-and-pro-mode)
  - [4.3 License synchronization](#43-license-synchronization)
  - [4.4 Backend update status](#44-backend-update-status)
  - [4.5 Recommended apps](#45-recommended-apps)
- [5. Policies and Seats](#5-policies-and-seats)
  - [5.1 Policy precedence](#51-policy-precedence)
  - [5.2 Editable in add-on](#52-editable-in-add-on)
  - [5.3 Share settings](#53-share-settings)
  - [5.4 Talk settings](#54-talk-settings)
  - [5.5 Email signature settings](#55-email-signature-settings)
  - [5.6 Template operation](#56-template-operation)
  - [5.7 Seat assignment and reporting](#57-seat-assignment-and-reporting)
  - [5.8 Group overrides](#58-group-overrides)
  - [5.9 User overrides](#59-user-overrides)
- [6. Delegated administration](#6-delegated-administration)
- [7. Client rollout dependencies](#7-client-rollout-dependencies)
  - [7.1 Thunderbird attachment automation](#thunderbird-attachment-automation-prerequisite)
- [8. Operational verification](#8-operational-verification)
- [9. Update, rollback, backup, and removal](#9-update-rollback-backup-and-removal)
  - [9.1 Update](#91-update)
  - [9.2 Roll back](#92-roll-back)
  - [9.3 Back up](#93-back-up)
  - [9.4 Restore](#94-restore)
  - [9.5 Disable, reinstall, or remove](#95-disable-reinstall-or-remove)
- [10. Operational checks and troubleshooting](#10-operational-checks-and-troubleshooting)
  - [10.1 Routine checks](#101-routine-checks)
  - [10.2 License synchronization fails](#102-license-synchronization-fails)
  - [10.3 A Seat cannot be assigned](#103-a-seat-cannot-be-assigned)
  - [10.4 A policy is missing or unexpected](#104-a-policy-is-missing-or-unexpected)
  - [10.5 A delegated admin cannot access a setting](#105-a-delegated-admin-cannot-access-a-setting)
  - [10.6 A template image cannot be previewed](#106-a-template-image-cannot-be-previewed)
  - [10.7 Backend update status is stale](#107-backend-update-status-is-stale)
  - [10.8 The app fails after an update or restore](#108-the-app-fails-after-an-update-or-restore)
- [11. Logs and support data](#11-logs-and-support-data)

---

## 1. Scope and responsibilities

NC Connector Backend is the central policy and template service for NC Connector mail add-ons.

It manages:

- Community or Pro operating mode
- license status and Seat capacity
- Seat assignment to Nextcloud users
- Share, Talk, and email signature defaults
- group and user overrides
- Share, password-mail, Talk, and signature templates
- delegated NC Connector administration

It does not deploy or configure Thunderbird or Outlook. Client deployment remains a separate administrative task.

One Seat belongs to one Nextcloud user. The mail add-on must authenticate as that same user to receive the expected Seat and policy state.

The backend does not store mail content or attachments. Templates, policies, license state, Seats, overrides, and delegations are stored in the Nextcloud database. External template images may be mirrored temporarily for editor previews.

---

## 2. Requirements and network access

### 2.1 Supported platform

| Component | Requirement |
|---|---|
| Nextcloud | 32 through 35 |
| PHP | 8.3 or newer |
| Transport | HTTPS for production access |
| Background jobs | Nextcloud background jobs must run regularly; system cron is recommended |
| Browser | A current browser for the Nextcloud administration interface |
| Mail client | A maintained NC Connector release for Thunderbird or Outlook Classic |

The PHP runtime must provide the normal Nextcloud XML and DOM capabilities. The web-server process also needs write access to `ncc_backend_4mc/img/runtime` when administrators use external images in template previews.

### 2.2 Nextcloud apps

| App | Requirement | Purpose |
|---|---|---|
| Files Sharing | Required for Share workflows used by the mail clients | Creates and manages public shares |
| Talk | Optional | Enables calendar-based Talk meetings |
| Secrets | Optional | Sends separate share passwords as expiring Secret links |

If Secrets is unavailable, NC Connector continues with plain separate password mails. The corresponding Secrets option is disabled in the backend UI.

### 2.3 Administrative access

Initial installation and full configuration require a Nextcloud administrator.

Shell-based lifecycle commands additionally require:

- access to the Nextcloud installation
- permission to run `occ`
- the correct PHP binary and web-server account for that installation

Commands in this guide use `php occ ...`. Adapt the PHP path and operating-system user to the local Nextcloud deployment.

Delegated NC Connector admins can manage only their assigned scopes. They cannot install the app, change license settings, assign Seats, or manage other delegations.

### 2.4 Network paths

| Direction | Destination | When used | Purpose |
|---|---|---|---|
| Mail client to Nextcloud | `/apps/ncc_backend_4mc/api/v1/status` or `/index.php/apps/ncc_backend_4mc/api/v1/status` | Client startup and policy refresh | Read Seat, license, and effective policy state |
| Nextcloud to `https://nc-connector.de/wp-json/ncc/v1/update-check` | At most once per UTC day | Community and Pro | Check the latest backend version |
| Nextcloud to `https://nc-connector.de/wp-json/ncc/v1/license/status` | Manual sync and the daily Pro job | Pro with stored credentials | Refresh license and Seat entitlement |
| Nextcloud to administrator-selected HTTPS image hosts | Template preview or image refresh | Only when templates contain external images | Mirror safe public images for the editor |

Allow DNS resolution, TLS validation, and outbound HTTPS through the Nextcloud proxy or firewall where these functions are required.

The update check runs independently of Pro mode and license credentials. It sends product, installed version, stable channel, and a daily pseudonymous identifier. The license request is sent only in Pro mode with stored credentials.

Template image retrieval rejects non-HTTPS URLs, private or reserved destinations, excessive redirects, files larger than 4 MB, unsupported image formats, and mismatched content types.

### 2.5 Pre-deployment checklist

Before installing:

1. Confirm the supported Nextcloud and PHP versions.
2. Confirm that Nextcloud background jobs run successfully.
3. Back up the Nextcloud database and configuration.
4. Keep the currently installed backend package if this is an update.
5. Verify outbound access required by the selected operating mode.
6. Select a non-admin pilot user with a working Thunderbird or Outlook setup.
7. Decide whether Community mode or Pro mode will be used.
8. Record the intended defaults, group rules, user exceptions, and delegated-admin scopes.

Expected result: installation can proceed without changing production-wide client behavior immediately.

---

## 3. Installation and first rollout

### 3.1 Install from the Nextcloud App Store

Goal: install the published backend package through Nextcloud.

Prerequisites:

- a full Nextcloud administrator
- a completed pre-deployment backup
- access to the Nextcloud App Store

Steps:

1. Open **Apps** in Nextcloud.
2. Search for **NC Connector for mail integration**.
3. Select **Download and enable**.
4. Open **Administration settings → NC Connector Backend**.

Expected result: the administration page opens and shows the installed backend version.

Verification:

1. Confirm that the **General** tab loads without an error.
2. Confirm that **Community** is available.
3. Confirm that the version row shows the installed version.
4. Check the Nextcloud log for new `ncc_backend_4mc` errors.

If installation fails, disable the app, correct the reported platform, permission, or package problem, and enable it again. Do not use destructive removal as a troubleshooting shortcut.

### 3.2 Install a signed release archive

Goal: install a release supplied outside the App Store.

Prerequisites:

- a signed release archive from a trusted source
- shell access to the Nextcloud server
- knowledge of the active Nextcloud apps directory

Steps:

1. Extract the archive into the configured Nextcloud apps directory.
2. Verify that the resulting path ends with `ncc_backend_4mc/appinfo/info.xml`.
3. Apply the same owner and permissions used by the other Nextcloud apps.
4. Run `php occ app:enable ncc_backend_4mc`.
5. Open **Administration settings → NC Connector Backend**.

Expected result: Nextcloud reports the app as enabled and the administration page opens.

Verification:

- `php occ app:list` lists `ncc_backend_4mc` under enabled apps.
- The displayed version matches the installed archive.
- No integrity or repair-step error appears in the Nextcloud log.

If the package structure or signature is rejected, disable the app and replace it with a valid signed archive. Keep the database backup until pilot acceptance is complete.

### 3.3 Configure the first Seat

Goal: establish a small working configuration before broad rollout.

Steps:

1. Open **General**.
2. Choose **Community** or **Pro**.
3. For Pro, enter the license email and license key, save them, and run **Sync now**.
4. Open **Group Settings → Default Settings**.
5. Review Share, Talk, and email signature defaults.
6. Replace example signature postal and legal text before enabling it for production.
7. Open **Seat assignment** and assign one non-admin pilot user.
8. Configure the mail add-on with the same Nextcloud user.
9. Test one Share workflow and every enabled Talk or signature workflow.

Expected result: the pilot user receives the configured policies and can complete the enabled workflows.

Rollback: remove the pilot Seat and return changed defaults to their previous values. This does not uninstall the backend or delete stored configuration.

### 3.4 Pilot acceptance

Complete these checks before assigning more Seats:

| Check | Expected result |
|---|---|
| Administration page | Loads for a full admin without server or browser-console errors |
| License mode | Community shows one Seat, or Pro shows the purchased entitlement |
| Seat assignment | Pilot user appears in **Assigned seats** |
| Runtime status | Authenticated pilot request returns HTTP 200 and an assigned Seat |
| Share | Effective password, expiry, permission, and link-target settings are applied |
| Talk | Enabled Talk settings and invitation format are applied |
| Signature | Matching sender identity receives the configured signature behavior |
| Group override | A pilot group value wins over the default |
| User override | A forced pilot-user value wins over the group |
| Editable setting | The add-on can change it locally |
| Locked setting | The add-on displays or applies the forced value |
| Logs | No new unexpected `warning` or `error` entry appears |

Record the Nextcloud version, backend version, client version, pilot user, date, and result. Expand the rollout only after the required rows pass.

---

## 4. General operation

### 4.1 Open the administration page

Full Nextcloud administrators use:

- **Settings → Administration → NC Connector Backend**

Delegated NC Connector admins use:

- **Settings → Personal → NC Connector Backend**

The direct path `/apps/ncc_backend_4mc/` remains available for deep links. It is not shown as a main app-bar entry.

The interface contains:

| Area | Purpose |
|---|---|
| `General` | Mode, license, update status, and recommended apps |
| `Group Settings` | Defaults, Seats, reports, group overrides, and user overrides |
| `Advanced` | Delegated NC Connector administration |

### 4.2 Community and Pro mode

| Mode | Capacity | External license request |
|---|---|---|
| `Community` | One Seat | None |
| `Pro` | Purchased Seat entitlement | Required after credentials are stored |

Changing mode does not delete settings or Seats. A mode or license state with insufficient entitlement can pause Seat access until capacity becomes valid again.

### 4.3 License synchronization

In Pro mode:

1. Enter **License email** and **License key**.
2. Save the credentials.
3. Select **Sync now**.
4. Review status, validity, grace period, purchased Seats, available Seats, last sync, and any error.

Expected result: the status becomes active or grace where applicable, and the Seat totals match the license.

The scheduled Pro synchronization runs every 24 hours when Pro mode and complete credentials are present. Community mode does not contact the license endpoint.

### 4.4 Backend update status

The General tab shows:

- installed backend version
- latest known stable version
- last check time
- update availability or the last error

The background job wakes every six hours but performs at most one successful check per UTC day. Opening the admin page starts the first check when no cached result exists.

The status row is informational. It does not download or install an update.

### 4.5 Recommended apps

The General tab reports whether optional integrations are available.

Currently:

- **Nextcloud Secrets** enables expiring Secret links for separate Share passwords.

Missing optional apps do not block the backend. Dependent settings become unavailable or fall back to their documented mode.

---

## 5. Policies and Seats

### 5.1 Policy precedence

The effective order is:

1. forced user override
2. forced matching group override
3. default

`inherit` moves to the next lower layer.

If several group overrides apply, the group with the lowest numeric priority wins. Use distinct priorities for overlapping groups so the result is easy to audit.

### 5.2 Editable in add-on

**Editable in add-on** separates the backend default from the local user choice.

When enabled:

- the backend sends a concrete default
- the add-on may keep a local choice for that setting
- the backend value field is inactive in the default-settings UI

When a group or user override is forced:

- the forced value is effective
- local editing is disabled for that setting

Templates remain backend-controlled. The detailed response fields are documented in [endpoints.md](endpoints.md).

### 5.3 Share settings

| Setting | Operational effect |
|---|---|
| `Base directory` | Prefills the target base path for new shares |
| `Share name` | Prefills the share title |
| `Upload/Create` | Allows recipients to add content |
| `Edit` | Allows recipients to modify content |
| `Delete` | Allows recipients to delete content |
| `Set password` | Enables password protection by default |
| `Send password separately` | Sends the password after the main mail |
| `Password mode` | Uses a plain password mail or an expiring Secrets link |
| `Nextcloud Secrets link expiry (days)` | Sets the Secrets-link lifetime |
| `Expiration (days)` | Sets the public-share lifetime |
| `Always share attachments via NC Connector` | Routes every attachment through the NC Connector flow |
| `Offer upload for files larger than (MB)` | Offers NC Connector above the configured threshold |
| `Attachment link target` | Uses `ZIP download` by default or the `Nextcloud share page` in attachment mode |
| `Language in share HTML block` | Selects a built-in language or `custom` |
| `Email share template` | Defines custom HTML for the main Share block |
| `Email password template` | Defines custom HTML for separate password delivery |

Operational dependencies:

- **Always share attachments** makes the threshold inactive.
- Disabling **Send password separately** makes password mode and Secrets expiry inactive.
- Missing Secrets support disables the Secrets option and uses plain password delivery.
- **Attachment link target** affects attachment automation only; manual shares keep the standard share page.
- Custom Share templates should contain `{URL}`, `{LINK_INTRO}`, and `{LINK_LABEL}` so the visible text matches the selected link target.

### 5.4 Talk settings

| Setting | Operational effect |
|---|---|
| `Language in Talk description text` | Selects a built-in language or `custom` |
| `Talk invitation output` | Returns custom invitations as HTML or cleaned plain text |
| `Talk invitation template` | Defines the custom invitation |
| `Title` | Prefills the room title |
| `Lobby active until start time` | Keeps participants in the lobby until the event starts |
| `Show in search` | Controls Talk search visibility |
| `Add users` | Adds internal users from event recipients |
| `Add guests` | Adds external recipients as guests |
| `Set password` | Enables Talk password protection |
| `Delete Talk room when deleting a saved event` | Allows the client that created a linked room to delete it with the saved event |
| `Room type` | Selects event or group room behavior |

`Set password` is the single Talk password control. The obsolete separate password-generation setting is no longer present.

### 5.5 Email signature settings

| Setting | Operational effect |
|---|---|
| `Add signature when composing` | Enables the managed signature for new messages |
| `Add signature when replying` | Enables it for replies |
| `Add signature when forwarding` | Enables it for forwards |
| `Email signature template` | Defines the managed HTML signature |

Important behavior:

- The backend returns HTML; clients may derive plain text.
- A disabled but add-on-editable compose setting still provides the reply, forward, and rendered template values so the user can enable signatures locally.
- A disabled and locked compose setting makes the dependent signature values inactive.
- The signature is applied only when the sender identity matches the email returned for that Seat user.
- A forced **Signature email address** user override replaces the Nextcloud profile email for matching and `{EMAIL}`.
- Empty profile values remove their surrounding line or table row.
- The built-in signature is an example. Replace its address and legal text before rollout.

Available variables:

- `{NAME}`
- `{EMAIL}`
- `{PHONE}`
- `{PHONE_MOBILE}`
- `{ABOUT}`
- `{FUNCTION}`
- `{ORGANISATION}`
- `{CUSTOM1}`
- `{CUSTOM2}`

`{PHONE_MOBILE}`, `{CUSTOM1}`, and `{CUSTOM2}` come from user overrides. `{ABOUT}` keeps line breaks.

### 5.6 Template operation

Share templates support:

- `{URL}`
- `{LINK_INTRO}`
- `{LINK_LABEL}`
- `{PASSWORD}`
- `{EXPIRATIONDATE}`
- `{RIGHTS}`
- `{NOTE}`

Talk templates support:

- `{MEETING_URL}`
- `{PASSWORD}`

Operational rules:

1. Select `custom` for the corresponding template language.
2. Open the template editor.
3. Edit or reset the template.
4. Preview both content and links.
5. Save the modal and then save the settings layer.
6. Test the result with a pilot client.

Unsafe scripts, event handlers, URL schemes, forms, and embedded content are removed. External preview images must use a public HTTPS URL, remain below 4 MB, and return a supported image type. The stored template keeps the external URL; the local runtime copy is only an editor cache.

Updating the built-in example does not rewrite templates already stored at the default, group, or user layer.

### 5.7 Seat assignment and reporting

Seat assignment provides:

- group filtering
- user search
- individual assignment
- bulk assignment for the filtered set
- bulk removal
- assigned-Seat overview
- CSV export of effective policy state

Admin accounts are excluded by default. This avoids accidental license use by administrative or automation accounts.

To allow admin accounts explicitly:

- show state: `php occ ncc:admin-seat-assignment status`
- enable: `php occ ncc:admin-seat-assignment enable`
- restore the default: `php occ ncc:admin-seat-assignment disable`

Disabling the override does not remove an existing admin Seat. Remove it from **Assigned seats** if it should no longer consume capacity.

The assigned-Seat table shows assignment state and links to matching group or user overrides. The CSV report omits raw template HTML and reports an effective custom template as `Custom`.

### 5.8 Group overrides

Use group overrides for department or team differences.

Per setting:

- `inherit` uses the default
- `forced` applies the group value and locks it in the add-on

Group overrides may be created before Seats are assigned. They become effective only for Seat users in the group.

Lower numeric priority wins when a user belongs to several matching groups.

### 5.9 User overrides

Use user overrides for individual exceptions.

Per setting:

- `inherit` checks the winning group and then the default
- `forced` applies the user value and locks it in the add-on

Signature user overrides additionally provide:

- `Signature email address`
- `Mobile phone`
- `Custom 1`
- `Custom 2`

Avoid using many user overrides as a substitute for group design. Review exceptions regularly through the assigned-Seat overview and CSV report.

---

## 6. Delegated administration

Only full Nextcloud administrators can create, change, or remove NC Connector delegations.

Delegations can cover:

| Area | Available scopes |
|---|---|
| Share | policies, templates, group overrides, user overrides |
| Talk | policies, templates, group overrides, user overrides |
| Signature | policies, template and signature user values, group overrides, user overrides |

Delegated admins:

- see NC Connector Backend in personal settings
- see only permitted tabs and rows
- can save only permitted settings
- may read the assigned-Seat overview when their scope requires override context
- cannot assign Seats
- cannot change license mode or credentials
- cannot manage delegations

The backend checks permissions on every request. UI visibility does not replace server-side access checks.

After changing a delegation, sign in as the delegated user and verify both visible rows and denied actions before relying on it in production.

---

## 7. Client rollout dependencies

Deploy the Thunderbird or Outlook add-on separately. The client must use the same Nextcloud identity that owns the Seat.

Before broad rollout:

1. Update the mail client and NC Connector add-on to a maintained compatible version.
2. Verify HTTPS access from the client network to the Nextcloud instance.
3. Verify the authenticated backend status request.
4. Test locally editable and forced policy values.
5. Test every enabled Share, Talk, password, and signature path.
6. Record the client version with the pilot result.

<a id="thunderbird-attachment-automation-prerequisite"></a>
### 7.1 Thunderbird attachment automation

This section applies only to Thunderbird.

When NC Connector should own the attachment workflow, disable Thunderbird's competing compose prompts centrally. NC Connector does not change these Thunderbird-wide preferences.

Relevant preferences:

| Preference | Recommended locked value | Purpose |
|---|---|---|
| `mail.compose.attachment_reminder` | `false` | Disables the missing-attachment prompt |
| `mail.compose.big_attachments.notify` | `false` | Disables Thunderbird's native large-attachment upload prompt |
| `mail.compose.big_attachments.threshold_kb` | `5120` | Records the standard 5 MB threshold even though notification is disabled |

Merge this into the existing `policies.json`:

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

Example with force installation:

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

Verification:

1. Restart Thunderbird after policy deployment.
2. Open Thunderbird settings and confirm that the affected preferences are locked.
3. Add an attachment above the NC Connector threshold.
4. Confirm that only the NC Connector flow appears.

Official references:

- [Thunderbird Enterprise Policies — Preferences](https://thunderbird.github.io/policy-templates/templates/esr140/#preferences)
- [Thunderbird compose preferences source](https://searchfox.org/comm-central/source/mail/components/preferences/compose.inc.xhtml)

---

## 8. Operational verification

The mail-client status endpoint is:

- Pretty URL: `GET /apps/ncc_backend_4mc/api/v1/status`
- Front-controller URL: `GET /index.php/apps/ncc_backend_4mc/api/v1/status`

Use an authenticated Seat user. Do not use an administrator account unless admin Seat assignment was explicitly enabled and that account owns a Seat.

Expected result:

- HTTP 200
- a current access and Seat state
- effective Share, Talk, and signature policies
- editability information for locally configurable settings

The exact response fields are documented in [endpoints.md](endpoints.md).

If the Pretty URL returns 404 while the front-controller URL works, correct the Nextcloud web-server rewrite configuration. Do not change backend files to compensate for a proxy or rewrite problem.

Run the pilot acceptance table again after:

- a backend update
- a Nextcloud major update
- a reverse-proxy or authentication change
- a license-mode change
- a broad policy redesign
- a client major update

---

## 9. Update, rollback, backup, and removal

### 9.1 Update

Goal: install a newer backend version while keeping configuration and Seat data.

Prerequisites:

- a maintenance window appropriate for the installation
- a current Nextcloud database and configuration backup
- the previous signed backend package
- the release notes for the target version

Steps:

1. Record the current Nextcloud, PHP, backend, Thunderbird, and Outlook versions.
2. Export the assigned-Seat CSV.
3. Back up the Nextcloud database and configuration.
4. Update through **Apps** or run `php occ app:update ncc_backend_4mc`.
5. Confirm that the app remains enabled.
6. Open the admin page and review version, license, Seat totals, and update status.
7. Run the pilot acceptance checks.
8. Review the Nextcloud log.

Expected result: the new version is active, stored policies and Seats remain present, and the pilot workflows pass.

If verification fails, stop the rollout and use the rollback procedure. Do not run destructive removal.

### 9.2 Roll back

Goal: return to the previous known-good application and data state.

Prerequisites:

- the previous signed app package
- the database and configuration backup taken before the update
- a maintenance window

Steps:

1. Disable the backend with `php occ app:disable ncc_backend_4mc`.
2. Restore the pre-update Nextcloud database and configuration according to the site backup procedure.
3. Restore the previous `ncc_backend_4mc` app directory or reinstall the previous signed package.
4. Apply the normal app ownership and permissions.
5. Enable the app with `php occ app:enable ncc_backend_4mc`.
6. Open the admin page and run the pilot acceptance checks.

Expected result: application code and stored data both match the previous release state.

Do not combine older app code with a database already changed by a newer release. Restore code and data from the same recovery point.

### 9.3 Back up

NC Connector data is part of the Nextcloud database. Back up the complete Nextcloud database rather than exporting only selected tables.

The app-owned tables use the configured Nextcloud table prefix and these suffixes:

- `nccb_settings`
- `nccb_seats`
- `nccb_client_overrides`
- `nccb_group_overrides`
- `nccb_admin_delegations`

The settings table contains policies, encrypted license credentials, license state, and cached update metadata. License-key decryption depends on the Nextcloud instance secrets, so the normal Nextcloud configuration backup is required as well.

Keep:

- full Nextcloud database backup
- Nextcloud `config/config.php` and instance secrets
- the installed or previous signed backend package
- the assigned-Seat CSV as an operational reference

The files under `ncc_backend_4mc/img/runtime` are a regenerable preview cache. Stored templates retain their external image URLs; the cache is not the primary copy of policy or template data.

### 9.4 Restore

Goal: restore backend operation after data loss or a failed update.

Steps:

1. Restore the Nextcloud database and configuration from the same backup set.
2. Restore a backend app version compatible with that database state.
3. Restore normal owner and permissions, including runtime image-cache write access if external images are used.
4. Enable the app.
5. Open **General** and review license and update status.
6. Verify Seat assignments, overrides, delegations, and templates.
7. Run the authenticated status check and pilot acceptance table.
8. Run a manual Pro sync if Pro mode is active.

Expected result: policy state matches the backup and the pilot user can complete the configured workflows.

### 9.5 Disable, reinstall, or remove

| Command | Data effect | Intended use |
|---|---|---|
| `php occ app:disable ncc_backend_4mc` | Keeps data | Maintenance or temporary shutdown |
| `php occ app:enable ncc_backend_4mc` | Keeps data and creates missing schema objects | Re-enable or repair installation |
| `php occ app:remove --keep-data ncc_backend_4mc` | Keeps database data | Remove app code before a controlled reinstall |
| `php occ app:remove ncc_backend_4mc` | Deletes app-owned data | Permanent destructive removal |

Full removal deletes:

- all five `nccb_*` tables
- both NC Connector background-job entries
- the runtime image cache

Before full removal:

1. Export the assigned-Seat report.
2. Take a complete backup.
3. Confirm that permanent data deletion is intended.
4. Record who approved the removal.

There is no in-app undo for full removal. Recovery requires a database and configuration restore.

---

## 10. Operational checks and troubleshooting

### 10.1 Routine checks

| Frequency or event | Check | Expected result |
|---|---|---|
| Daily | Nextcloud background-job health | Jobs run on schedule without repeated failures |
| Daily in Pro | Last license sync | Recent timestamp and no error |
| Daily | Backend update status | Recent daily check or a documented network exception |
| Weekly | Seat usage | Assigned and available totals match rollout records |
| Weekly | Nextcloud log | No repeated `ncc_backend_4mc` warning or error |
| Monthly | Delegations | Only current administrators retain scopes |
| Monthly | User overrides | Exceptions remain necessary and documented |
| After policy changes | Pilot workflow | Effective client behavior matches the change |
| After updates or restore | Full pilot acceptance | All required rows pass |

Use the assigned-Seat CSV for periodic review. It records effective policies without exposing raw template HTML.

### 10.2 License synchronization fails

Goal: restore Pro entitlement refresh.

Checks:

1. Confirm that **Pro** mode is selected.
2. Confirm that both license fields are stored.
3. Verify server access to `https://nc-connector.de/wp-json/ncc/v1/license/status`.
4. Check DNS, proxy, TLS trust, and firewall logs.
5. Review the last error in **General**.
6. Filter the Nextcloud log for `ncc_backend_4mc`.
7. Run **Sync now** again after correcting the cause.

Expected result: the last-sync timestamp advances and Seat totals match the entitlement.

Do not repeatedly replace credentials before checking the recorded error and network path.

### 10.3 A Seat cannot be assigned

Checks:

1. Review total, assigned, and available Seats.
2. In Pro, confirm an active or grace license state.
3. Confirm that the target is a real Nextcloud user.
4. Clear group and text filters.
5. If the target is an admin, check `php occ ncc:admin-seat-assignment status`.
6. Check the Nextcloud log for a Seat-limit or permission warning.

Expected result: the user appears in Seat search and assignment updates the assigned-Seat table.

### 10.4 A policy is missing or unexpected

Checks:

1. Confirm that the mail client authenticates as the Seat owner.
2. Confirm an active Seat.
3. Read the authenticated status endpoint.
4. Check for a forced user override.
5. Check matching groups and their numeric priorities.
6. Check the default and **Editable in add-on** state.
7. Confirm that the client version supports the setting.
8. Refresh the add-on policy state or restart the client.

Expected result: the effective value follows user, group, then default precedence.

### 10.5 A delegated admin cannot access a setting

Checks:

1. Sign in as a full Nextcloud administrator.
2. Open **Advanced** and review the user's active delegation.
3. Confirm the product area and action scope.
4. Save the delegation again if it changed.
5. Have the delegated user sign out and back in.
6. Check for an HTTP 403 and the matching server warning.

Expected result: permitted rows appear; actions outside the delegation remain unavailable.

### 10.6 A template image cannot be previewed

Checks:

1. Confirm a public HTTPS image URL.
2. Confirm that redirects remain on public HTTPS destinations.
3. Confirm a supported image format and a file size below 4 MB.
4. Confirm that the server response content type matches the image data.
5. Confirm web-server write access to `ncc_backend_4mc/img/runtime`.
6. Read the warning shown by the editor and the matching Nextcloud log entry.

Expected result: the editor displays the local preview image while the stored template keeps the external URL.

### 10.7 Backend update status is stale

Checks:

1. Confirm that Nextcloud background jobs run.
2. Verify outbound access to `https://nc-connector.de/wp-json/ncc/v1/update-check`.
3. Open the General tab to initialize a missing first result.
4. Review the last-check time and error.
5. Check the Nextcloud log.

Expected result: one successful result is stored per UTC day.

### 10.8 The app fails after an update or restore

Checks:

1. Confirm supported Nextcloud and PHP versions.
2. Confirm that `ncc_backend_4mc/appinfo/info.xml` belongs to the intended package.
3. Confirm file owner, permissions, and app integrity.
4. Confirm that the restored database, configuration, and app package belong to the same recovery point.
5. Run `php occ app:enable ncc_backend_4mc` and record the exact error.
6. Review the Nextcloud log before another change.

If the cause cannot be corrected safely, return to the last known-good backup using the rollback procedure.

---

## 11. Logs and support data

Collect the shortest log window that contains one complete reproduction.

### Nextcloud server log

1. Reproduce the problem once.
2. Open **Administration settings → Logging** and filter for `ncc_backend_4mc`.
3. With shell access, use `php occ log:watch` while reproducing if appropriate.
4. Copy the relevant `warning` or `error` entries and their timestamps.

### Browser console

For admin-UI problems:

1. Open the NC Connector Backend page.
2. Open the browser developer tools.
3. Reproduce the problem.
4. Record the request path, HTTP method, status, response message, and console error.

### Support package

Include:

- backend version
- Nextcloud version
- PHP version
- Community or Pro mode
- full-admin or delegated-admin role
- affected user and group structure using anonymized identifiers
- mail client and add-on version when a client is involved
- exact steps and expected result
- relevant server and browser log excerpts

Remove before sharing:

- license keys
- app passwords
- authorization headers
- cookies and tokens
- private Share, Talk, or Secrets links
- customer names, addresses, mail content, and attachment names not required for diagnosis
