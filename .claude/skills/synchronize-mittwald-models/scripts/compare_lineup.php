<?php

/**
 * Diffs the model IDs mittwald currently offers against the catalog's keys.
 *
 * This is the half of the audit that cannot live in the test suite: it is a
 * claim about what upstream offers *today*, so it needs the lineup you fetched
 * in this run as input. The structural half — every catalogued model
 * instantiates, and exactly one ModelClient and one ResultConverter out of the
 * ones Factory::createProvider() wires up claims it — is asserted by
 * tests/ModelRoutingTest.php and tests/ModelCatalogTest.php, so run
 * `composer test` alongside this.
 *
 * Pass the lineup on stdin (one ID per line, `#` comments and blanks ignored)
 * or as arguments:
 *
 *   php .claude/skills/synchronize-mittwald-models/scripts/compare_lineup.php <<'EOF'
 *   gpt-oss-120b
 *   Qwen3-Embedding-8B
 *   EOF
 *
 *   php .claude/skills/synchronize-mittwald-models/scripts/compare_lineup.php gpt-oss-120b …
 *
 * Give it every row of the fetched model table, verbatim. A row you drop reads
 * exactly like a model mittwald retired.
 */

declare(strict_types=1);

use Symfony\AI\Platform\Capability;

$root = dirname(__DIR__, 4);

if (!is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Could not find vendor/autoload.php below $root. Run composer install.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';

$offered = array_slice($argv, 1);

if ([] === $offered) {
    if (stream_isatty(STDIN)) {
        fwrite(STDERR, "No model IDs given. Pass them as arguments or on stdin — see the header of this file.\n");
        exit(1);
    }

    $offered = preg_split('/\R/', (string) stream_get_contents(STDIN)) ?: [];
}

$offered = array_values(array_filter(array_map(
    static fn (string $line): string => trim(preg_replace('/#.*$/', '', $line) ?? ''),
    $offered,
), static fn (string $line): bool => '' !== $line));

if ([] === $offered) {
    fwrite(STDERR, "No model IDs found in the input.\n");
    exit(1);
}

$catalogClass = 'Mittwald\\Symfony\\AI\\Platform\\Bridge\\ModelCatalog';

if (!class_exists($catalogClass)) {
    fwrite(STDERR, "$catalogClass could not be autoloaded.\n");
    exit(1);
}

$catalog = new $catalogClass();
$models = $catalog->getModels();
$catalogKeys = array_keys($models);

$width = max(array_map(strlen(...), [...$catalogKeys, ...$offered])) + 2;
$failures = 0;

echo "== Offered upstream, missing from the catalog ==\n";
$missing = array_values(array_diff($offered, $catalogKeys));
foreach ($missing as $model) {
    printf("! %-{$width}s add it with the add-model skill, or say why it is out of scope\n", $model);
    ++$failures;
}
if ([] === $missing) {
    echo "  (none — every offered model has a catalog entry)\n";
}

echo "\n== In the catalog, absent from the lineup you passed ==\n";
$extra = array_values(array_diff($catalogKeys, $offered));
foreach ($extra as $model) {
    printf("! %-{$width}s retired upstream, or missing from the table you fetched?\n", $model);
    ++$failures;
}
if ([] === $extra) {
    echo "  (none — the catalog offers nothing beyond the lineup you passed)\n";
}

if ([] !== $extra) {
    echo "\n  A model shows up here for two very different reasons: mittwald withdrew\n";
    echo "  it, or the fetch that produced your lineup dropped a row. Confirm the\n";
    echo "  withdrawal independently — a fresh fetch of the model table plus the\n";
    echo "  absence of its dedicated model doc page in the sitemap — before removing\n";
    echo "  anything. Removal is a breaking change for callers still passing the ID.\n";
}

echo "\n== Catalog entries, for the capability cross-check ==\n";
foreach ($models as $name => $config) {
    printf(
        "  %-{$width}s %-20s [%s]\n",
        $name,
        (new ReflectionClass($config['class']))->getShortName(),
        implode(', ', array_map(static fn (Capability $c): string => $c->value, $config['capabilities'])),
    );
}

echo "\nNothing above checks a capability claim against the modality column —\n";
echo "compare that list to the fetched model table by eye, row by row.\n";

exit($failures > 0 ? 1 : 0);
