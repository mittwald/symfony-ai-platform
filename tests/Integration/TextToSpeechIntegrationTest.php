<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Result\BinaryResult;

#[Group('integration')]
final class TextToSpeechIntegrationTest extends AbstractIntegrationTestCase
{
    public function testTextToSpeechReturnsAudioBytes(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke(
            'Qwen3-TTS-12Hz-1.7B-CustomVoice',
            'Hello and welcome!',
            ['voice' => 'ryan'],
        )->getResult();

        self::assertInstanceOf(BinaryResult::class, $result);
        self::assertGreaterThan(0, \strlen($result->getContent()));
    }
}
