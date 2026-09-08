# mittwald/symfony-ai-platform

A [Symfony AI](https://github.com/symfony/ai) **platform bridge** for
[mittwald's AI Hosting API](https://llm.aihosting.mittwald.de).

## What this package does

[Symfony AI Platform](https://github.com/symfony/ai) gives PHP applications one
vendor-neutral API for talking to AI models: you build a `MessageBag`, call
`$platform->invoke(...)`, and read the answer off a result object — regardless of
who actually runs the model. A *bridge* is the adapter that plugs one concrete
provider into that API.

This package is the bridge for
[**mittwald AI Hosting**](https://developer.mittwald.de/docs/v2/platform/aihosting/introduction/),
which serves models such as GPT-OSS, Qwen, Ministral, Whisper and GLM over an
OpenAI-compatible API. Installing it lets you:

- **Use mittwald-hosted models through the standard Symfony AI interfaces** —
  for chat and embeddings, the same code that talks to the OpenAI or Anthropic
  bridges works here; only the factory call and the model ID change.
- **Cover five operation types, not just chat** — chat completion (with
  streaming, tool calling, vision and reasoning models), text embeddings,
  speech-to-text, text-to-speech and document reranking, each with a result
  object that already knows how to decode mittwald's responses.
- **Know where your prompts go** — mittwald describes the models as stateless,
  so "no content-related information from submitted inputs, outputs, or prompts
  is stored or processed for other purposes", and
  [Dedicated AI Hosting](https://developer.mittwald.de/docs/v2/platform/aihosting/dedicated/)
  is hosted in Germany. Building a GDPR-compliant application on top of it
  remains your own responsibility — read the
  [data protection notes](https://developer.mittwald.de/docs/v2/platform/aihosting/access-and-usage/data-protection/)
  before you rely on any of this.
- **Swap providers without rewriting application code**, because everything is
  expressed against `Symfony\AI\Platform\PlatformInterface`. Reranking and
  text-to-speech take provider-shaped payloads and options, so those are less
  portable than chat.

If you're building agents, RAG pipelines or tool-calling workflows on top of
[Symfony AI Agent](https://github.com/symfony/ai) or the AI Bundle, this bridge
is the piece that supplies the model backend.

## Requirements

| Requirement | Version / notes |
|-------------|-----------------|
| PHP | 8.2 or newer |
| `symfony/ai-platform` | `^0.13` (installed automatically) |
| `symfony/http-client` | `^7.3 \|\| ^8.0` (installed automatically) |
| `symfony/mime` | `^7.3 \|\| ^8.0` (installed automatically) |
| API key | A mittwald AI Hosting API key — see [below](#getting-an-api-key) |

You do **not** need a full Symfony application — the package works in any PHP
project with Composer autoloading. The Symfony components above are pulled in as
regular dependencies.

## Installation

```bash
composer require mittwald/symfony-ai-platform
```

That single command installs the bridge and its dependencies. Nothing else needs
to be registered — there is no bundle to enable and no configuration file to
create.

### Getting an API key

Following mittwald's
[Gaining access](https://developer.mittwald.de/docs/v2/platform/aihosting/access-and-usage/access/)
guide, you need an mStudio account, an organization, and a project with an active
hosting product. In that project, open the **AI-Hosting** entry in the sidebar and
complete the (paid) booking; you then receive your API base URL and can create an
API key.

The key is sent as an HTTP bearer token, by default to
`https://llm.aihosting.mittwald.de`. If your base URL differs, pass it as
`$baseUrl` — see [Usage](#usage). Note that mittwald's docs quote endpoints
including the `/v1` suffix; leave that off, because the bridge appends the
version and path itself.

Treat the key like a password: keep it out of version control and read it from an
environment variable or a secrets store.

### Quick start

```php
use Mittwald\Symfony\AI\Platform\Bridge\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$platform = Factory::createPlatform(getenv('MITTWALD_AI_API_KEY'));

$result = $platform->invoke('gpt-oss-120b', new MessageBag(
    Message::ofUser('Explain what a Symfony AI platform bridge is, in one sentence.'),
));

echo $result->asText();
```

### Using it in a Symfony application

Register the platform as a service and inject `PlatformInterface` wherever you
need it:

```yaml
# config/services.yaml
services:
    Symfony\AI\Platform\PlatformInterface:
        factory: ['Mittwald\Symfony\AI\Platform\Bridge\Factory', 'createPlatform']
        arguments:
            $apiKey: '%env(MITTWALD_AI_API_KEY)%'
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

You never pick an endpoint yourself: the model ID you pass to `invoke()` is
looked up in the bridge's [model catalog](#supported-models), which decides
whether the call becomes a chat completion, an embedding, a transcription, a
reranking or a speech synthesis request — and therefore which `as*()` method the
result understands.

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

These are the model IDs this bridge's catalog knows about. mittwald's own
[Available models](https://developer.mittwald.de/docs/v2/platform/aihosting/models/)
page is the authoritative list of what the API currently serves.

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
