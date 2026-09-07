<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Result\VectorResult;

#[Group('integration')]
final class EmbeddingsIntegrationTest extends AbstractIntegrationTestCase
{
    public function testEmbedReturnsNonEmptyVector(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke('Qwen3-Embedding-8B', 'The quick brown fox jumps over the lazy dog.')->getResult();

        self::assertInstanceOf(VectorResult::class, $result);

        $vectors = $result->getContent();
        self::assertNotEmpty($vectors);
        self::assertNotEmpty($vectors[0]->getData());
    }
}
