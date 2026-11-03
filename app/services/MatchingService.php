<?php
declare(strict_types=1);

final class MatchingService
{
    /**
     * Score structured student and opportunity skills, returning an explainable result.
     * Eligibility and application status are intentionally handled by callers.
     */
    public static function score(array $studentSkills, array $requiredSkills, array $verifiedTrainingSkills = []): array
    {
        $normalize = static fn (array $skills): array => array_values(array_unique(array_filter(array_map(
            static fn ($skill): string => mb_strtolower(trim((string) $skill)),
            $skills
        ))));

        $student = $normalize($studentSkills);
        $required = $normalize($requiredSkills);
        $training = $normalize($verifiedTrainingSkills);
        if ($required === []) {
            return ['score' => 0, 'matched_skills' => [], 'missing_skills' => [], 'verified_training_skills' => []];
        }
        $matchedProfile = array_values(array_intersect($required, $student));
        $matchedTraining = array_values(array_diff(array_intersect($required, $training), $matchedProfile));
        $matched = array_values(array_unique(array_merge($matchedProfile, $matchedTraining)));
        $missing = array_values(array_diff($required, $matched));
        return [
            'score' => (int) round(count($matched) / count($required) * 100),
            'matched_skills' => $matched,
            'missing_skills' => $missing,
            'verified_training_skills' => $matchedTraining,
        ];
    }

    public static function scoreStudentForInternship(PDO $pdo, int $studentId, int $internshipId): array
    {
        $studentQuery = $pdo->prepare('SELECT s.name FROM tbl_student_skills ss JOIN tbl_skills s ON s.id=ss.skill_id WHERE ss.student_id=?');
        $studentQuery->execute([$studentId]);
        $studentSkills = $studentQuery->fetchAll(PDO::FETCH_COLUMN);

        $requiredQuery = $pdo->prepare('SELECT s.name FROM tbl_internship_skills ins JOIN tbl_skills s ON s.id=ins.skill_id WHERE ins.internship_id=? AND ins.is_required=1');
        $requiredQuery->execute([$internshipId]);
        $requiredSkills = $requiredQuery->fetchAll(PDO::FETCH_COLUMN);

        $trainingQuery = $pdo->prepare("SELECT t.acquired_skills FROM tbl_student_trainings t WHERE t.user_id=? AND t.verification_status='verified'");
        $trainingQuery->execute([$studentId]);
        $verifiedSkills = [];
        foreach ($trainingQuery->fetchAll(PDO::FETCH_COLUMN) as $encodedSkills) {
            $skills = json_decode((string) $encodedSkills, true);
            if (is_array($skills)) {
                $verifiedSkills = array_merge($verifiedSkills, $skills);
            }
        }
        return self::score($studentSkills, $requiredSkills, $verifiedSkills);
    }
}
