<?php
/**
 * CLI-only: seeds real, publicly-reported Quezon City infrastructure
 * projects (not fictional placeholders) so the system reflects actual QC
 * government work — spanning active, completed/turnover, and cancelled
 * statuses — plus realistic citizen ratings and feedback per project.
 *
 * Sources (public news coverage, Aug 2026):
 * - DPWH/QC detention-basin flood-control network at Elliptical Road,
 *   Banawe Street, E. Rodriguez Ave-Amoranto St, and Commonwealth Ave-Don
 *   Antonio Drive (Manila Bulletin, Inquirer, May 2026).
 * - QC's 167 infrastructure projects completed in 2025 worth PHP5.6B,
 *   including the New Kamuning Public Market, Bahay Modernismo
 *   rehabilitation at Quezon Memorial Circle, and new facilities at Rosario
 *   Maclang Bautista General Hospital (PNA, Tribune, Nov 2025).
 * - QC's termination of 4 Discaya-linked infrastructure contracts following
 *   PCAB Resolution No. 075 (license revocation), effective Sept 19, 2025:
 *   the Six-Storey Deck Multi-Purpose Building, the Ermitaño Creek
 *   reinforced concrete canal, and both phases of the Balingasa High-Rise
 *   Housing project (Inquirer, Philstar, Rappler, GMA News, Oct 2025).
 *
 * Budget figures, exact progress percentages, and contractor attribution
 * are reasonable estimates for demo purposes (except where a real figure
 * was publicly reported) — NOT official government figures. The project
 * names, locations, statuses, and cancellation reason are accurate.
 *
 * Usage: php scripts/seed-real-qc-projects.php
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

const ADMIN_USER_ID = 2;      // 'admin' — same created_by/turnover_by used by the existing seed projects
const HOPE_USER_ID = 23;      // 'hope' (City Mayor) — approved_by, matching existing convention
const DISTRICT_ENGINEERS = [
    'District 1' => 4,
    'District 2' => 31,
    'District 3' => 37,
    'District 4' => 43,
    'District 5' => 49,
    'District 6' => 55,
];
const CONTRACTOR_POOL = [1, 2, 3, 4, 5]; // JKL Builders, ABC Construction, XYZ Infrastructure, Delta Civil Works, Omega Builders Inc.

$cancelReason = 'Contract terminated following PCAB Board Resolution No. 075, Series of 2025, which revoked the contractor\'s license for violations of licensing requirements and procurement laws, effective September 19, 2025.';

$projects = [
    // ── ACTIVE — DPWH/QC detention-basin flood-control network ──────────
    [
        'name' => 'Elliptical Road Detention Basin',
        'description' => "Underground stormwater detention basin along Elliptical Road — one of four detention basins built by DPWH and Quezon City in the city's most flood-prone corridors (Elliptical Road, Banawe Street, E. Rodriguez Avenue-Amoranto Street, and Commonwealth Avenue-Don Antonio Drive) to temporarily hold stormwater and reduce road flooding during heavy rain.",
        'location' => 'Elliptical Road, Barangay Bagong Pag-asa, District 1, Quezon City',
        'district' => 'District 1', 'category' => 'Drainage and Flood Control', 'funding_source' => 'National Government Fund',
        'budget' => 185000000, 'start_date' => '2025-03-01', 'end_date' => '2026-11-30', 'progress' => 58, 'status' => 'active',
        'implementing_office' => 'DPWH - Quezon City 2nd District Engineering Office',
        'physical_target' => '1 underground detention basin with pump station, approx. 5,000 cu.m. capacity',
    ],
    [
        'name' => 'Banawe Street Detention Basin',
        'description' => "Stormwater detention basin along Banawe Street, part of DPWH and Quezon City's network of flood-mitigation detention basins in chronically flooded corridors.",
        'location' => 'Banawe Street, Barangay Santo Domingo (Matalahib), District 1, Quezon City',
        'district' => 'District 1', 'category' => 'Drainage and Flood Control', 'funding_source' => 'National Government Fund',
        'budget' => 162000000, 'start_date' => '2025-05-01', 'end_date' => '2027-01-31', 'progress' => 37, 'status' => 'active',
        'implementing_office' => 'DPWH - Quezon City 2nd District Engineering Office',
        'physical_target' => '1 underground detention basin with pump station',
    ],
    [
        'name' => 'E. Rodriguez Avenue–Amoranto Street Detention Basin',
        'description' => 'Stormwater detention basin at the E. Rodriguez Avenue-Amoranto Street junction, addressing recurring flash flooding in the area during the monsoon season.',
        'location' => 'E. Rodriguez Avenue corner Amoranto Street, Barangay N.S. Amoranto (Gintong Silahis), District 1, Quezon City',
        'district' => 'District 1', 'category' => 'Drainage and Flood Control', 'funding_source' => 'National Government Fund',
        'budget' => 198000000, 'start_date' => '2025-01-15', 'end_date' => '2026-10-15', 'progress' => 66, 'status' => 'active',
        'implementing_office' => 'DPWH - Quezon City 2nd District Engineering Office',
        'physical_target' => '1 underground detention basin with pump station',
    ],
    [
        'name' => 'Commonwealth Avenue–Don Antonio Drive Detention Basin',
        'description' => 'Stormwater detention basin at Commonwealth Avenue corner Don Antonio Drive, part of the citywide detention-basin network targeting the most flood-prone intersections.',
        'location' => 'Commonwealth Avenue corner Don Antonio Drive, Barangay Commonwealth, District 2, Quezon City',
        'district' => 'District 2', 'category' => 'Drainage and Flood Control', 'funding_source' => 'National Government Fund',
        'budget' => 172000000, 'start_date' => '2025-04-01', 'end_date' => '2026-12-31', 'progress' => 44, 'status' => 'active',
        'implementing_office' => 'DPWH - Quezon City 2nd District Engineering Office',
        'physical_target' => '1 underground detention basin with pump station',
    ],

    // ── COMPLETED/TURNOVER — part of QC's 167 projects finished in 2025 ──
    [
        'name' => 'New Kamuning Public Market',
        'description' => "Redevelopment of the Kamuning Public Market into a modern, weather-protected public market facility — one of the 167 city infrastructure projects (worth a combined PHP5.6 billion) completed in 2025.",
        'location' => 'Kamuning Road, Barangay Kamuning, District 4, Quezon City',
        'district' => 'District 4', 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 210000000, 'start_date' => '2024-02-01', 'end_date' => '2025-10-01', 'progress' => 100, 'status' => 'turnover',
        'implementing_office' => 'Quezon City General Services Department',
        'physical_target' => '1 multi-level public market building with vendor stalls, cold storage, and parking',
        'turnover_office' => 'Public Market Administration Office',
    ],
    [
        'name' => 'Bahay Modernismo Rehabilitation',
        'description' => 'Rehabilitation of Bahay Modernismo, a heritage modernist house inside the Quezon Memorial Circle, restored as a cultural and exhibit space — part of the 167 city infrastructure projects completed in 2025.',
        'location' => 'Quezon Memorial Circle, Barangay Central, District 4, Quezon City',
        'district' => 'District 4', 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 42000000, 'start_date' => '2024-06-01', 'end_date' => '2025-09-15', 'progress' => 100, 'status' => 'turnover',
        'implementing_office' => 'Quezon City Parks Development and Administration Department (PDAD)',
        'physical_target' => '1 rehabilitated heritage structure with exhibit galleries',
        'turnover_office' => 'Parks Development and Administration Department (PDAD)',
    ],
    [
        'name' => 'Rosario Maclang Bautista General Hospital New Facilities',
        'description' => 'New patient-care facilities at Rosario Maclang Bautista General Hospital (formerly Novaliches District Hospital) — part of the 167 city infrastructure projects completed in 2025.',
        'location' => 'Barangay Novaliches Proper, District 5, Quezon City',
        'district' => 'District 5', 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 95000000, 'start_date' => '2024-03-01', 'end_date' => '2025-08-20', 'progress' => 100, 'status' => 'turnover',
        'implementing_office' => 'Quezon City Health Department',
        'physical_target' => '1 new patient-care wing with expanded bed capacity',
        'turnover_office' => 'Rosario Maclang Bautista General Hospital',
    ],

    // ── CANCELLED — real terminations, PCAB Resolution No. 075 (2025) ───
    [
        'name' => 'Six (6) Storey with Deck Multi-Purpose Building',
        'description' => 'Proposed multi-purpose government building with parking deck. Contract terminated before substantial completion following the contractor\'s PCAB license revocation.',
        'location' => 'Quezon City', // exact site not publicly specified
        'district' => null, 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 150000000, 'start_date' => '2024-11-01', 'end_date' => null, 'progress' => 15, 'status' => 'cancelled',
        'implementing_office' => 'Quezon City General Services Department',
        'physical_target' => '1 six-storey multi-purpose building with parking deck',
        'rejection_reason' => $cancelReason,
    ],
    [
        'name' => 'Reinforced Concrete Canal at Ermitaño Creek',
        'description' => 'Proposed reinforced concrete canal along Ermitaño Creek to reduce flooding near UP Diliman and Krus na Ligas. Contract terminated before substantial completion following the contractor\'s PCAB license revocation.',
        'location' => 'Ermitaño Creek, Barangay Krus na Ligas, District 4, Quezon City',
        'district' => 'District 4', 'category' => 'Drainage and Flood Control', 'funding_source' => 'LGU General Fund',
        'budget' => 85000000, 'start_date' => '2025-01-01', 'end_date' => null, 'progress' => 22, 'status' => 'cancelled',
        'implementing_office' => 'Quezon City Engineering Department',
        'physical_target' => 'Reinforced concrete canal, approx. 400 linear meters',
        'rejection_reason' => $cancelReason,
    ],
    [
        'name' => 'Housing 32-Balingasa High Rise Housing (Phase 1A)',
        'description' => 'Proposed high-rise socialized housing for Barangay Balingasa residents, Phase 1A. Contract terminated before substantial completion following the contractor\'s PCAB license revocation.',
        'location' => 'Barangay Balingasa, District 1, Quezon City',
        'district' => 'District 1', 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 320000000, 'start_date' => '2024-08-01', 'end_date' => null, 'progress' => 28, 'status' => 'cancelled',
        'implementing_office' => 'Quezon City Urban Poor Affairs Office',
        'physical_target' => '1 high-rise residential building, Phase 1A',
        'rejection_reason' => $cancelReason,
    ],
    [
        'name' => 'Housing 32-Balingasa High Rise Housing (Phase 2)',
        'description' => 'Proposed high-rise socialized housing for Barangay Balingasa residents, Phase 2. Contract terminated before substantial completion following the contractor\'s PCAB license revocation.',
        'location' => 'Barangay Balingasa, District 1, Quezon City',
        'district' => 'District 1', 'category' => 'Public Buildings and Facilities', 'funding_source' => 'LGU General Fund',
        'budget' => 280000000, 'start_date' => '2025-02-01', 'end_date' => null, 'progress' => 9, 'status' => 'cancelled',
        'implementing_office' => 'Quezon City Urban Poor Affairs Office',
        'physical_target' => '1 high-rise residential building, Phase 2',
        'rejection_reason' => $cancelReason,
    ],
];

// ── Idempotency: skip any project whose name already exists ────────────
$existingNames = $pdo->query('SELECT name FROM projects')->fetchAll(PDO::FETCH_COLUMN);
$existingNames = array_map('mb_strtolower', $existingNames);

$codeBase = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$contractorIdx = 0;
$insertedProjectIds = [];
$skipped = 0;

$insertStmt = $pdo->prepare('
    INSERT INTO projects (
        project_code, name, description, location, district, contractor_id, budget,
        start_date, end_date, progress, status, created_by,
        approved_by, approved_at, engineering_reviewed_by, engineering_reviewed_at,
        ntp_issued_by, ntp_issued_at, completion_inspected_by, completion_inspected_at, completion_remarks,
        turnover_by, turnover_at, turnover_office, rejection_reason,
        category, funding_source, implementing_office, physical_target
    ) VALUES (?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?,?)
');

foreach ($projects as $p) {
    if (in_array(mb_strtolower($p['name']), $existingNames, true)) {
        echo "Skipping (already exists): {$p['name']}\n";
        $skipped++;
        continue;
    }

    $codeBase++;
    $code = 'PRJ-' . str_pad((string) $codeBase, 3, '0', STR_PAD_LEFT);
    $district = $p['district'];
    $engineerId = $district !== null ? (DISTRICT_ENGINEERS[$district] ?? null) : null;
    $isCancelled = $p['status'] === 'cancelled';
    $isTurnover = $p['status'] === 'turnover';
    $isActive = $p['status'] === 'active';

    $approvedAt = date('Y-m-d', strtotime($p['start_date'] . ' -25 days'));
    $engReviewedAt = date('Y-m-d', strtotime($p['start_date'] . ' -10 days'));
    $ntpAt = date('Y-m-d', strtotime($p['start_date'] . ' -2 days'));
    $completionInspectedAt = $isTurnover ? date('Y-m-d', strtotime($p['end_date'] . ' -5 days')) : null;
    $turnoverAt = $isTurnover ? date('Y-m-d', strtotime($p['end_date'] . ' +7 days')) : null;

    $insertStmt->execute([
        $code, $p['name'], $p['description'], $p['location'], $district,
        !$isCancelled ? CONTRACTOR_POOL[$contractorIdx++ % count(CONTRACTOR_POOL)] : null,
        $p['budget'], $p['start_date'], $p['end_date'], $p['progress'], $p['status'], ADMIN_USER_ID,
        HOPE_USER_ID, $approvedAt,
        $engineerId, $engReviewedAt,
        $engineerId, $ntpAt,
        $isTurnover ? $engineerId : null, $completionInspectedAt, $isTurnover ? 'Inspected and accepted — meets contract specifications.' : null,
        $isTurnover ? ADMIN_USER_ID : null, $turnoverAt, $p['turnover_office'] ?? null,
        $p['rejection_reason'] ?? null,
        $p['category'], $p['funding_source'], $p['implementing_office'], $p['physical_target'],
    ]);

    $newId = (int) $pdo->lastInsertId();
    $insertedProjectIds[$newId] = $p;
    echo "Created {$code}: {$p['name']} ({$p['status']})\n";

    // Engineer assignment — same district engineer reviewed it throughout,
    // matching includes engineer/includes/scope.php's one-engineer-per-
    // district model. Cancelled projects keep their assignment too (it's
    // part of that engineer's real history, just on a closed-out project).
    if ($engineerId !== null) {
        $pdo->prepare("
            INSERT INTO engineer_project_assignments (engineer_id, project_id, assigned_by, assignment_notes, status)
            VALUES (?, ?, ?, 'Seeded: district engineer of record', ?)
        ")->execute([$engineerId, $newId, ADMIN_USER_ID, $isActive ? 'active' : 'closed']);
    }

    // Expenses — roughly tracks progress against budget, split into a few
    // realistic line items rather than one lump sum.
    $spentRatio = $isCancelled ? ($p['progress'] / 100) * 0.9 : ($p['progress'] / 100) * 0.95;
    $totalSpent = round($p['budget'] * $spentRatio, -3);
    if ($totalSpent > 0) {
        $lineItems = [
            ['Site Preparation', 0.15], ['Materials', 0.45], ['Labor', 0.25], ['Equipment Rental', 0.15],
        ];
        $expenseDateBase = strtotime($p['start_date']);
        foreach ($lineItems as $i => [$label, $share]) {
            $amount = round($totalSpent * $share, 2);
            if ($amount <= 0) continue;
            $pdo->prepare('INSERT INTO expenses (project_id, category, description, amount, expense_date) VALUES (?, ?, ?, ?, ?)')
                ->execute([$newId, $label, "$label for {$p['name']}", $amount, date('Y-m-d', $expenseDateBase + ($i + 1) * 30 * 86400)]);
        }
    }
}

echo "\n" . count($insertedProjectIds) . " project(s) created, {$skipped} skipped (already present).\n";

if (empty($insertedProjectIds)) {
    echo "Nothing new to attach ratings/feedback to — exiting.\n";
    exit(0);
}

// ── Ratings + feedback, using the existing @ratingseed.ipms.local demo
//    citizens (see scripts/seed-citizen-ratings-demo.php) rather than
//    minting yet another batch of fake accounts. ─────────────────────────
$citizens = $pdo->query("
    SELECT c.id FROM citizens c INNER JOIN users u ON u.id = c.user_id
    WHERE u.email LIKE '%@ratingseed.ipms.local'
")->fetchAll(PDO::FETCH_COLUMN);

if (!$citizens) {
    echo "No demo citizens found (run scripts/seed-citizen-ratings-demo.php first) — skipping ratings/feedback.\n";
    exit(0);
}

$reviewBank = [
    'active_positive' => [
        "Malaking tulong ito sa amin dito sa {area} — konting ulan lang, hindi na kaagad bumabaha.",
        "Progress is visible every time I pass by. Sana matapos on schedule.",
        "Solid ang construction, organized ang site. Excited for when this is done.",
    ],
    'active_mixed' => [
        "Okay naman ang gawain pero medyo matagal na traffic dito dahil sa construction.",
        "Good project overall, just wish there was clearer signage around the site for pedestrians.",
        "Progress seems slow lately — sana bigyan ng update kung kailan matatapos.",
    ],
    'active_negative' => [
        "Ang tagal na nito, hindi pa rin tapos. Sobrang abala sa daan araw-araw.",
        "Construction noise late at night, hindi na maka-tulog nang maayos.",
    ],
    'completed_positive' => [
        "Ang ganda ng resulta! Talagang napaganda ang lugar namin. Salamat QC gov't.",
        "Excellent facility, mabilis na ang serbisyo dito ngayon. Well done.",
        "This turned out really well — clean, organized, and clearly built to last.",
        "Proud of this project. Napakalaking pagbabago dito sa amin.",
    ],
    'completed_mixed' => [
        "Maganda ang bagong facility, pero sana dagdagan pa ang parking.",
        "Good improvement overall, though it took longer than expected to finish.",
    ],
    'cancelled_negative' => [
        "Sayang, matagal na naghintay tapos kanselado na lang pala. Sana may kapalit na plano.",
        "Disappointed — nag-abala ang construction dito for months tapos wala palang natapos.",
        "This should never have started if it wasn't going to be finished properly. Hope the city re-bids this soon.",
        "Nakakainis, dinamay pa kami sa traffic at ingay tapos hindi rin natapos.",
    ],
];

$feedbackBank = [
    'active' => [
        ['category' => 'project_delay', 'priority' => 'medium', 'message' => "Update lang po kung kailan matatapos ang project sa {area}? Matagal na kasing may barikada dito."],
        ['category' => 'safety_hazard', 'priority' => 'high', 'message' => "May open excavation na walang proper signage sa construction site sa {area}, delikado sa gabi."],
        ['category' => 'inquiry', 'priority' => 'low', 'message' => "Ano po ang planned capacity ng detention basin na ito? Sana ma-address talaga yung baha dito."],
    ],
    'completed' => [
        ['category' => 'commendation', 'priority' => 'low', 'message' => "Gusto ko lang pasalamatan ang QC gov't sa bagong facility dito sa {area}. Malaking tulong sa community."],
        ['category' => 'suggestion', 'priority' => 'low', 'message' => "Suggestion po sana magdagdag ng mas maraming waiting area / benches sa bagong facility."],
    ],
    'cancelled' => [
        ['category' => 'complaint', 'priority' => 'high', 'message' => "Bakit po natigil itong proyekto na ito sa {area}? Sayang yung natapos na, delikado pa iniwan."],
        ['category' => 'inquiry', 'priority' => 'medium', 'message' => "May plano po ba ang siyudad na ipagpatuloy ito sa ibang contractor? Matagal na itong naka-abandona."],
    ],
];

function pickCitizen(array $citizens): int
{
    return (int) $citizens[array_rand($citizens)];
}

$totalRatings = 0;
$totalFeedback = 0;

foreach ($insertedProjectIds as $projectId => $p) {
    $area = explode(',', $p['location'])[0];
    $status = $p['status'];

    if ($status === 'cancelled') {
        $sentimentPool = ['cancelled_negative'];
        $ratingCount = random_int(4, 6);
        $starPicker = fn() => random_int(1, 100) <= 75 ? random_int(1, 2) : 3;
    } elseif ($status === 'turnover') {
        $sentimentPool = ['completed_positive', 'completed_positive', 'completed_positive', 'completed_mixed'];
        $ratingCount = random_int(6, 9);
        $starPicker = fn() => random_int(1, 100) <= 80 ? 5 : (random_int(1, 100) <= 70 ? 4 : 3);
    } else { // active
        $sentimentPool = ['active_positive', 'active_positive', 'active_mixed', 'active_negative'];
        $ratingCount = random_int(4, 7);
        $starPicker = fn() => random_int(1, 100) <= 55 ? random_int(4, 5) : (random_int(1, 100) <= 70 ? 3 : random_int(1, 2));
    }

    $ratingCitizens = $citizens;
    shuffle($ratingCitizens);
    $ratingCitizens = array_slice($ratingCitizens, 0, min($ratingCount, count($ratingCitizens)));

    foreach ($ratingCitizens as $citizenId) {
        $sentiment = $sentimentPool[array_rand($sentimentPool)];
        $comment = str_replace('{area}', $area, $reviewBank[$sentiment][array_rand($reviewBank[$sentiment])]);
        $star = $starPicker();
        $createdAt = date('Y-m-d H:i:s', time() - random_int(1, 75) * 86400);

        try {
            $pdo->prepare("
                INSERT INTO project_ratings (project_id, citizen_id, rating, comment, status, moderated_by, moderated_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'approved', ?, ?, ?, ?)
            ")->execute([$projectId, $citizenId, $star, $comment, ADMIN_USER_ID, $createdAt, $createdAt, $createdAt]);
            $totalRatings++;
        } catch (Throwable $e) {
            // UNIQUE(project_id, citizen_id) collision — skip, harmless.
        }
    }

    $fbKey = $status === 'cancelled' ? 'cancelled' : ($status === 'turnover' ? 'completed' : 'active');
    $fbCount = random_int(2, 3);
    $fbCitizens = $citizens;
    shuffle($fbCitizens);
    $fbCitizens = array_slice($fbCitizens, 0, $fbCount);

    foreach ($fbCitizens as $citizenId) {
        $tmpl = $feedbackBank[$fbKey][array_rand($feedbackBank[$fbKey])];
        $nameStmt = $pdo->prepare('SELECT first_name, last_name FROM citizens WHERE id = ?');
        $nameStmt->execute([$citizenId]);
        $n = $nameStmt->fetch();
        $citizenName = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''));
        $createdAt = date('Y-m-d H:i:s', time() - random_int(1, 75) * 86400);

        $pdo->prepare("
            INSERT INTO feedback (project_id, citizen_id, citizen_name, message, category, concern_type, priority, district, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'project', ?, ?, 'open', ?)
        ")->execute([
            $projectId, $citizenId, $citizenName,
            str_replace('{area}', $area, $tmpl['message']), $tmpl['category'], $tmpl['priority'],
            $p['district'], $createdAt,
        ]);
        $totalFeedback++;
    }
}

echo "Created {$totalRatings} rating(s) and {$totalFeedback} feedback/complaint entr(y/ies) across " . count($insertedProjectIds) . " project(s).\n";
