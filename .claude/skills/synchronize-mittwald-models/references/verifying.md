# Verifying a model change

Shared reference for the `add-model` and `synchronize-mittwald-models` skills.

## The harness

```bash
php .claude/skills/synchronize-mittwald-models/scripts/verify_catalog.php
```

It loads `ModelCatalog` through the real composer autoloader, instantiates
every catalogued model, and checks two things per model:

- it actually instantiates (catches a bad `class` value or a class that
  doesn't extend `Model`);
- exactly one of the `ModelClient`s `PlatformFactory::create()` wires up
  claims it via `supports()`. Zero means the model is offered by the catalog
  but `Platform::invoke()` will fail on it at call time; more than one means
  two operation types are fighting over the same model class.

It then diffs the catalog's keys against hand-maintained `$current` and
`$retired` arrays — **update those from the verbatim mittwald model table
before trusting a run**. A model present in `$current` but absent from the
catalog is a gap; one present in `$retired` but still in the catalog should
have been removed. Only add a model to `$retired` once its absence upstream is
actually confirmed (a fresh fetch of the model table, or a direct answer from
mittwald) — this list is a claim about reality, not a place to copy names from
elsewhere.

Reading the output: a `!` line is either a structural problem (no client, bad
instantiation) or a drift-vs-docs problem (missing/still-present). Neither is
optional to explain away — see the reports each skill's Step asks for.

One pass over `getModels()` covers both concerns here, because the catalog is
a plain array keyed by exact model name: PHP itself throws on a bad `class`
entry, and "no client claims this model" is just another property of the same
instantiated `Model` to check.

## What it cannot check

`verify_catalog.php` only checks structural wiring — it cannot tell you
whether a `capabilities` list actually matches the model's documented
modality column (there is no local copy of that table to check against, only
the hand-maintained snapshot arrays). Cross-check capability claims against
the fetched model table by eye.

## Standard checks

```bash
composer install
vendor/bin/phpstan analyse
```

`phpstan.neon` runs at level 5 over `src/` with `symfony/ai-platform` present
as a real, resolvable dependency, so a clean run here is a real signal. Treat
any new error as worth explaining, not noise to filter past.

## End-to-end

There is no local API Explorer equivalent to exercise against. Verifying a
model actually behaves as claimed means calling it for real:

```php
$platform = \Mittwald\Symfony\AI\Platform\Bridge\PlatformFactory::create($apiKey);
$result = $platform->invoke('<model-id>', /* payload matching the operation type */);
```

against a live API key, once per operation type touched. `README.md`'s
"Usage" section has one example per operation type to adapt.
