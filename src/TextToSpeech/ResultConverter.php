<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech;

use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeechModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (!$response instanceof ResponseInterface) {
            throw new RuntimeException('Expected an HTTP response object.');
        }

        $this->throwOnHttpError($response);

        $mimeType = null;
        foreach ($response->getHeaders(false)['content-type'] ?? [] as $headerValue) {
            $mimeType = $headerValue;
            break;
        }

        return new BinaryResult($response->getContent(false), $mimeType);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
