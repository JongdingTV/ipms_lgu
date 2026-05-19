<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Get budget stats
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(budget), 0) as total_budget,
        COALESCE(SUM(CASE WHEN status = 'completed' AND DATEDIFF(NOW(), end_date) <= 0 THEN 1 ELSE 0 END), 0) as on_time_projects
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
");
$stmt->execute();
$stats = $stmt->fetch();

// Get expenses
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_expenses
    FROM expenses
");
$stmt->execute();
$expenseStats = $stmt->fetch();

$totalBudget = (float)($stats['total_budget'] ?? 0);
$totalExpenses = (float)($expenseStats['total_expenses'] ?? 0);
$budgetRemaining = $totalBudget - $totalExpenses;

// Get expense breakdown by project
$stmt = $pdo->prepare("
    SELECT 
        p.name as project_name,
        e.category,
        COALESCE(SUM(e.amount), 0) as amount,
        e.expense_date
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.status NOT IN ('draft','returned','cancelled')
    GROUP BY e.id, p.id, p.name, e.category, e.expense_date
    ORDER BY e.expense_date DESC
    LIMIT 20
");
$stmt->execute();
$expenses = $stmt->fetchAll();

echo json_encode([
    'stats' => [
        'total_budget' => $totalBudget,
        'total_expenses' => $totalExpenses,
        'budget_remaining' => $budgetRemaining,
        'on_time_projects' => (int)($stats['on_time_projects'] ?? 0)
    ],
    'expenses' => $expenses
]);
