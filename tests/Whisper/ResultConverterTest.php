<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Whisper;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsWhisperModelOnly(): void
    {
        $converter = new ResultConverter();

        self::assertTrue($converter->supports(new WhisperModel('whisper-large-v3-turbo')));
        self::assertFalse($converter->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testConvertReturnsTextResultByDefault(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult(['text' => 'transcribed audio']));

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('transcribed audio', $result->getContent());
    }

    public function testConvertReturnsEmptyTextResultWhenTextIsMissing(): void
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([]));

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('', $result->getContent());
    }

    public function testConvertReturnsObjectResultForVerboseJsonFormat(): void
    {
        $converter = new ResultConverter();

        $data = ['text' => 'transcribed', 'segments' => [['id' => 0, 'text' => 'transcribed']]];

        $result = $converter->convert($this->rawResult($data), ['response_format' => 'verbose_json']);

        self::assertInstanceOf(ObjectResult::class, $result);
        self::assertSame($data, $result->getContent());
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

        return new RawHttpResult($httpClient->request('POST', '/v1/audio/transcriptions'));
    }
}
