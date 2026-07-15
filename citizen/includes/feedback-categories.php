<?php
/**
 * Single source of truth for citizen feedback categories (value => label).
 * The dashboard form renders its options from this list and
 * api/submit-feedback.php validates against the same keys, so the two can
 * never drift apart. Keys must match the feedback.category ENUM in
 * database.sql.
 */
function feedbackCategories(): array
{
    return [
        'complaint' => 'General Complaint',
        'road_damage' => 'Road Damage (potholes, cracks)',
        'drainage_flooding' => 'Drainage & Flooding',
        'streetlight' => 'Streetlight / Electrical',
        'sidewalk_accessibility' => 'Sidewalk & Accessibility',
        'safety_hazard' => 'Safety Hazard',
        'project_delay' => 'Project Delay / Inactivity',
        'suggestion' => 'Suggestion',
        'inquiry' => 'Inquiry',
        'commendation' => 'Commendation / Praise',
    ];
}
