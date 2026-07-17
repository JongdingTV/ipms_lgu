<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Same scope formula as the staff dashboard (api/dashboard.php) so the
// citizen transparency numbers always match what admins see.
$publicScope = "status NOT IN ('draft','returned','cancelled')";

// Get budget stats. "On time" = a finished project (completed or turned over)
// whose recorded completion moment was on or before its planned end date —
// comparing against NOW() would silently drop projects once their end date
// passes, even though they finished on schedule.
$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(budget), 0) AS total_budget,
        SUM(CASE
            WHEN status IN ('completed','turnover')
             AND end_date IS NOT NULL
             AND DATE(COALESCE(turnover_at, completion_inspected_at, updated_at)) <= end_date
            THEN 1 ELSE 0 END) AS on_time_projects,
        SUM(CASE WHEN status IN ('completed','turnover') THEN 1 ELSE 0 END) AS finished_projects
    FROM projects
    WHERE $publicScope
");
$stats = $stmt->fetch();

// Expenses of publicly-visible projects only, so every figure below slices
// the same pot of money.
$stmt = $pdo->query("
    SELECT COALESCE(SUM(e.amount), 0) AS total_expenses
    FROM expenses e
    JOIN projects p ON p.id = e.project_id
    WHERE p.$publicScope
");
$expenseStats = $stmt->fetch();

$totalBudget = (float)($stats['total_budget'] ?? 0);
$totalExpenses = (float)($expenseStats['total_expenses'] ?? 0);
$budgetRemaining = max(0, $totalBudget - $totalExpenses);

// ── Chart: spending by expense category ──
$stmt = $pdo->query("
    SELECT COALESCE(NULLIF(TRIM(e.category), ''), 'Uncategorized') AS category,
           SUM(e.amount) AS total
    FROM expenses e
    JOIN projects p ON p.id = e.project_id
    WHERE p.$publicScope
    GROUP BY COALESCE(NULLIF(TRIM(e.category), ''), 'Uncategorized')
    ORDER BY total DESC
");
$byCategory = $stmt->fetchAll();

// ── Chart: monthly spending, last 12 months ──
$stmt = $pdo->query("
    SELECT DATE_FORMAT(e.expense_date, '%Y-%m') AS ym, SUM(e.amount) AS total
    FROM expenses e
    JOIN projects p ON p.id = e.project_id
    WHERE p.$publicScope
      AND e.expense_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
    GROUP BY ym
    ORDER BY ym
");
$monthlyRaw = [];
foreach ($stmt->fetchAll() as $row) {
    $monthlyRaw[$row['ym']] = (float)$row['total'];
}
// Fill the gaps so the line spans a full year even in quiet months.
$monthly = [];
$cursor = new DateTime(date('Y-m-01'));
$cursor->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $ym = $cursor->format('Y-m');
    $monthly[] = [
        'month' => $cursor->format('M Y'),
        'total' => $monthlyRaw[$ym] ?? 0,
    ];
    $cursor->modify('+1 month');
}

// ── Chart: budget vs spent for the biggest projects ──
$stmt = $pdo->query("
    SELECT p.name, p.budget,
           COALESCE(SUM(e.amount), 0) AS spent
    FROM projects p
    LEFT JOIN expenses e ON e.project_id = p.id
    WHERE p.$publicScope AND p.budget > 0
    GROUP BY p.id, p.name, p.budget
    ORDER BY p.budget DESC
    LIMIT 6
");
$projectBudgets = $stmt->fetchAll();

// Get expense breakdown by project (recent line items)
$stmt = $pdo->query("
    SELECT p.name AS project_name, e.category, e.amount, e.expense_date
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.$publicScope
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT 500
");
$expenses = $stmt->fetchAll();

echo json_encode([
    'stats' => [
        'total_budget' => $totalBudget,
        'total_expenses' => $totalExpenses,
        'budget_remaining' => $budgetRemaining,
        'on_time_projects' => (int)($stats['on_time_projects'] ?? 0),
        'finished_projects' => (int)($stats['finished_projects'] ?? 0),
    ],
    'budget_donut' => [
        'spent' => $totalExpenses,
        'remaining' => $budgetRemaining,
    ],
    'by_category' => $byCategory,
    'monthly_spending' => $monthly,
    'project_budgets' => $projectBudgets,
    'expenses' => $expenses,
]);
