# IPMS Engineer Portal ↔ Urban Planning System integration

Data-sharing only, in both directions:

- **Urban Planning System owns** road planning and the infrastructure inventory (road records, classifications, master data).
- **Engineer Portal (this repo) owns** inspections — it never edits, creates, or deletes road records, and never manages Urban Planning users.
- **Admin Project Registration (this repo) owns** road geometry for Roads and Bridges projects — the exact alignment being constructed or rehabilitated, captured once when the project is registered.
- The only things exchanged are **inspection requests in**, **inspection reports out**, and **road geometry out**.

Opposite direction from the IPMS ↔ CIMMS integration (`integrations/cimm/`): there, IPMS is the sender and the receiver lives in the partner repo. Here, inspection requests originate on the Urban Planning System's side, so **this repo hosts both endpoints** for that flow. Road geometry is one-way — IPMS is always the sender, the Urban Planning System only ever reads.

## Flow

```
Urban Planning System (their repo)
  → POST integrations/urban-planning/inspection-requests.php   (new road inspection request)
  → INSERT urban_planning_inspections (status='pending')        (IPMS/Engineer Portal DB)
  → Engineer opens "Urban Planning Inspection" page, performs the inspection, submits
  → UPDATE urban_planning_inspections (status='completed', condition fields, photos, etc.)
  → Urban Planning System (their repo)
  → GET integrations/urban-planning/inspection-results.php      (pull completed results)
```

```
Admin registers/edits a project with Category = "Roads and Bridges"
  → Road Geometry section appears on the Project Registration form
  → Admin draws the road alignment on the map (start -> ... -> end)
  → INSERT/UPDATE project_road_geometry                          (IPMS/Admin DB)
  → Urban Planning System (their repo)
  → GET integrations/urban-planning/road-geometry-feed.php       (pull road alignments)
```

## Setup

### 1. This repo (Engineer Portal / IPMS)

- Set `URBAN_PLANNING_API_KEY` in `.env` (shared secret — the Urban Planning System must send the identical value).
- No migration to run by hand: `engineer/includes/urban-planning-schema.php` self-heals both tables (`urban_planning_inspections`, `urban_planning_inspection_photos`) on first use, same as the rest of this codebase.

### 2. Urban Planning System (their repo — not part of this codebase)

That project needs its own sender/poller code, analogous to how `includes/CimmClient.php` is IPMS's own sender for the CIMMS integration:

- POST to `inspection-requests.php` whenever a road needs an inspection.
- Poll `inspection-results.php` periodically (or on a webhook trigger, if they add one) to pull completed results.
- Send header `X-API-Key: <the same URBAN_PLANNING_API_KEY value>` on every request.

## Payload — inbound (Urban Planning System → here)

`POST integrations/urban-planning/inspection-requests.php`, JSON or form body:

| Field | Required | Notes |
|---|---|---|
| `road_id` | yes | Their own road identifier |
| `road_name` | yes | |
| `barangay` | yes | |
| `district` | yes | |
| `road_type` | no | e.g. "Arterial", "Barangay Road" |
| `road_length` | no | Kilometers |
| `priority` | no | `low` \| `medium` (default) \| `high` \| `urgent` |
| `requested_by` | no | Requester name/office on their side — not one of our user accounts |
| `request_date` | yes | Date they logged the request |
| `road_latitude`, `road_longitude` | no | The road's registered location |
| `external_reference` | no | Their own record id, echoed back in results for correlation |

Response:
```json
{ "success": true, "inspection_request_id": 7, "status": "pending", "message": "Inspection request accepted into the Engineer Portal queue." }
```

## Payload — outbound (here → Urban Planning System)

`GET integrations/urban-planning/inspection-results.php` (header `X-API-Key: ...`)

- Returns completed inspections only. By default, only results not yet pulled (`synced_to_urban_planning_at IS NULL`); pass `?all=1` to re-list every completed result (e.g. a first backfill).
- By default, a successful pull marks returned rows as synced; pass `?peek=1` to inspect without consuming.
- Each result includes `road_id`/`external_reference` (to correlate back to their own record), every condition field, `severity`, `recommendation`, `remarks`, the inspection's own GPS pin, `engineer_name`, `submitted_at`, and `photo_urls` (absolute paths on this server).

`GET integrations/urban-planning/road-geometry-feed.php` (header `X-API-Key: ...`, same key as above)

- Returns every Roads and Bridges project that has a drawn road geometry — always the current/live state, not a consume-once queue like the results feed above.
- Response shape:
  ```json
  {
    "success": true,
    "count": 1,
    "roads": [
      {
        "project_id": 12,
        "project_name": "Commonwealth Avenue Rehabilitation",
        "project_status": "active",
        "road_name": "Commonwealth Avenue",
        "road_type": "National Road",
        "road_status": "Rehabilitation",
        "polyline_coordinates": [[14.671, 121.048], [14.673, 121.050]],
        "road_length_meters": 1234.56,
        "start_coordinate": { "lat": 14.671, "lng": 121.048 },
        "end_coordinate": { "lat": 14.673, "lng": 121.050 },
        "barangays_covered": ["Culiat", "Pasong Tamo"],
        "districts_covered": ["District 1"]
      }
    ]
  }
  ```
- Deliberately narrow field list — matches exactly what the Road Geometry spec calls for: Project ID, Project Name, Road Name, Road Type, Road Status, Polyline Coordinates, Road Length, Start Coordinate, End Coordinate, Barangays Covered, Districts Covered, Project Status. No budget, no contractor/engineer identities, no internal remarks, no supporting documents.

## What this integration deliberately does NOT do

- No endpoint here lets the Urban Planning System edit or delete anything — the inspection receiver only ever inserts new `pending` requests, and the road geometry feed is GET-only with no write path at all.
- No endpoint here manages Urban Planning users, roads, or inventory — that data model doesn't exist in this repo at all.
- The Engineer Portal's own modules (Assigned Projects, Milestones, Inspection Review, etc.) and Admin's own Project Registration flow for every other category are untouched by this integration; it is entirely new, additive code.

## Adding another integration later

Follow this exact shape for the next external system (Asset Management, GIS, Disaster Risk Management, CIMMS-in-the-other-direction, etc.):
1. A small schema file (`engineer/includes/<system>-schema.php` or similar) with its own self-healing tables.
2. A portal-facing API (`engineer/api/<system>.php`) for the Engineer Portal's own UI to call.
3. An `integrations/<system>/` folder for whichever direction(s) of cross-system traffic it needs, plus its own README.

None of that requires touching the Engineer Portal's existing sidebar items, pages, or `engineer/api/portal.php` — each integration is additive and independently removable.
