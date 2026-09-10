# Verifying a model change

Shared reference for the `add-model` and `synchronize-mittwald-models` skills.

Verification splits in two: what the repo can assert about itself, which is the
unit test suite's job and runs in CI on every PR, and what only a fetch of the
mittwald docs can settle, which is `compare_lineup.php`'s job and yours.

## The test suite

```bash
composer test
```

`tests/ModelRoutingTest.php` reads `ModelCatalog` and asserts, for every model
in it:

- it instantiates (a `class` value that doesn't exist, or doesn't extend
  `Model`, throws right here);
- exactly one of the `ModelClient`s `Factory::createProvider()` wires up claims
  it via `supports()`, and it is the one for that model's class. Zero means the
  model is offered by the catalog but `Provider::invoke()` fails on it at call
  time; more than one means two operation types are fighting over the same
  model class and whichever is iterated first silently wins;
- the same for `ResultConverter`s, where the call-time failure is a
  `RuntimeException` instead.

It reads both wiring lists off the `Provider` that `Factory::createProvider()`
actually returns, so a client that stops being wired up fails the test rather
than being quietly missed.

`tests/ModelCatalogTest.php` pins the intended class and the capability claims
per model, and `testEveryCatalogedModelHasAnExpectedClass` fails if a model in
the catalog has no row there — so a new entry cannot land without someone
stating what it is supposed to be.

**A model added to `ModelCatalog.php` therefore needs a row in
`ModelCatalogTest::modelClassProvider()`**, and a new *operation type* also
needs a row in `ModelRoutingTest::OPERATION_TYPES`. That is the point: the
addition can't be half-applied.

## The lineup diff

```bash
php .claude/skills/synchronize-mittwald-models/scripts/compare_lineup.php <<'EOF'
gpt-oss-120b
Qwen3-Embedding-8B
…
EOF
```

Feed it every model ID from the table you fetched in this run, verbatim — IDs
on stdin (one per line, `#` comments ignored) or as arguments. It diffs them
against the catalog's keys and reports both directions:

- **offered upstream, missing from the catalog** — a gap; add it with the
  `add-model` skill or say why it is out of scope.
- **in the catalog, absent from your lineup** — a *candidate* retiral, not a
  confirmed one. It reads identically whether mittwald withdrew the model or
  your fetch dropped a row, which is why the script cannot decide it for you.
  Confirm withdrawal independently (a fresh fetch of the model table, plus the
  absence of that model's dedicated doc page from the sitemap) before removing
  anything; removal is a breaking change for callers still passing the ID.

The script deliberately holds no list of its own. A snapshot checked in here
would be stale on its next run and green while stale — the lineup has to come
from the fetch you just did.

## What neither can check

Nothing local can tell you whether a `capabilities` list matches the model's
documented modality column. `compare_lineup.php` prints every catalog entry
with its class and capabilities as its last section; compare that to the
fetched table by eye, row by row.

## Standard checks

```bash
composer install
composer run check   # vendor/bin/phpstan analyse
```

`phpstan.neon` runs at level 5 over `src/` with `symfony/ai-platform` present
as a real, resolvable dependency, so a clean run here is a real signal. Treat
any new error as worth explaining, not noise to filter past.

## End-to-end

There is no local API Explorer equivalent to exercise against. Verifying a
model actually behaves as claimed means calling it for real:

```php
$platform = \Mittwald\Symfony\AI\Platform\Bridge\Factory::createPlatform($apiKey);
$result = $platform->invoke('<model-id>', /* payload matching the operation type */);
```

against a live API key, once per operation type touched. `composer run
test:integration` does exactly this for every operation type when
`MITTWALD_AI_API_KEY` is set, and `README.md`'s "Usage" section has one example
per operation type to adapt.
