<?php
declare(strict_types=1);

final class CredentialStorage
{
    private const MAX_BYTES = 8_388_608;
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Choose a valid file to upload.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Files must be no larger than 8 MB.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'document')));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $docxMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        if ($extension === 'docx' || $mime === $docxMime) {
            if ($extension !== 'docx' || !self::isDocx((string) $file['tmp_name'])) {
                throw new RuntimeException('The DOCX file is invalid or damaged.');
            }
            $mime = $docxMime;
        } elseif (!is_string($mime) || !isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('Allowed formats are PDF, DOCX, JPEG, PNG, and WebP.');
        }
        $key = bin2hex(random_bytes(32));
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private document storage could not be initialized.');
        }
        $destination = $directory . DIRECTORY_SEPARATOR . $key;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }
        chmod($destination, 0600);

        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'document';
        return [
            'storage_key' => $key,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $mime,
            'file_size' => $size,
        ];
    }

    public static function path(string $key): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            throw new InvalidArgumentException('Invalid stored document key.');
        }
        return self::directory() . DIRECTORY_SEPARATOR . $key;
    }

    public static function snapshot(string $sourceKey): array
    {
        $source = self::path($sourceKey);
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException('A selected document is missing from private storage.');
        }
        $size = filesize($source);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('A selected document exceeds the 8 MB application limit.');
        }

        $key = bin2hex(random_bytes(32));
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private document storage could not be initialized.');
        }
        $destination = self::path($key);
        if (!copy($source, $destination)) {
            throw new RuntimeException('A document snapshot could not be created.');
        }
        if (!chmod($destination, 0600)) {
            unlink($destination);
            throw new RuntimeException('Private document permissions could not be secured.');
        }
        $sha256 = hash_file('sha256', $destination);
        if (!is_string($sha256)) {
            unlink($destination);
            throw new RuntimeException('The application document snapshot could not be verified.');
        }

        return [
            'storage_key' => $key,
            'file_size' => $size,
            'sha256' => $sha256,
        ];
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

    private static function directory(): string
    {
        $configured = getenv('SMARTJOB_PRIVATE_STORAGE');
        return $configured ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'smartjob';
    }
}
