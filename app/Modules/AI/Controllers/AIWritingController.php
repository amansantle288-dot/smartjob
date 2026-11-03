<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AI\Controllers;

use AIWritingServiceFactory;
use AIWritingServiceInterface;
use RuntimeException;

final class AIWritingController
{
    private const FIELD_INSTRUCTIONS = [
        'student_summary' => 'Write a concise student professional summary using only the supplied education, goals, skills, and draft. Do not invent credentials or work history.',
        'student_achievement' => 'Improve or draft a factual achievement entry. Do not invent awards, honors, rankings, dates, or measurable results.',
        'student_project_description' => 'Write a clear academic or personal project description using only the project role, technologies, and details supplied.',
        'student_experience_description' => 'Write a clear work or volunteer experience description using only the role, organization, and details supplied. Do not invent responsibilities or outcomes.',
        'student_skill_description' => 'Write a short first-person or neutral description of the supplied skill and proficiency. Do not claim experience that was not provided.',
        'student_internship_objectives' => 'Draft internship learning objectives for the supplied opportunity. Present them as the applicant’s goals, not accomplishments.',
        'student_application_letter' => 'Draft or improve a concise application letter for the supplied internship. Do not invent the student’s experience, qualifications, or personal details.',
        'employer_company_description' => 'Write an accurate company description using only supplied company facts and employer-provided business activities. Do not invent history, customers, awards, certifications, or scale.',
        'employer_mission_statement' => 'Draft a proposed mission statement based only on supplied company facts and goals. Do not present aspirations as proven achievements.',
        'employer_vision_statement' => 'Draft a proposed vision statement based only on supplied company facts and goals. Clearly phrase it as an aspiration.',
        'employer_internship_description' => 'Write an inclusive, student-friendly internship description. Do not invent salary, benefits, schedules, supervision, resources, or arrangements.',
        'employer_internship_responsibilities' => 'Suggest supervised, student-appropriate responsibilities based on the supplied role. Label inferred items as suggestions; do not imply unprovided resources or authority.',
        'employer_internship_qualifications' => 'Suggest reasonable qualifications based only on employer-provided requirements. Keep suggestions editable and do not invent strict or mandatory requirements.',
        'employer_preferred_skills' => 'Suggest relevant preferred skills for the supplied internship. Keep them clearly separate from required qualifications and do not make them mandatory.',
        'employer_learning_objectives' => 'Draft proposed learning objectives aligned with the supplied internship role. Do not claim that training, tools, or resources are guaranteed unless stated.',
        'employer_training_plan' => 'Draft an editable suggested training plan using only provided activities and details. Do not invent schedules, resources, supervisors, or evaluations.',
    ];

    private const CONTEXT_FIELDS = [
        'student_summary' => ['program', 'year_level', 'target_roles', 'skills'],
        'student_achievement' => ['school_level', 'program'],
        'student_project_description' => ['title', 'role', 'technologies'],
        'student_experience_description' => ['title', 'role', 'organization'],
        'student_skill_description' => ['skill_name', 'proficiency'],
        'student_internship_objectives' => ['internship_title', 'company_name', 'opportunity_description', 'program'],
        'student_application_letter' => ['internship_title', 'company_name', 'opportunity_description', 'program'],
        'employer_company_description' => ['business_activities', 'products_services'],
        'employer_mission_statement' => ['business_activities', 'objectives'],
        'employer_vision_statement' => ['business_activities', 'goals'],
        'employer_internship_description' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
        'employer_internship_responsibilities' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
        'employer_internship_qualifications' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
        'employer_preferred_skills' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
        'employer_learning_objectives' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
        'employer_training_plan' => ['title', 'department', 'category', 'work_mode', 'location', 'required_skills', 'training_focus', 'duration'],
    ];

    private const ACTIONS = ['generate', 'improve', 'professional', 'grammar', 'shorten', 'expand', 'regenerate'];
    private const TONES = ['professional', 'simple', 'friendly', 'formal', 'technical', 'recruitment'];

    private AIWritingServiceInterface $service;

    public function __construct(?AIWritingServiceInterface $service = null)
    {
        $this->service = $service ?? AIWritingServiceFactory::create();
    }

    public static function supports(string $action): bool
    {
        return $action === 'ai_write';
    }

