# mittwald/symfony-ai-platform

Symfony AI platform bridge for [mittwald's AI Hosting API](https://llm.aihosting.mittwald.de).

## Installation

```bash
composer require mittwald/symfony-ai-platform
```

## Usage

Every operation type shares the same setup:

```php
use Mittwald\Symfony\AI\Platform\Bridge\Factory;

$platform = Factory::createPlatform('your-api-key');
```

`Factory::createProvider()` returns the bare `ProviderInterface` instead, for
callers that compose their own `Platform` (or discover bridges by the Symfony AI
factory convention, such as TYPO3's `b13/aim`).

> `PlatformFactory::create()` still works but is deprecated: Symfony AI renamed
> `PlatformFactory` to `Factory` in bridge release 0.8.

Both methods accept the same optional overrides sibling Symfony AI bridges
expose: `$httpClient`, `$modelCatalog`, `$dispatcher`, `$contract`, `$name`
(default `'mittwald'`), and `$baseUrl` (default
`'https://llm.aihosting.mittwald.de'`); `createPlatform()` additionally
accepts `$modelRouter`. `$baseUrl` matters if you're on
[Dedicated AI Hosting](https://developer.mittwald.de/docs/v2/platform/aihosting/dedicated/),
which serves your reserved capacity from a customer-specific subdomain instead
of the shared endpoint:

```php
$provider = Factory::createProvider('your-api-key', baseUrl: 'https://your-company.llm.aihosting.mittwald.de');
```

API errors are translated into the shared platform exceptions
(`AuthenticationException` for 401, `BadRequestException` for 400,
`RateLimitExceededException` for 429, `ServerException` for 5xx) via
`HttpStatusErrorHandlingTrait`, the same convention other Symfony AI bridges use.

### Chat

```php
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')));
echo $result->asText();
```

Streaming:

```php
$result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')), ['stream' => true]);
foreach ($result->asStream() as $chunk) {
    echo $chunk;
}
```

### Embeddings

```php
$result = $platform->invoke('Qwen3-Embedding-8B', 'text to embed');
$vectors = $result->asVectors();
```

### Speech-to-Text

```php
$result = $platform->invoke('whisper-large-v3-turbo', '/path/to/audio.mp3');
echo $result->asText();
```

### Reranking

```php
$result = $platform->invoke('Qwen3-VL-Reranker-2B', [
    'query' => 'What is the capital of France?',
    'documents' => ['Paris is the capital of France.', 'Berlin is the capital of Germany.'],
]);
foreach ($result->asReranking() as $entry) {
    echo $entry->getIndex().': '.$entry->getScore().PHP_EOL;
}
```

### Text-to-Speech

```php
$result = $platform->invoke('Qwen3-TTS-12Hz-1.7B-CustomVoice', 'Hello and welcome!', ['voice' => 'ryan']);
$result->asFile('/path/to/output.mp3');
```

## Supported Models

| Model | Capabilities |
|-------|-------------|
| `gpt-oss-120b` | Text, Tool Calling, Streaming, Reasoning |
| `Ministral-3-14B-Instruct-2512` | Text, Image, Tool Calling, Streaming |
| `Qwen3.5-122B-A10B-FP8` | Text, Image, Tool Calling, Streaming, Reasoning |
| `Qwen3.6-35B-A3B-FP8` | Text, Image, Tool Calling, Streaming, Reasoning |
| `Qwen3.5-0.8B` | Text, Tool Calling, Streaming, Reasoning |
| `Qwen3.8-27B-NVFP4` | Text, Image, Tool Calling, Reasoning, Streaming |
| `GLM-OCR` | Text, Image, PDF |
| `Qwen3-Embedding-8B` | Embeddings |
| `whisper-large-v3-turbo` | Speech-to-Text |
| `Qwen3-VL-Reranker-2B` | Text, Image, Reranking |
| `Qwen3-TTS-12Hz-1.7B-CustomVoice` | Text-to-Speech |

## Development

```bash
composer install
composer run check   # static analysis (phpstan)
composer run test    # PHPUnit unit test suite (mocked HTTP, no network access)
```

### Integration tests

A separate suite in `tests/Integration` exercises every operation type against
the real mittwald AI Hosting API. It requires a live API key and makes actual
(billed) requests, so it is never part of `composer run test` and does not run
on pull requests. Run it explicitly:

```bash
MITTWALD_AI_API_KEY=your-api-key composer run test:integration
```

Without `MITTWALD_AI_API_KEY` set, every test in that suite is skipped. In CI,
the `Integration tests` workflow runs it once a day (and can be triggered
manually) using a repository secret named `MITTWALD_AI_API_KEY`.

## License

MIT
