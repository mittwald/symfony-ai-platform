# mittwald/symfony-ai-platform

Symfony AI platform bridge for [mittwald's AI Hosting API](https://llm.aihosting.mittwald.de).

## Installation

```bash
composer require mittwald/symfony-ai-platform
```

## Usage

```php
use Mittwald\Symfony\AI\Platform\Bridge\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$platform = PlatformFactory::create('your-api-key');

// Chat completion
$result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')));
echo $result->asText();

// Streaming
$result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')), ['stream' => true]);
foreach ($result->asStream() as $chunk) {
    echo $chunk;
}

// Embeddings
$result = $platform->invoke('Qwen3-Embedding-8B', 'text to embed');
$vectors = $result->asVectors();

// Speech-to-text
$result = $platform->invoke('Whisper-Large-V3-Turbo', '/path/to/audio.mp3');
echo $result->asText();
```

## Supported Models

| Model | Capabilities |
|-------|-------------|
| `gpt-oss-120b` | Text, Tool Calling, Streaming |
| `Ministral-3-14B-Instruct-2512` | Text, Image, Tool Calling, Streaming |
| `Devstral-Small-2-24B-Instruct-2512` | Text, Image, Tool Calling, Streaming |
| `Qwen3.5-122B-FP8` | Text, Image, Tool Calling, Streaming |
| `Qwen3.6-35B-FP8` | Text, Image, Tool Calling, Streaming |
| `Qwen3-Embedding-8B` | Embeddings |
| `Whisper-Large-V3-Turbo` | Speech-to-Text |

## License

MIT
