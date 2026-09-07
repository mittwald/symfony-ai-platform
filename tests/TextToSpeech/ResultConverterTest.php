<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\TextToSpeech;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsTextToSpeechModelOnly(): void
    {
        $converter = new ResultConverter();

        self::assertTrue($converter->supports(new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice')));
        self::assertFalse($converter->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testConvertReturnsBinaryResultWithMimeType(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult('binary-audio-data', ['content-type' => 'audio/mpeg']));

        self::assertInstanceOf(BinaryResult::class, $result);
        self::assertSame('binary-audio-data', $result->getContent());
        self::assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testConvertReturnsBinaryResultWithNullMimeTypeWhenHeaderIsMissing(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult('binary-audio-data'));

        self::assertInstanceOf(BinaryResult::class, $result);
        self::assertNull($result->getMimeType());
    }

    public function testConvertThrowsWhenRawResultObjectIsNotAResponse(): void
    {
        $converter = new ResultConverter();

        $rawResult = new class implements RawResultInterface {
            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                return [];
            }

            public function getObject(): object
            {
                return new \stdClass();
            }
        };

        $this->expectException(RuntimeException::class);

        $converter->convert($rawResult);
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

        $rawResult = $this->rawResult(
            json_encode(['error' => ['message' => 'oops']], \JSON_THROW_ON_ERROR),
            [],
            $statusCode,
        );

        $this->expectException($expectedException);

        $converter->convert($rawResult);
    }

    public function testGetTokenUsageExtractorReturnsNull(): void
    {
        $converter = new ResultConverter();

        self::assertNull($converter->getTokenUsageExtractor());
    }

    /**
     * @param array<string, string> $responseHeaders
     */
    private function rawResult(string $body, array $responseHeaders = [], int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse($body, [
            'http_code' => $statusCode,
            'response_headers' => $responseHeaders,
        ]));

        return new RawHttpResult($httpClient->request('POST', '/v1/audio/speech'));
    }
}
