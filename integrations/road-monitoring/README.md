# IPMS ↔ LG Road Monitoring System integration

Repo: https://github.com/conopioclarence96-commits/lg-road-monitoring

Data-sharing only, one direction:

- **IPMS (this repo) owns** infrastructure projects — planning, budgets, contractors, engineers, schedules, and the road alignment (geometry) drawn during Project Registration for Roads and Bridges projects.
- **LG Road Monitoring System owns** live road condition/incident monitoring (their `road_transportation_reports` / `road_maintenance_reports` tables) and their own public completed-projects showcase.
- The only thing exchanged is a **read-only feed**: Roads and Bridges projects across their full public lifecycle (new, ongoing, completed, cancelled), with their road geometry, so their monitoring dashboard can plot/flag roads that are about to be worked on, currently being worked on, finished, or called off — instead of only reacting to citizen incident reports after the fact.

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

Already built on their side: `lgu_staff/pages/api/ipms-road-projects-pull.php` (the poller) and `lgu_staff/pages/api/ipms_road_projects_data.php` (the cache table + upsert/prune logic), consumed by their public dashboard in `index.php`. Nothing new needs to be written there for this lifecycle expansion — just:

- Set `IPMS_BASE_URL` and the matching `ROAD_MONITORING_API_KEY` in their own `.env` (currently unset on both sides — this is what's blocking the integration from authenticating at all right now, independent of the feed's status coverage).
- Their poller already upserts on `project_id` and prunes anything no longer returned, so the newly-added `completed`/`cancelled` rows flow through their existing logic unchanged — they'll just stop disappearing from the cache the moment a project finishes or gets cancelled.
- Schedule `ipms-road-projects-pull.php` on a cron (hourly is plenty — this data doesn't change minute to minute).

## Payload — outbound (here → LG Road Monitoring System)

`GET integrations/road-monitoring/upcoming-roads-feed.php` (header `X-API-Key: ...`)

- Returns every Roads and Bridges project with drawn geometry that HOPE has approved, at any stage from approval through completion or cancellation — i.e. `approved`, `bidding`, `awarded`, `assigned`, `active`, `delayed`, `on_hold`, `completion_inspection`, `completed`, `turnover`, `cancelled`. Still-internal drafts/reviews (`draft`, `endorsed`, `returned`, `planning`) are excluded — they haven't been approved yet and may still be rejected or reworked.
- Each row also carries a `status_bucket` field — one of `new` (approved/bidding/awarded/assigned — not yet under construction), `ongoing` (active/delayed/on_hold/completion_inspection), `completed` (completed/turnover), or `cancelled` — so the Road Monitoring System doesn't need to hardcode or guess IPMS's internal status ENUM.
- Always the current/live state, not a consume-once queue — just re-poll and replace on each pull. A project that's been completed or cancelled keeps appearing in this feed (with that status) rather than disappearing — it only drops out of the feed if it's deleted or recategorized away from Roads and Bridges in IPMS.
- Response shape:
  ```json
  {
    "success": true,
    "count": 2,
    "roads": [
      {
        "project_id": 9,
        "project_name": "Kabayani Street–Matandang Balara Bridge",
        "project_status": "delayed",
        "status_bucket": "ongoing",
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
      },
      {
        "project_id": 4,
        "project_name": "Commonwealth Avenue Widening",
        "project_status": "cancelled",
        "status_bucket": "cancelled",
        "progress_percent": 15,
        "start_date": "2023-01-15",
        "end_date": "2024-06-30",
        "road_name": "Commonwealth Avenue Widening",
        "road_type": "National Road",
        "road_status": "Road Widening",
        "polyline_coordinates": [[14.700, 121.050], [14.712, 121.062]],
        "road_length_meters": 980.2,
        "start_coordinate": { "lat": 14.700, "lng": 121.050 },
        "end_coordinate": { "lat": 14.712, "lng": 121.062 },
        "barangays_covered": ["Commonwealth"],
        "districts_covered": ["District 2"]
      }
    ]
  }
  ```
- Deliberately narrow field list: Project ID, Project Name, Project Status, Status Bucket, Progress, Start/End Date, Road Name, Road Type, Road Status, Polyline Coordinates, Road Length, Start/End Coordinate, Barangays Covered, Districts Covered. No budget, no contractor/engineer identities, no internal remarks or documents — same restraint as the Urban Planning road geometry feed.

## What this integration deliberately does NOT do

- No endpoint here lets the Road Monitoring System create, edit, or cancel an IPMS project — this is a read-only feed, full stop.
- No incident/condition report data flows from their system into IPMS — that data model (`road_transportation_reports`, `road_maintenance_reports`) stays entirely theirs; this integration has no inbound side.
- Nothing about IPMS's own Admin modules (Project Registration, GIS Map, Reports, etc.) changes because of this integration — it is entirely new, additive code living only under `integrations/road-monitoring/`.

## Adding another integration later

Follow this exact shape, same as documented in `integrations/urban-planning/README.md` and `integrations/facilities-reservation/README.md`:
1. A small schema file if the integration needs its own tables (this one didn't — it reads the existing `projects` and `project_road_geometry` tables directly).
2. An `integrations/<system>/` folder for whichever direction(s) of cross-system traffic it needs, plus its own README.
3. A single shared-secret env var (`<SYSTEM>_API_KEY`), checked via `X-API-Key` and `hash_equals()`, matching CIMMS/Urban Planning/Facilities Reservation/this one.
