<?php

/**
 * Loads the catalog through the real composer autoloader, builds every
 * catalogued model, and reports which ModelClient (if any) would actually
 * handle it in PlatformFactory::create().
 *
 * This merges the two concerns the Drupal counterpart (ai_provider_mittwald)
 * needed two separate scripts for. There the model filters were regexes
 * parsed out of source, so class-declaration fatals and filter drift were
 * genuinely different failure modes. Here the catalog is a plain PHP array
 * keyed by exact model name, so both concerns collapse into one pass:
 * instantiation errors surface immediately (no reflection tricks needed —
 * PHP throws), and "no client claims this model class" is just another
 * property of the same instantiated Model to check.
 *
 * Update $current and $retired from the verbatim mittwald model table
 * before trusting a run — they are maintained by hand, same as their
 * counterparts in ai_provider_mittwald's verify_filters.php.
 *
 * Usage:
 * php .claude/skills/synchronize-mittwald-models/scripts/verify_catalog.php
 */

declare(strict_types=1);

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;

// The lineup documented at the mittwald AI hosting models page:
// https://developer.mittwald.de/docs/v2/platform/aihosting/models/
// Last synchronised: 2026-09-04. Re-fetch before trusting this.
$current = [
    'gpt-oss-120b',
    'Qwen3.5-0.8B',
    'Ministral-3-14B-Instruct-2512',
    'Qwen3.5-122B-A10B-FP8',
    'Qwen3.6-35B-A3B-FP8',
    'Qwen3.8-27B-NVFP4',
    'GLM-OCR',
    'Qwen3-Embedding-8B',
    'Qwen3-VL-Reranker-2B',
    'whisper-large-v3-turbo',
    'Qwen3-TTS-12Hz-1.7B-CustomVoice',
];

// Models withdrawn upstream. These must be absent from ModelCatalog entirely.
$retired = [
    'Mistral-Small-3.2-24B-Instruct',
    'Mistral-Medium-3.5-128B',
    'qwen3-coder-30b',
    'Devstral-Small-2507',
    'Devstral-Small-2-24B-Instruct-2512',
];

$root = dirname(__DIR__, 4);

if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Could not find vendor/autoload.php below $root. Run composer install.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';

$catalogClass = 'Mittwald\\Symfony\\AI\\Platform\\Bridge\\ModelCatalog';
$factoryClass = 'Mittwald\\Symfony\\AI\\Platform\\Bridge\\PlatformFactory';

if (!class_exists($catalogClass)) {
    fwrite(STDERR, "$catalogClass could not be autoloaded.\n");
    exit(1);
}

$catalog = new $catalogClass();
$models = $catalog->getModels();

// Build the same ModelClient list PlatformFactory::create() wires up, without
// making a network call — a bearer token and a HttpClient are enough,
// nothing here sends a request.
$factoryReflection = new ReflectionClass($factoryClass);
$httpClient = \Symfony\Component\HttpClient\HttpClient::create();

/** @var list<ModelClientInterface> $modelClients */
$modelClients = [];
foreach ([
    'Mittwald\\Symfony\\AI\\Platform\\Bridge\\Chat\\ModelClient',
    'Mittwald\\Symfony\\AI\\Platform\\Bridge\\Embeddings\\ModelClient',
    'Mittwald\\Symfony\\AI\\Platform\\Bridge\\Whisper\\ModelClient',
] as $clientClass) {
    if (!class_exists($clientClass)) {
        fwrite(STDERR, "Warning: $clientClass referenced by this script no longer exists — update the hardcoded list to match PlatformFactory::create().\n");
        continue;
    }
    $modelClients[] = new $clientClass($httpClient);
}

echo "Models declared in ".basename(str_replace('\\', '/', $catalogClass)).":\n";

$width = max(array_map('strlen', array_keys($models))) + 2;
$failures = 0;

foreach ($models as $name => $config) {
    $flags = [];

    try {
        $model = $catalog->getModel($name);
    } catch (\Throwable $e) {
        printf("! %-{$width}s FAILED TO INSTANTIATE: %s\n", $name, $e->getMessage());
        ++$failures;
        continue;
    }

    $supportingClients = [];
    foreach ($modelClients as $client) {
        if ($client->supports($model)) {
            $supportingClients[] = (new ReflectionClass($client))->getNamespaceName();
        }
    }

    if ([] === $supportingClients) {
        $flags[] = 'NO MODEL CLIENT SUPPORTS THIS — Platform::invoke() will fail at call time';
        ++$failures;
    } elseif (\count($supportingClients) > 1) {
        $flags[] = 'multiple clients claim this model: '.implode(', ', $supportingClients);
        ++$failures;
    }

    $capabilities = implode(', ', array_map(static fn (Capability $c): string => $c->value, $model->getCapabilities()));
    $flag = $flags ? '! ' : '  ';
    printf(
        "%s%-{$width}s %-20s [%s]%s\n",
        $flag,
        $name,
        (new ReflectionClass($model))->getShortName(),
        $capabilities,
        $flags ? ' -- '.implode('; ', $flags) : ''
    );
}

$catalogKeys = array_keys($models);

echo "\n== Currently offered upstream — each should be a catalog key ==\n";
foreach ($current as $model) {
    $inCatalog = \in_array($model, $catalogKeys, true);
    printf('%s%-'.$width."s %s\n", $inCatalog ? '  ' : '! ', $model, $inCatalog ? 'in catalog' : 'MISSING FROM CATALOG');
    if (!$inCatalog) {
        ++$failures;
    }
}

echo "\n== Retired upstream — each should be absent from the catalog ==\n";
foreach ($retired as $model) {
    $inCatalog = \in_array($model, $catalogKeys, true);
    printf('%s%-'.$width."s %s\n", $inCatalog ? '! ' : '  ', $model, $inCatalog ? 'STILL IN CATALOG' : 'absent');
    if ($inCatalog) {
        ++$failures;
    }
}

echo "\n== Catalog keys matching neither list (not yet classified) ==\n";
foreach ($catalogKeys as $model) {
    if (!\in_array($model, $current, true) && !\in_array($model, $retired, true)) {
        echo "  $model  -- update \$current or \$retired in this script\n";
    }
}

echo "\nLines marked ! need attention.\n";
echo "Also check capability claims themselves against the modality column —\n";
echo "this harness only checks structural wiring, not whether a claimed\n";
echo "capability matches what the model documentation says it can do.\n";

exit($failures > 0 ? 1 : 0);
