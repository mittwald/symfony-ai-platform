<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\ModelCatalog;
use Mittwald\Symfony\AI\Platform\Bridge\PlatformFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PlatformFactoryTest extends TestCase
{
    public function testCreateReturnsPlatform(): void
    {
        $platform = PlatformFactory::create('test-api-key');

        self::assertInstanceOf(Platform::class, $platform);
    }

    public function testCreateConfiguresHttpClientWithBaseUriAndBearerToken(): void
    {
        /** @var array{0: string, 1: string, 2: array<string, mixed>}|null $captured */
        $captured = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = [$method, $url, $options];

                return new MockResponse(json_encode([
                    'choices' => [
                        ['message' => ['content' => 'Hello!']],
                    ],
                ], \JSON_THROW_ON_ERROR));
            },
        );

        $platform = PlatformFactory::create('test-api-key', $httpClient);

        $result = $platform->invoke('gpt-oss-120b', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->getResult();

        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/chat/completions', $url);
        self::assertContains('Authorization: Bearer test-api-key', $options['headers']);
        $contentTypeHeaders = array_filter(
            $options['headers'],
            static fn (string $header): bool => str_starts_with($header, 'Content-Type: application/json'),
        );
        self::assertNotEmpty($contentTypeHeaders);

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('Hello!', $result->getContent());
    }

    public function testCreateUsesDefaultModelCatalogWhenNoneIsGiven(): void
    {
        $platform = PlatformFactory::create('test-api-key');

        $model = $platform->getModelCatalog()->getModel('gpt-oss-120b');

        self::assertInstanceOf(ChatModel::class, $model);
    }

    public function testCreateUsesProvidedModelCatalog(): void
    {
        $catalog = new ModelCatalog([
            'my-custom-model' => [
                'class' => ChatModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
            ],
        ]);

        $platform = PlatformFactory::create('test-api-key', modelCatalog: $catalog);

        $model = $platform->getModelCatalog()->getModel('my-custom-model');

        self::assertInstanceOf(ChatModel::class, $model);
        self::assertSame('my-custom-model', $model->getName());
    }
}
