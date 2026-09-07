<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Whisper;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->tempFile && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        $this->tempFile = null;
    }

    public function testSupportsWhisperModelOnly(): void
    {
        $client = new ModelClient(new MockHttpClient());

        self::assertTrue($client->supports(new WhisperModel('whisper-large-v3-turbo')));
        self::assertFalse($client->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testRequestSendsMultipartFormDataWithFileFromDataPart(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>, 3: string}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options, self::consumeBody($options['body'])];

                return new MockResponse('{"text": "transcribed"}');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $result = $client->request(
            new WhisperModel('whisper-large-v3-turbo'),
            ['file' => new DataPart('raw-audio-bytes', 'audio.mp3', 'audio/mpeg')],
            ['language' => 'en', 'temperature' => 0.2, 'response_format' => 'json'],
        );

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertNotNull($captured);
        [$method, $url, $options, $body] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/audio/transcriptions', $url);

        $contentTypeHeaders = array_filter(
            $options['headers'],
            static fn (string $header): bool => str_starts_with($header, 'Content-Type: multipart/form-data'),
        );
        self::assertNotEmpty($contentTypeHeaders);

        self::assertStringContainsString('name="model"', $body);
        self::assertStringContainsString('whisper-large-v3-turbo', $body);
        self::assertStringContainsString('name="file"; filename="audio.mp3"', $body);
        self::assertStringContainsString('raw-audio-bytes', $body);
        self::assertStringContainsString('name="language"', $body);
        self::assertStringContainsString('en', $body);
        self::assertStringContainsString('name="temperature"', $body);
        self::assertStringContainsString('0.2', $body);
        self::assertStringContainsString('name="response_format"', $body);
    }

    public function testRequestAcceptsFilePathAsStringPayload(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'whisper-test-');
        file_put_contents($this->tempFile, 'audio-file-contents');

        /** @var string|null $capturedBody */
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): ResponseInterface {
                $capturedBody = self::consumeBody($options['body']);

                return new MockResponse('{"text": "transcribed"}');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $client->request(new WhisperModel('whisper-large-v3-turbo'), $this->tempFile);

        self::assertNotNull($capturedBody);
        self::assertStringContainsString('audio-file-contents', $capturedBody);
        self::assertStringContainsString(basename($this->tempFile), $capturedBody);
    }

    public function testRequestAcceptsFilePathWithinArrayPayload(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'whisper-test-');
        file_put_contents($this->tempFile, 'array-payload-audio');

        /** @var string|null $capturedBody */
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): ResponseInterface {
                $capturedBody = self::consumeBody($options['body']);

                return new MockResponse('{"text": "transcribed"}');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $client->request(new WhisperModel('whisper-large-v3-turbo'), ['file' => $this->tempFile]);

        self::assertNotNull($capturedBody);
        self::assertStringContainsString('array-payload-audio', $capturedBody);
    }

    /**
     * Reads a Symfony HttpClient request "body" option (string, or the internal
     * read-callback produced from an iterable/generator body) into a plain string.
     *
     * @param mixed $body
     */
    private static function consumeBody($body): string
    {
        if (\is_string($body)) {
            return $body;
        }

        $chunks = [];
        while ('' !== ($chunk = $body(16372))) {
            $chunks[] = $chunk;
        }

        return implode('', $chunks);
    }
}
