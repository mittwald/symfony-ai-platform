<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Chat;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $eventSourceHttpClient;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        $this->eventSourceHttpClient = new EventSourceHttpClient($this->httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ChatModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $body = array_merge($options, $payload);
        $body['model'] = $model->getName();

        if ($body['stream'] ?? false) {
            $response = $this->eventSourceHttpClient->request('POST', '/v1/chat/completions', [
                'json' => $body,
            ]);
        } else {
            $response = $this->httpClient->request('POST', '/v1/chat/completions', [
                'json' => $body,
            ]);
        }

        return new RawHttpResult($response);
    }
}
