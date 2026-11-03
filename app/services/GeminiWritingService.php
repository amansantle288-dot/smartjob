<?php
declare(strict_types=1);

require_once __DIR__ . '/AIWritingServiceInterface.php';
require_once __DIR__ . '/AIHttpClient.php';

final class GeminiWritingService implements AIWritingServiceInterface
{
    private ?Closure $transport;
    private ?Closure $sleeper;

    public function __construct(?callable $transport = null, ?callable $sleeper = null)
    {
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
        $this->sleeper = $sleeper === null ? null : Closure::fromCallable($sleeper);
    }

    public function generate(string $instructions, string $input): string
    {
        $apiKey = trim((string) getenv('GEMINI_API_KEY'));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini writing is not configured. Set GEMINI_API_KEY in the private server environment.');
        }

        $model = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite'));
        if (!preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $model)) {
            throw new RuntimeException('The Gemini model configuration is invalid.');
        }
        $timeoutValue = getenv('GEMINI_TIMEOUT');
        $timeout = $timeoutValue === false || $timeoutValue === '' ? 30 : filter_var($timeoutValue, FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < 5 || $timeout > 60) {
            throw new RuntimeException('The Gemini timeout must be between 5 and 60 seconds.');
        }

        try {
            $requestBody = json_encode([
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $instructions . "\n\n" . $input]],
                ]],
                'generationConfig' => ['maxOutputTokens' => 512],
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The Gemini writing request could not be prepared.', 0, $exception);
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $headers = [
            'x-goog-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $response = $this->requestWithRetry($url, $headers, $requestBody, $timeout);
        $this->validateStatus($response['status'], $response['body']);

        try {
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Gemini returned an unreadable response.', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini returned an unreadable response.');
        }

        $blockReason = (string) ($decoded['promptFeedback']['blockReason'] ?? '');
        if ($blockReason !== '') {
            throw new RuntimeException('Gemini could not generate this draft because the prompt was blocked. Review the text and try again.');
        }

        $candidate = $decoded['candidates'][0] ?? null;
        if (!is_array($candidate)) {
            throw new RuntimeException('Gemini did not return a usable draft. Review the request and try again.');
        }
        $finishReason = strtoupper((string) ($candidate['finishReason'] ?? ''));
        if (in_array($finishReason, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) {
            throw new RuntimeException('Gemini could not generate this draft because the response was blocked. Review the request and try again.');
        }

        $parts = $candidate['content']['parts'] ?? null;
        if (!is_array($parts)) {
            throw new RuntimeException('Gemini did not return a usable draft. Review the request and try again.');
        }
        $segments = [];
        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                $segments[] = trim($part['text']);
            }
        }
        $text = trim(implode("\n", $segments));
        if ($text === '' || mb_strlen($text) > 8000) {
            throw new RuntimeException('Gemini did not return usable text.');
        }

        return $text;
    }

    private function requestWithRetry(string $url, array $headers, string $requestBody, int $timeout): array
    {
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->transport !== null
                ? ($this->transport)($url, $headers, $requestBody, $timeout)
                : AIHttpClient::post($url, $headers, $requestBody, $timeout);
            if (!is_array($response) || !isset($response['status'], $response['body']) || !is_int($response['status']) || !is_string($response['body'])) {
                throw new RuntimeException('Gemini returned an invalid response.');
            }
            if (!in_array($response['status'], [500, 503], true) || $attempt === $maxAttempts) {
                return $response;
            }

            $headersReceived = $response['headers'] ?? [];
            $retryAfter = is_array($headersReceived) ? $this->retryAfterSeconds($headersReceived) : null;
            if ($retryAfter !== null && $retryAfter > 3) {
                return $response;
            }
            $delayMicroseconds = $retryAfter === null
                ? 250000 * (2 ** ($attempt - 1))
                : $retryAfter * 1000000;
            if ($this->sleeper !== null) {
                ($this->sleeper)($delayMicroseconds);
            } else {
                usleep($delayMicroseconds);
            }
        }

        throw new RuntimeException('Gemini could not complete the request.');
    }

    private function retryAfterSeconds(array $headers): ?int
    {
        $retryAfter = null;
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'retry-after' && is_string($value)) {
                $retryAfter = trim($value);
                break;
            }
        }
        if ($retryAfter === null || $retryAfter === '') {
            return null;
        }
        if (ctype_digit($retryAfter)) {
            return (int) $retryAfter;
        }
        $timestamp = strtotime($retryAfter);
        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function validateStatus(int $status, string $body): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }
        if ($status === 400) {
            throw new RuntimeException('Gemini rejected the writing request. Check the prompt and configuration.');
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException('Gemini rejected its API key or account permissions. Check the server configuration.');
        }
        if ($status === 404) {
            $modelMessage = $this->sanitizedModelAvailabilityMessage($body);
            throw new RuntimeException($modelMessage ?? 'The configured Gemini model is unavailable. Check GEMINI_MODEL.');
        }
        if ($status === 429) {
            throw new RuntimeException('Gemini rate limit or quota reached (HTTP 429). Please try again later.');
        }
        if ($status === 503) {
            throw new RuntimeException('Gemini service is temporarily unavailable (HTTP 503). Please try again later.');
        }
        if ($status === 500) {
            throw new RuntimeException('Gemini encountered an internal server error (HTTP 500). Please try again later.');
        }
        if ($status >= 500) {
            throw new RuntimeException('Gemini is temporarily unavailable (HTTP ' . $status . '). Please try again later.');
        }
        throw new RuntimeException('Gemini rejected the writing request (HTTP ' . $status . '). Check the provider configuration.');
    }

    private function sanitizedModelAvailabilityMessage(string $body): ?string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $message = $decoded['error']['message'] ?? null;
        if (!is_string($message)) {
            return null;
        }
        $message = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? '');
        if (
            !preg_match('/\bmodel(?:s)?\b/i', $message)
            || !preg_match('/\b(unavailable|no longer available|not found|not supported|does not exist)\b/i', $message)
        ) {
            return null;
        }

        $apiKey = trim((string) getenv('GEMINI_API_KEY'));
        if ($apiKey !== '') {
            $message = str_replace($apiKey, '[redacted]', $message);
        }
        $message = preg_replace('/\bAIza[0-9A-Za-z_-]{20,}\b/', '[redacted]', $message) ?? '';
        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $message) ?? '';
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        return 'Gemini model unavailable: ' . mb_substr($message, 0, 300) . ' Check GEMINI_MODEL.';
    }
}
