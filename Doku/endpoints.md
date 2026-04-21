# NC Connector – Mail Client Runtime API

This specification applies to external mail clients such as Thunderbird, Outlook Classic, and Outlook Web.
For mail clients, **only one public read-only runtime endpoint** is exposed: `GET /api/v1/status`.

## Read endpoints

### 1) Combined status + policies
- **HTTP method:** `GET`
- **Path:** `/apps/ncc_backend_4mc/api/v1/status`
- **Path variant without pretty URLs:** `/index.php/apps/ncc_backend_4mc/api/v1/status`
- **Purpose:** Returns license state, seat state, and the effective policy settings in a single request.
- **Auth / permissions:** Authenticated Nextcloud user (session or app password)
- **Request parameters:**
  - Query: none for normal mail-client usage
  - Internal admin tooling may additionally use `user_id=<nextcloud-user-id>` to inspect or export the effective state for a specific user
  - Body: none
- **Response shape:**
  - `status`: license and seat state for the resolved user
  - `policy`: effective settings grouped into `share` and `talk`
  - `policy_editable`: add-on editability grouped into `share` and `talk`
  - There is **no** separate `default` block in the runtime response.
- **Policy null rules:**
  - `policy.share` and `policy.talk` are `null` when `status.overlicensed=true` or `status.seat_assigned=false`.
  - `policy_editable.share` and `policy_editable.talk` are also `null` when `status.overlicensed=true` or `status.seat_assigned=false`.
  - For settings with **Editable in add-on** enabled, `policy` still returns the configured backend default value.
  - Whether a setting may be changed in the add-on is returned separately in `policy_editable` as `true` or `false`.
  - Effective precedence is:
    - user override
    - group override
    - default
  - If a user has a **forced override**, that value always wins over group overrides and global defaults.
  - If no user override exists but a matching group override does, the group override wins over the global default.
  - A group setting in mode **inherit** therefore falls back to the global default.
  - A user setting in mode **inherit** therefore falls back first to a matching group override and only then to the global default.
  - A **forced override** also sets the corresponding `policy_editable` value to `false` for that user and setting.
  - If a user override is removed and the setting falls back to a group override or the default again, `policy_editable` follows that lower layer again.
  - `policy.share.attachments_min_size_mb` is `null` when `policy.share.attachments_always_via_ncconnector=true`.
  - `policy.share.share_html_block_template` and `policy.share.share_password_template` are `null` when `policy.share.language_share_html_block != "custom"`.
  - `policy.talk.talk_invitation_template` and `policy.talk.talk_invitation_template_format` are `null` when `policy.talk.language_talk_description != "custom"`.
  - `policy.talk.event_description_type` is always either `"html"` or `"plain_text"`.
  - `policy_editable` does not contain `event_description_type`, because it is derived from the effective talk template mode and is not directly editable in mail clients.
  - If `custom` is active, `policy.share.share_html_block_template` contains the stored HTML template with the direct logo URL.
  - If `policy.talk.talk_invitation_template_format = "html"`, `policy.talk.talk_invitation_template` contains the stored HTML template.
  - If `policy.talk.talk_invitation_template_format = "plain_text"`, `policy.talk.talk_invitation_template` contains cleaned plain text with preserved raw URLs and preserved template variables such as `{MEETING_URL}` or `{PASSWORD}`.
  - If `policy.talk.language_talk_description != "custom"`, `policy.talk.talk_invitation_template_format` is `null` and `policy.talk.event_description_type` falls back to `"plain_text"`.
- **Example request (curl):**
```bash
curl -u "alice:APP_PASSWORD" \
  -H "Accept: application/json" \
  "https://cloud.example.com/apps/ncc_backend_4mc/api/v1/status"
```
- **Example response (JSON):**
```json
{
  "status": {
    "user_id": "alice",
    "seat_assigned": true,
    "seat_state": "active",
    "overlicensed": false,
    "mode": "pro",
    "is_valid": true,
    "expires_at_iso": "2026-04-18T00:00:00+00:00",
    "grace_until_iso": "2026-05-02T00:00:00+00:00"
  },
  "policy": {
    "share": {
      "share_base_directory": "NC Connector",
      "share_name_template": "Share name",
      "share_permission_upload": true,
      "share_permission_edit": true,
      "share_permission_delete": true,
      "share_set_password": true,
      "share_send_password_separately": true,
      "share_expire_days": 8,
      "attachments_always_via_ncconnector": false,
      "attachments_min_size_mb": 5,
      "share_html_block_template": null,
      "share_password_template": null,
      "language_share_html_block": "en"
    },
    "talk": {
      "event_description_type": "plain_text",
      "language_talk_description": "en",
      "talk_invitation_template_format": null,
      "talk_invitation_template": null,
      "talk_generate_password": true,
      "talk_title": "Meeting",
      "talk_lobby_active": true,
      "talk_show_in_search": true,
      "talk_add_users": true,
      "talk_add_guests": false,
      "talk_set_password": true,
      "talk_room_type": "event"
    }
  },
  "policy_editable": {
    "share": {
      "share_base_directory": true,
      "share_name_template": true,
      "share_permission_upload": true,
      "share_permission_edit": true,
      "share_permission_delete": true,
      "share_set_password": true,
      "share_send_password_separately": true,
      "share_expire_days": true,
      "attachments_always_via_ncconnector": true,
      "attachments_min_size_mb": true,
      "share_html_block_template": false,
      "share_password_template": false,
      "language_share_html_block": true
    },
    "talk": {
      "language_talk_description": true,
      "talk_invitation_template_format": false,
      "talk_invitation_template": false,
      "talk_generate_password": true,
      "talk_title": true,
      "talk_lobby_active": true,
      "talk_show_in_search": true,
      "talk_add_users": true,
      "talk_add_guests": true,
      "talk_set_password": true,
      "talk_room_type": true
    }
  }
}
```

## Write endpoints

- There are **no** write endpoints for mail clients.
- Admin write operations remain internal to the admin UI and are not part of this client specification.

## Runtime status codes

- `200 OK`: request accepted, response body contains `status`, `policy`, and `policy_editable`
- `401 Unauthorized`: authentication missing or invalid
- `404 Not Found`: wrong route or app not deployed under the expected app id
- `500 Internal Server Error`: unexpected server error during request handling

## Conventions

- **Date format:** ISO-8601 UTC (`YYYY-MM-DDTHH:MM:SS+00:00`), in some cases also Unix timestamp
- **Field names:** `snake_case`
- **Paging:** not required for the exposed mail-client endpoint
- **Seat state values:** `seat_state` is `none`, `active`, or `suspended_overlimit`


