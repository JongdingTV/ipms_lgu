<?php
// ============================================================
// api/test.php — Simple test endpoint
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

try {
    $db = getDB();
    
    // Try to fetch tables
    $tables = $db->query("SHOW TABLES FROM lgu_infrastructure")->fetchAll();
    
    respond([
        'status' => 'ok',
        'message' => 'Database connection successful',
        'database' => 'lgu_infrastructure',
        'tables_count' => count($tables),
        'tables' => array_map(fn($t) => array_values($t)[0], $tables),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    respond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'database' => 'lgu_infrastructure',
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}
