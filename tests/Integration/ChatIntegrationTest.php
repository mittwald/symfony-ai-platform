<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;

#[Group('integration')]
final class ChatIntegrationTest extends AbstractIntegrationTestCase
{
    public function testChatCompletionReturnsText(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke(
            'gpt-oss-120b',
            new MessageBag(Message::ofUser('Reply with exactly one word: pong')),
            // gpt-oss-120b is a reasoning model: a low budget can be entirely
            // consumed by hidden reasoning tokens before any visible content.
            ['max_tokens' => 200],
        )->getResult();

        self::assertInstanceOf(TextResult::class, $result);
        self::assertNotSame('', trim($result->getContent()));
    }

    public function testStreamingChatCompletionYieldsTextDeltas(): void
    {
        $platform = $this->createPlatform();

        $deferred = $platform->invoke(
            'gpt-oss-120b',
            new MessageBag(Message::ofUser('Count from one to three.')),
            ['stream' => true, 'max_tokens' => 200],
        );

        $chunks = [];
        foreach ($deferred->asStream() as $delta) {
            self::assertInstanceOf(TextDelta::class, $delta);
            $chunks[] = (string) $delta;
        }

        self::assertNotEmpty($chunks);
        self::assertNotSame('', trim(implode('', $chunks)));
    }
}
