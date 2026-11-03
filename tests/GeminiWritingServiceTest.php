<?php
declare(strict_types=1);

require __DIR__ . '/../app/services/GeminiWritingService.php';

$previousValues = [];
foreach (['GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_TIMEOUT'] as $name) {
    $previousValues[$name] = getenv($name);
}
putenv('GEMINI_API_KEY=test-key-not-for-network-use');
putenv('GEMINI_MODEL');
putenv('GEMINI_TIMEOUT=15');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $messagePart) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), $messagePart), 'Unexpected Gemini service failure.');
        return;
    }
    throw new RuntimeException('An invalid Gemini response or configuration was accepted.');
};
$serviceWithResponse = static fn (array $response): GeminiWritingService =>
    new GeminiWritingService(static fn (): array => $response, static function (): void {});

try {
    $captured = [];
    $service = new GeminiWritingService(static function (string $url, array $headers, string $body, int $timeout) use (&$captured): array {
        $captured = compact('url', 'headers', 'body', 'timeout');
        return [
            'status' => 200,
            'body' => '{"candidates":[{"content":{"parts":[{"text":"A concise draft."},{"text":"A second sentence."}]}}]}',
        ];
    });
    $text = $service->generate('Write a factual summary.', '{"program":"Computer Science"}');
    $assert($text === "A concise draft.\nA second sentence.", 'Gemini text parts were not combined.');
    $assert($captured['url'] === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', 'The default Gemini endpoint or model was incorrect.');
    $assert(in_array('x-goog-api-key: test-key-not-for-network-use', $captured['headers'], true), 'The Gemini API key header was missing.');
    $request = json_decode($captured['body'], true, 512, JSON_THROW_ON_ERROR);
    $assert($request['contents'][0]['role'] === 'user', 'The Gemini content role was incorrect.');
    $assert($request['contents'][0]['parts'][0]['text'] === "Write a factual summary.\n\n{\"program\":\"Computer Science\"}", 'The writing instructions and context were not sent.');
    $assert($request['generationConfig']['maxOutputTokens'] === 512 && $captured['timeout'] === 15, 'Generation token or timeout configuration was incorrect.');

    putenv('GEMINI_MODEL=gemini-custom-model');
    $service->generate('Write.', 'Input.');
    $assert($captured['url'] === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-custom-model:generateContent', 'GEMINI_MODEL was not read dynamically.');
    putenv('GEMINI_MODEL=gemini-3.5-flash-lite');

    $expectFailure(
        static fn () => $serviceWithResponse(['status' => 200, 'body' => '{bad json'])->generate('Write.', 'Input.'),
        'unreadable response'
    );
    $expectFailure(
        static fn () => $serviceWithResponse(['status' => 200, 'body' => '{"candidates":[]}'])->generate('Write.', 'Input.'),
        'usable draft'
    );
    $expectFailure(
        static fn () => $serviceWithResponse(['status' => 200, 'body' => '{"promptFeedback":{"blockReason":"SAFETY"}}'])->generate('Write.', 'Input.'),
        'prompt was blocked'
    );
    $expectFailure(
        static fn () => $serviceWithResponse(['status' => 200, 'body' => '{"candidates":[{"finishReason":"SAFETY","content":{"parts":[]}}]}'])->generate('Write.', 'Input.'),
        'response was blocked'
    );
    $expectFailure(
        static fn () => $serviceWithResponse(['status' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":" "}]}}]}'])->generate('Write.', 'Input.'),
        'usable text'
    );

    foreach ([400 => 'rejected the writing request', 401 => 'API key or account permissions', 403 => 'API key or account permissions', 500 => 'internal server error (HTTP 500)', 503 => 'service is temporarily unavailable (HTTP 503)'] as $status => $message) {
        $expectFailure(
            static fn () => $serviceWithResponse(['status' => $status, 'body' => '{"error":"private provider details"}'])->generate('Write.', 'Input.'),
            $message
        );
    }

    $retiredModelMessage = 'This model models/gemini-retired-flash-lite is no longer available to new users. Please update your code to use models/gemini-3.5-flash-lite. Test key: AIza123456789012345678901234567890';
    try {
        $serviceWithResponse([
            'status' => 404,
            'body' => json_encode(['error' => ['message' => $retiredModelMessage]], JSON_THROW_ON_ERROR),
        ])->generate('Write.', 'Input.');
        throw new RuntimeException('An unavailable Gemini model was accepted.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'This model models/gemini-retired-flash-lite is no longer available to new users. Please update your code to use models/gemini-3.5-flash-lite.'), 'The sanitized Gemini model availability message was not shown.');
        $assert(!str_contains($exception->getMessage(), 'AIza'), 'A key-like string leaked through the sanitized provider error.');
        $assert(str_contains($exception->getMessage(), 'Check GEMINI_MODEL.'), 'The model configuration guidance was missing.');
    }

    $calls = 0;
    $delays = [];
    $retryingService = new GeminiWritingService(
        static function () use (&$calls): array {
            $calls++;
            return $calls < 3
                ? ['status' => 503, 'body' => '{}', 'headers' => ['Retry-After' => '1']]
                : ['status' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":"Retry succeeded."}]}}]}'];
        },
        static function (int $delay) use (&$delays): void {
            $delays[] = $delay;
        }
    );
    $assert($retryingService->generate('Write.', 'Input.') === 'Retry succeeded.', 'Gemini did not recover after bounded service-unavailable retries.');
    $assert($calls === 3 && $delays === [1000000, 1000000], 'Gemini did not respect Retry-After within the retry limit.');

    $calls = 0;
    $delays = [];
    $limitedService = new GeminiWritingService(
        static function () use (&$calls): array {
            $calls++;
            return ['status' => 429, 'body' => '{"error":"private provider details"}'];
        },
        static function (int $delay) use (&$delays): void {
            $delays[] = $delay;
        }
    );
    $expectFailure(static fn () => $limitedService->generate('Write.', 'Input.'), 'rate limit or quota');
    $assert($calls === 1 && $delays === [], 'Gemini quota errors should be reported without retrying.');

    foreach ([500, 503] as $serverError) {
        $calls = 0;
        $delays = [];
        $recoveringService = new GeminiWritingService(
            static function () use (&$calls, $serverError): array {
                $calls++;
                return $calls < 3
                    ? ['status' => $serverError, 'body' => '{}']
                    : ['status' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":"Server retry succeeded."}]}}]}'];
            },
            static function (int $delay) use (&$delays): void {
                $delays[] = $delay;
            }
        );
        $assert($recoveringService->generate('Write.', 'Input.') === 'Server retry succeeded.', 'Gemini did not recover from a transient server error.');
        $assert($calls === 3 && $delays === [250000, 500000], 'Gemini server-error retries were not bounded with exponential backoff.');
    }

    $calls = 0;
    $delays = [];
    $longRetryService = new GeminiWritingService(
        static function () use (&$calls): array {
            $calls++;
            return ['status' => 429, 'body' => '{}', 'headers' => ['retry-after' => '60']];
        },
        static function (int $delay) use (&$delays): void {
            $delays[] = $delay;
        }
    );
    $expectFailure(static fn () => $longRetryService->generate('Write.', 'Input.'), 'rate limit or quota');
    $assert($calls === 1 && $delays === [], 'Gemini retried a 429 rate-limit response.');

    putenv('GEMINI_MODEL=invalid/model');
    $expectFailure(static fn () => $service->generate('Write.', 'Input.'), 'model configuration is invalid');
    putenv('GEMINI_MODEL');
    putenv('GEMINI_TIMEOUT=2');
    $expectFailure(static fn () => $service->generate('Write.', 'Input.'), 'timeout must be between');
    putenv('GEMINI_TIMEOUT=15');
    putenv('GEMINI_API_KEY');
    $expectFailure(static fn () => $service->generate('Write.', 'Input.'), 'GEMINI_API_KEY');
} finally {
    foreach ($previousValues as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

echo "Gemini request, response, error, and retry checks passed.\n";
