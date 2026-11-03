<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\AI\Controllers\AIWritingController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$employerId = 0;
$companyId = 0;
$otherCompanyId = 0;
$internshipIds = [];
$previousValues = [];
foreach (['AI_PROVIDER', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_TIMEOUT', 'AI_STUDENT_DAILY_LIMIT', 'AI_EMPLOYER_DAILY_LIMIT', 'OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_TIMEOUT', 'OPENAI_STUDENT_DAILY_LIMIT', 'OPENAI_EMPLOYER_DAILY_LIMIT'] as $name) {
    $previousValues[$name] = getenv($name);
}
putenv('AI_PROVIDER=gemini');
putenv('GEMINI_API_KEY=test-key-not-for-network-use');
putenv('GEMINI_MODEL=gemini-3.5-flash-lite');
putenv('GEMINI_TIMEOUT=15');
putenv('AI_STUDENT_DAILY_LIMIT=1');
putenv('AI_EMPLOYER_DAILY_LIMIT=3');
putenv('OPENAI_STUDENT_DAILY_LIMIT=1');
putenv('OPENAI_EMPLOYER_DAILY_LIMIT=3');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $messagePart) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), $messagePart), 'Unexpected AI writing controller failure.');
        return;
    }
    throw new RuntimeException('An invalid AI writing request was accepted.');
};

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB AI Student Test', 'ai-student-' . $token . '@example.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB AI Employer Test', 'ai-employer-' . $token . '@example.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")
        ->execute(['SMARTJOB AI Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'pending')")
        ->execute(['SMARTJOB Other AI Company ' . $token]);
    $otherCompanyId = (int) $pdo->lastInsertId();

    $insertInternship = $pdo->prepare('INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, application_deadline, status) VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?)');
    $insertInternship->execute([$companyId, $employerId, 'AI test internship', 'Test opportunity.', 'draft']);
    $internshipIds[] = (int) $pdo->lastInsertId();
    $insertInternship->execute([$otherCompanyId, $employerId, 'Foreign AI internship', 'Test opportunity.', 'draft']);
    $internshipIds[] = (int) $pdo->lastInsertId();

    $requests = [];
    $service = new GeminiWritingService(static function (string $url, array $headers, string $body, int $timeout) use (&$requests): array {
        $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return ['status' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":"A reviewed draft."}]}}]}'];
    });
    $controller = new AIWritingController($service);
    $assert(AIWritingController::supports('ai_write') && !AIWritingController::supports('write_with_ai'), 'AI action routing support was incorrect.');

    $_SESSION['user_id'] = $studentId;
    $_POST = ['consent' => '0', 'field' => 'student_summary'];
    $expectFailure(static fn () => $controller->handle('ai_write'), 'Confirm that the selected writing details');
    $assert($requests === [], 'The provider was called without consent.');

    $_POST = [
        'consent' => '1',
        'field' => 'student_summary',
        'writing_action' => 'generate',
        'tone' => 'professional',
        'existing_text' => '',
        'context' => ['program' => 'Computer Science', 'additional_details' => 'Interested in accessible software.'],
    ];
    $result = $controller->handle('ai_write');
    $assert(($result['json']['text'] ?? '') === 'A reviewed draft.' && $result['page'] === 'biodata', 'The student writing request did not return a preview.');
    $assert(str_contains($requests[0]['contents'][0]['parts'][0]['text'], 'Interested in accessible software.'), 'The explicitly entered additional details were not sent.');

    $_POST['field'] = 'employer_company_description';
    $expectFailure(static fn () => $controller->handle('ai_write'), 'not available for your account');

    $_POST['field'] = 'student_summary';
    $expectFailure(static fn () => $controller->handle('ai_write'), 'daily AI writing limit');

    $_SESSION['user_id'] = $employerId;
    $_POST = [
        'consent' => '1',
        'field' => 'employer_internship_description',
        'writing_action' => 'generate',
        'tone' => 'professional',
        'existing_text' => '',
    ];
    $_POST['internship_id'] = (string) $internshipIds[0];
    $result = $controller->handle('ai_write');
    $assert(($result['json']['text'] ?? '') === 'A reviewed draft.' && $result['page'] === 'company_profile', 'The approved employer writing request did not return a preview.');

    $_POST['internship_id'] = (string) $internshipIds[1];
    $expectFailure(static fn () => $controller->handle('ai_write'), 'not available to your company');
    $_POST['internship_id'] = '999999999';
    $expectFailure(static fn () => $controller->handle('ai_write'), 'not available to your company');

    $usage = $pdo->prepare('SELECT request_count FROM tbl_ai_writing_usage WHERE user_id=? AND usage_day=CURRENT_DATE');
    $usage->execute([$employerId]);
    $assert((int) $usage->fetchColumn() === 1, 'Rejected cross-company requests consumed an AI usage slot.');
} finally {
    foreach ($internshipIds as $internshipId) {
        $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$internshipId]);
    }
    if ($companyId) {
        $pdo->prepare('DELETE FROM tbl_company_members WHERE company_id=?')->execute([$companyId]);
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    if ($otherCompanyId) {
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$otherCompanyId]);
    }
    foreach ([$studentId, $employerId] as $userId) {
        if ($userId) {
            $pdo->prepare('DELETE FROM tbl_ai_writing_usage WHERE user_id=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$userId]);
        }
    }
    unset($_SESSION['user_id']);
    $_POST = [];
    foreach ($previousValues as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

echo "Gemini writing authorization, consent, preview, rate-limit, and ownership checks passed.\n";
