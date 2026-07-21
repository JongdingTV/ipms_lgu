# IPMS ↔ Barangay Culiat Facilities Reservation System integration

Repo: https://github.com/lmfollero123/facilities-reservation-system1

Data-sharing only, one direction:

- **IPMS (this repo) owns** infrastructure projects — planning, budgets, contractors, engineers, schedules.
- **Facilities Reservation System owns** facility bookings — resident registration, admin approval, scheduling, check-in.
- The only thing exchanged is a **read-only status feed**: which Culiat facilities/locations currently have (or soon will have) an IPMS project affecting them, so the Reservation System can avoid letting residents book a facility that's mid-renovation or under construction.

Same shape as the IPMS ↔ Urban Planning System integration (`integrations/urban-planning/`): as of writing, the Reservation System has no live endpoint of its own for IPMS to push to (its `routes/web.php` still points at placeholder view files, not a working API), so this is a **pull/poll feed they call**, not a push. Swapping to a push later, once they build a receiver, is a contained change to this one file — nothing else in IPMS needs to change.

## Flow

```
Facilities Reservation System (their repo)
  → GET integrations/facilities-reservation/facility-status-feed.php   (header X-API-Key: ...)
  ← { facilities_affected: [...], upcoming_work: [...] }
  → Their own booking logic decides whether to block a facility for the affected window
```

## Setup

### 1. This repo (IPMS)

- Set `FACILITIES_RESERVATION_API_KEY` in `.env` (shared secret — the Reservation System must send the identical value). Nothing else to configure; the feed reads directly from the existing `projects` table, no new schema.
- The barangay filter (`Culiat`) is one constant — `PUBLIC_FACILITIES_BARANGAY_FILTER` in `includes/config.php` — shared with the Admin-side Public Facilities Integration page (`api/public-facilities.php`). Supporting another barangay later, for either integration, is a one-line change there.

### 2. Facilities Reservation System (their repo — not part of this codebase)

That project needs its own poller, analogous to how `includes/CimmClient.php` is IPMS's own sender for the CIMMS integration:

- Poll `facility-status-feed.php` periodically (or before showing/accepting a booking).
- Send header `X-API-Key: <the same FACILITIES_RESERVATION_API_KEY value>` on every request.
- Match `facilities_affected[].location` against their own facility records — **there is no shared facility ID between the two systems today**, so this match currently has to be done by name/keyword on their end (or by a human curating the mapping once). If they'd rather match on something more structured, the next step would be agreeing on a shared facility identifier and adding it to both sides.

## Payload — outbound (here → Facilities Reservation System)

`GET integrations/facilities-reservation/facility-status-feed.php` (header `X-API-Key: ...`)

Response:
```json
{
  "success": true,
  "barangay": "Culiat",
  "count": 1,
  "facilities_affected": [
    {
      "project_id": 24,
      "project_code": "PRJ-012",
      "name": "Culiat Multi-Purpose Hall Renovation",
      "category": "Public Buildings and Facilities",
      "location": "Barangay Culiat, District 1, Quezon City",
      "status": "active",
      "progress": 45,
      "start_date": "2026-01-01",
      "expected_completion": "2026-12-01",
      "latitude": 14.671,
      "longitude": 121.048
    }
  ]
}
```

- `facilities_affected` — projects currently in `active`, `delayed`, `on_hold`, or `completion_inspection` — i.e., work that's actually under way right now. Treat these as "unavailable for booking."
- Pass `?include_upcoming=1` to also get an `upcoming_work` array of the same shape, for projects in `approved`, `bidding`, `awarded`, or `assigned` — not yet started, useful for planning ahead but not a reason to block a booking today.
- `expected_completion` is IPMS's own project end date, not a hard guarantee — treat it as an estimate, same as it is inside IPMS itself.

## What this integration deliberately does NOT do

- No endpoint here lets the Reservation System create, edit, or cancel an IPMS project — this is a read-only feed, full stop.
- No booking, reservation, or resident data flows back into IPMS — that data model doesn't exist in this repo at all, and this integration has no inbound side.
- Nothing about IPMS's own Admin modules (Project Registration, GIS Map, Reports, etc.) changes because of this integration — it is entirely new, additive code living only under `integrations/facilities-reservation/`.

## Adding another integration later

Follow this exact shape, same as documented in `integrations/urban-planning/README.md`:
1. A small schema file if the integration needs its own tables (this one didn't — it reads the existing `projects` table directly).
2. An `integrations/<system>/` folder for whichever direction(s) of cross-system traffic it needs, plus its own README.
3. A single shared-secret env var (`<SYSTEM>_API_KEY`), checked via `X-API-Key` and `hash_equals()`, matching CIMMS/Urban Planning/this one.
