<?php
declare(strict_types=1);

final class AIWritingServiceFactory
{
    public static function create(?callable $transport = null, ?callable $sleeper = null): AIWritingServiceInterface
    {
        $configuredProvider = strtolower(trim((string) getenv('AI_PROVIDER')));
        if ($configuredProvider === '') {
            if (trim((string) getenv('GEMINI_API_KEY')) !== '') {
                $configuredProvider = 'gemini';
            } elseif (trim((string) getenv('OPENAI_API_KEY')) !== '') {
                $configuredProvider = 'openai';
            } else {
                $configuredProvider = 'gemini';
            }
        }

        return match ($configuredProvider) {
            'gemini' => new GeminiWritingService($transport, $sleeper),
            'openai' => new OpenAIWritingService($transport),
            default => throw new RuntimeException('AI_PROVIDER must be set to gemini or openai.'),
        };
    }
}
