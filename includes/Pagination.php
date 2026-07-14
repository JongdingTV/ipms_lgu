<?php
// ============================================================
// includes/Pagination.php — shared LIMIT/OFFSET pagination helper.
// Matches the {data, total, page, last_page} shape already shipped by
// api/contractors.php and api/projects.php (plus per_page, for the shared
// front-end widget in assets/js/pagination.js), so the whole app converges
// on one pagination contract.
// ============================================================

/**
 * $selectSql must NOT contain its own LIMIT/OFFSET — this appends them.
 * For GROUP BY/HAVING queries, wrap $countSql as
 * "SELECT COUNT(*) FROM (<grouped query>) AS t" since a plain COUNT(*)
 * over a HAVING-filtered query does not count the right thing.
 */
function paginate(PDO $db, string $selectSql, string $countSql, array $params, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare($selectSql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);

    return [
        'data' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'last_page' => (int) ceil($total / $perPage),
    ];
}
