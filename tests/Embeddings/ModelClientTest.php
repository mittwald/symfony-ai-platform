<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testSupportsEmbeddingModelOnly(): void
    {
        $client = new ModelClient(new MockHttpClient());

        self::assertTrue($client->supports(new EmbeddingModel('Qwen3-Embedding-8B')));
        self::assertFalse($client->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testRequestSendsModelAndInput(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse('{}');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $result = $client->request(new EmbeddingModel('Qwen3-Embedding-8B'), 'text to embed');

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/embeddings', $url);

        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Qwen3-Embedding-8B', $body['model']);
        self::assertSame('text to embed', $body['input']);
        self::assertArrayNotHasKey('encoding_format', $body);
    }

    public function testRequestForwardsEncodingFormatOptionWhenProvided(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse('{}');
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $client->request(
            new EmbeddingModel('Qwen3-Embedding-8B'),
            ['text one', 'text two'],
            ['encoding_format' => 'base64'],
        );

        self::assertNotNull($captured);
        [, , $options] = $captured;
        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['text one', 'text two'], $body['input']);
        self::assertSame('base64', $body['encoding_format']);
    }
}
