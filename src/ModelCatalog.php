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
                    Capability::THINKING,
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
            'Qwen3.5-122B-A10B-FP8' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'Qwen3.6-35B-A3B-FP8' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'Qwen3.5-0.8B' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'Qwen3.8-27B-NVFP4' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'GLM-OCR' => [
                'class' => ChatModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Qwen3-Embedding-8B' => [
                'class' => EmbeddingModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'whisper-large-v3-turbo' => [
                'class' => WhisperModel::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'Qwen3-VL-Reranker-2B' => [
                'class' => RerankModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::RERANKING,
                ],
            ],
            'Qwen3-TTS-12Hz-1.7B-CustomVoice' => [
                'class' => TextToSpeechModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
        ], $additionalModels);
    }
}
