<?php
declare(strict_types=1);

final class ProfilePhotoStorage
{
    private const MAX_BYTES = 5_242_880;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public static function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Choose a valid JPG or PNG profile photo.');
        }

        $temporaryPath = (string) $file['tmp_name'];
        $size = (int) ($file['size'] ?? 0);
        $image = @getimagesize($temporaryPath);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if ($size < 1 || $size > self::MAX_BYTES || !is_array($image) || !is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime]) || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Profile photos must be a valid JPG or PNG image no larger than 5 MB.');
        }

        $key = bin2hex(random_bytes(32));
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private profile photo storage could not be initialized.');
        }
        $destination = $directory . DIRECTORY_SEPARATOR . $key;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The profile photo could not be stored.');
        }
        chmod($destination, 0600);

        return ['key' => $key, 'mime_type' => $mime];
    }

    public static function path(string $key): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            throw new InvalidArgumentException('Invalid profile photo key.');
        }
        return self::directory() . DIRECTORY_SEPARATOR . $key;
    }

    public static function remove(string $key): void
    {
        $path = self::path($key);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('The previous profile photo could not be removed from private storage.');
        }
    }

    public static function dataUri(?string $key): ?string
    {
        if (!$key) {
            return null;
        }
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('The stored profile photo has an unsupported format.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The stored profile photo could not be read.');
        }
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private static function directory(): string
    {
        $configured = getenv('SMARTJOB_PRIVATE_STORAGE');
        $root = $configured ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'smartjob';
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'profile-photos';
    }
}
