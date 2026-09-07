<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\EmbeddingTokenUsageExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EmbeddingTokenUsageExtractorTest extends TestCase
{
    public function testExtractReturnsTokenUsageFromUsageData(): void
    {
        $extractor = new EmbeddingTokenUsageExtractor();

        $usage = $extractor->extract($this->rawResult([
            'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
        ]));

        self::assertInstanceOf(TokenUsage::class, $usage);
        self::assertSame(5, $usage->getPromptTokens());
        self::assertSame(5, $usage->getTotalTokens());
        self::assertNull($usage->getCompletionTokens());
    }

    public function testExtractReturnsNullWhenUsageIsMissing(): void
    {
        $extractor = new EmbeddingTokenUsageExtractor();

        self::assertNull($extractor->extract($this->rawResult(['data' => []])));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR)));

        return new RawHttpResult($httpClient->request('POST', '/v1/embeddings'));
    }
}
