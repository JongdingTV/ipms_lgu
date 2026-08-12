<?php
/**
 * Rule-based, advisory-only bid ranking for BAC's Award Recommendation queue.
 * Deterministic and fully explainable (no ML/LLM call) — combines the bid's
 * price competitiveness and BAC's own technical score with the bidding
 * contractor's track record (ContractorScoring.php's performance_score),
 * credibility_score, and onboarding-document completeness.
 *
 * This produces a suggestion only: BAC staff still choose which bid to
 * recommend via the existing "recommend" action, and this score is never
 * written anywhere or used to gate that decision.
 */

function bacRecommendationWeights(): array
{
    return [
        'price' => 25,
        'technical' => 25,
        'performance' => 30,
        'credibility' => 10,
        'documents' => 10,
    ];
}

/**
 * $bid must include: bid_amount, budget, technical_score, contractor_id,
 * performance_score, credibility_score (the last two are cheap to select
 * alongside the bid row itself — see bacListCandidateBids()).
 */
function bacCalculateBidRecommendationScore(PDO $db, array $bid): array
{
    $weights = bacRecommendationWeights();

    $budget = (float) ($bid['budget'] ?? 0);
    $amount = (float) ($bid['bid_amount'] ?? 0);
    $variancePct = $budget > 0 ? (($amount - $budget) / $budget) * 100 : 0.0;

    if ($budget <= 0) {
        // No budget on file to compare against — neutral, not penalized.
        $priceScore = $weights['price'] * 0.5;
    } elseif ($variancePct > 0) {
        // Over budget: score falls to 0 by +20% over.
        $priceScore = $weights['price'] * max(0, 1 - min(1, $variancePct / 20));
    } elseif ($variancePct < -30) {
        // Unusually far under budget can signal an unrealistic/under-scoped
        // bid, so it's rewarded less than a moderately competitive one.
        $priceScore = $weights['price'] * 0.6;
    } else {
        // 0% to -30% under budget — reward being competitively below
        // budget, peaking around -12%.
        $closeness = 1 - (abs($variancePct + 12) / 30);
        $priceScore = $weights['price'] * max(0.6, min(1, $closeness));
    }

    $technicalScore = $weights['technical'] * (max(0, min(100, (float) ($bid['technical_score'] ?? 0))) / 100);

    $performanceScore = $weights['performance'] * (max(0, min(100, (float) ($bid['performance_score'] ?? 65))) / 100);

    $credibilityScore = $weights['credibility'] * (max(0, min(5, (float) ($bid['credibility_score'] ?? 2.5))) / 5);

    $contractorId = (int) ($bid['contractor_id'] ?? 0);
    $docStmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified
        FROM supporting_documents WHERE owner_type = 'contractor' AND owner_id = ?
    ");
    $docStmt->execute([$contractorId]);
    $docRow = $docStmt->fetch();
    $docTotal = (int) ($docRow['total'] ?? 0);
    // No documents on file yet is treated as neutral (half credit), not a
    // penalty — some contractors onboarded before document tracking existed.
    $docCompleteness = $docTotal > 0 ? ((int) $docRow['verified']) / $docTotal : 0.5;
    $documentsScore = $weights['documents'] * $docCompleteness;

    $total = $priceScore + $technicalScore + $performanceScore + $credibilityScore + $documentsScore;
    $total = (int) round(max(0, min(100, $total)));

    $label = 'Not recommended';
    if ($total >= 80) {
        $label = 'Strongly suggested';
    } elseif ($total >= 65) {
        $label = 'Suggested';
    } elseif ($total >= 45) {
        $label = 'Worth reviewing';
    }

    return [
        'score' => $total,
        'label' => $label,
        'breakdown' => [
            'price' => ['weight' => $weights['price'], 'earned' => round($priceScore, 1), 'variance_pct' => round($variancePct, 1)],
            'technical' => ['weight' => $weights['technical'], 'earned' => round($technicalScore, 1)],
            'performance' => ['weight' => $weights['performance'], 'earned' => round($performanceScore, 1)],
            'credibility' => ['weight' => $weights['credibility'], 'earned' => round($credibilityScore, 1)],
            'documents' => ['weight' => $weights['documents'], 'earned' => round($documentsScore, 1), 'verified' => (int) ($docRow['verified'] ?? 0), 'total' => $docTotal],
        ],
    ];
}

/**
 * Ranks every candidate bid for a single project so BAC can compare
 * competing bidders side by side. $bids is the array of raw DB rows from
 * bacListCandidateBids() (or any row set with the same shape) already
 * filtered to one project_id.
 */
function bacRankBidsForProject(PDO $db, array $bids): array
{
    $ranked = array_map(function (array $bid) use ($db) {
        $bid['recommendation'] = bacCalculateBidRecommendationScore($db, $bid);
        return $bid;
    }, $bids);

    usort($ranked, fn($a, $b) => $b['recommendation']['score'] <=> $a['recommendation']['score']);

    return $ranked;
}
