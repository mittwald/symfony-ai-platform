<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof EmbeddingModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->checkErrorResponse($result);

        $data = $result->getData();
        $vectors = [];

        foreach ($data['data'] ?? [] as $embedding) {
            $vectors[] = new Vector($embedding['embedding']);
        }

        return new VectorResult(...$vectors);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new EmbeddingTokenUsageExtractor();
    }

    private function checkErrorResponse(RawResultInterface $result): void
    {
        $response = $result->getObject();

        if (!$response instanceof ResponseInterface) {
            return;
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        match ($statusCode) {
            401 => throw new AuthenticationException('Invalid API key or unauthorized access.'),
            429 => throw new RateLimitExceededException(),
            400 => throw new BadRequestException('Bad request: ' . ($result->getData()['error']['message'] ?? 'Unknown error')),
            default => throw new BadRequestException(\sprintf('HTTP %d: %s', $statusCode, $result->getData()['error']['message'] ?? 'Unknown error')),
        };
    }
}
