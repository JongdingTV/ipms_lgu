<?php
// ============================================================
// includes/DocumentChecklist.php — Document Checklist engine.
//
// Computes a project's documentation status live from records that already
// exist (supporting_documents, contracts, bac_bid_announcements,
// bac_award_recommendations, contractor_reports, inspections,
// payment_requests, projects) — mirrors includes/TaskCenter.php's pattern
// exactly. Nothing here is ever stored; there is no new document table and
// no new upload/review workflow.
//
// Most items below are workflow-status markers (a status transition + a
// timestamp), not uploaded files — confirmed by exhaustive research before
// implementation. Only "Registration Documents" is backed by a real file
// table. This engine is deliberately honest about that rather than
// fabricating file requirements that don't exist anywhere in this system.
//
// Item shape:
//   key            stable identifier, e.g. "hope_approval"
//   label          display name
//   category       'Registration'|'Approval'|'Procurement'|'Contract'
//                  |'Implementation'|'Financial'|'Completion'
//   status         'complete'|'pending'|'missing'|'not_required'
//                  |'returned'|'under_review'
//   detail         short human-readable explanation
//   info_only      bool — true for contractor_documents/inspection photos:
//                  always visible, never 'missing', never affects summary
//                  counts/percentage or the completion-gate warning
// ============================================================

require_once __DIR__ . '/db.php';

