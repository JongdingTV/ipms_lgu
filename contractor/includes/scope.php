<?php

if (!function_exists('contractorScopeHasUserLink')) {
    function contractorScopeHasUserLink(PDO $db): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            $stmt = $db->query("SHOW COLUMNS FROM contractors LIKE 'user_id'");
            $hasColumn = (bool) $stmt->fetch();
        } catch (Throwable $e) {
            $hasColumn = false;
        }

        return $hasColumn;
    }
}

if (!function_exists('contractorScopeCurrentId')) {
    function contractorScopeCurrentId(PDO $db): ?int
    {
        $user = currentUser();
        if (!$user || ($user['role'] ?? '') !== 'contractor') {
            return null;
        }

        if (contractorScopeHasUserLink($db)) {
            $stmt = $db->prepare("SELECT id FROM contractors WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) $user['user_id']]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        if (!empty($user['email'])) {
            $stmt = $db->prepare("SELECT id FROM contractors WHERE email = ? LIMIT 1");
            $stmt->execute([$user['email']]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }
}

if (!function_exists('contractorScopeEmptyProjectList')) {
    function contractorScopeEmptyProjectList(int $page = 1): array
    {
        return [
            'data' => [],
            'total' => 0,
            'page' => $page,
            'last_page' => 0,
        ];
    }
}
