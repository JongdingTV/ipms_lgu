# IPMS ↔ CIMMS integration

When a verified citizen submits **Infrastructure Maintenance Issue** feedback in IPMS, the record is saved locally and pushed to CIMMS so it appears in the [Requests queue](https://cimm.infragovservices.com/lgu-portal/public/requests.php) (`requests` table, `#RPT-###` references).

**CIMMS receiver (canonical):** [EXEQUIELKENT/LGU](https://github.com/EXEQUIELKENT/LGU) → `lgu-portal/public/api/ipms-requests.php`  
**IPMS sender (this repo):** `includes/CimmClient.php` + `citizen/api/submit-feedback.php`

Project concerns (`concern_type=project`) stay in IPMS only.

## Flow

```
Citizen dashboard (IPMS)
  → POST citizen/api/submit-feedback.php  (concern_type=maintenance)
  → INSERT feedback (IPMS DB)
  → CimmClient → POST CIMMS /lgu-portal/public/api/ipms-requests.php
  → INSERT requests + evidence_images (CIMMS DB, source=ipms)
  → Staff see it on requests.php / employee.php
```

## Setup

### 1. CIMMS (LGU repo)

The receiver already lives in the LGU repo. On the CIMMS server ensure:

- `lgu-portal/public/api/ipms-requests.php` is deployed (alias: `ipms-request.php`)
- Run migration: `lgu-portal/sql/ipms_integration.sql`
- Set **`CIMM_IPMS_API_KEY`** in the server environment (must match IPMS `CIMM_API_KEY`).  
Local XAMPP dev fallback (both sides): `CIMM_IPMS_SHARED_KEY_2026`

CIMMS uses `db.php` → database `cimm_lgu`, table **`requests`** (PK `req_id`), evidence in **`evidence_images`**.

### 2. IPMS `.env`

```env
CIMM_API_ENABLED=1
CIMM_API_URL=https://cimm.infragovservices.com/lgu-portal/public/api/ipms-requests.php
CIMM_API_KEY=your-shared-secret
CIMM_API_TIMEOUT=20
CIMM_SSL_VERIFY=1
```

**Local XAMPP** (IPMS + CIMMS on same machine):

```env
CIMM_API_ENABLED=1
CIMM_API_URL=http://localhost/LGU/lgu-portal/public/api/ipms-requests.php
CIMM_API_KEY=your-shared-secret
CIMM_SSL_VERIFY=0
```

Set the same key in CIMMS (Apache `SetEnv CIMM_IPMS_API_KEY ...` or edit the default in `ipms-requests.php` for dev only).

### 3. IPMS database migration

```bash
mysql -u root your_ipms_db < database/migrations/cimm_feedback_integration.sql
```

## Payload (IPMS → CIMMS)

Multipart fields aligned with `citizenrepform.php`:

| Field | Notes |
|-------|-------|
| `infrastructure` | Roads / Street Lights / Drainage / Public Facilities / Water Supply / Electrical |
| `location`, `district`, `barangay` | QC location (`Brgy. …, District …, Quezon City`) |
| `coord_lat`, `coord_lng` | Optional pin → `coordinates` column |
| `name`, `contact_number`, `req_email` | Contact (`09XXXXXXXXX` required) |
| `issue` | Description (≥ 10 chars) |
| `source`, `source_feedback_id` | `ipms` + IPMS feedback id (idempotent) |
| `evidence[0]`, `evidence[1]`, … | Optional photos → `evidence_images` |

## Response

```json
{ "success": true, "request_id": "12", "reference": "RPT-012", "message": "Request accepted into CIMMS" }
```

IPMS stores `cimm_reference` / `cimm_sync_status` on the feedback row for track-feedback and admin review.
