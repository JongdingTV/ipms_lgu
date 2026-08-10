<?php
/**
 * CLI-only demo data generator: ~50 realistic citizen accounts with star
 * ratings/reviews spread across real rating-eligible projects, so the
 * moderation queue and every public rating display has real-looking content
 * to show instead of being empty.
 *
 * Every seeded account's email ends in "@ratingseed.ipms.local" — never
 * shown anywhere in the UI (display names always go through the existing
 * "Juan D." convention), but it makes the whole batch identifiable and
 * cleanable with one statement:
 *
 *   DELETE FROM users WHERE email LIKE '%@ratingseed.ipms.local';
 *
 * (cascades to citizens -> project_ratings via existing FKs).
 *
 * Usage: php scripts/seed-citizen-ratings-demo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workflow.php';

$pdo = getDB();
projectRatingsEnsureSchema($pdo);

$existing = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@ratingseed.ipms.local'")->fetchColumn();
if ($existing > 0) {
    echo "Demo data already present ($existing seeded account(s) found) — not creating duplicates.\n";
    echo "To reset and re-run, first clean up with:\n";
    echo "  DELETE FROM users WHERE email LIKE '%@ratingseed.ipms.local';\n";
    exit(0);
}

$eligibleStatuses = projectRatingEligibleStatuses();
$placeholders = implode(',', array_fill(0, count($eligibleStatuses), '?'));
$projects = $pdo->prepare("SELECT id, project_code, name FROM projects WHERE status IN ($placeholders)");
$projects->execute($eligibleStatuses);
$eligibleProjects = $projects->fetchAll();

if (!$eligibleProjects) {
    echo "No rating-eligible projects found (active/delayed/on_hold/completion_inspection/completed/turnover) — nothing to seed against.\n";
    exit(1);
}

$adminUserId = (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn()
    ?: $pdo->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn());

$firstNames = [
    'Maria', 'Jose', 'Juan', 'Ana', 'Antonio', 'Rosario', 'Francisco', 'Teresa', 'Manuel', 'Cristina',
    'Ricardo', 'Josefina', 'Eduardo', 'Corazon', 'Fernando', 'Remedios', 'Roberto', 'Leonora', 'Ernesto', 'Imelda',
    'Danilo', 'Gloria', 'Rodrigo', 'Erlinda', 'Arnel', 'Marilou', 'Bienvenido', 'Perlita', 'Renato', 'Nenita',
    'Alfredo', 'Zenaida', 'Ramon', 'Divina', 'Nestor', 'Consuelo', 'Alejandro', 'Herminia', 'Rogelio', 'Adoracion',
    'Melvin', 'Rowena', 'Reynaldo', 'Aurora', 'Vicente', 'Lourdes', 'Armando', 'Editha', 'Bayani', 'Julieta',
];
$lastNames = [
    'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Ramos', 'Flores',
    'Villanueva', 'Castillo', 'Aquino', 'Del Rosario', 'Gonzales', 'Pascual', 'Fernandez', 'Salazar', 'Domingo', 'Marquez',
    'Navarro', 'Aguilar', 'Rivera', 'Rosales', 'Manalo', 'Perez', 'De Guzman', 'Valdez', 'Lopez', 'Alcantara',
];
$barangays = [
    'Bagong Silangan', 'Batasan Hills', 'Commonwealth', 'Culiat', 'Fairview', 'Holy Spirit', 'Novaliches Proper',
    'Payatas', 'San Bartolome', 'Tandang Sora', 'UP Campus', 'Diliman', 'Project 6', 'Sauyo', 'Sta. Lucia',
];

$reviewsByStar = [
    5 => [
        'Ang bilis ng progress, halata talaga na maayos ang pagkakagawa. Salamat po sa proyektong ito!',
        'Excellent work on this project — the road used to flood every rainy season, now it doesn\'t.',
        'Napakalinis at organized ng construction site. Kudos to the team.',
        'Grabe ang improvement dito sa amin, mas maganda na maglakad sa lugar namin ngayon.',
        'Well-managed project from start to finish. Very happy as a resident here.',
        'Sulit ang budget, kita naman talaga sa quality ng ginawa.',
    ],
    4 => [
        'Good progress overall, medyo naantala lang nang kaunti pero solid naman ang quality.',
        'Happy with the results, sana lang mas mabilis next time.',
        'Malaking tulong ito sa aming barangay, konting improvement na lang sa signage.',
        'Solid work, may ilang araw lang na tahimik ang site pero tuloy naman ang project.',
    ],
    3 => [
        'Okay lang, may mga araw na parang walang gumagalaw sa site.',
        'Average lang ang pace, sana mabigyan ng update kung kailan matatapos.',
        'Fine so far but communication with residents could be better.',
    ],
    2 => [
        'Sobrang tagal na, hindi pa rin tapos matagal na dapat.',
        'Traffic pa rin sa area dahil hindi pa fully done.',
        'Medyo disappointed sa bilis, sana bigyang pansin pa.',
    ],
    1 => [
        'Wala pang nangyayari dito sa amin, ilang buwan na.',
        'Disappointed — no visible progress despite the status shown.',
    ],
];

$moderationRemarks = [
    'Comment does not appear related to this project.',
    'Contains unverifiable claims — flagged for follow-up.',
    'Duplicate submission from the same citizen.',
    'Under review by the barangay coordination office.',
];

function weightedStar(): int
{
    $r = random_int(1, 100);
    if ($r <= 82) return 5;
    if ($r <= 92) return 4;
    if ($r <= 97) return 3;
    if ($r <= 99) return 2;
    return 1;
}

function weightedStatus(): string
{
    $r = random_int(1, 100);
    if ($r <= 80) return 'approved';
    if ($r <= 88) return 'pending';
    if ($r <= 94) return 'rejected';
    if ($r <= 98) return 'flagged';
    return 'archived';
}

function randomPastDateTime(int $maxDaysAgo): string
{
    return date('Y-m-d H:i:s', time() - random_int(0, $maxDaysAgo * 86400) - random_int(0, 86399));
}

$genders = ['Male', 'Female', 'Other'];
$civilStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

$citizenCount = 50;
$citizenIds = [];
$insertUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'citizen', 'active')");
$insertCitizen = $pdo->prepare("
    INSERT INTO citizens (
        user_id, first_name, last_name, email, phone, date_of_birth, gender, civil_status,
        address, barangay, city, province, postal_code, id_type, id_number,
        ai_verification_passed, ai_verified_at, verification_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Quezon City', 'Metro Manila', '1100', 'Passport', ?, 1, NOW(), 'verified')
");

$dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

for ($i = 1; $i <= $citizenCount; $i++) {
    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];
    $username = 'ratingseed_' . $i;
    $email = $username . '@ratingseed.ipms.local';
    $phone = '09' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    $dob = date('Y-m-d', strtotime('-' . random_int(22, 65) . ' years -' . random_int(0, 365) . ' days'));
    $idNumber = 'SEED-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT) . '-' . bin2hex(random_bytes(3));

    $insertUser->execute([$username, $email, $dummyHash, "$first $last"]);
    $userId = (int) $pdo->lastInsertId();

    $insertCitizen->execute([
        $userId, $first, $last, $email, $phone, $dob,
        $genders[array_rand($genders)], $civilStatuses[array_rand($civilStatuses)],
        $barangays[array_rand($barangays)] . ' area', $barangays[array_rand($barangays)],
        $idNumber,
    ]);
    $citizenIds[] = (int) $pdo->lastInsertId();
}

$insertRating = $pdo->prepare("
    INSERT INTO project_ratings (project_id, citizen_id, rating, comment, status, moderated_by, moderated_at, decision_remarks, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$totalRatings = 0;
$starTally = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$statusTally = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'flagged' => 0, 'archived' => 0];

foreach ($citizenIds as $citizenId) {
    $ratingsForThisCitizen = random_int(1, 3);
    $pool = $eligibleProjects;
    shuffle($pool);
    $picks = array_slice($pool, 0, min($ratingsForThisCitizen, count($pool)));

    foreach ($picks as $project) {
        $star = weightedStar();
        $status = weightedStatus();
        $comment = $reviewsByStar[$star][array_rand($reviewsByStar[$star])];
        $createdAt = randomPastDateTime(90);

        $moderatedBy = null;
        $moderatedAt = null;
        $remarks = null;
        if ($status !== 'pending') {
            $moderatedBy = $adminUserId ?: null;
            $moderatedAt = date('Y-m-d H:i:s', min(time(), strtotime($createdAt) + random_int(3600, 7 * 86400)));
            if (in_array($status, ['rejected', 'flagged'], true)) {
                $remarks = $moderationRemarks[array_rand($moderationRemarks)];
            }
        }

        try {
            $insertRating->execute([
                $project['id'], $citizenId, $star, $comment, $status,
                $moderatedBy, $moderatedAt, $remarks, $createdAt, $moderatedAt ?: $createdAt,
            ]);
            $totalRatings++;
            $starTally[$star]++;
            $statusTally[$status]++;
        } catch (Throwable $e) {
            // UNIQUE(project_id, citizen_id) collision or similar — skip, the
            // batch total below reflects what actually landed.
        }
    }
}

echo "Seeded " . count($citizenIds) . " demo citizen account(s) and $totalRatings rating(s).\n";
echo "By star: " . implode(', ', array_map(fn($s) => "{$s}★={$starTally[$s]}", [5, 4, 3, 2, 1])) . "\n";
echo "By status: " . implode(', ', array_map(fn($s) => "$s={$statusTally[$s]}", array_keys($statusTally))) . "\n";
echo "\nTo remove this demo data later:\n";
echo "  DELETE FROM users WHERE email LIKE '%@ratingseed.ipms.local';\n";
