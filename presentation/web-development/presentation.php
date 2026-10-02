<?php
/**
 * SkillSnap presentation extract — Web Developer Intern.
 * Topic: authentication, CRUD, upload boundaries and public sharing.
 */

function is_https_request(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_skillsnap_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('skillsnap_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function candidate_by_id(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function save_candidate(PDO $pdo, array $input, int $id = 0): int {
    $fields = [
        'full_name','email','phone','current_role','target_role','target_location',
        'current_ctc','expected_ctc','notice_period','stage','status','notes'
    ];

    $values = [];
    foreach ($fields as $field) $values[$field] = trim((string)($input[$field] ?? ''));
    if ($values['full_name'] === '') throw new InvalidArgumentException('Candidate name is required.');

    if ($id > 0) {
        $set = implode(',', array_map(fn($field) => "`{$field}`=?", $fields));
        $stmt = $pdo->prepare("UPDATE skillsnap_candidates SET {$set},updated_at=? WHERE id=?");
        $stmt->execute([...array_values($values), date('Y-m-d H:i:s'), $id]);
        return $id;
    }

    $columns = implode(',', array_map(fn($field) => "`{$field}`", $fields));
    $marks = implode(',', array_fill(0, count($fields), '?'));
    $stmt = $pdo->prepare("INSERT INTO skillsnap_candidates({$columns},created_at,updated_at) VALUES({$marks},?,?)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute([...array_values($values), $now, $now]);
    return (int)$pdo->lastInsertId();
}

function create_share_link(PDO $pdo, int $candidateId): string {
    $stmt = $pdo->prepare('SELECT share_token FROM skillsnap_candidates WHERE id=?');
    $stmt->execute([$candidateId]);
    $token = trim((string)$stmt->fetchColumn());
    if ($token === '') $token = bin2hex(random_bytes(24));

    $pdo->prepare('UPDATE skillsnap_candidates SET share_token=?,share_enabled=1,updated_at=? WHERE id=?')
        ->execute([$token, date('Y-m-d H:i:s'), $candidateId]);

    $scheme = is_https_request() ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'], '?');
    return $base . '?share=' . rawurlencode($token);
}

function validate_public_requirement(PDO $pdo, int $requirementId, string $token): ?int {
    $stmt = $pdo->prepare(
        'SELECT cr.candidate_id
         FROM skillsnap_candidate_requirements cr
         JOIN skillsnap_candidates c ON c.id=cr.candidate_id
         WHERE cr.id=? AND c.share_token=? AND c.share_enabled=1'
    );
    $stmt->execute([$requirementId, $token]);
    $candidateId = (int)$stmt->fetchColumn();
    return $candidateId > 0 ? $candidateId : null;
}

/* Presentation takeaway:
 * The public share token is a capability boundary. It does not make the entire candidate
 * database public. Every write from a shared profile must prove that the requirement belongs
 * to the candidate represented by that active token.
 */
