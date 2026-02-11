<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
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
        return $model instanceof EmbeddingModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $body = [
            'model' => $model->getName(),
            'input' => $payload,
        ];

        if (isset($options['encoding_format'])) {
            $body['encoding_format'] = $options['encoding_format'];
        }

        $response = $this->httpClient->request('POST', '/v1/embeddings', [
            'json' => $body,
        ]);

        return new RawHttpResult($response);
    }
}
