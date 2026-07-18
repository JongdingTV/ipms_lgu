# IPMS ↔ CIMMS integration

When a verified citizen submits **Infrastructure Maintenance Issue** feedback in IPMS, the record is saved locally and then pushed to CIMMS so it appears in the [Requests / maintenance queue](https://cimm.infragovservices.com/lgu-portal/public/requests.php).

## Flow

```
Citizen dashboard (IPMS)
  → POST citizen/api/submit-feedback.php  (concern_type=maintenance)
  → INSERT feedback (IPMS DB)
  → CimmClient → POST CIMMS /public/api/ipms-requests.php
  → INSERT citizen_reports (CIMMS DB)  → visible on requests/reports UI
```

Project concerns (`concern_type=project`) stay in IPMS only.

## Setup

### 1. Deploy the CIMMS receiver

Copy `integrations/cimm/ipms-requests.php` to the CIMMS host:

` /lgu-portal/public/api/ipms-requests.php `

Set on CIMMS (env or edit the file defaults):

| Variable | Purpose |
|----------|---------|
| `CIMM_IPMS_API_KEY` | Shared secret (must match IPMS `CIMM_API_KEY`) |
| `CIMM_DB_HOST` / `NAME` / `USER` / `PASS` | Same DB as CIMMS |
| `CIMM_IPMS_TABLE` | Optional; default `citizen_reports` |

### 2. Configure IPMS `.env`

```env
CIMM_API_ENABLED=1
CIMM_API_URL=https://cimm.infragovservices.com/lgu-portal/public/api/ipms-requests.php
CIMM_API_KEY=your-shared-secret
CIMM_API_TIMEOUT=20
CIMM_SSL_VERIFY=1
```

### 3. Run the IPMS migration

```bash
mysql -u root < database/migrations/cimm_feedback_integration.sql
```

## Payload (IPMS → CIMMS)

Multipart fields aligned with CIMMS `citizenrepform.php`:

| Field | Notes |
|-------|-------|
| `infrastructure` | Roads / Street Lights / Drainage / Public Facilities / … |
| `location`, `district`, `barangay` | QC location |
| `coord_lat`, `coord_lng` | Optional pin |
| `name`, `contact_number`, `req_email` | Contact (PH `09XXXXXXXXX` required) |
| `issue` | Description |
| `priority`, `category` | From IPMS feedback |
| `source`, `source_feedback_id` | Idempotency |
| `evidence[]` | Optional photos |

## Response

```json
{ "success": true, "request_id": "12", "reference": "RPT-012", "message": "…" }
```

IPMS stores `cimm_reference` / `cimm_sync_status` on the feedback row for the citizen track page and admin dashboard.
