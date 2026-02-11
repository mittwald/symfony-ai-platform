<?php

namespace Mittwald\Symfony\AI\Platform\Bridge;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $this->models = array_merge([
            'gpt-oss-120b' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Ministral-3-14B-Instruct-2512' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Devstral-Small-2-24B-Instruct-2512' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen3-Embedding-8B' => [
                'class' => EmbeddingModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'Whisper-Large-V3-Turbo' => [
                'class' => WhisperModel::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
        ], $additionalModels);
    }
}
