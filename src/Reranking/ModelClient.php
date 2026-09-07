<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Reranking;

use Mittwald\Symfony\AI\Platform\Bridge\RerankModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof RerankModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['documents'])) {
            throw new InvalidArgumentException('Reranking payload must be an array with "query" and "documents" keys.');
        }

        $body = [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['documents'],
        ];

        if (isset($options['instruction'])) {
            $body['instruction'] = $options['instruction'];
        }

        $response = $this->httpClient->request('POST', '/v1/rerank', [
            'json' => $body,
        ]);

        return new RawHttpResult($response);
    }
}
