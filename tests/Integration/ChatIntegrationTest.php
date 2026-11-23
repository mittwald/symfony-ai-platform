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

    public function testKolibriChatCompletionReturnsText(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke(
            'Kolibri-1',
            new MessageBag(Message::ofUser('Reply with exactly one word: pong')),
            // Kolibri-1 thinks by default at the highest reasoning depth and
            // can return an empty answer if max_tokens is consumed by hidden
            // reasoning tokens, so turn thinking off and keep a small budget.
            ['max_tokens' => 1024, 'chat_template_kwargs' => ['reasoning_effort' => 'none']],
        )->getResult();

        self::assertInstanceOf(TextResult::class, $result);
        self::assertNotSame('', trim($result->getContent()));
    }

    public function testDeepSeekChatCompletionReturnsText(): void
    {
        $platform = $this->createPlatform();

        $result = $platform->invoke(
            'DeepSeek-V4.1-Flash-1017',
            new MessageBag(Message::ofUser('Reply with exactly one word: pong')),
            // DeepSeek-V4.1-Flash-1017 thinks by default; a small budget can
            // be entirely consumed by hidden reasoning tokens, so turn
            // thinking off and keep the budget small.
            ['max_tokens' => 1024, 'chat_template_kwargs' => ['reasoning_effort' => 'none']],
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
