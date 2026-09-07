<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Reranking;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ModelClientTest extends TestCase
{
    public function testSupportsRerankModelOnly(): void
    {
        $client = new ModelClient(new MockHttpClient());

        self::assertTrue($client->supports(new RerankModel('Qwen3-VL-Reranker-2B')));
        self::assertFalse($client->supports(new ChatModel('gpt-oss-120b')));
    }

    public function testRequestThrowsWhenPayloadIsNotAnArray(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $client->request(new RerankModel('Qwen3-VL-Reranker-2B'), 'not-an-array');
    }

    public function testRequestThrowsWhenQueryOrDocumentsAreMissing(): void
    {
        $client = new ModelClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $client->request(new RerankModel('Qwen3-VL-Reranker-2B'), ['query' => 'What is the capital of France?']);
    }

    public function testRequestSendsQueryAndDocuments(): void
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

        $result = $client->request(new RerankModel('Qwen3-VL-Reranker-2B'), [
            'query' => 'What is the capital of France?',
            'documents' => ['Paris is the capital of France.', 'Berlin is the capital of Germany.'],
        ]);

        self::assertInstanceOf(RawHttpResult::class, $result);
        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/rerank', $url);

        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Qwen3-VL-Reranker-2B', $body['model']);
        self::assertSame('What is the capital of France?', $body['query']);
        self::assertSame(['Paris is the capital of France.', 'Berlin is the capital of Germany.'], $body['documents']);
        self::assertArrayNotHasKey('instruction', $body);
    }

    public function testRequestForwardsInstructionOptionWhenProvided(): void
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
            new RerankModel('Qwen3-VL-Reranker-2B'),
            ['query' => 'q', 'documents' => ['d1']],
            ['instruction' => 'Rank by relevance.'],
        );

        self::assertNotNull($captured);
        [, , $options] = $captured;
        $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Rank by relevance.', $body['instruction']);
    }
}
