# Where a model ID lives in this repo

Shared reference for the `add-model` and `synchronize-mittwald-models` skills.
Both add and removal have to visit every row below; a half-applied change is the
usual failure mode, because a missed touchpoint fails silently rather than
erroring.

Sweep before concluding anything is complete:

```bash
grep -rn "<model-id>" src/ tests/ README.md composer.json
```

Do not narrow that to `src/`. Model names live in the test suite, in the
README's code examples, and in `composer.json`'s `keywords` too.

## The inventory

| Location | What lives there |
| --- | --- |
| `src/ModelCatalog.php` | the single source of truth: every model's catalog key, its `class`, and its `capabilities` list |
| `src/Factory::createProvider()` | wires one `ModelClient` + `ResultConverter` pair per operation type into the `Provider` (`PlatformFactory::create()` is a deprecated alias for `Factory::createPlatform()`) |
| `src/<OperationType>/ModelClient.php` | `supports()` keys off `$model instanceof <ModelClass>` — only needed for a new operation type |
| `src/<OperationType>/ResultConverter.php` | turns the raw HTTP response into the operation's `Result` type — pairs with the client above |
| `src/<ModelClassName>.php` | e.g. `ChatModel`, `EmbeddingModel`, `WhisperModel` — one per operation type, referenced by `class` in the catalog |
| `tests/ModelCatalogTest.php` | `modelClassProvider()` states the intended class per model and asserts capability claims; a catalog entry with no row there fails `testEveryCatalogedModelHasAnExpectedClass` |
| `tests/ModelRoutingTest.php` | `OPERATION_TYPES` maps each model class to the client/converter pair that must claim it — only needed for a new operation type |
| `README.md` | the model table (name + capabilities column), the PHP usage examples, and the "Supported Models" prose |
| `composer.json` `keywords` | family names mentioned there (`mistral`, `devstral`, `qwen3`, `whisper`, …) |

There is **no configuration file, migration mechanism, or hardcoded default
model to keep in sync** — this library is stateless. A caller passes a model
name string to `Platform::invoke()` on every call, there is nothing persisted
to migrate, and `Factory::createProvider()` bakes in no default model for any
operation type. See "Retiring a model" below for what removal actually implies
in this architecture.

## Routing a model to an operation type

There is **no regex filter and no prefix-matching hazard** —
`AbstractModelCatalog::getModel()` looks up the catalog key by exact string
match (with an optional `base:variant` fallback and `?query=string` option
parsing baked into the base class; see
`vendor/symfony/ai-platform/src/ModelCatalog/AbstractModelCatalog.php`). A typo
in a catalog key just produces a `ModelNotFoundException` — it cannot silently
steal another model's traffic the way an over-broad regex can.

What *can* still go wrong:

- **Copy-pasting capabilities from a sibling model in the same family.**
  `Qwen3.5-0.8B` is text-only while `Qwen3.5-122B-A10B-FP8` is vision- and
  reasoning-capable, and both are Qwen3.5. Capability claims must come from the
  model table's **modality column** for that exact model ID, never from the
  family name or from another entry in the same family.
- **A `class` value that no `ModelClient` in `Factory::createProvider()`
  supports.** The catalog will happily return a `Model` instance for it —
  `getModel()` never checks client coverage — but `Provider::invoke()` fails at
  call time with no signal at catalog-build time: the model looks offered
  right up until someone calls it. `tests/ModelRoutingTest.php` asserts this
  for every catalogued model, so `composer test` catches it; see
  `references/verifying.md`.

## Capabilities the catalog does not use

`Symfony\AI\Platform\Capability` defines more cases than `ModelCatalog.php`
draws on — `OUTPUT_STRUCTURED` (JSON/structured output) among them. Read
`vendor/symfony/ai-platform/src/Capability.php` for the full enum rather than
assuming the set in use is the set that exists. Do not treat an unused case as
accidental scope-limiting either: check the modality column for that exact
model before adding one, and if a documented modality has no capability claim
behind it, flag the drift rather than fixing it silently outside a task that
asked for it.

## Retiring a model

Removing a catalog entry is a breaking change for any caller still passing that
model name — the next call gets a `ModelNotFoundException` instead of a
degraded-but-working response. There is no persisted config to migrate, so the
fix is entirely on the consumer side:

- Remove the entry from `src/ModelCatalog.php`, its row in
  `tests/ModelCatalogTest::modelClassProvider()`, and every
  README/`composer.json` reference.
- Say so plainly in the commit message / PR description — name the retired
  model and that it now throws `ModelNotFoundException` — so a consumer
  upgrading the package notices before it breaks in production rather than
  after.
- If the retired model was used in a README code example, repoint the example
  at a model that is still offered.
