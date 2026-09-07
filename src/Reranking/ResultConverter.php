<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Reranking;

use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof RerankModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->checkErrorResponse($result);

        $data = $result->getData();

        if (!isset($data['results'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        return new RerankingResult(
            array_map(
                static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['relevance_score']),
                $data['results'],
            ),
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
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
