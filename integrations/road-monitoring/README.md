# IPMS ↔ LG Road Monitoring System integration

Repo: https://github.com/conopioclarence96-commits/lg-road-monitoring

Data-sharing only, one direction:

- **IPMS (this repo) owns** infrastructure projects — planning, budgets, contractors, engineers, schedules, and the road alignment (geometry) drawn during Project Registration for Roads and Bridges projects.
- **LG Road Monitoring System owns** live road condition/incident monitoring (their `road_transportation_reports` / `road_maintenance_reports` tables) and their own public completed-projects showcase.
- The only thing exchanged is a **read-only feed**: upcoming and ongoing Roads and Bridges projects, with their road geometry, so their monitoring dashboard can plot/flag roads that are about to be (or currently being) worked on — instead of only reacting to citizen incident reports after the fact.

Same shape as the IPMS ↔ Urban Planning System and IPMS ↔ Facilities Reservation integrations: as of writing, the Road Monitoring System has no live endpoint of its own for IPMS to push to (no `ipms`-related code exists in their repo yet), so this is a **pull/poll feed they call**, not a push. Swapping to a push later, once they build a receiver, is a contained change to this one file — nothing else in IPMS needs to change.

## Flow

```
LG Road Monitoring System (their repo)
  → GET integrations/road-monitoring/upcoming-roads-feed.php   (header X-API-Key: ...)
  ← { roads: [...] }
  → Their own dashboard/map (they already have a TomTom map integration,
    lgu_staff/pages/api/tomtom/proxy.php) plots the polyline and shows the
    project's timeline/progress to warn drivers and residents ahead of time
```

## Setup

### 1. This repo (IPMS)

- Set `ROAD_MONITORING_API_KEY` in `.env` (shared secret — the Road Monitoring System must send the identical value). Nothing else to configure; the feed reads directly from the existing `projects` and `project_road_geometry` tables, no new schema.

### 2. LG Road Monitoring System (their repo — not part of this codebase)

Nothing exists there yet for this integration — it needs a new poller, analogous to how `includes/CimmClient.php` is IPMS's own sender for the CIMMS integration (they already have their own CIMMS-shaped files — `lgu_staff/pages/api/cimm-reports-pull.php` / `cimm-reports-webhook.php` — as a reference pattern to follow):

- Poll `upcoming-roads-feed.php` periodically (e.g. hourly is plenty — this data doesn't change minute to minute).
- Send header `X-API-Key: <the same ROAD_MONITORING_API_KEY value>` on every request.
- Store/refresh what's returned in their own table (or just re-render on each poll) — there's no shared project ID between the two systems today, so if they want to track "seen before" state, `project_id` from this feed is the stable key to key off of.

## Payload — outbound (here → LG Road Monitoring System)

`GET integrations/road-monitoring/upcoming-roads-feed.php` (header `X-API-Key: ...`)

- Returns every Roads and Bridges project with drawn geometry that HOPE has approved and that isn't finished or cancelled yet — i.e. `approved`, `bidding`, `awarded`, `assigned`, `active`, `delayed`, `on_hold`, `completion_inspection`. Still-internal drafts/reviews (`draft`, `endorsed`, `returned`, `planning`) and finished/dead projects (`completed`, `turnover`, `cancelled`) are excluded.
- Always the current/live state, not a consume-once queue — just re-poll and replace on each pull.
- Response shape:
  ```json
  {
    "success": true,
    "count": 1,
    "roads": [
      {
        "project_id": 9,
        "project_name": "Kabayani Street–Matandang Balara Bridge",
        "project_status": "delayed",
        "progress_percent": 60,
        "start_date": "2022-06-01",
        "end_date": "2027-06-30",
        "road_name": "Kabayani Street–Matandang Balara Bridge",
        "road_type": "Bridge",
        "road_status": "Bridge Construction",
        "polyline_coordinates": [[14.665, 121.085], [14.652, 121.101]],
        "road_length_meters": 2164.7,
        "start_coordinate": { "lat": 14.665, "lng": 121.085 },
        "end_coordinate": { "lat": 14.652, "lng": 121.101 },
        "barangays_covered": ["Matandang Balara"],
        "districts_covered": ["District 3"]
      }
    ]
  }
  ```
- Deliberately narrow field list: Project ID, Project Name, Project Status, Progress, Start/End Date, Road Name, Road Type, Road Status, Polyline Coordinates, Road Length, Start/End Coordinate, Barangays Covered, Districts Covered. No budget, no contractor/engineer identities, no internal remarks or documents — same restraint as the Urban Planning road geometry feed.

## What this integration deliberately does NOT do

- No endpoint here lets the Road Monitoring System create, edit, or cancel an IPMS project — this is a read-only feed, full stop.
- No incident/condition report data flows from their system into IPMS — that data model (`road_transportation_reports`, `road_maintenance_reports`) stays entirely theirs; this integration has no inbound side.
- Nothing about IPMS's own Admin modules (Project Registration, GIS Map, Reports, etc.) changes because of this integration — it is entirely new, additive code living only under `integrations/road-monitoring/`.

## Adding another integration later

Follow this exact shape, same as documented in `integrations/urban-planning/README.md` and `integrations/facilities-reservation/README.md`:
1. A small schema file if the integration needs its own tables (this one didn't — it reads the existing `projects` and `project_road_geometry` tables directly).
2. An `integrations/<system>/` folder for whichever direction(s) of cross-system traffic it needs, plus its own README.
3. A single shared-secret env var (`<SYSTEM>_API_KEY`), checked via `X-API-Key` and `hash_equals()`, matching CIMMS/Urban Planning/Facilities Reservation/this one.
