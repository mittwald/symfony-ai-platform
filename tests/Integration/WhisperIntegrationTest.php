<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

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
    public function testTranscribeReturnsTextResult(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke('whisper-large-v3-turbo', __DIR__.'/fixtures/sample.wav')->getResult();

        self::assertInstanceOf(TextResult::class, $result);
    }
}
