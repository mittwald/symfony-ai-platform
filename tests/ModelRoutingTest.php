<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Tests;

use Mittwald\Symfony\AI\Platform\Bridge\Chat\ModelClient as ChatModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Chat\ResultConverter as ChatResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ModelClient as EmbeddingsModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use Mittwald\Symfony\AI\Platform\Bridge\Factory;
use Mittwald\Symfony\AI\Platform\Bridge\ModelCatalog;
use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ModelClient as RerankingModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Reranking\ResultConverter as RerankingResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ModelClient as TextToSpeechModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech\ResultConverter as TextToSpeechResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ModelClient as WhisperModelClient;
use Mittwald\Symfony\AI\Platform\Bridge\Whisper\ResultConverter as WhisperResultConverter;
use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Structural checks over the catalog as a whole, rather than over one model.
 *
 * A catalog entry can be perfectly well-formed and still be unusable: nothing
 * in `AbstractModelCatalog::getModel()` checks that any registered client can
 * actually handle the class it names, so a model with no matching client looks
 * offered right up until `Provider::invoke()` throws `ModelNotFoundException`
 * at call time. Two clients claiming the same model is the mirror image —
 * `Provider` silently picks whichever it iterates first. The same holds for
 * `ResultConverter`s, where the call-time failure is a `RuntimeException`.
 *
 * The data provider reads `ModelCatalog` itself, so a model added there is
 * covered here without touching this file.
 */
final class ModelRoutingTest extends TestCase
{
    /**
     * The client/converter pair that is supposed to handle each model class.
     *
     * @var array<class-string, array{class-string<ModelClientInterface>, class-string<ResultConverterInterface>}>
     */
    private const OPERATION_TYPES = [
        ChatModel::class => [ChatModelClient::class, ChatResultConverter::class],
        EmbeddingModel::class => [EmbeddingsModelClient::class, EmbeddingsResultConverter::class],
        WhisperModel::class => [WhisperModelClient::class, WhisperResultConverter::class],
        RerankModel::class => [RerankingModelClient::class, RerankingResultConverter::class],
        TextToSpeechModel::class => [TextToSpeechModelClient::class, TextToSpeechResultConverter::class],
    ];

    /**
     * @return iterable<string, array{0: string, 1: class-string}>
     */
    public static function catalogModelProvider(): iterable
    {
        foreach ((new ModelCatalog())->getModels() as $name => $config) {
            yield $name => [$name, $config['class']];
        }
    }

    #[DataProvider('catalogModelProvider')]
    public function testCatalogedModelIsClaimedByExactlyOneModelClient(string $modelName, string $modelClass): void
    {
        $model = (new ModelCatalog())->getModel($modelName);

        $claiming = array_values(array_filter(
            self::wiredModelClients(),
            static fn (ModelClientInterface $client): bool => $client->supports($model),
        ));

        self::assertCount(
            1,
            $claiming,
            \sprintf(
                'Exactly one ModelClient wired up by Factory::createProvider() must claim "%s"; %d do. '
                .'None means Provider::invoke() fails at call time, more than one means two operation '
                .'types fight over the same model class.',
                $modelName,
                \count($claiming),
            ),
        );
        self::assertInstanceOf(self::expectedClientFor($modelClass), $claiming[0]);
    }

    #[DataProvider('catalogModelProvider')]
    public function testCatalogedModelIsClaimedByExactlyOneResultConverter(string $modelName, string $modelClass): void
    {
        $model = (new ModelCatalog())->getModel($modelName);

        $claiming = array_values(array_filter(
            self::wiredResultConverters(),
            static fn (ResultConverterInterface $converter): bool => $converter->supports($model),
        ));

        self::assertCount(
            1,
            $claiming,
            \sprintf(
                'Exactly one ResultConverter wired up by Factory::createProvider() must claim "%s"; %d do.',
                $modelName,
                \count($claiming),
            ),
        );
        self::assertInstanceOf(self::expectedConverterFor($modelClass), $claiming[0]);
    }

    /**
     * @return class-string<ModelClientInterface>
     */
    private static function expectedClientFor(string $modelClass): string
    {
        return self::operationTypeFor($modelClass)[0];
    }

    /**
     * @return class-string<ResultConverterInterface>
     */
    private static function expectedConverterFor(string $modelClass): string
    {
        return self::operationTypeFor($modelClass)[1];
    }

    /**
     * @return array{class-string<ModelClientInterface>, class-string<ResultConverterInterface>}
     */
    private static function operationTypeFor(string $modelClass): array
    {
        self::assertArrayHasKey(
            $modelClass,
            self::OPERATION_TYPES,
            \sprintf(
                'ModelCatalog routes a model to "%s", which this test knows no operation type for. '
                .'A new model class needs a ModelClient/ResultConverter pair wired into '
                .'Factory::createProvider() and a row in self::OPERATION_TYPES.',
                $modelClass,
            ),
        );

        return self::OPERATION_TYPES[$modelClass];
    }

    /**
     * @return list<ModelClientInterface>
     */
    private static function wiredModelClients(): array
    {
        /** @var list<ModelClientInterface> $clients */
        $clients = self::wiring('modelClients');

        return $clients;
    }

    /**
     * @return list<ResultConverterInterface>
     */
    private static function wiredResultConverters(): array
    {
        /** @var list<ResultConverterInterface> $converters */
        $converters = self::wiring('resultConverters');

        return $converters;
    }

    /**
     * Reads a wiring list out of the Provider that Factory::createProvider()
     * actually builds, instead of restating that list here: a copy in the test
     * would keep passing after the Factory stopped wiring one of them up,
     * which is the exact gap these tests exist to close. Provider keeps both
     * lists private, hence the reflection — if a future symfony/ai-platform
     * renames them, this fails loudly rather than silently checking nothing.
     *
     * The MockHttpClient keeps construction offline; nothing here sends a
     * request.
     *
     * @return list<object>
     */
    private static function wiring(string $property): array
    {
        $provider = Factory::createProvider('test-api-key', new MockHttpClient());

        self::assertTrue(
            (new \ReflectionObject($provider))->hasProperty($property),
            \sprintf('%s has no "%s" property to read the wiring from.', $provider::class, $property),
        );

        /** @var iterable<object> $wiring */
        $wiring = (new \ReflectionProperty($provider, $property))->getValue($provider);

        return array_values(\is_array($wiring) ? $wiring : iterator_to_array($wiring, false));
    }
}
