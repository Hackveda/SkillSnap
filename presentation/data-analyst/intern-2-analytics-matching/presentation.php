<?php
/**
 * SkillSnap presentation extract — Data Analyst Intern 2.
 * Topic: evidence scoring, readiness analytics and company/job matching.
 */

function final_status(array $row): string {
    return (string)($row['review_status'] ?: ($row['ai_status'] ?: $row['auto_status']));
}

function category_match(array $requirements): float {
    $weightedTotal = 0.0;
    $weightedEarned = 0.0;

    foreach ($requirements as $row) {
        $status = final_status($row);
        if ($status === 'not_required') continue;

        $weight = max(0.01, (float)($row['weight'] ?? 1));
        $credit = $status === 'existing' ? 1.0 : ($status === 'partial' ? 0.5 : 0.0);
        $weightedTotal += $weight;
        $weightedEarned += $weight * $credit;
    }

    return $weightedTotal > 0 ? round($weightedEarned / $weightedTotal * 100, 1) : 0.0;
}

function highest_impact_gaps(array $requirements): array {
    $gaps = [];
    foreach ($requirements as $row) {
        if (final_status($row) !== 'missing') continue;
        $weight = max(0.01, (float)($row['weight'] ?? 1));
        $mentions = max(1, (int)($row['mentions'] ?? 1));
        $gaps[] = [
            'term' => $row['term'],
            'group' => $row['group_name'],
            'impact' => round($weight * $mentions, 2)
        ];
    }
    usort($gaps, fn($a, $b) => $b['impact'] <=> $a['impact']);
    return array_slice($gaps, 0, 10);
}

function job_match_score(array $groupScores): float {
    $weights = [
        'skills' => 0.40,
        'experience' => 0.30,
        'projects' => 0.20,
        'certificates' => 0.10
    ];

    $activeWeight = 0.0;
    $earned = 0.0;
    foreach ($weights as $group => $weight) {
        if (!isset($groupScores[$group])) continue;
        $activeWeight += $weight;
        $earned += $weight * (float)$groupScores[$group];
    }

    return $activeWeight > 0 ? round($earned / $activeWeight, 1) : 0.0;
}

function readiness_summary(array $groups): array {
    $summary = [];
    foreach (['skills','experience','projects','certificates'] as $group) {
        $summary[$group] = category_match($groups[$group] ?? []);
    }

    $all = array_merge(...array_values($groups));
    $summary['critical_gaps'] = highest_impact_gaps($all);
    return $summary;
}

/* Presentation takeaway:
 * SkillSnap separates descriptive metrics (what exists), diagnostic metrics (what is
 * missing), and prescriptive priorities (what to fix first). A job score is a fit
 * estimate for one specific job; it is not a probability that the candidate will be hired.
 */
