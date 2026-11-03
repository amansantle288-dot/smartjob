<?php
declare(strict_types=1);

require_once __DIR__ . '/AIWritingServiceInterface.php';
require_once __DIR__ . '/AIHttpClient.php';

final class OpenAIWritingService implements AIWritingServiceInterface
{
    private ?Closure $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    public function generate(string $instructions, string $input): string
    {
        $apiKey = trim((string) getenv('OPENAI_API_KEY'));
        if ($apiKey === '') {
            throw new RuntimeException('AI writing is not configured. Set OPENAI_API_KEY in the private server environment.');
        }

        $model = trim((string) (getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
        if (!preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $model)) {
            throw new RuntimeException('The AI writing model configuration is invalid.');
        }
        $timeoutValue = getenv('OPENAI_TIMEOUT');
        $timeout = $timeoutValue === false || $timeoutValue === '' ? 30 : filter_var($timeoutValue, FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < 5 || $timeout > 60) {
            throw new RuntimeException('The AI writing timeout must be between 5 and 60 seconds.');
        }

        try {
            $requestBody = json_encode([
                'model' => $model,
                'instructions' => $instructions,
                'input' => $input,
                'max_output_tokens' => 1200,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The AI writing request could not be prepared.', 0, $exception);
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($this->transport !== null) {
            $response = ($this->transport)('https://api.openai.com/v1/responses', $headers, $requestBody, $timeout);
        } else {
            $response = $this->sendRequest($headers, $requestBody, $timeout);
        }

        if (!is_array($response) || !isset($response['status'], $response['body']) || !is_int($response['status']) || !is_string($response['body'])) {
            throw new RuntimeException('The AI writing service returned an invalid response.');
        }
        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new RuntimeException('The AI writing service rejected its server configuration. Check the API key and account permissions.');
        }
        if ($response['status'] === 429) {
            throw new RuntimeException('The AI provider is temporarily limiting requests. Please try again later.');
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('The AI writing service is temporarily unavailable.');
        }

        try {
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The AI writing service returned an unreadable response.', 0, $exception);
        }
        $text = trim((string) ($decoded['output_text'] ?? ''));
        if ($text === '' && isset($decoded['output']) && is_array($decoded['output'])) {
            $segments = [];
            foreach ($decoded['output'] as $item) {
                foreach (($item['content'] ?? []) as $content) {
                    if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) {
                        $segments[] = $content['text'];
                    }
                }
            }
            $text = trim(implode("\n", $segments));
        }
        if ($text === '' || mb_strlen($text) > 8000) {
            throw new RuntimeException('The AI writing service did not return usable text.');
        }

        return $text;
    }

    private function sendRequest(array $headers, string $requestBody, int $timeout): array
    {
        return AIHttpClient::post('https://api.openai.com/v1/responses', $headers, $requestBody, $timeout);
    }
}
