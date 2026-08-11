<?php
// ============================================================
// includes/AiAssistantContext.php — AI Project Assistant data grounding
//
// Builds a fresh, plain-text "data snapshot" from live queries on every
// request, then wraps it in a decision-support persona. This snapshot is
// injected as Gemini's system_instruction (via includes/ChatbotClient.php's
// pluggable $systemPrompt param), so the model only ever reasons over
// figures this file just queried — it has no other way to know anything
// about this system, and is instructed to never invent numbers beyond it.
//
// Every section here reuses an existing, already-established computation
// rather than inventing a new one: includes/ContractorScoring.php for
// contractor scores, includes/ProjectHealth.php for the priority-projects
// ranking, includes/DocumentChecklist.php for documentation gaps, and the
// same "pending inspection" LEFT JOIN pattern engineer/api/portal.php's own
// pending_inspections action already uses.
// ============================================================

require_once __DIR__ . '/DocumentChecklist.php';

function aiAssistantSystemPrompt(string $dataSnapshot): string
{
    $persona = <<<'PROMPT'
You are the IPMS AI Project Assistant, a decision-support tool for Quezon
City LGU Infrastructure Project Management System (IPMS) staff (Admin /
Engineering Head and Super Admin users). You help staff quickly understand
project status, delay risk, budget risk, contractor and engineer
performance, inspection backlog, and citizen feedback, using the live data
snapshot provided below.

Hard limits — do not break these:
- You are READ-ONLY decision support. You cannot approve, reject, modify,
  delete, create, or otherwise change any record in this system, and you
  must never claim or imply that you have done so. If asked to perform an
  action ("approve this project", "delete this contractor", "reassign this
  engineer", etc.), explain that you cannot take actions yourself and name
  the page/role that can (e.g. "Project Approval can approve this from the
  Project Approval page" or "an Admin can do this from Contractor
  Assignment").
- Answer ONLY using the DATA SNAPSHOT below. Never invent a project name,
  figure, date, or status that is not in it. If the answer is not in the
  snapshot, say you do not have that data right now rather than guessing.
- All scores (contractor/engineer performance, project health) are
  rule-based advisory signals, not certainties — describe them as such
  ("advisory score", "signals suggest") rather than stating them as fact.
- Stay scoped to IPMS project/contractor/engineer/feedback topics. For
  unrelated questions, politely redirect back to what you can help with.
- Reply in plain text only — no markdown (no **bold**, no # headers). For
  lists, use simple "- " prefixed lines. Keep answers concise and
  scannable: lead with the direct answer, then supporting detail.
PROMPT;

    return $persona . "\n\n" . $dataSnapshot;
}

function aiAssistantBuildContext(PDO $db): string
{
    $sections = [
        '=== LIVE IPMS DATA SNAPSHOT (as of ' . date('M j, Y g:i A') . ') ===',
        aiAssistantOverviewSection($db),
        aiAssistantDelayedSection($db),
        aiAssistantBudgetRiskSection($db),
        aiAssistantContractorSection($db),
        aiAssistantOverdueInspectionsSection($db),
        aiAssistantFeedbackSection($db),
        aiAssistantPrioritySection($db),
        aiAssistantDocumentGapsSection($db),
        '=== END OF DATA SNAPSHOT ===',
    ];

    return implode("\n\n", $sections);
}

function aiAssistantOverviewSection(PDO $db): string
{
    $total = (int) $db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $active = (int) $db->query("
        SELECT COUNT(*) FROM projects
        WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection')
    ")->fetchColumn();
    $delayed = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'delayed'")->fetchColumn();
    $completed = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status IN ('completed','turnover')")->fetchColumn();
    $money = $db->query('SELECT COALESCE(SUM(budget), 0) AS total_budget FROM projects')->fetch();
    $spent = (float) $db->query('SELECT COALESCE(SUM(amount), 0) FROM expenses')->fetchColumn();

    return "PROJECT OVERVIEW\n" .
        "Total projects: {$total} | Active: {$active} | Delayed: {$delayed} | Completed/turned over: {$completed}\n" .
        'Total budget across all projects: PHP ' . number_format((float) $money['total_budget']) .
        ' | Total spent to date: PHP ' . number_format($spent);
}

function aiAssistantDelayedSection(PDO $db): string
{
    $rows = $db->query("
        SELECT p.project_code, p.name, p.progress, DATEDIFF(CURDATE(), p.end_date) AS days_overdue,
               COALESCE(c.name, 'Unassigned') AS contractor_name
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.status = 'delayed'
        ORDER BY days_overdue DESC
        LIMIT 15
    ")->fetchAll();

    if ($rows === []) {
        return "DELAYED PROJECTS\nNo projects are currently marked as delayed.";
    }

    $lines = ['DELAYED PROJECTS (' . count($rows) . ')'];
    foreach ($rows as $r) {
        $overdue = $r['days_overdue'] !== null ? ((int) $r['days_overdue']) . ' days overdue' : 'overdue (end date not set)';
        $lines[] = "- {$r['project_code']} \"{$r['name']}\" — {$overdue}, {$r['progress']}% complete, contractor: {$r['contractor_name']}";
    }
    return implode("\n", $lines);
}

// "Budget risk" = any project with at least one flagged expense, OR whose
// total spend has reached 90%+ of its budget — the same 90% threshold
// api/dashboard.php's high_risk_alerts KPI already uses elsewhere in this app.
function aiAssistantBudgetRiskSection(PDO $db): string
{
    $rows = $db->query("
        SELECT p.project_code, p.name, p.budget,
               COALESCE(SUM(e.amount), 0) AS total_spent,
               SUM(CASE WHEN e.flagged = 1 THEN 1 ELSE 0 END) AS flagged_count
        FROM projects p
        JOIN expenses e ON e.project_id = p.id
        WHERE p.status NOT IN ('draft', 'cancelled')
        GROUP BY p.id, p.project_code, p.name, p.budget
        HAVING flagged_count > 0 OR (p.budget > 0 AND total_spent / p.budget >= 0.9)
        ORDER BY flagged_count DESC, total_spent DESC
        LIMIT 15
    ")->fetchAll();

    if ($rows === []) {
        return "BUDGET RISK\nNo flagged expenses and no project has reached 90% of its budget.";
    }

    $lines = ['BUDGET RISK — flagged expenses or budget utilization at 90% or higher (' . count($rows) . ')'];
    foreach ($rows as $r) {
        $budget = (float) $r['budget'];
        $spent = (float) $r['total_spent'];
        $pctText = $budget > 0 ? round(($spent / $budget) * 100) . '% of budget' : 'budget not set';
        $lines[] = "- {$r['project_code']} \"{$r['name']}\" — PHP " . number_format($spent) . ' spent of PHP ' . number_format($budget) .
            " ({$pctText}), {$r['flagged_count']} flagged expense(s)";
    }
    return implode("\n", $lines);
}

// Reuses includes/ContractorScoring.php's live-computed score directly —
// same engine and same 85/70 risk thresholds as the Contractor Performance
// Dashboard, so the assistant's answers never disagree with that page.
function aiAssistantContractorSection(PDO $db): string
{
    $contractors = $db->query('SELECT id, name, is_blacklisted FROM contractors ORDER BY name ASC')->fetchAll();
    if ($contractors === []) {
        return "CONTRACTOR PERFORMANCE\nNo contractors on file.";
    }

    $scored = [];
    $blacklisted = [];
    foreach ($contractors as $c) {
        $score = contractorCalculatePerformanceScore($db, (int) $c['id']);
        $scored[] = ['name' => (string) $c['name'], 'score' => $score];
        if ((int) ($c['is_blacklisted'] ?? 0) === 1) {
            $blacklisted[] = (string) $c['name'];
        }
    }
    usort($scored, fn(array $a, array $b): int => $b['score'] <=> $a['score']);

    $lines = ['CONTRACTOR PERFORMANCE RANKING (advisory score out of 100, highest first)'];
    $rank = 1;
    foreach ($scored as $s) {
        $risk = $s['score'] >= 85 ? 'low risk' : ($s['score'] >= 70 ? 'medium risk' : 'high risk');
        $lines[] = "{$rank}. {$s['name']} — {$s['score']}/100 ({$risk})";
        $rank++;
    }
    $lines[] = $blacklisted !== [] ? 'Blacklisted contractors: ' . implode(', ', $blacklisted) : 'Blacklisted contractors: none';
    return implode("\n", $lines);
}

// Mirrors engineer/api/portal.php's own action=pending_inspections query
// (LEFT JOIN inspections + "no inspection yet OR report itself still
// submitted/under_review"), narrowed to reports at least 7 days old.
function aiAssistantOverdueInspectionsSection(PDO $db): string
{
    $rows = $db->query("
        SELECT p.project_code, p.name, DATEDIFF(CURDATE(), r.report_date) AS days_pending,
               COALESCE(cc.name, 'Unknown') AS contractor_name,
               COALESCE(GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', '), 'Unassigned') AS engineer_names
        FROM contractor_reports r
        JOIN projects p ON p.id = r.project_id
        LEFT JOIN contractors cc ON cc.id = r.contractor_id
        LEFT JOIN inspections i ON i.progress_report_id = r.id
        LEFT JOIN engineer_project_assignments epa ON epa.project_id = p.id AND epa.status = 'active'
        LEFT JOIN users u ON u.id = epa.engineer_id
        WHERE (i.id IS NULL OR r.status IN ('submitted', 'under_review'))
          AND r.report_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY r.id, p.project_code, p.name, r.report_date, cc.name
        ORDER BY days_pending DESC
        LIMIT 15
    ")->fetchAll();

    if ($rows === []) {
        return "OVERDUE INSPECTIONS\nNo progress reports have been waiting more than 7 days for an inspection.";
    }

    $lines = ['OVERDUE INSPECTIONS — progress reports awaiting inspection for more than 7 days (' . count($rows) . ')'];
    foreach ($rows as $r) {
        $lines[] = "- {$r['project_code']} \"{$r['name']}\" — report pending {$r['days_pending']} days, contractor: {$r['contractor_name']}, assigned engineer(s): {$r['engineer_names']}";
    }
    return implode("\n", $lines);
}

function aiAssistantFeedbackSection(PDO $db): string
{
    $byStatus = $db->query('SELECT status, COUNT(*) AS total FROM feedback GROUP BY status')->fetchAll();
    $byCategory = $db->query('SELECT category, COUNT(*) AS total FROM feedback GROUP BY category ORDER BY total DESC')->fetchAll();
    $recent = $db->query("
        SELECT f.message, f.priority, f.status, p.name AS project_name
        FROM feedback f
        LEFT JOIN projects p ON p.id = f.project_id
        WHERE f.priority IN ('urgent', 'high') AND f.status IN ('open', 'in_progress')
        ORDER BY FIELD(f.priority, 'urgent', 'high'), f.created_at DESC
        LIMIT 8
    ")->fetchAll();

    $statusText = $byStatus !== []
        ? implode(' | ', array_map(fn(array $r) => ucfirst((string) $r['status']) . ': ' . $r['total'], $byStatus))
        : 'no feedback on file';
    $categoryText = $byCategory !== []
        ? implode(', ', array_map(fn(array $r) => str_replace('_', ' ', (string) $r['category']) . ' (' . $r['total'] . ')', $byCategory))
        : 'none';

    $lines = ['CITIZEN FEEDBACK SUMMARY (all-time)', "By status: {$statusText}", "By category: {$categoryText}"];

    if ($recent === []) {
        $lines[] = 'No open urgent/high-priority feedback right now.';
    } else {
        $lines[] = 'Open urgent/high-priority items (' . count($recent) . '):';
        foreach ($recent as $r) {
            $excerpt = mb_strimwidth((string) $r['message'], 0, 120, '...');
            $project = $r['project_name'] ?? 'general/no specific project';
            $lines[] = "- [{$r['priority']}, {$r['status']}] {$project}: \"{$excerpt}\"";
        }
    }

    return implode("\n", $lines);
}

// Reuses includes/ProjectHealth.php's canonical 6-factor advisory score
// (the same one Project Details/Cards and the Reports page already show) —
// worst-first, so this list is always consistent with what staff see there.
function aiAssistantPrioritySection(PDO $db): string
{
    $rows = projectHealthAllScores($db, 8);
    if ($rows === []) {
        return "PROJECTS REQUIRING IMMEDIATE ATTENTION\nNo active projects to assess yet.";
    }

    $lines = ['PROJECTS REQUIRING IMMEDIATE ATTENTION (advisory health score out of 100, most critical first)'];
    $rank = 1;
    foreach ($rows as $r) {
        $lines[] = "{$rank}. {$r['project_code']} \"{$r['name']}\" — health {$r['health_score']}/100 ({$r['health_status']}), status: " . str_replace('_', ' ', (string) $r['status']);
        $rank++;
    }
    return implode("\n", $lines);
}

// Reuses includes/DocumentChecklist.php's live checklist directly, per
// project — the same engine behind the Document Checklist section on the
// Admin/HOPE/Engineer project views and the checklist_gap Task Center task,
// so the assistant's answers never disagree with what those show.
function aiAssistantDocumentGapsSection(PDO $db): string
{
    $projects = $db->query("
        SELECT id, project_code, name FROM projects WHERE status NOT IN ('draft', 'cancelled')
    ")->fetchAll();

    $gaps = [];
    foreach ($projects as $p) {
        $missing = documentChecklistMissingRequired(documentChecklistForProject($db, (int) $p['id']));
        if ($missing) {
            $gaps[] = [
                'project_code' => $p['project_code'], 'name' => $p['name'],
                'labels' => implode(', ', array_column($missing, 'label')),
                'count' => count($missing),
            ];
        }
    }

    if ($gaps === []) {
        return "DOCUMENTATION GAPS\nNo project currently has a required documentation item missing.";
    }

    usort($gaps, fn(array $a, array $b): int => $b['count'] <=> $a['count']);
    $lines = ['DOCUMENTATION GAPS — projects missing a required Document Checklist item (' . count($gaps) . ')'];
    foreach (array_slice($gaps, 0, 15) as $g) {
        $lines[] = "- {$g['project_code']} \"{$g['name']}\" — missing: {$g['labels']}";
    }
    return implode("\n", $lines);
}
