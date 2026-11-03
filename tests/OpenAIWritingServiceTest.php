<?php
declare(strict_types=1);

require __DIR__ . '/../app/services/OpenAIWritingService.php';

$previousValues = [];
foreach (['OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_TIMEOUT'] as $name) {
    $previousValues[$name] = getenv($name);
}
putenv('OPENAI_API_KEY=test-key-not-for-network-use');
putenv('OPENAI_MODEL=gpt-4.1-mini');
putenv('OPENAI_TIMEOUT=15');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $messagePart) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), $messagePart), 'Unexpected service failure.');
        return;
    }
    throw new RuntimeException('An invalid AI response was accepted.');
};

try {
    $captured = [];
    $service = new OpenAIWritingService(static function (string $url, array $headers, string $body, int $timeout) use (&$captured): array {
        $captured = compact('url', 'headers', 'body', 'timeout');
        return ['status' => 200, 'body' => '{"output_text":"A clear, factual draft."}'];
    });
    $text = $service->generate('Write accurately.', '{"title":"Intern"}');
    $assert($text === 'A clear, factual draft.', 'The response text was not extracted.');
    $assert($captured['url'] === 'https://api.openai.com/v1/responses', 'The Responses API endpoint was incorrect.');
    $assert(in_array('Authorization: Bearer test-key-not-for-network-use', $captured['headers'], true), 'The server-side authorization header was missing.');
    $request = json_decode($captured['body'], true, 512, JSON_THROW_ON_ERROR);
    $assert($request['model'] === 'gpt-4.1-mini' && $request['instructions'] === 'Write accurately.' && $request['input'] === '{"title":"Intern"}', 'The provider request payload was incorrect.');
    $assert($captured['timeout'] === 15, 'The configured timeout was not applied.');

    $alternate = new OpenAIWritingService(static fn (): array => [
        'status' => 200,
        'body' => '{"output":[{"content":[{"type":"output_text","text":"First paragraph."}]},{"content":[{"type":"output_text","text":"Second paragraph."}]}]}',
    ]);
    $assert($alternate->generate('Write.', 'Input.') === "First paragraph.\nSecond paragraph.", 'Output content segments were not combined.');

    $expectFailure(
        static fn () => (new OpenAIWritingService(static fn (): array => ['status' => 200, 'body' => '{bad json']))->generate('Write.', 'Input.'),
        'unreadable response'
    );
    $expectFailure(
        static fn () => (new OpenAIWritingService(static fn (): array => ['status' => 200, 'body' => '{"output_text":"  "}']))->generate('Write.', 'Input.'),
        'usable text'
    );
    $expectFailure(
        static fn () => (new OpenAIWritingService(static fn (): array => ['status' => 401, 'body' => 'sensitive provider response']))->generate('Write.', 'Input.'),
        'rejected its server configuration'
    );

    putenv('OPENAI_API_KEY');
    $expectFailure(
        static fn () => (new OpenAIWritingService(static fn (): array => throw new RuntimeException('Transport should not run.')))->generate('Write.', 'Input.'),
        'not configured'
    );
} finally {
    foreach ($previousValues as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

echo "AI writing service request and response checks passed.\n";
