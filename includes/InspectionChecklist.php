<?php
// ============================================================
// includes/InspectionChecklist.php — Mobile Inspection checklist catalog.
//
// Same shape as includes/DocumentChecklist.php: a static PHP catalog, not a
// DB table — there is nothing to look up per-project except which category
// applies, so a fabricated "requirements" table would only add a migration
// with no real benefit. Answers themselves are stored per-inspection as
// JSON in inspections.checklist_answers (see engineer/api/mobile-inspection.php).
//
// Item shape:
//   key         stable identifier, e.g. "cq_materials"
//   category    'Construction Quality'|'Project Progress'|'Site Condition'
//   label       the checklist question/statement shown to the engineer
//   applies_to  null (every category) or an array of projects.category
//               values (see projectCategoryEnumSql() in includes/workflow.php)
// ============================================================

require_once __DIR__ . '/db.php';

function inspectionChecklistCatalog(): array
{
    return [
        // ---- Construction Quality -----------------------------------------
        ['key' => 'cq_materials', 'category' => 'Construction Quality', 'label' => 'Materials used conform to approved specifications', 'applies_to' => null],
        ['key' => 'cq_workmanship', 'category' => 'Construction Quality', 'label' => 'Workmanship meets acceptable quality standards', 'applies_to' => null],
        ['key' => 'rb_pavement', 'category' => 'Construction Quality', 'label' => 'Pavement surface/thickness matches specification', 'applies_to' => ['Roads and Bridges']],

        // ---- Project Progress ----------------------------------------------
        ['key' => 'pp_schedule', 'category' => 'Project Progress', 'label' => 'Work is proceeding according to the approved schedule', 'applies_to' => null],
        ['key' => 'pp_accomplishment', 'category' => 'Project Progress', 'label' => "Physical accomplishment matches the contractor's reported percentage", 'applies_to' => null],
        ['key' => 'rb_signage', 'category' => 'Project Progress', 'label' => 'Road signage/markings installed per plan', 'applies_to' => ['Roads and Bridges']],

        // ---- Site Condition --------------------------------------------------
        ['key' => 'sc_safety', 'category' => 'Site Condition', 'label' => 'Safety signage and barriers properly in place', 'applies_to' => null],
        ['key' => 'sc_cleanliness', 'category' => 'Site Condition', 'label' => 'Site is reasonably clean, free of debris hazards', 'applies_to' => null],
        ['key' => 'rb_drainage', 'category' => 'Site Condition', 'label' => 'Road-side drainage/canals clear and functioning', 'applies_to' => ['Roads and Bridges']],
    ];
}

/**
 * Filters the catalog down to what applies to one project's category.
 * Returns null (not the project's fault) only if the project itself doesn't exist.
 */
function inspectionChecklistForProject(PDO $db, int $projectId): ?array
{
    $stmt = $db->prepare("SELECT category FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $category = $stmt->fetchColumn();
    if ($category === false) {
        return null;
    }

    return array_values(array_filter(
        inspectionChecklistCatalog(),
        fn(array $item): bool => $item['applies_to'] === null || in_array($category, $item['applies_to'], true)
    ));
}

/**
 * Groups a flat checklist item list by category, preserving catalog order —
 * what the wizard's Checklist step (and the read-only detail view) render.
 */
function inspectionChecklistGroupByCategory(array $items): array
{
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['category']][] = $item;
    }
    return $grouped;
}
