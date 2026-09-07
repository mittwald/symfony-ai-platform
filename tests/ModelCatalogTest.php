<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use Mittwald\Symfony\AI\Platform\Bridge\ModelCatalog;
use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;

final class ModelCatalogTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: class-string}>
     */
    public static function modelClassProvider(): iterable
    {
        yield 'gpt-oss-120b' => ['gpt-oss-120b', ChatModel::class];
        yield 'Ministral-3-14B-Instruct-2512' => ['Ministral-3-14B-Instruct-2512', ChatModel::class];
        yield 'Qwen3.5-122B-A10B-FP8' => ['Qwen3.5-122B-A10B-FP8', ChatModel::class];
        yield 'Qwen3.6-35B-A3B-FP8' => ['Qwen3.6-35B-A3B-FP8', ChatModel::class];
        yield 'Qwen3.5-0.8B' => ['Qwen3.5-0.8B', ChatModel::class];
        yield 'Qwen3.8-27B-NVFP4' => ['Qwen3.8-27B-NVFP4', ChatModel::class];
        yield 'GLM-OCR' => ['GLM-OCR', ChatModel::class];
        yield 'Qwen3-Embedding-8B' => ['Qwen3-Embedding-8B', EmbeddingModel::class];
        yield 'whisper-large-v3-turbo' => ['whisper-large-v3-turbo', WhisperModel::class];
        yield 'Qwen3-VL-Reranker-2B' => ['Qwen3-VL-Reranker-2B', RerankModel::class];
        yield 'Qwen3-TTS-12Hz-1.7B-CustomVoice' => ['Qwen3-TTS-12Hz-1.7B-CustomVoice', TextToSpeechModel::class];
    }

    #[DataProvider('modelClassProvider')]
    public function testGetModelResolvesToExpectedClass(string $modelName, string $expectedClass): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel($modelName);

        self::assertInstanceOf($expectedClass, $model);
        self::assertSame($modelName, $model->getName());
    }

    public function testChatModelsSupportInputMessagesAndOutputText(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('gpt-oss-120b');

        self::assertTrue($model->supports(Capability::INPUT_MESSAGES));
        self::assertTrue($model->supports(Capability::OUTPUT_TEXT));
        self::assertTrue($model->supports(Capability::TOOL_CALLING));
        self::assertFalse($model->supports(Capability::INPUT_IMAGE));
    }

    public function testThinkingCapableModelsExposeThinkingCapability(): void
    {
        $catalog = new ModelCatalog();

        self::assertTrue($catalog->getModel('Qwen3.5-122B-A10B-FP8')->supports(Capability::THINKING));
        self::assertTrue($catalog->getModel('gpt-oss-120b')->supports(Capability::THINKING));
    }

    public function testGlmOcrSupportsPdfInput(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('GLM-OCR');

        self::assertTrue($model->supports(Capability::INPUT_PDF));
        self::assertFalse($model->supports(Capability::TOOL_CALLING));
    }

    public function testEmbeddingModelSupportsEmbeddingsCapability(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('Qwen3-Embedding-8B');

        self::assertTrue($model->supports(Capability::EMBEDDINGS));
        self::assertFalse($model->supports(Capability::OUTPUT_TEXT));
    }

    public function testRerankModelSupportsRerankingCapability(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('Qwen3-VL-Reranker-2B');

        self::assertTrue($model->supports(Capability::RERANKING));
        self::assertTrue($model->supports(Capability::INPUT_IMAGE));
    }

    public function testTextToSpeechModelSupportsTextToSpeechCapability(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('Qwen3-TTS-12Hz-1.7B-CustomVoice');

        self::assertTrue($model->supports(Capability::TEXT_TO_SPEECH));
    }

    public function testWhisperModelSupportsSpeechToTextCapability(): void
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('whisper-large-v3-turbo');

        self::assertTrue($model->supports(Capability::SPEECH_TO_TEXT));
        self::assertTrue($model->supports(Capability::INPUT_AUDIO));
    }

    public function testUnknownModelThrowsModelNotFoundException(): void
    {
        $catalog = new ModelCatalog();

        $this->expectException(ModelNotFoundException::class);

        $catalog->getModel('does-not-exist');
    }

    public function testAdditionalModelsAreMergedIntoTheCatalog(): void
    {
        $catalog = new ModelCatalog([
            'custom-model' => [
                'class' => ChatModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT],
            ],
        ]);

        $model = $catalog->getModel('custom-model');

        self::assertInstanceOf(ChatModel::class, $model);
        self::assertTrue($model->supports(Capability::OUTPUT_TEXT));

        // Built-in models remain available alongside the additional ones.
        self::assertInstanceOf(ChatModel::class, $catalog->getModel('gpt-oss-120b'));
    }

    public function testAdditionalModelsCanOverrideBuiltInModels(): void
    {
        $catalog = new ModelCatalog([
            'gpt-oss-120b' => [
                'class' => EmbeddingModel::class,
                'capabilities' => [Capability::EMBEDDINGS],
            ],
        ]);

        $model = $catalog->getModel('gpt-oss-120b');

        self::assertInstanceOf(EmbeddingModel::class, $model);
    }
}
