<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Applications\Controllers\ApplicationController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$storageDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smartjob-snapshot-' . $token;
$previousStorage = getenv('SMARTJOB_PRIVATE_STORAGE');
$studentId = 0;
$employerId = 0;
$companyId = 0;
$internshipId = 0;
$credentialId = 0;
$applicationId = 0;
$snapshotKey = null;
$sourceKey = bin2hex(random_bytes(32));

try {
    putenv('SMARTJOB_PRIVATE_STORAGE=' . $storageDirectory);
    if (!mkdir($storageDirectory, 0700, true)) {
        throw new RuntimeException('Could not create the isolated document storage for the snapshot test.');
    }

    $userInsert = $pdo->prepare('INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, \'active\')');
    $userInsert->execute(['SMARTJOB Snapshot Student', 'snapshot-student-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $userInsert->execute(['SMARTJOB Snapshot Employer', 'snapshot-employer-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'employer']);
    $employerId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")
        ->execute(['SMARTJOB Snapshot Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, 1, ?, 'published')")
        ->execute([$companyId, $employerId, 'Snapshot Internship ' . $token, 'Snapshot integration test.', date('Y-m-d', strtotime('+1 day'))]);
    $internshipId = (int) $pdo->lastInsertId();

    $sourceContents = "%PDF-1.4\nImmutable application document test.\n";
    file_put_contents(CredentialStorage::path($sourceKey), $sourceContents);
    chmod(CredentialStorage::path($sourceKey), 0600);
    $pdo->prepare('INSERT INTO tbl_student_credentials (user_id, category, original_name, storage_key, mime_type, file_size) VALUES (?, \'resume\', ?, ?, \'application/pdf\', ?)')
        ->execute([$studentId, 'snapshot-resume.pdf', $sourceKey, strlen($sourceContents)]);
    $credentialId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $studentId;
    $_POST = ['internship_id' => $internshipId, 'credential_ids' => [$credentialId]];
    $result = (new ApplicationController())->handle('apply');
    if ($result['notice'] !== 'Your application was submitted.') {
        throw new RuntimeException('The test application was not submitted.');
    }

    $applicationQuery = $pdo->prepare('SELECT id FROM tbl_applications WHERE internship_id=? AND student_id=?');
    $applicationQuery->execute([$internshipId, $studentId]);
    $applicationId = (int) $applicationQuery->fetchColumn();
    $snapshotQuery = $pdo->prepare('SELECT storage_key, sha256, file_size FROM tbl_application_document_snapshots WHERE application_id=? AND credential_id=?');
    $snapshotQuery->execute([$applicationId, $credentialId]);
    $snapshot = $snapshotQuery->fetch();
    if (!$snapshot) {
        throw new RuntimeException('Application submission did not create an immutable document snapshot.');
    }
    $snapshotKey = (string) $snapshot['storage_key'];
    $snapshotPath = CredentialStorage::path($snapshotKey);
    if (hash_file('sha256', $snapshotPath) !== $snapshot['sha256'] || (int) $snapshot['file_size'] !== strlen($sourceContents)) {
        throw new RuntimeException('The application snapshot metadata does not match its stored content.');
    }

    file_put_contents(CredentialStorage::path($sourceKey), 'The student later changed the original file.');
    if (file_get_contents($snapshotPath) !== $sourceContents) {
        throw new RuntimeException('Changing the original credential altered the application snapshot.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($studentId && $internshipId && !$applicationId) {
        $applicationQuery = $pdo->prepare('SELECT id FROM tbl_applications WHERE internship_id=? AND student_id=?');
        $applicationQuery->execute([$internshipId, $studentId]);
        $applicationId = (int) $applicationQuery->fetchColumn();
    }
    if ($applicationId) {
        $snapshotQuery = $pdo->prepare('SELECT storage_key FROM tbl_application_document_snapshots WHERE application_id=?');
        $snapshotQuery->execute([$applicationId]);
        foreach ($snapshotQuery->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $path = CredentialStorage::path((string) $key);
            if (is_file($path)) {
                unlink($path);
            }
        }
        $pdo->prepare('DELETE FROM tbl_applications WHERE id=?')->execute([$applicationId]);
    }
    if ($studentId || $employerId) {
        $ids = array_values(array_filter([$studentId, $employerId]));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM tbl_audit_logs WHERE user_id IN ({$placeholders})")->execute($ids);
        $pdo->prepare("DELETE FROM tbl_notifications WHERE user_id IN ({$placeholders})")->execute($ids);
    }
    if ($credentialId) {
        $pdo->prepare('DELETE FROM tbl_student_credentials WHERE id=?')->execute([$credentialId]);
    }
    if ($internshipId) {
        $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$internshipId]);
    }
    if ($companyId) {
        $pdo->prepare('DELETE FROM tbl_company_members WHERE company_id=?')->execute([$companyId]);
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    if ($studentId || $employerId) {
        $ids = array_values(array_filter([$studentId, $employerId]));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM tbl_users WHERE id IN ({$placeholders})")->execute($ids);
    }
    foreach ([$sourceKey, $snapshotKey] as $key) {
        if ($key) {
            $path = CredentialStorage::path((string) $key);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
    if (is_dir($storageDirectory)) {
        rmdir($storageDirectory);
    }
    putenv($previousStorage === false ? 'SMARTJOB_PRIVATE_STORAGE' : 'SMARTJOB_PRIVATE_STORAGE=' . $previousStorage);
}

echo "Application documents are snapshotted independently of the mutable student credential.\n";
