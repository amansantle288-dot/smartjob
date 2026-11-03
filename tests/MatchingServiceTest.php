<?php
declare(strict_types=1);

require __DIR__ . '/../app/services/MatchingService.php';

$cases = [
    [
        'name' => 'full case-insensitive match with duplicate values',
        'student' => ['PHP', 'SQL', ' php '],
        'required' => ['php', 'sql'],
        'score' => 100,
        'matched' => ['php', 'sql'],
        'missing' => [],
    ],
    [
        'name' => 'partial match reports skill gaps',
        'student' => ['HTML', 'CSS'],
        'required' => ['html', 'css', 'javascript'],
        'score' => 67,
        'matched' => ['html', 'css'],
        'missing' => ['javascript'],
    ],
    [
        'name' => 'no matching skills',
        'student' => [],
        'required' => ['excel'],
        'score' => 0,
        'matched' => [],
        'missing' => ['excel'],
    ],
    [
        'name' => 'empty requirements do not imply a match',
        'student' => ['python'],
        'required' => [],
        'score' => 0,
        'matched' => [],
        'missing' => [],
    ],
    [
        'name' => 'verified training contributes distinct competency evidence',
        'student' => ['html'],
        'required' => ['html', 'javascript', 'php'],
        'training' => [' JavaScript '],
        'score' => 67,
        'matched' => ['html', 'javascript'],
        'missing' => ['php'],
        'verified_training' => ['javascript'],
    ],
];

foreach ($cases as $case) {
    $result = MatchingService::score($case['student'], $case['required'], $case['training'] ?? []);
    foreach (['score' => 'score', 'matched_skills' => 'matched', 'missing_skills' => 'missing'] as $key => $expectedKey) {
        if ($result[$key] !== $case[$expectedKey]) {
            fwrite(STDERR, "Failed: {$case['name']} ({$key})\n");
            exit(1);
        }
    }
    if (($result['verified_training_skills'] ?? []) !== ($case['verified_training'] ?? [])) {
        fwrite(STDERR, "Failed: {$case['name']} (verified_training_skills)\n");
        exit(1);
    }
}

echo count($cases) . " matching tests passed.\n";
