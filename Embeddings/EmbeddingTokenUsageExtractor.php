<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Embeddings;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final class EmbeddingTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        $data = $rawResult->getData();
        $usage = $data['usage'] ?? null;

        if (null === $usage) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $usage['prompt_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null,
        );
    }
}
