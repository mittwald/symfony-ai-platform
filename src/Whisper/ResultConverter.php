<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Whisper;

use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof WhisperModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if ($response instanceof ResponseInterface) {
            $this->throwOnHttpError($response);
        }

        $data = $result->getData();
        $format = $options['response_format'] ?? 'json';

        if ('verbose_json' === $format) {
            return new ObjectResult($data);
        }

        return new TextResult($data['text'] ?? '');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
