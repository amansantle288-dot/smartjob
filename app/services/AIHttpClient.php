<?php
declare(strict_types=1);

final class AIHttpClient
{
    /**
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public static function post(string $url, array $headers, string $requestBody, int $timeout): array
    {
        return function_exists('curl_init')
            ? self::sendCurlRequest($url, $headers, $requestBody, $timeout)
            : self::sendStreamRequest($url, $headers, $requestBody, $timeout);
    }

    private static function sendCurlRequest(string $url, array $headers, string $requestBody, int $timeout): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('The AI writing request could not be started.');
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failed = $body === false;
        curl_close($handle);
        if ($failed || !is_string($body)) {
            throw new RuntimeException('The AI writing service could not be reached. Please try again later.');
        }

        return ['status' => $status, 'body' => $body, 'headers' => $responseHeaders];
    }

    private static function sendStreamRequest(string $url, array $headers, string $requestBody, int $timeout): array
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) || !extension_loaded('openssl')) {
            throw new RuntimeException('AI writing requires PHP cURL or HTTPS streams with allow_url_fopen and OpenSSL enabled.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $requestBody,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        if (!is_string($body)) {
            throw new RuntimeException('The AI writing service could not be reached. Please try again later.');
        }

        $status = 0;
        $parsedHeaders = [];
        foreach ($responseHeaders as $header) {
            if (preg_match('/\AHTTP\/\d(?:\.\d)?\s+(\d{3})\b/', $header, $matches)) {
                $status = (int) $matches[1];
                continue;
            }
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        if ($status === 0) {
            throw new RuntimeException('The AI writing service returned an invalid response.');
        }

        return ['status' => $status, 'body' => $body, 'headers' => $parsedHeaders];
    }
}
