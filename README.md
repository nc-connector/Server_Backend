# NC Connector Backend

This repository contains the **Nextcloud backend** for NC Connector.

It is the central control layer for the mail add-ons.  
The add-ons run in the mail client, but this backend decides:

- who is allowed to use NC Connector
- which settings the add-on receives
- which values users may change locally
- which templates and texts are delivered centrally

## What this backend is good for

If you want NC Connector to behave consistently across a team, this backend is the relevant part.

It gives admins a central place to manage:

- **seats** for individual Nextcloud users
- **license mode** (`Community` / `Pro`)
- **default policies** for mail clients
- **group overrides** for teams or departments
- **user overrides** for individual exceptions
- **Share and Talk templates**, including visual editors and preview

In short:

- without backend = users work directly in the add-on
- with backend = admins control rollout, policies, templates, and seat access centrally

## Backend comparison

| Capability | Without backend | With backend |
|---|---|---|
| Start directly in the mail client | ✅ | ✅ |
| Seat assignment and central policy control | ❌ | ✅ |
| Separate password delivery workflow | ❌ | ✅ |
| Group-based policy overrides | ❌ | ✅ |
| User-specific policy overrides | ❌ | ✅ |
| Custom Share and Talk templates | ❌ | ✅ |
| Visual template editor with preview | ❌ | ✅ |
| Central reporting for assigned seats | ❌ | ✅ |

## What the backend can do

### 1) Seat-based access

One seat maps to one Nextcloud user.

Admins can:

- assign and remove seats
- filter users by group
- bulk-assign seats
- see which users currently have a seat
- export a CSV report for documentation

### 2) Central mail-client policies

The backend provides effective settings to the mail add-ons, for example:

- share permissions
- password defaults
- expiry defaults
- attachment handling
- Talk room defaults
- language and template selection

This means the add-on does not have to guess how it should behave.  
It gets a clear policy payload from Nextcloud.

### 3) Group and user overrides

Policy resolution follows a clear order:

- **user override**
- **group override**
- **default**

That allows a practical setup:

- one clean global default
- team-specific deviations via group overrides
- one-off exceptions via user overrides

### 4) Central templates

Admins can centrally manage:

- Share HTML template
- Share password email template
- Talk invitation template

The backend includes:

- visual editor modal
- live preview
- template variables
- language helper for supported locales
- local runtime image handling for editor rendering

### 5) Auditable operation

The backend is meant to be supportable in production.

That means:

- server-side warnings and errors are logged consistently
- denied admin actions and invalid payloads leave traces in the Nextcloud log
- unexpected backend failures include exception context in the server log
- admin UI and user-page failures are visible in the browser console

## Community vs. Pro

The backend supports two operating modes:

- **Community**
  - 1 included seat
  - no license lookup
- **Pro**
  - seat entitlement comes from the license backend
  - license email and license key are managed in Nextcloud

## What the add-ons get from this backend

Mail clients use the backend mainly as a **read-only policy source**.

They receive:

- current seat/license state
- effective Share policies
- effective Talk policies
- information about which settings are still editable in the add-on

This keeps the client implementation simpler and makes support cases easier to reason about.

## Typical use case

A company wants:

- centrally managed Share defaults
- separate password delivery
- controlled Talk defaults
- templates in corporate wording/design
- different rules for departments
- a clear list of licensed users

That is exactly what this backend is for.

## Repository structure

- `nc_connector_backend/` – the actual Nextcloud app
- `Doku/` – project documentation
- `release/` – signing and packaging helpers

## Documentation

Further details:

- `Doku/admin.md`
- `Doku/endpoints.md`
- `Doku/development.md`
- `Translations.md`
