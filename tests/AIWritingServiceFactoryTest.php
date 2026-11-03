<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$previousValues = [];
foreach (['AI_PROVIDER', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_TIMEOUT', 'OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_TIMEOUT'] as $name) {
    $previousValues[$name] = getenv($name);
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $messagePart) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), $messagePart), 'Unexpected AI provider selection error.');
        return;
    }
    throw new RuntimeException('An unsupported AI provider was accepted.');
};

try {
    putenv('GEMINI_API_KEY=test-gemini-key');
    putenv('GEMINI_MODEL=gemini-3.5-flash-lite');
    putenv('GEMINI_TIMEOUT=15');
    putenv('OPENAI_API_KEY=test-openai-key');
    putenv('OPENAI_MODEL=gpt-4.1-mini');
    putenv('OPENAI_TIMEOUT=15');
    putenv('AI_PROVIDER=gemini');
    $seenUrls = [];
    $transport = static function (string $url, array $headers, string $body, int $timeout) use (&$seenUrls): array {
        $seenUrls[] = $url;
        if (str_contains($url, 'generativelanguage.googleapis.com')) {
            return ['status' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":"Gemini draft."}]}}]}'];
        }
        return ['status' => 200, 'body' => '{"output_text":"OpenAI draft."}'];
    };

    $gemini = AIWritingServiceFactory::create($transport, static function (): void {});
    $assert($gemini instanceof GeminiWritingService, 'AI_PROVIDER=gemini did not select Gemini.');
    $assert($gemini->generate('Write.', 'Context.') === 'Gemini draft.', 'Gemini selection did not generate with its provider.');

    $limitRequests = [];
    $limitedGemini = AIWritingServiceFactory::create(
        static function (string $url) use (&$limitRequests): array {
            $limitRequests[] = $url;
            return ['status' => 429, 'body' => '{"error":"quota"}'];
        },
        static function (): void {}
    );
    try {
        $limitedGemini->generate('Write.', 'Context.');
        throw new RuntimeException('Gemini quota errors unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'rate limit or quota'), 'Gemini quota failure was not reported accurately.');
    }
    $assert(count($limitRequests) === 1 && !array_filter($limitRequests, static fn (string $url): bool => str_contains($url, 'api.openai.com')), 'Gemini quota errors triggered an OpenAI fallback or retry.');

    putenv('AI_PROVIDER=openai');
    $openai = AIWritingServiceFactory::create($transport);
    $assert($openai instanceof OpenAIWritingService, 'AI_PROVIDER=openai did not select OpenAI.');
    $assert($openai->generate('Write.', 'Context.') === 'OpenAI draft.', 'OpenAI selection did not generate with its provider.');

    putenv('AI_PROVIDER');
    $defaultGemini = AIWritingServiceFactory::create($transport, static function (): void {});
    $assert($defaultGemini instanceof GeminiWritingService, 'Configured Gemini was not preferred by default.');

    putenv('GEMINI_API_KEY');
    $legacyOpenAI = AIWritingServiceFactory::create($transport);
    $assert($legacyOpenAI instanceof OpenAIWritingService, 'An existing OpenAI-only configuration was not preserved.');

    putenv('OPENAI_API_KEY');
    $unconfiguredDefault = AIWritingServiceFactory::create($transport, static function (): void {});
    $assert($unconfiguredDefault instanceof GeminiWritingService, 'Gemini was not the default provider when no keys were configured.');

    putenv('AI_PROVIDER=gemini');
    putenv('OPENAI_API_KEY=test-openai-key');
    $explicitGemini = AIWritingServiceFactory::create(static function (): array {
        throw new RuntimeException('A provider request should not be made without Gemini configuration.');
    }, static function (): void {});
    $expectFailure(static fn () => $explicitGemini->generate('Write.', 'Context.'), 'GEMINI_API_KEY');

    putenv('AI_PROVIDER=unsupported-provider');
    $expectFailure(static fn () => AIWritingServiceFactory::create(), 'AI_PROVIDER must be set');
    $assert(count($seenUrls) === 2, 'The selected provider made unexpected external requests.');
} finally {
    foreach ($previousValues as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

echo "AI provider selection checks passed.\n";
