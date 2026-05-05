<?php

namespace Mittwald\Symfony\AI\Platform\Bridge\Chat;

use Mittwald\Symfony\AI\Platform\Bridge\ChatModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly TokenUsageExtractor $tokenUsageExtractor = new TokenUsageExtractor(),
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ChatModel;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->createStreamGenerator($result));
        }

        $this->checkErrorResponse($result);

        $data = $result->getData();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        if (isset($message['tool_calls']) && [] !== $message['tool_calls']) {
            return $this->createToolCallResult($message['tool_calls']);
        }

        return new TextResult($message['content'] ?? '');
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return $this->tokenUsageExtractor;
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

    /**
     * @param array<int, array<string, mixed>> $toolCalls
     */
    private function createToolCallResult(array $toolCalls): ToolCallResult
    {
        $calls = [];

        foreach ($toolCalls as $toolCall) {
            $arguments = $toolCall['function']['arguments'] ?? '{}';

            if (\is_string($arguments)) {
                $arguments = json_decode($arguments, true, flags: \JSON_THROW_ON_ERROR);
            }

            $calls[] = new ToolCall(
                id: $toolCall['id'],
                name: $toolCall['function']['name'],
                arguments: $arguments,
            );
        }

        return new ToolCallResult(...$calls);
    }

    private function createStreamGenerator(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $choice = $data['choices'][0] ?? [];
            $delta = $choice['delta'] ?? [];

            if (isset($delta['content']) && '' !== $delta['content']) {
                yield $delta['content'];
            }
        }
    }
}
