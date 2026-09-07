<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Chat;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\ResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Chat\TokenUsageExtractor;
use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsChatModelOnly(): void
    {
        $converter = new ResultConverter();

        self::assertTrue($converter->supports(new ChatModel('gpt-oss-120b')));
        self::assertFalse($converter->supports(new EmbeddingModel('Qwen3-Embedding-8B')));
    }

    public function testConvertReturnsTextResultForPlainMessageContent(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'choices' => [
                ['message' => ['content' => 'Hello there!']],
            ],
        ]));

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('Hello there!', $result->getContent());
    }

    public function testConvertReturnsEmptyTextResultWhenContentIsMissing(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult(['choices' => [['message' => []]]]));

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('', $result->getContent());
    }

    public function testConvertReturnsToolCallResultWhenToolCallsArePresent(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'choices' => [
                ['message' => ['tool_calls' => [
                    [
                        'id' => 'call_1',
                        'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Berlin"}'],
                    ],
                ]]],
            ],
        ]));

        self::assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        self::assertCount(1, $toolCalls);
        self::assertSame('call_1', $toolCalls[0]->getId());
        self::assertSame('get_weather', $toolCalls[0]->getName());
        self::assertSame(['city' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testConvertDecodesToolCallArgumentsGivenAsArray(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'choices' => [
                ['message' => ['tool_calls' => [
                    [
                        'id' => 'call_1',
                        'function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Berlin']],
                    ],
                ]]],
            ],
        ]));

        self::assertInstanceOf(ToolCallResult::class, $result);
        self::assertSame(['city' => 'Berlin'], $result->getContent()[0]->getArguments());
    }

    public function testConvertReturnsStreamResultWhenStreamOptionIsSet(): void
    {
        $converter = new ResultConverter();

        $rawResult = new class implements RawResultInterface {
            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                yield ['choices' => [['delta' => ['content' => 'Hel']]]];
                yield ['choices' => [['delta' => ['content' => 'lo']]]];
                yield ['choices' => [['delta' => []]]];
            }

            public function getObject(): object
            {
                return new \stdClass();
            }
        };

        $result = $converter->convert($rawResult, ['stream' => true]);

        self::assertInstanceOf(StreamResult::class, $result);
        $deltas = iterator_to_array($result->getContent());
        self::assertContainsOnlyInstancesOf(TextDelta::class, $deltas);
        self::assertSame(['Hel', 'lo'], array_map(strval(...), $deltas));
    }

    /**
     * @return iterable<string, array{0: int, 1: class-string}>
     */
    public static function errorStatusCodeProvider(): iterable
    {
        yield '401 unauthorized' => [401, AuthenticationException::class];
        yield '429 rate limited' => [429, RateLimitExceededException::class];
        yield '400 bad request' => [400, BadRequestException::class];
        yield '500 server error' => [500, ServerException::class];
    }

    #[DataProvider('errorStatusCodeProvider')]
    public function testConvertThrowsExpectedExceptionForErrorStatusCodes(int $statusCode, string $expectedException): void
    {
        $converter = new ResultConverter();

        $this->expectException($expectedException);

        $converter->convert($this->rawResult(
            ['error' => ['message' => 'something went wrong']],
            $statusCode,
        ));
    }

    public function testGetTokenUsageExtractorReturnsTokenUsageExtractor(): void
    {
        $converter = new ResultConverter();

        self::assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
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

        return new RawHttpResult($httpClient->request('POST', '/v1/chat/completions'));
    }
}
