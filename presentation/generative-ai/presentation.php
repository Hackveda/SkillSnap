<?php
/**
 * SkillSnap presentation extract — Generative AI Intern.
 * Topic: structured AI evidence classification and resume intelligence.
 */

function requirement_analysis_schema(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'results' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['existing','partial','missing']],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'evidence' => ['type' => 'string'],
                        'reason' => ['type' => 'string']
                    ],
                    'required' => ['id','status','confidence','evidence','reason']
                ]
            ]
        ],
        'required' => ['results']
    ];
}

function openai_structured_response(
    string $apiKey,
    string $model,
    string $instructions,
    string $input,
    array $schema,
    string $schemaName
): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required.');

    $body = [
        'model' => $model,
        'store' => false,
        'instructions' => $instructions,
        'input' => $input,
        'max_output_tokens' => 6000,
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => $schemaName,
                'strict' => true,
                'schema' => $schema
            ]
        ]
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 60
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('OpenAI connection failed: ' . $error);
    $response = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException($response['error']['message'] ?? ('HTTP ' . $status));
    }

    $text = '';
    foreach (($response['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $content) {
            if (isset($content['text'])) $text .= $content['text'];
        }
    }

    $data = json_decode($text, true);
    if (!is_array($data)) throw new RuntimeException('Structured AI response could not be decoded.');
    return $data;
}

function classify_requirements_with_ai(
    string $apiKey,
    string $model,
    string $resumeText,
    string $targetRole,
    array $requirements
): array {
    $instructions = 'You are a strict hiring evidence evaluator. Compare each target-role requirement with the resume only. existing means direct credible evidence. partial means related, adjacent, limited or incomplete evidence. missing means no credible resume evidence. Do not invent skills, projects, certifications, employers, durations or results. Evidence must quote or closely reproduce a short resume excerpt.';

    $input = "Target role: {$targetRole}\n\nRESUME:\n{$resumeText}\n\nREQUIREMENTS JSON:\n" .
        json_encode($requirements, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    return openai_structured_response(
        $apiKey,
        $model,
        $instructions,
        $input,
        requirement_analysis_schema(),
        'requirement_evidence'
    );
}

/* Visual review concept used in the full export:
 * 1. Render PDF/DOCX resume pages into images.
 * 2. Send the images plus target-role context to a vision-capable model.
 * 3. Request structured scores for ATS readability, six-second clarity, hierarchy,
 *    evidence visibility and density.
 * 4. Request acceptance causes, rejection causes, layout gaps and reviewer personas.
 * 5. Keep those presentation signals separate from factual resume evidence.
 */

/* ATS generation rule used in SkillSnap:
 * Rewrite and reorder evidence that the candidate actually has.
 * Never manufacture employers, dates, degrees, certifications, years or delivered outcomes.
 * Unsupported job requirements belong in development priorities, not as claimed experience.
 */