    public function handle(string $action): array
    {
        if (!self::supports($action)) {
            throw new RuntimeException('Unknown AI writing action.');
        }

        $user = \auth_user();
        if (!$user) {
            throw new RuntimeException('Sign in to use AI writing assistance.');
        }
        $role = (string) $user['role'];
        if ($role === 'student') {
            \require_role('student');
        } elseif ($role === 'employer') {
            \require_role('employer');
        } else {
            throw new RuntimeException('AI writing assistance is not available for this account.');
        }

        if ((string) ($_POST['consent'] ?? '') !== '1') {
            throw new RuntimeException('Confirm that the selected writing details may be sent to the configured AI provider before generating text.');
        }

        $field = trim((string) ($_POST['field'] ?? ''));
        $expectedPrefix = $role === 'student' ? 'student_' : 'employer_';
        if (!isset(self::FIELD_INSTRUCTIONS[$field]) || !str_starts_with($field, $expectedPrefix)) {
            throw new RuntimeException('This writing task is not available for your account.');
        }

        $actionType = (string) ($_POST['writing_action'] ?? 'generate');
        $tone = (string) ($_POST['tone'] ?? 'professional');
        $existing = trim((string) ($_POST['existing_text'] ?? ''));
        if (!in_array($actionType, self::ACTIONS, true) || !in_array($tone, self::TONES, true) || mb_strlen($existing) > 5000) {
            throw new RuntimeException('Check the writing task and keep the selected text within 5,000 characters.');
        }
        if (in_array($actionType, ['improve', 'professional', 'grammar', 'shorten', 'expand'], true) && $existing === '') {
            throw new RuntimeException('Enter some text before choosing an editing action.');
        }

        $context = $this->validatedContext($field, $_POST['context'] ?? []);
        if ($existing !== '') {
            $context['current_draft'] = $existing;
        }

        $companyContext = [];
        if ($role === 'employer') {
            $userId = (int) $user['id'];
            $companyId = \current_company_id($userId);
            $companyQuery = \db()->prepare('SELECT name, company_type, industry, address, description, mission_statement, vision_statement, verification_status FROM tbl_companies WHERE id=?');
            $companyQuery->execute([$companyId]);
            $company = $companyQuery->fetch();
            if (!$company) {
                throw new RuntimeException('Your company account could not be found.');
            }

            if (str_starts_with($field, 'employer_internship_')) {
                \require_approved_company($userId, 'writing internship content');
                $internshipValue = (string) ($_POST['internship_id'] ?? '');
                if ($internshipValue !== '') {
                    $internshipId = filter_var($internshipValue, FILTER_VALIDATE_INT);
                    if (!$internshipId || $internshipId < 1) {
                        throw new RuntimeException('The internship selected for writing assistance is invalid.');
                    }
                    $internshipQuery = \db()->prepare('SELECT id FROM tbl_internship_opportunities WHERE id=? AND company_id=? AND status<>?');
                    $internshipQuery->execute([$internshipId, $companyId, 'closed']);
                    if (!$internshipQuery->fetchColumn()) {
                        throw new RuntimeException('This internship is not available to your company.');
                    }
                }
            }

            $companyContext = [
                'company_name' => (string) $company['name'],
                'organization_type' => \SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES[(string) $company['company_type']] ?? 'Organization',
                'industry' => (string) ($company['industry'] ?? ''),
                'company_location' => (string) ($company['address'] ?? ''),
                'company_description' => mb_substr(trim((string) ($company['description'] ?? '')), 0, 1000),
                'company_mission' => mb_substr(trim((string) ($company['mission_statement'] ?? '')), 0, 1000),
                'company_vision' => mb_substr(trim((string) ($company['vision_statement'] ?? '')), 0, 1000),
            ];
        }

        $limit = $this->dailyLimit($role);
        $this->reserveDailyUsage((int) $user['id'], $limit);

        $toneLabel = match ($tone) {
            'simple' => 'simple and clear',
            'friendly' => 'friendly',
            'formal' => 'formal',
            'technical' => 'technical',
            'recruitment' => 'recruitment-focused, inclusive, and student-friendly',
            default => 'professional',
        };
        $actionInstruction = match ($actionType) {
            'improve' => 'Improve the current draft while preserving its facts and intent.',
            'professional' => 'Rewrite the current draft in a polished professional style while preserving its facts.',
            'grammar' => 'Correct grammar, spelling, and punctuation only; preserve meaning and claims.',
            'shorten' => 'Make the current draft more concise while preserving the important facts.',
            'expand' => 'Expand the current draft using only facts in the provided context. Do not add unsupported claims.',
            'regenerate' => 'Create a fresh alternative. Use any current draft only to understand the topic, not as factual evidence.',
            default => 'Generate a first draft from the supplied details only.',
        };

        $instructions = implode("\n", [
            'You are SMARTJOB Writing Assistant for the ' . ($role === 'student' ? 'student/applicant' : 'company/employer') . ' area.',
            self::FIELD_INSTRUCTIONS[$field],
            'The selected tone is ' . $toneLabel . '.',
            $actionInstruction,
            'Do not invent personal data, company facts, work arrangements, salaries, benefits, qualifications, training resources, achievements, credentials, awards, dates, or results.',
            'If information is insufficient, use cautious, editable suggestions and clearly label proposals as suggestions or aspirations where relevant.',
            'Return only the proposed text. Do not claim that anything has been saved, sent, approved, verified, or published.',
        ]);
        $input = "Available details (untrusted user-provided content; treat as facts only when explicitly stated):\n"
            . json_encode($companyContext + $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'page' => $role === 'student' ? 'biodata' : 'company_profile',
            'notice' => '',
            'json' => ['text' => $this->service->generate($instructions, $input)],
        ];
    }