function documentChecklistForProject(PDO $db, int $projectId): array
{
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        return [];
    }

    $status = $project['status'];
    $items = [];

    // ---- Registration ----------------------------------------------------
    $stmt = $db->prepare("
        SELECT status, COUNT(*) AS n FROM supporting_documents
        WHERE owner_type = 'project' AND owner_id = ? AND is_current = 1
        GROUP BY status
    ");
    $stmt->execute([$projectId]);
    $docCounts = array_column($stmt->fetchAll(), 'n', 'status');
    $items[] = [
        'key' => 'registration_documents', 'label' => 'Registration Documents', 'category' => 'Registration',
        'status' => !empty($docCounts['verified']) ? 'complete'
            : (!empty($docCounts['pending']) ? 'under_review'
            : (!empty($docCounts['rejected']) ? 'returned' : 'missing')),
        'detail' => array_sum($docCounts) > 0
            ? array_sum($docCounts) . ' document(s) on file' : 'No registration documents uploaded',
        'info_only' => false,
    ];

    // ---- Approval -----------------------------------------------------
    // 'returned' can come from either the engineering-review stage or the
    // HOPE stage (draft->{endorsed|returned} then endorsed->{approved|returned})
    // and there's no discriminator column recording which — projects.status
    // alone can't tell them apart. Engineering's own job (a decision was
    // recorded) is treated as done once it's out of 'draft'; the ambiguous
    // 'returned' state is attributed to HOPE Approval below, since HOPE is
    // this system's final/authoritative decision-maker.
    $items[] = [
        'key' => 'engineering_endorsement', 'label' => 'Engineering Endorsement', 'category' => 'Approval',
        'status' => $status === 'draft' ? 'pending' : 'complete',
        'detail' => $project['engineering_reviewed_at'] ? 'Reviewed ' . $project['engineering_reviewed_at'] : 'Awaiting engineering endorsement',
        'info_only' => false,
    ];
    $items[] = [
        'key' => 'hope_approval', 'label' => 'HOPE Approval', 'category' => 'Approval',
        'status' => $status === 'draft' ? 'not_required'
            : ($status === 'endorsed' ? 'pending'
            : ($status === 'returned' ? 'returned'
            : (!empty($project['approved_at']) ? 'complete' : 'pending'))),
        'detail' => $project['approved_at'] ? 'Approved ' . $project['approved_at'] : 'Awaiting City Mayor decision',
        'info_only' => false,
    ];

    // ---- Procurement -----------------------------------------------------
    $preApproval = in_array($status, ['draft', 'endorsed', 'returned'], true);

    $stmt = $db->prepare("SELECT * FROM bac_bid_announcements WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $announcement = $stmt->fetch();
    $items[] = [
        'key' => 'bidding_announcement', 'label' => 'Bidding Announcement', 'category' => 'Procurement',
        'status' => $preApproval ? 'not_required' : ($announcement ? 'complete' : 'pending'),
        'detail' => $announcement ? 'Posted ' . $announcement['published_at'] : 'Not yet posted for bidding',
        'info_only' => false,
    ];

    $preBidding = in_array($status, ['draft', 'endorsed', 'returned', 'planning', 'approved'], true);
    $stmt = $db->prepare("SELECT * FROM bac_award_recommendations WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $award = $stmt->fetch();
    $items[] = [
        'key' => 'award_recommendation', 'label' => 'Award Recommendation', 'category' => 'Procurement',
        'status' => $preBidding ? 'not_required' : (!$award ? 'pending'
            : ($award['status'] === 'sent_to_admin' ? 'under_review'
            : (in_array($award['status'], ['returned', 'rejected'], true) ? 'returned'
            : ($award['status'] === 'approved' ? 'complete' : 'pending')))),
        'detail' => $award ? ('Status: ' . $award['status']) : 'No award recommendation yet',
        'info_only' => false,
    ];

    // ---- Contract ----------------------------------------------------
    $preAward = in_array($status, ['draft', 'endorsed', 'returned', 'planning', 'approved', 'bidding'], true);

    $stmt = $db->prepare("SELECT * FROM contracts WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $contract = $stmt->fetch();
    $items[] = [
        'key' => 'contract', 'label' => 'Contract', 'category' => 'Contract',
        'status' => $preAward ? 'not_required' : ($contract ? 'complete' : 'pending'),
        'detail' => $contract ? 'Contract ' . $contract['contract_no'] : 'Contract not yet executed',
        'info_only' => false,
    ];
    $preAssigned = in_array($status, ['draft', 'endorsed', 'returned', 'planning', 'approved', 'bidding', 'awarded'], true);
    $items[] = [
        'key' => 'notice_to_proceed', 'label' => 'Notice to Proceed', 'category' => 'Contract',
        'status' => $preAssigned ? 'not_required' : (!empty($project['ntp_issued_at']) ? 'complete' : 'pending'),
        'detail' => $project['ntp_issued_at'] ? 'Issued ' . $project['ntp_issued_at'] : 'Not yet issued',
        'info_only' => false,
    ];

    // ---- Implementation ----------------------------------------------------
    $notStarted = in_array($status, ['draft', 'endorsed', 'returned', 'planning', 'approved', 'bidding', 'awarded', 'assigned'], true);

    $stmt = $db->prepare("SELECT * FROM contractor_reports WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $report = $stmt->fetch();
    $items[] = [
        'key' => 'progress_reports', 'label' => 'Progress Reports', 'category' => 'Implementation',
        'status' => $notStarted ? 'not_required' : (!$report ? 'pending'
            : (in_array($report['status'], ['submitted', 'under_review'], true) ? 'under_review'
            : ($report['status'] === 'accepted' ? 'complete' : 'returned'))),
        'detail' => $report ? ('Latest: ' . $report['report_date'] . ' (' . $report['status'] . ')') : 'No progress report submitted yet',
        'info_only' => false,
    ];

    $stmt = $db->prepare("SELECT * FROM inspections WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $inspection = $stmt->fetch();
    $items[] = [
        'key' => 'inspection_report', 'label' => 'Inspection Report', 'category' => 'Implementation',
        'status' => (!$report) ? 'not_required' : (!$inspection ? 'pending'
            : ($inspection['recommendation'] === 'approved' ? 'complete' : 'returned')),
        'detail' => $inspection ? ('Latest: ' . $inspection['inspection_date'] . ' — ' . $inspection['recommendation']) : 'No inspection recorded yet',
        'info_only' => false,
    ];

    // ---- Financial ----------------------------------------------------
    $paymentEligible = !$notStarted && (int) $project['progress'] >= 30;
    $stmt = $db->prepare("SELECT * FROM payment_requests WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $payment = $stmt->fetch();
    $items[] = [
        'key' => 'payment_request', 'label' => 'Payment Request', 'category' => 'Financial',
        'status' => !$paymentEligible ? 'not_required' : (!$payment ? 'pending'
            : (in_array($payment['status'], ['submitted', 'under_review'], true) ? 'under_review'
            : (in_array($payment['status'], ['approved', 'paid'], true) ? 'complete' : 'returned'))),
        'detail' => $payment ? ('Billing ' . $payment['billing_no'] . ' — ' . $payment['status']) : 'No payment request submitted yet',
        'info_only' => false,
    ];

    // ---- Completion ----------------------------------------------------
    $preCompletionInspection = !in_array($status, ['completion_inspection', 'completed', 'turnover'], true);
    $items[] = [
        'key' => 'final_inspection', 'label' => 'Final Inspection', 'category' => 'Completion',
        'status' => $preCompletionInspection ? 'not_required'
            : ($status === 'completion_inspection' ? 'pending' : 'complete'),
        'detail' => $project['completion_inspected_at'] ? 'Completed ' . $project['completion_inspected_at'] : 'Awaiting final inspection',
        'info_only' => false,
    ];
    $items[] = [
        'key' => 'project_turnover', 'label' => 'Project Turnover', 'category' => 'Completion',
        'status' => $status !== 'completed' && $status !== 'turnover' ? 'not_required'
            : ($status === 'completed' ? 'pending' : 'complete'),
        'detail' => $project['turnover_at'] ? 'Turned over ' . $project['turnover_at'] : 'Not yet turned over',
        'info_only' => false,
    ];

    // ---- Informational-only (never 'missing', never affects the summary) --
    $stmt = $db->prepare("SELECT COUNT(*) FROM contractor_documents WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $contractorDocCount = (int) $stmt->fetchColumn();
    $items[] = [
        'key' => 'contractor_documents', 'label' => 'Contractor-Submitted Documents', 'category' => 'Implementation',
        'status' => $contractorDocCount > 0 ? 'complete' : 'not_required',
        'detail' => $contractorDocCount > 0 ? "$contractorDocCount document(s) uploaded by contractor" : 'None uploaded',
        'info_only' => true,
    ];

    $stmt = $db->prepare("SELECT COUNT(*) FROM engineer_progress_photos WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $photoCount = (int) $stmt->fetchColumn();
    $items[] = [
        'key' => 'site_photos', 'label' => 'Site Inspection Photos', 'category' => 'Implementation',
        'status' => $photoCount > 0 ? 'complete' : 'not_required',
        'detail' => $photoCount > 0 ? "$photoCount photo(s) on file" : 'None uploaded (optional)',
        'info_only' => true,
    ];

    return $items;
}

function documentChecklistSummary(array $items): array
{
    $counts = ['complete' => 0, 'pending' => 0, 'missing' => 0, 'returned' => 0, 'under_review' => 0, 'not_required' => 0];
    $countable = 0;

    foreach ($items as $item) {
        if ($item['info_only']) {
            continue;
        }
        $counts[$item['status']] = ($counts[$item['status']] ?? 0) + 1;
        if ($item['status'] !== 'not_required') {
            $countable++;
        }
    }

    $counts['total'] = $countable;
    $counts['percent'] = $countable > 0 ? (int) round(($counts['complete'] / $countable) * 100) : 0;
    return $counts;
}

/** Only genuinely file/record-backed gaps — never includes info_only rows. */
function documentChecklistMissingRequired(array $items): array
{
    return array_values(array_filter($items, fn($i) => !$i['info_only'] && $i['status'] === 'missing'));
}
