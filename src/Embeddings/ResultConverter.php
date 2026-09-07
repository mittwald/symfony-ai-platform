<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Embeddings;

use Mittwald\Symfony\AI\Platform\Bridge\EmbeddingModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
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
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof EmbeddingModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if ($response instanceof ResponseInterface) {
            $this->throwOnHttpError($response);
        }

        $data = $result->getData();
        $vectors = [];

        foreach ($data['data'] ?? [] as $embedding) {
            $vectors[] = new Vector($embedding['embedding']);
        }

        return new VectorResult($vectors);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new EmbeddingTokenUsageExtractor();
    }
}
