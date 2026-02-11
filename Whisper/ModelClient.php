<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Whisper;

use Mittwald\Symfony\AI\Platform\Bridge\WhisperModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof WhisperModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $formFields = [
            'model' => $model->getName(),
        ];

        if (\is_array($payload) && isset($payload['file'])) {
            $file = $payload['file'];
            $formFields['file'] = \is_string($file) ? DataPart::fromPath($file) : $file;
        } elseif (\is_string($payload)) {
            $formFields['file'] = DataPart::fromPath($payload);
        }

        foreach (['language', 'temperature', 'response_format'] as $optionKey) {
            if (isset($options[$optionKey])) {
                $formFields[$optionKey] = (string) $options[$optionKey];
            }
        }

        $formData = new FormDataPart($formFields);

        $response = $this->httpClient->request('POST', '/v1/audio/transcriptions', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
        ]);

        return new RawHttpResult($response);
    }
}
