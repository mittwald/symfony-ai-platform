<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\TextToSpeech;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testSupportsTextToSpeechModelOnly(): void
    {
        $client = new ModelClient(new MockHttpClient());

        self::assertTrue($client->supports(new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice')));
        self::assertFalse($client->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testRequestThrowsWhenVoiceOptionIsMissing(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for TextToSpeech requests.');

        $client->request(new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'), 'Hello!');
    }

    public function testRequestThrowsWhenStreamOptionIsSet(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $client->request(new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'), 'Hello!', ['voice' => 'ryan', 'stream' => true]);
    }

    public function testRequestThrowsWhenStreamFormatOptionIsSet(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $client->request(
            new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'),
            'Hello!',
            ['voice' => 'ryan', 'stream_format' => 'sse'],
        );
    }

    public function testRequestThrowsWhenArrayPayloadHasNoTextKey(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');

        $client->request(new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'), [], ['voice' => 'ryan']);
    }

    public function testRequestSendsStringPayloadAsInput(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse('binary-audio-data');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $result = $client->request(
            new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'),
            'Hello and welcome!',
            ['voice' => 'ryan'],
        );

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/audio/speech', $url);

        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Qwen3-TTS-12Hz-1.7B-CustomVoice', $body['model']);
        self::assertSame('Hello and welcome!', $body['input']);
        self::assertSame('ryan', $body['voice']);
    }

    public function testRequestSendsArrayPayloadTextKeyAsInput(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse('binary-audio-data');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $client->request(
            new TextToSpeechModel('Qwen3-TTS-12Hz-1.7B-CustomVoice'),
            ['text' => 'Hi there'],
            ['voice' => 'ryan', 'speed' => 1.2],
        );

        self::assertNotNull($captured);
        [, , $options] = $captured;
        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Hi there', $body['input']);
        self::assertSame(1.2, $body['speed']);
    }
}
