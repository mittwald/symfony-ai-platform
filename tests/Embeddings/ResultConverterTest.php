<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\EmbeddingTokenUsageExtractor;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsEmbeddingModelOnly(): void
    {
        $converter = new ResultConverter();

        self::assertTrue($converter->supports(new EmbeddingModel('Qwen3-Embedding-8B')));
        self::assertFalse($converter->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testConvertReturnsVectorResult(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3]],
                ['embedding' => [0.4, 0.5, 0.6]],
            ],
        ]));

        self::assertInstanceOf(VectorResult::class, $result);
        $vectors = $result->getContent();
        self::assertCount(2, $vectors);
        self::assertSame([0.1, 0.2, 0.3], $vectors[0]->getData());
        self::assertSame([0.4, 0.5, 0.6], $vectors[1]->getData());
    }

    public function testConvertReturnsEmptyVectorResultWhenDataIsMissing(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([]));

        self::assertInstanceOf(VectorResult::class, $result);
        self::assertSame([], $result->getContent());
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

    public function testGetTokenUsageExtractorReturnsEmbeddingTokenUsageExtractor(): void
    {
        $converter = new ResultConverter();

        self::assertInstanceOf(EmbeddingTokenUsageExtractor::class, $converter->getTokenUsageExtractor());
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

        return new RawHttpResult($httpClient->request('POST', '/v1/embeddings'));
    }
}
