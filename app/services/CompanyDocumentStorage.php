<?php
declare(strict_types=1);

final class CompanyDocumentStorage
{
    private const DEFAULT_MAX_FILE_BYTES = 8_388_608;
    private const DEFAULT_MAX_BATCH_BYTES = 33_554_432;
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public static function maxFileBytes(): int
    {
        return self::configuredLimit('SMARTJOB_COMPANY_DOCUMENT_MAX_FILE_BYTES', self::DEFAULT_MAX_FILE_BYTES);
    }

    public static function maxBatchBytes(): int
    {
        return self::configuredLimit('SMARTJOB_COMPANY_DOCUMENT_MAX_BATCH_BYTES', self::DEFAULT_MAX_BATCH_BYTES);
    }

    public static function validate(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException(self::uploadErrorMessage($error));
        }

        $path = (string) $file['tmp_name'];
        $size = filesize($path);
        $originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'document')));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $docxMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        if ($size === false || $size < 1 || $size > self::maxFileBytes()) {
            throw new RuntimeException('Each verification document must be no larger than ' . self::formatBytes(self::maxFileBytes()) . '.');
        }
        if ($extension === 'docx') {
            if (!in_array($mime, [$docxMime, 'application/zip', 'application/octet-stream'], true) || !self::isDocx($path)) {
                throw new RuntimeException('The DOCX file is invalid or damaged.');
            }
            $mime = $docxMime;
        } elseif (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime]) || !in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
            throw new RuntimeException('Verification documents must be valid PDF, DOCX, JPG, JPEG, or PNG files.');
        } elseif ($mime === 'application/pdf') {
            $handle = fopen($path, 'rb');
            $signature = $handle ? fread($handle, 5) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== '%PDF-') {
                throw new RuntimeException('The PDF file content is invalid.');
            }
        } else {
            $image = getimagesize($path);
            if (!is_array($image) || ($image['mime'] ?? null) !== $mime) {
                throw new RuntimeException('The image file content does not match its file type.');
            }
        }

        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'document';
        return [
            'tmp_name' => $path,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $mime,
            'file_size' => $size,
        ];
    }

    public static function store(array $validatedFile): array
    {
        $source = (string) ($validatedFile['tmp_name'] ?? '');
        if (!is_uploaded_file($source)) {
            throw new RuntimeException('The uploaded verification document is no longer available.');
        }

        $key = bin2hex(random_bytes(32));
        $destination = \CredentialStorage::path($key);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private document storage could not be initialized.');
        }
        if (!move_uploaded_file($source, $destination)) {
            throw new RuntimeException('An uploaded verification document could not be stored.');
        }
        if (!chmod($destination, 0600)) {
            unlink($destination);
            throw new RuntimeException('Private document permissions could not be secured.');
        }

        return [
            'storage_key' => $key,
            'original_name' => (string) $validatedFile['original_name'],
            'mime_type' => (string) $validatedFile['mime_type'],
            'file_size' => (int) $validatedFile['file_size'],
        ];
    }

    public static function remove(string $key): void
    {
        $path = \CredentialStorage::path($key);
        if (is_file($path) && !unlink($path)) {
            error_log('Unable to remove a company verification document after a failed batch.');
        }
    }

    private static function isDocx(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('DOCX uploads require PHP ZIP support to be enabled.');
        }
        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) {
            return false;
        }
        $valid = $archive->locateName('[Content_Types].xml') !== false
            && $archive->locateName('word/document.xml') !== false;
        $archive->close();
        return $valid;
    }

    private static function configuredLimit(string $environmentVariable, int $default): int
    {
        $value = getenv($environmentVariable);
        if ($value === false || $value === '') {
            return $default;
        }
        $limit = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit < 1) {
            throw new RuntimeException($environmentVariable . ' must be a positive integer.');
        }
        return $limit;
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'An uploaded verification document exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'A verification document was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Select at least one verification document to submit.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not receive a verification document. Please contact support if this continues.',
            default => 'Choose a valid verification document to upload.',
        };
    }

    private static function formatBytes(int $bytes): string
    {
        return $bytes >= 1_048_576 ? rtrim(rtrim(number_format($bytes / 1_048_576, 1), '0'), '.') . ' MB' : (int) ceil($bytes / 1024) . ' KB';
    }
}
