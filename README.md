# mittwald/symfony-ai-platform

Symfony AI platform bridge for [mittwald's AI Hosting API](https://llm.aihosting.mittwald.de).

## Installation

```bash
composer require mittwald/symfony-ai-platform
```

## Usage

Every operation type shares the same setup:

```php
use Mittwald\Symfony\AI\Platform\Bridge\PlatformFactory;

$platform = PlatformFactory::create('your-api-key');
```

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
$result = $platform->invoke('Whisper-Large-V3-Turbo', '/path/to/audio.mp3');
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
| `gpt-oss-120b` | Text, Tool Calling, Streaming |
| `Ministral-3-14B-Instruct-2512` | Text, Image, Tool Calling, Streaming |
| `Qwen3.5-122B-A10B-FP8` | Text, Image, Tool Calling, Streaming, Reasoning |
| `Qwen3.6-35B-A3B-FP8` | Text, Image, Tool Calling, Streaming, Reasoning |
| `Qwen3.5-0.8B` | Text, Tool Calling, Streaming, Reasoning |
| `Qwen3.8-27B-NVFP4` | Text, Image, Tool Calling, Reasoning, Streaming |
| `GLM-OCR` | Text, Image |
| `Qwen3-Embedding-8B` | Embeddings |
| `Whisper-Large-V3-Turbo` | Speech-to-Text |
| `Qwen3-VL-Reranker-2B` | Text, Image, Reranking |
| `Qwen3-TTS-12Hz-1.7B-CustomVoice` | Text-to-Speech |

## License

MIT
