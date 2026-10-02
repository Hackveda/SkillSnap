<?php
/**
 * SkillSnap presentation extract — Data Analyst Intern 1.
 * Topic: market intelligence and target-role requirement aggregation.
 * This file is for explanation only; working source is /working/skillsnap.php.
 */

function normalize_term(string $value): string {
    $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    $value = preg_replace('/[^\pL\pN+#.\/ -]+/u', ' ', $value);
    return trim(preg_replace('/\s+/u', ' ', $value));
}

function weighted_group(array $parsedJob, string $group): array {
    $items = $parsedJob[$group] ?? [];
    if ($group === 'certificates' && !$items) {
        $items = $parsedJob['certifications'] ?? [];
    }

    $result = [];
    foreach ($items as $item) {
        if (is_string($item)) {
            $term = trim($item);
            $weight = 1.0;
        } elseif (is_array($item)) {
            $term = trim((string)($item['term'] ?? $item['name'] ?? $item['title'] ?? ''));
            $weight = is_numeric($item['weight'] ?? null) ? (float)$item['weight'] : 1.0;
        } else {
            continue;
        }
        if ($term !== '') $result[] = ['term' => $term, 'weight' => $weight];
    }
    return $result;
}

function aggregate_role_requirements(PDO $pdo, string $role, string $location = ''): array {
    $groups = ['skills', 'experience', 'projects', 'certificates'];
    $aggregate = array_fill_keys($groups, []);

    $sql = "SELECT Title, Company, Location, parsed_json, skill_desc
            FROM skillsnap_job_details
            WHERE analysed=1 AND (Title LIKE ? OR parsed_json LIKE ?)";
    $args = ["%{$role}%", "%{$role}%"];
    if ($location !== '') {
        $sql .= " AND Location LIKE ?";
        $args[] = "%{$location}%";
    }
    $sql .= " ORDER BY ID DESC LIMIT 5000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $jobsScanned = 0;

    while ($job = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $jobsScanned++;
        $parsed = json_decode((string)$job['parsed_json'], true) ?: [];
        $seenInThisJob = [];

        foreach ($groups as $group) {
            foreach (weighted_group($parsed, $group) as $item) {
                $key = normalize_term($item['term']);
                if ($key === '' || isset($seenInThisJob[$group][$key])) continue;
                $seenInThisJob[$group][$key] = true;

                if (!isset($aggregate[$group][$key])) {
                    $aggregate[$group][$key] = [
                        'term' => $item['term'],
                        'weight' => 0.0,
                        'mentions' => 0
                    ];
                }
                $aggregate[$group][$key]['weight'] += $item['weight'];
                $aggregate[$group][$key]['mentions']++;
            }
        }
    }

    foreach ($groups as $group) {
        $rows = array_values($aggregate[$group]);
        usort($rows, fn($a, $b) =>
            ($b['weight'] <=> $a['weight']) ?: ($b['mentions'] <=> $a['mentions'])
        );
        $aggregate[$group] = array_slice($rows, 0, 30);
    }

    return ['jobs_scanned' => $jobsScanned, 'groups' => $aggregate];
}

/* Presentation takeaway:
 * The benchmark is derived from the job market, not from a hard-coded course syllabus.
 * A requirement becomes more important when it carries more accumulated weight and/or
 * appears across more matching jobs.
 */
