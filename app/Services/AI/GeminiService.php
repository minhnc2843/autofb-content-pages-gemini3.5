<?php

namespace App\Services\AI;

use RuntimeException;

class GeminiService implements AIProviderInterface
{
    public function generateText(string $prompt): array
    {
        throw new RuntimeException('Gemini is disabled in Phase 1.');
    }

    public function analyzeMedia(array $mediaData, string $prompt): array
    {
        throw new RuntimeException('Gemini is disabled in Phase 1.');
    }

    public function scorePost(array $postData): array
    {
        return ['score' => 0, 'suggestions' => ['Gemini mock result. Enable in Phase 4.']];
    }

    public function auditPage(array $pageData): array
    {
        return ['page_score' => 0, 'suggestions' => ['Gemini mock result. Enable in Phase 4.']];
    }
}
