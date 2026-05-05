<?php

namespace Mittwald\Symfony\AI\Platform\Bridge;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\ModelClient as ChatModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Chat\ResultConverter as ChatResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ModelClient as EmbeddingsModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ModelClient as WhisperModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ResultConverter as WhisperResultConverter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalog $modelCatalog = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): Platform {
        $httpClient = self::configureHttpClient($httpClient ?? HttpClient::create(), $apiKey);
        $modelCatalog ??= new ModelCatalog();

        $modelClients = [
            new ChatModelClient($httpClient),
            new EmbeddingsModelClient($httpClient),
            new WhisperModelClient($httpClient),
        ];

        $resultConverters = [
            new ChatResultConverter(),
            new EmbeddingsResultConverter(),
            new WhisperResultConverter(),
        ];

        return new Platform(
            $modelClients,
            $resultConverters,
            $modelCatalog,
            Contract::create(),
            $dispatcher,
        );
    }

    private static function configureHttpClient(HttpClientInterface $httpClient, string $apiKey): HttpClientInterface
    {
        return $httpClient->withOptions([
            'base_uri' => 'https://llm.aihosting.mittwald.de',
            'auth_bearer' => $apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
