<?php
// ============================================================
// api/test.php — Simple test endpoint
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

try {
    $db = getDB();
    
    // Try to fetch tables
    $tables = $db->query("SHOW TABLES")->fetchAll();
    
    respond([
        'status' => 'ok',
        'message' => 'Database connection successful',
        'database' => DB_NAME,
        'tables_count' => count($tables),
        'tables' => array_map(fn($t) => array_values($t)[0], $tables),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    respond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'database' => DB_NAME,
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}
