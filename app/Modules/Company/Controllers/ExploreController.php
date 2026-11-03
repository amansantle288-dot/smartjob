<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Company\Controllers;

use RuntimeException;

final class ExploreController
{
    public function viewData(string $page): array
    {
        if ($page === 'home') {
            $featured = \db()->query("SELECT c.id, c.name, c.industry, c.address, c.description, c.logo_key, (SELECT COUNT(*) FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) AS opportunity_count FROM tbl_companies c WHERE c.verification_status='approved' AND EXISTS (SELECT 1 FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) ORDER BY opportunity_count DESC, c.created_at DESC LIMIT 3")->fetchAll();
            return ['featuredCompanies' => $featured];
        }
        if ($page !== 'explore') {
            return [];
        }

        $companyId = filter_var($_GET['company_id'] ?? null, FILTER_VALIDATE_INT);
        $internshipId = filter_var($_GET['internship_id'] ?? null, FILTER_VALIDATE_INT);

        $filters = [];
        $params = [];
        if ($companyId) {
            $filters[] = 'c.id = ?';
            $params[] = $companyId;
        }
        foreach ([
            'q' => ['c.name', 180],
            'industry' => ['c.industry', 120],
        ] as $key => [$column, $limit]) {
            $value = trim(mb_substr((string) ($_GET[$key] ?? ''), 0, $limit));
            if ($value !== '') {
                $filters[] = "{$column} LIKE ?";
                $params[] = '%' . $value . '%';
            }
        }

        $location = trim(mb_substr((string) ($_GET['location'] ?? ''), 0, 180));
        if ($location !== '') {
            $filters[] = "(c.address LIKE ? OR i.location LIKE ?)";
            array_push($params, '%' . $location . '%', '%' . $location . '%');
        }

        $category = trim(mb_substr((string) ($_GET['category'] ?? ''), 0, 100));
        if ($category !== '') {
            $filters[] = "i.category LIKE ?";
            $params[] = '%' . $category . '%';
        }

        $skill = trim(mb_substr((string) ($_GET['skill'] ?? ''), 0, 120));
        if ($skill !== '') {
            $filters[] = "EXISTS (SELECT 1 FROM tbl_internship_skills ik JOIN tbl_skills sk ON sk.id=ik.skill_id WHERE ik.internship_id=i.id AND sk.name LIKE ?)";
            $params[] = '%' . $skill . '%';
        }

        $sql = "SELECT i.id, i.title, i.category, i.description, i.requirements, i.location, i.work_mode, i.slots, i.application_deadline, i.published_at, i.hiring_duration_days, i.status,
                       c.id AS company_id, c.name AS company_name, c.industry, c.address, c.description AS company_description, c.mission_statement, c.vision_statement, c.website, c.logo_key,
                       (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') FROM tbl_internship_skills ik JOIN tbl_skills s ON s.id=ik.skill_id WHERE ik.internship_id=i.id) AS required_skills
                FROM tbl_internship_opportunities i
                JOIN tbl_companies c ON c.id=i.company_id
                WHERE c.verification_status='approved' AND i.status='published'" . ($filters ? ' AND ' . implode(' AND ', $filters) : '') . '
                ORDER BY i.published_at DESC, i.id DESC';

        $query = \db()->prepare($sql);
        $query->execute($params);
        $opportunities = $query->fetchAll();

        $user = \auth_user();
        $savedOpportunityIds = [];
        $savedCompanyIds = [];
        if ($user && $user['role'] === 'student') {
            $savedQuery = \db()->prepare('SELECT internship_id FROM tbl_saved_internships WHERE student_id=?');
            $savedQuery->execute([(int) $user['id']]);
            foreach ($savedQuery->fetchAll(\PDO::FETCH_COLUMN) as $savedId) {
                $savedOpportunityIds[(int) $savedId] = true;
            }
            $savedCompanyQuery = \db()->prepare('SELECT company_id FROM tbl_saved_companies WHERE student_id=?');
            $savedCompanyQuery->execute([(int) $user['id']]);
            foreach ($savedCompanyQuery->fetchAll(\PDO::FETCH_COLUMN) as $savedId) {
                $savedCompanyIds[(int) $savedId] = true;
            }
        }
        foreach ($opportunities as &$opportunity) {
            $opportunity['hiring_status'] = \HiringCountdown::status(
                (string) $opportunity['application_deadline'],
                (string) $opportunity['status']
            );
            $opportunity['posted_date'] = \HiringCountdown::postedDate((string) $opportunity['published_at']);
            $opportunity['required_skills_list'] = array_values(array_filter(array_map('trim', preg_split('/\s*[,;\n]+\s*/u', (string) ($opportunity['required_skills'] ?? ''))), static fn (string $skillName): bool => $skillName !== ''));
            $opportunity['is_saved'] = isset($savedOpportunityIds[(int) $opportunity['id']]);
            if ($user && $user['role'] === 'student') {
                $matched = \MatchingService::scoreStudentForInternship(\db(), (int) $user['id'], (int) $opportunity['id']);
                $opportunity['match_score'] = $matched['score'];
                $opportunity['matched_skills'] = $matched['matched_skills'];
                $opportunity['missing_skills'] = $matched['missing_skills'];
                $opportunity['verified_training_skills'] = $matched['verified_training_skills'];

                $statusStmt = \db()->prepare('SELECT status FROM tbl_applications WHERE internship_id=? AND student_id=? ORDER BY created_at DESC LIMIT 1');
                $statusStmt->execute([(int) $opportunity['id'], (int) $user['id']]);
                $opportunity['application_status'] = $statusStmt->fetchColumn() ?: null;
            } else {
                $opportunity['match_score'] = null;
                $opportunity['match_summary'] = 'Sign in to view your match score';
            }
        }
        unset($opportunity);

        $selectedOpportunity = null;
        $selectedOpportunityId = $internshipId ?: ($opportunities[0]['id'] ?? null);
        if ($selectedOpportunityId) {
            foreach ($opportunities as $opportunity) {
                if ((int) $opportunity['id'] === (int) $selectedOpportunityId) {
                    $selectedOpportunity = $opportunity;
                    break;
                }
            }
        }

        $selectedCompany = null;
        if ($selectedOpportunity) {
            $selectedCompany = [
                'id' => (int) $selectedOpportunity['company_id'],
                'name' => (string) $selectedOpportunity['company_name'],
                'industry' => (string) $selectedOpportunity['industry'],
                'address' => (string) $selectedOpportunity['address'],
                'description' => (string) ($selectedOpportunity['company_description'] ?? ''),
                'mission_statement' => (string) ($selectedOpportunity['mission_statement'] ?? ''),
                'vision_statement' => (string) ($selectedOpportunity['vision_statement'] ?? ''),
                'website' => (string) ($selectedOpportunity['website'] ?? ''),
                'logo_key' => $selectedOpportunity['logo_key'] ?? null,
            ];
        } elseif ($companyId) {
            $companyQuery = \db()->prepare("SELECT id, name, industry, address, description, mission_statement, vision_statement, website, logo_key FROM tbl_companies WHERE id=? AND verification_status='approved'");
            $companyQuery->execute([$companyId]);
            $selectedCompany = $companyQuery->fetch() ?: null;
            if (!$selectedCompany) {
                http_response_code(404);
                throw new RuntimeException('This company profile is not available.');
            }
        }
        if ($selectedCompany) {
            $selectedCompany['is_saved'] = isset($savedCompanyIds[(int) $selectedCompany['id']]);
        }

        $companies = [];
        $seenCompanyIds = [];
        foreach ($opportunities as $opportunity) {
            $companyIdValue = (int) $opportunity['company_id'];
            if (isset($seenCompanyIds[$companyIdValue])) {
                continue;
            }
            $seenCompanyIds[$companyIdValue] = true;
            $companies[] = [
                'id' => $companyIdValue,
                'name' => (string) $opportunity['company_name'],
                'industry' => (string) $opportunity['industry'],
                'address' => (string) $opportunity['address'],
                'logo_key' => $opportunity['logo_key'] ?? null,
            ];
        }

        $industries = \db()->query("SELECT DISTINCT industry FROM tbl_companies WHERE verification_status='approved' AND industry IS NOT NULL AND industry<>'' ORDER BY industry")->fetchAll(\PDO::FETCH_COLUMN);
        $categories = \db()->query("SELECT DISTINCT category FROM tbl_internship_opportunities i JOIN tbl_companies c ON c.id=i.company_id WHERE c.verification_status='approved' AND i.status='published' AND i.application_deadline >= CURDATE() AND category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(\PDO::FETCH_COLUMN);
        $suggestions = $this->suggestedCompanies();

        return compact('companies', 'selectedCompany', 'opportunities', 'selectedOpportunity', 'industries', 'categories', 'suggestions');
    }

    private function suggestedCompanies(): array
    {
        $user = \auth_user();
        if (!$user || $user['role'] !== 'student') {
            return [];
        }

        $profile = \db()->prepare('SELECT sp.program, sp.year_level, sp.bio, sp.preferred_location, sp.preferred_work_mode, ea.tertiary_course, ea.senior_high_strand FROM tbl_student_profiles sp LEFT JOIN tbl_student_educational_attainment ea ON ea.user_id=sp.user_id WHERE sp.user_id=?');
        $profile->execute([(int) $user['id']]);
        $student = $profile->fetch() ?: [];
        $skillQuery = \db()->prepare('SELECT s.name FROM tbl_student_skills ss JOIN tbl_skills s ON s.id=ss.skill_id WHERE ss.student_id=?');
        $skillQuery->execute([(int) $user['id']]);
        $interests = preg_split('/[^\p{L}\p{N}]+/u', strtolower(implode(' ', array_filter([
            (string) ($student['program'] ?? ''),
            (string) ($student['bio'] ?? ''),
            (string) ($student['tertiary_course'] ?? ''),
            (string) ($student['senior_high_strand'] ?? ''),
        ])))) ?: [];
        $stopWords = ['and', 'the', 'for', 'with', 'you', 'your', 'from', 'are', 'this', 'that'];
        $terms = array_values(array_unique(array_filter(array_merge(array_map('strtolower', $skillQuery->fetchAll(\PDO::FETCH_COLUMN)), $interests), static fn (string $term): bool => mb_strlen($term) >= 3 && !in_array($term, $stopWords, true))));
        $location = strtolower(trim((string) ($student['preferred_location'] ?? '')));
        if (!$terms && $location === '') {
            return [];
        }

        $companies = \db()->query("SELECT c.id, c.name, c.industry, c.address, c.description, c.logo_key,
                (SELECT GROUP_CONCAT(CONCAT(i.title, ' ', COALESCE(i.category,''), ' ', i.description, ' ', COALESCE(i.requirements,'')) SEPARATOR ' ') FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) AS opportunity_text,
                (SELECT GROUP_CONCAT(DISTINCT s.name SEPARATOR ' ') FROM tbl_internship_opportunities i JOIN tbl_internship_skills ik ON ik.internship_id=i.id JOIN tbl_skills s ON s.id=ik.skill_id WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) AS required_skills,
                (SELECT GROUP_CONCAT(DISTINCT i.work_mode SEPARATOR ' ') FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) AS work_modes
            FROM tbl_companies c WHERE c.verification_status='approved' AND EXISTS (SELECT 1 FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline >= CURDATE()) ORDER BY c.created_at DESC LIMIT 100")->fetchAll();
        foreach ($companies as &$company) {
            $haystack = strtolower(implode(' ', array_filter([
                $company['industry'] ?? '',
                $company['address'] ?? '',
                $company['description'] ?? '',
                $company['opportunity_text'] ?? '',
                $company['required_skills'] ?? '',
            ])));
            $score = 0;
            foreach ($terms as $term) {
                $term = trim((string) $term);
                if (mb_strlen($term) >= 3 && str_contains($haystack, $term)) {
                    $score += 2;
                }
            }
            if ($location !== '' && str_contains($haystack, $location)) {
                $score += 3;
            }
            $preferredMode = (string) ($student['preferred_work_mode'] ?? 'any');
            if ($preferredMode !== 'any' && str_contains((string) ($company['work_modes'] ?? ''), $preferredMode)) {
                $score += 1;
            }
            $company['match_score'] = $score;
        }
        unset($company);
        usort($companies, static fn (array $a, array $b): int => $b['match_score'] <=> $a['match_score']);
        $matches = array_values(array_filter($companies, static fn (array $company): bool => $company['match_score'] > 0));
        return array_slice($matches, 0, 3);
    }
}
