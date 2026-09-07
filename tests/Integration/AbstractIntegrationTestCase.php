<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use Mittwald\Symfony\AI\Platform\Bridge\PlatformFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Platform;

/**
 * Base class for tests that hit the real mittwald AI Hosting API.
 *
 * These tests are skipped unless MITTWALD_AI_API_KEY is set, so they never run
 * as part of the default "composer test" suite. Run them explicitly via
 * "composer test:integration".
 */
abstract class AbstractIntegrationTestCase extends TestCase
{
    protected function createPlatform(): Platform
    {
        $apiKey = getenv('MITTWALD_AI_API_KEY');

        if (false === $apiKey || '' === $apiKey) {
            self::markTestSkipped('Set the MITTWALD_AI_API_KEY environment variable to run integration tests against the live mittwald AI Hosting API.');
        }

        return PlatformFactory::create($apiKey);
    }
}
