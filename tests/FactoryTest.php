<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Factory;
use Mittwald\Symfony\AI\Platform\Bridge\ModelCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouter\RoutingDecision;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FactoryTest extends TestCase
{
    public function testCreateProviderReturnsProviderInterface(): void
    {
        $provider = Factory::createProvider('test-api-key');

        self::assertInstanceOf(ProviderInterface::class, $provider);
        self::assertSame('mittwald', $provider->getName());
    }

    public function testCreatePlatformReturnsPlatformInterface(): void
    {
        self::assertInstanceOf(PlatformInterface::class, Factory::createPlatform('test-api-key'));
    }

    public function testCreateProviderSupportsCatalogModels(): void
    {
        $provider = Factory::createProvider('test-api-key');

        self::assertTrue($provider->supports('gpt-oss-120b'));
    }

    public function testCreateProviderConfiguresHttpClientWithBaseUriAndBearerToken(): void
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

        $provider = Factory::createProvider('test-api-key', $httpClient);

        $result = $provider->invoke('gpt-oss-120b', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->getResult();

        self::assertNotNull($captured);
        [$method, $url, $options] = $captured;

        self::assertSame('POST', $method);
        self::assertSame('https://llm.aihosting.mittwald.de/v1/chat/completions', $url);
        self::assertContains('Authorization: Bearer test-api-key', $options['headers']);

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('Hello!', $result->getContent());
    }

    public function testCreateProviderUsesProvidedModelCatalog(): void
    {
        $catalog = new ModelCatalog([
            'my-custom-model' => [
                'class' => ChatModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
            ],
        ]);

        $provider = Factory::createProvider('test-api-key', modelCatalog: $catalog);

        $model = $provider->getModelCatalog()->getModel('my-custom-model');

        self::assertInstanceOf(ChatModel::class, $model);
        self::assertSame('my-custom-model', $model->getName());
    }

    public function testCreateProviderUsesProvidedNameAndBaseUrl(): void
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

        $provider = Factory::createProvider(
            'test-api-key',
            $httpClient,
            name: 'custom-provider',
            baseUrl: 'https://custom.example.com',
        );

        self::assertSame('custom-provider', $provider->getName());

        $provider->invoke('gpt-oss-120b', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->getResult();

        self::assertNotNull($captured);
        [, $url] = $captured;
        self::assertSame('https://custom.example.com/v1/chat/completions', $url);
    }

    public function testCreatePlatformUsesProvidedModelRouter(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => 'Hello!']],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $modelRouter = new class implements ModelRouterInterface {
            public bool $wasCalled = false;

            public function resolve(string|Model $model, iterable $providers, array|string|object $input, array $options = []): RoutingDecision
            {
                $this->wasCalled = true;

                return (new CatalogBasedModelRouter())->resolve($model, $providers, $input, $options);
            }
        };

        $platform = Factory::createPlatform('test-api-key', $httpClient, modelRouter: $modelRouter);
        $platform->invoke('gpt-oss-120b', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->getResult();

        self::assertTrue($modelRouter->wasCalled);
    }
}
