<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Chat;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\TokenUsageExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TokenUsageExtractorTest extends TestCase
{
    public function testExtractReturnsTokenUsageFromUsageData(): void
    {
        $extractor = new TokenUsageExtractor();

        $usage = $extractor->extract($this->rawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]));

        self::assertInstanceOf(TokenUsage::class, $usage);
        self::assertSame(10, $usage->getPromptTokens());
        self::assertSame(20, $usage->getCompletionTokens());
        self::assertSame(30, $usage->getTotalTokens());
    }

    public function testExtractReturnsNullWhenUsageIsMissing(): void
    {
        $extractor = new TokenUsageExtractor();

        self::assertNull($extractor->extract($this->rawResult(['choices' => []])));
    }

    public function testExtractReturnsNullWhenStreamOptionIsSet(): void
    {
        $extractor = new TokenUsageExtractor();

        $usage = $extractor->extract(
            $this->rawResult(['usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30]]),
            ['stream' => true],
        );

        self::assertNull($usage);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR)));

        return new RawHttpResult($httpClient->request('POST', '/v1/chat/completions'));
    }
}
