<?php
/**
 * Urban Planning System integration — shared schema + constants.
 *
 * Ownership split (per the integration agreement): the Urban Planning
 * System owns road planning and the infrastructure inventory; it sends us
 * inspection *requests* for specific roads. The Engineer Portal owns
 * inspections — it never edits road master data, only ever reads the road
 * fields a request arrived with and writes back an inspection result.
 *
 * Required by three places that all need the same table/constants without
 * duplicating them: engineer/api/urban-planning.php (the portal-facing
 * API), integrations/urban-planning/inspection-requests.php (inbound
 * receiver — where a real Urban Planning System would POST new requests),
 * and integrations/urban-planning/inspection-results.php (outbound feed —
 * where it can pull completed results).
 *
 * This is also the template for future integrations (Asset Management,
 * GIS, Disaster Risk Management, CIMMS): each gets its own small schema
 * file + api/*.php + integrations/<system>/ folder, following this same
 * shape, without touching the Engineer Portal's existing modules at all.
 */

const URBAN_PLANNING_CONDITIONS = ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'];
const URBAN_PLANNING_STATUSES = ['pending', 'assigned', 'in_progress', 'completed', 'returned'];
const URBAN_PLANNING_PRIORITIES = ['low', 'medium', 'high', 'urgent'];
const URBAN_PLANNING_SEVERITIES = ['low', 'medium', 'high', 'critical'];
const URBAN_PLANNING_RECOMMENDATIONS = ['Routine Maintenance', 'Repair', 'Rehabilitation', 'Road Reconstruction', 'Further Investigation', 'No Action Needed'];

function urbanPlanningConditionEnumSql(): string
{
    return "ENUM('" . implode("','", URBAN_PLANNING_CONDITIONS) . "')";
}

function urbanPlanningEnsureSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS urban_planning_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY,

            -- Request fields, owned by the Urban Planning System — read-only on our side.
            road_id VARCHAR(40) NOT NULL,
            road_name VARCHAR(200) NOT NULL,
            barangay VARCHAR(100) NOT NULL,
            district VARCHAR(20) NOT NULL,
            road_type VARCHAR(80) NULL,
            road_length DECIMAL(8,2) NULL COMMENT 'kilometers',
            priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            requested_by VARCHAR(150) NULL COMMENT 'requester name/office from the Urban Planning System, not one of our users',
            request_date DATE NOT NULL,
            road_latitude DECIMAL(10,7) NULL,
            road_longitude DECIMAL(10,7) NULL,
            external_reference VARCHAR(64) NULL COMMENT 'Urban Planning System''s own record id, if it provides one',
            status ENUM('pending','assigned','in_progress','completed','returned') NOT NULL DEFAULT 'pending',

            -- Inspection result fields, owned by the Engineer Portal.
            engineer_id INT NULL,
            inspection_date DATE NULL,
            road_condition " . urbanPlanningConditionEnumSql() . " NULL,
            surface_condition " . urbanPlanningConditionEnumSql() . " NULL,
            drainage_condition " . urbanPlanningConditionEnumSql() . " NULL,
            sidewalk_condition " . urbanPlanningConditionEnumSql() . " NULL,
            streetlight_condition " . urbanPlanningConditionEnumSql() . " NULL,
            traffic_sign_condition " . urbanPlanningConditionEnumSql() . " NULL,
            overall_condition " . urbanPlanningConditionEnumSql() . " NULL,
            severity ENUM('low','medium','high','critical') NULL,
            recommendation ENUM('Routine Maintenance','Repair','Rehabilitation','Road Reconstruction','Further Investigation','No Action Needed') NULL,
            remarks TEXT NULL,
            inspection_latitude DECIMAL(10,7) NULL,
            inspection_longitude DECIMAL(10,7) NULL,
            submitted_at DATETIME NULL,

            -- Outbound sync bookkeeping — when the Urban Planning System last pulled this result.
            synced_to_urban_planning_at DATETIME NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_urban_planning_status (status),
            INDEX idx_urban_planning_engineer (engineer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS urban_planning_inspection_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inspection_id INT NOT NULL,
            photo_path VARCHAR(255) NOT NULL,
            caption VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_up_photos_inspection (inspection_id),
            CONSTRAINT fk_up_photos_inspection FOREIGN KEY (inspection_id) REFERENCES urban_planning_inspections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
