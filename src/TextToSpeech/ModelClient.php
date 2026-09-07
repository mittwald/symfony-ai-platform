<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\TextToSpeech;

use Mittwald\Symfony\AI\Platform\Bridge\TextToSpeechModel;
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
        return $model instanceof TextToSpeechModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for TextToSpeech requests.');
        }

        if (isset($options['stream']) || isset($options['stream_format'])) {
            throw new InvalidArgumentException('Streaming text-to-speech results is not supported yet.');
        }

        $input = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $body = array_merge($options, [
            'model' => $model->getName(),
            'input' => $input,
        ]);

        $response = $this->httpClient->request('POST', '/v1/audio/speech', [
            'json' => $body,
        ]);

        return new RawHttpResult($response);
    }
}
