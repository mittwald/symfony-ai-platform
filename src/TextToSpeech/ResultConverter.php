<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech;

use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
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

        $this->checkErrorResponse($response);

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

    private function checkErrorResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $data = $response->toArray(false);

        match ($statusCode) {
            401 => throw new AuthenticationException('Invalid API key or unauthorized access.'),
            429 => throw new RateLimitExceededException(),
            400 => throw new BadRequestException('Bad request: '.($data['error']['message'] ?? 'Unknown error')),
            default => throw new BadRequestException(\sprintf('HTTP %d: %s', $statusCode, $data['error']['message'] ?? 'Unknown error')),
        };
    }
}
