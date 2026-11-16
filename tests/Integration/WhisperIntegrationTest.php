<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Result\TextResult;

#[Group('integration')]
final class WhisperIntegrationTest extends AbstractIntegrationTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function speechToTextModelProvider(): iterable
    {
        yield 'whisper-large-v3-turbo' => ['whisper-large-v3-turbo'];
        yield 'cohere-transcribe-03-2026' => ['cohere-transcribe-03-2026'];
    }

    /**
     * The fixture is a short synthesized tone, not speech, so this only
     * verifies the request/response round-trip (multipart upload, auth,
     * response parsing) rather than transcription accuracy. Both speech-to-text
     * models serve /v1/audio/transcriptions; for the tone fixture cohere
     * transcribe returns an empty transcript, which is still a valid TextResult.
     */
    #[DataProvider('speechToTextModelProvider')]
    public function testTranscribeReturnsTextResult(string $modelName): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke($modelName, __DIR__.'/fixtures/sample.wav')->getResult();

        self::assertInstanceOf(TextResult::class, $result);
    }
}
