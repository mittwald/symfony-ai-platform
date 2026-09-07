<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Chat;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\ModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testSupportsChatModelOnly(): void
    {
        $client = new ModelClient(new MockHttpClient());

        self::assertTrue($client->supports(new ChatModel('gpt-oss-120b')));
        self::assertFalse($client->supports(new EmbeddingModel('Qwen3-Embedding-8B')));
    }

    public function testRequestSendsPayloadAndOptionsMergedWithModelName(): void
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

        $result = $client->request(
            new ChatModel('gpt-oss-120b'),
            ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            ['temperature' => 0.5],
        );

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/chat/completions', $url);

        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('gpt-oss-120b', $body['model']);
        self::assertSame(0.5, $body['temperature']);
        self::assertSame([['role' => 'user', 'content' => 'Hi']], $body['messages']);
    }

    public function testRequestRoutesThroughEventSourceClientWhenStreamingIsRequested(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse('', ['response_headers' => ['content-type' => 'text/event-stream']]);
            },
            'https://llm.aihosting.mittwald.de',
        );

        $client = new ModelClient($httpClient);

        $result = $client->request(
            new ChatModel('gpt-oss-120b'),
            ['messages' => [['role' => 'user', 'content' => 'Hi']], 'stream' => true],
        );

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertInstanceOf(ResponseInterface::class, $result->getObject());

        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/chat/completions', $url);

        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('gpt-oss-120b', $body['model']);
        self::assertTrue($body['stream']);
    }
}
