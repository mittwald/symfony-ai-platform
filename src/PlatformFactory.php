<?php

namespace Mittwald\Symfony\AI\Platform\Bridge;

use Symfony\AI\Platform\Platform;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @deprecated since 0.2, use {@see Factory::createPlatform()} instead.
 *             Symfony AI renamed `PlatformFactory` to `Factory` in bridge
 *             release 0.8; this class is kept as a thin alias for BC.
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalog $modelCatalog = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): Platform {
        $platform = Factory::createPlatform($apiKey, $httpClient, $modelCatalog, $dispatcher);
        \assert($platform instanceof Platform);

        return $platform;
    }
}
