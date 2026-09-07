<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Result\RerankingResult;

#[Group('integration')]
final class RerankingIntegrationTest extends AbstractIntegrationTestCase
{
    public function testRerankReturnsAScoredEntryPerDocument(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke('Qwen3-VL-Reranker-2B', [
            'query' => 'What is the capital of France?',
            'documents' => [
                'Bananas are a good source of potassium.',
                'Paris is the capital of France.',
            ],
        ])->getResult();

        self::assertInstanceOf(RerankingResult::class, $result);

        $entries = $result->getContent();
        self::assertCount(2, $entries);

        $indices = array_map(static fn ($entry) => $entry->getIndex(), $entries);
        sort($indices);
        self::assertSame([0, 1], $indices);

        $scoresByIndex = [];
        foreach ($entries as $entry) {
            self::assertGreaterThanOrEqual(0.0, $entry->getScore());
            self::assertLessThanOrEqual(1.0, $entry->getScore());
            $scoresByIndex[$entry->getIndex()] = $entry->getScore();
        }

        // Document 1 ("Paris is the capital of France.") clearly answers the
        // query and should be scored above the unrelated document 0.
        self::assertGreaterThan($scoresByIndex[0], $scoresByIndex[1]);
    }
}
