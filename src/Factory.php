<?php

namespace Mittwald\Symfony\AI\Platform\Bridge;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\ModelClient as ChatModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Chat\ResultConverter as ChatResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ModelClient as EmbeddingsModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ModelClient as RerankingModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ResultConverter as RerankingResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ModelClient as TextToSpeechModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ResultConverter as TextToSpeechResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ModelClient as WhisperModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ResultConverter as WhisperResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Entry point for the mittwald AI Hosting bridge.
 *
 * Follows the bridge factory convention Symfony AI settled on in bridge
 * release 0.8: a class named `Factory` exposing an explicit `createProvider()`
 * (one inference backend) and `createPlatform()` (a platform wrapping it).
 * Consumers that discover bridges by that convention — e.g. TYPO3's `b13/aim`
 * extension — need both the class name and `createProvider()` to be present.
 */
final class Factory
{
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalog $modelCatalog = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?Contract $contract = null,
        string $name = 'mittwald',
        string $baseUrl = 'https://llm.aihosting.mittwald.de',
    ): ProviderInterface {
        $httpClient = self::configureHttpClient($httpClient ?? HttpClient::create(), $apiKey, $baseUrl);
        $modelCatalog ??= new ModelCatalog();

        $modelClients = [
            new ChatModelClient($httpClient),
            new EmbeddingsModelClient($httpClient),
            new WhisperModelClient($httpClient),
            new RerankingModelClient($httpClient),
            new TextToSpeechModelClient($httpClient),
        ];

        $resultConverters = [
            new ChatResultConverter(),
            new EmbeddingsResultConverter(),
            new WhisperResultConverter(),
            new RerankingResultConverter(),
            new TextToSpeechResultConverter(),
        ];

        return new Provider(
            $name,
            $modelClients,
            $resultConverters,
            $modelCatalog,
            $contract ?? Contract::create(),
            $dispatcher,
        );
    }

    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalog $modelCatalog = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?Contract $contract = null,
        string $name = 'mittwald',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://llm.aihosting.mittwald.de',
    ): PlatformInterface {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $dispatcher, $contract, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $dispatcher,
        );
    }

    private static function configureHttpClient(HttpClientInterface $httpClient, string $apiKey, string $baseUrl): HttpClientInterface
    {
        return $httpClient->withOptions([
            'base_uri' => $baseUrl,
            'auth_bearer' => $apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
