<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Result\TextResult;

#[Group('integration')]
final class WhisperIntegrationTest extends AbstractIntegrationTestCase
{
    /**
     * The fixture is a short synthesized tone, not speech, so this only
     * verifies the request/response round-trip (multipart upload, auth,
     * response parsing) rather than transcription accuracy.
     */
    #[DataProvider('transcriptionModelsProvider')]
    public function testTranscribeReturnsTextResult(string $modelName): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke($modelName, __DIR__.'/fixtures/sample.wav')->getResult();

        self::assertInstanceOf(TextResult::class, $result);
    }

    /**
     * @return iterable<string, string[]>
     */
    public static function transcriptionModelsProvider(): iterable
    {
        yield 'whisper-large-v3-turbo' => ['whisper-large-v3-turbo'];
        yield 'cohere-transcribe-03-2026' => ['cohere-transcribe-03-2026'];
    }
}
