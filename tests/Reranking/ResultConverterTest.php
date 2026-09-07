<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Reranking;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsRerankModelOnly(): void
    {
        $converter = new ResultConverter();

        self::assertTrue($converter->supports(new RerankModel('Qwen3-VL-Reranker-2B')));
        self::assertFalse($converter->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testConvertReturnsRerankingResult(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'results' => [
                ['index' => 1, 'relevance_score' => 0.9],
                ['index' => 0, 'relevance_score' => 0.3],
            ],
        ]));

        self::assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        self::assertCount(2, $entries);
        self::assertSame(1, $entries[0]->getIndex());
        self::assertSame(0.9, $entries[0]->getScore());
        self::assertSame(0, $entries[1]->getIndex());
        self::assertSame(0.3, $entries[1]->getScore());
    }

    public function testConvertThrowsWhenResultsKeyIsMissing(): void
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);

        $converter->convert($this->rawResult(['unexpected' => 'shape']));
    }

    /**
     * @return iterable<string, array{0: int, 1: class-string}>
     */
    public static function errorStatusCodeProvider(): iterable
    {
        yield '401 unauthorized' => [401, AuthenticationException::class];
        yield '429 rate limited' => [429, RateLimitExceededException::class];
        yield '400 bad request' => [400, BadRequestException::class];
        yield '500 server error' => [500, BadRequestException::class];
    }

    #[DataProvider('errorStatusCodeProvider')]
    public function testConvertThrowsExpectedExceptionForErrorStatusCodes(int $statusCode, string $expectedException): void
    {
        $converter = new ResultConverter();

        $this->expectException($expectedException);

        $converter->convert($this->rawResult(['error' => ['message' => 'oops']], $statusCode));
    }

    public function testGetTokenUsageExtractorReturnsNull(): void
    {
        $converter = new ResultConverter();

        self::assertNull($converter->getTokenUsageExtractor());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse(
            json_encode($data, \JSON_THROW_ON_ERROR),
            ['http_code' => $statusCode],
        ));

        return new RawHttpResult($httpClient->request('POST', '/v1/rerank'));
    }
}