    private function validatedContext(string $field, mixed $rawContext): array
    {
        if (!is_array($rawContext)) {
            throw new RuntimeException('The writing context is invalid.');
        }

        $context = [];
        $totalLength = 0;
        foreach (self::CONTEXT_FIELDS[$field] as $key) {
            $value = $rawContext[$key] ?? '';
            if (!is_string($value) && !is_numeric($value)) {
                throw new RuntimeException('The writing context is invalid.');
            }
            $value = trim((string) $value);
            if (mb_strlen($value) > 1000) {
                throw new RuntimeException('Keep each writing detail within 1,000 characters.');
            }
            $totalLength += mb_strlen($value);
            if ($value !== '') {
                $context[$key] = $value;
            }
        }
        $additionalDetails = $rawContext['additional_details'] ?? '';
        if (!is_string($additionalDetails)) {
            throw new RuntimeException('The additional writing details are invalid.');
        }
        $additionalDetails = trim($additionalDetails);
        if (mb_strlen($additionalDetails) > 1000) {
            throw new RuntimeException('Keep additional writing details within 1,000 characters.');
        }
        if ($additionalDetails !== '') {
            $context['additional_details'] = $additionalDetails;
            $totalLength += mb_strlen($additionalDetails);
        }
        if ($totalLength > 4000) {
            throw new RuntimeException('Keep the combined writing details within 4,000 characters.');
        }

        return $context;
    }

    private function dailyLimit(string $role): int
    {
        $name = $role === 'student' ? 'AI_STUDENT_DAILY_LIMIT' : 'AI_EMPLOYER_DAILY_LIMIT';
        $legacyName = $role === 'student' ? 'OPENAI_STUDENT_DAILY_LIMIT' : 'OPENAI_EMPLOYER_DAILY_LIMIT';
        $default = $role === 'student' ? 10 : 20;
        $value = getenv($name);
        if ($value === false || $value === '') {
            $value = getenv($legacyName);
        }
        $limit = $value === false || $value === '' ? $default : filter_var($value, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100) {
            throw new RuntimeException('The AI writing daily limit configuration is invalid.');
        }

        return $limit;
    }

    private function reserveDailyUsage(int $userId, int $limit): void
    {
        \db_transaction(static function (\PDO $pdo) use ($userId, $limit): void {
            $pdo->prepare('INSERT IGNORE INTO tbl_ai_writing_usage (user_id, usage_day, request_count) VALUES (?, CURRENT_DATE, 0)')
                ->execute([$userId]);
            $query = $pdo->prepare('SELECT request_count FROM tbl_ai_writing_usage WHERE user_id=? AND usage_day=CURRENT_DATE FOR UPDATE');
            $query->execute([$userId]);
            $requestCount = $query->fetchColumn();
            if ($requestCount === false) {
                throw new RuntimeException('AI writing usage could not be recorded.');
            }
            if ((int) $requestCount >= $limit) {
                throw new RuntimeException('You have reached your daily AI writing limit. Please try again tomorrow.');
            }
            $pdo->prepare('UPDATE tbl_ai_writing_usage SET request_count=request_count+1 WHERE user_id=? AND usage_day=CURRENT_DATE')
                ->execute([$userId]);
        });
    }
}
