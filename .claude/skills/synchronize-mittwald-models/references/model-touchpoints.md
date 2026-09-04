# Where a model ID lives in this repo

Shared reference for the `add-model` and `synchronize-mittwald-models` skills.
Both add and removal have to visit every row below; a half-applied change is the
usual failure mode, because a missed touchpoint fails silently rather than
erroring.

Sweep before concluding anything is complete:

```bash
grep -rn "<model-id>" src/ README.md composer.json
```

Do not narrow that to `src/`. Model names live in the README's code examples
and in `composer.json`'s `keywords` too.

## The inventory

| Location | What lives there |
| --- | --- |
| `src/ModelCatalog.php` | the single source of truth: every model's catalog key, its `class`, and its `capabilities` list |
| `src/PlatformFactory::create()` | wires one `ModelClient` + `ResultConverter` pair per operation type into the `Provider` |
| `src/<OperationType>/ModelClient.php` | `supports()` keys off `$model instanceof <ModelClass>` — only needed for a new operation type |
| `src/<OperationType>/ResultConverter.php` | turns the raw HTTP response into the operation's `Result` type — pairs with the client above |
| `src/<ModelClassName>.php` | e.g. `ChatModel`, `EmbeddingModel`, `WhisperModel` — one per operation type, referenced by `class` in the catalog |
| `README.md` | the model table (name + capabilities column), the PHP usage examples, and the "Supported Models" prose |
| `composer.json` `keywords` | family names mentioned there (`mistral`, `devstral`, `qwen3`, `whisper`, …) |

There is **no equivalent of `definitions/api_defaults.yml`, install hooks, or a
hardcoded rate-limit-probe model** — those are Drupal-specific concerns tied to
persisted site configuration and a plugin-setup flow. This library is stateless:
a caller passes a model name string to `Platform::invoke()` on every call, there
is nothing to migrate, and there is no default model baked into the factory.
Do not go looking for those touchpoints here; see "Retiring a model" below for
what removal actually implies in this architecture.

## Routing a model to an operation type

Unlike the Drupal provider, there is **no regex filter and no prefix-matching
hazard** — `AbstractModelCatalog::getModel()` looks up the catalog key by exact
string match (with an optional `base:variant` fallback and `?query=string`
option parsing baked into the base class; see
`vendor/symfony/ai-platform/src/ModelCatalog/AbstractModelCatalog.php`). A typo
in a catalog key just produces a `ModelNotFoundException` — it cannot silently
steal another model's traffic the way an over-broad regex can.

What *can* still go wrong, and is the direct analogue of the Drupal over-match
hazard:

- **Copy-pasting capabilities from a sibling model in the same family.**
  `Qwen3.5-0.8B` is text-only while `Qwen3.5-122B-A10B-FP8` is vision- and
  reasoning-capable, and both are Qwen3.5. Capability claims must come from the
  model table's **modality column** for that exact model ID, never from the
  family name or from another entry in the same family.
- **A `class` value that no `ModelClient` in `PlatformFactory::create()`
  supports.** The catalog will happily return a `Model` instance for it —
  `getModel()` never checks client coverage — but `Platform::invoke()` fails at
  call time with no signal at catalog-build time. This is the equivalent of the
  Drupal module offering an operation type whose interface isn't declared: it
  looks offered right up until someone calls it. `scripts/verify_catalog.php`
  checks this explicitly; see `references/verifying.md`.

## Capabilities beyond the four currently used

`Symfony\AI\Platform\Capability` also defines `THINKING` (reasoning),
`OUTPUT_STRUCTURED` (JSON/structured output), `RERANKING`, `TEXT_TO_SPEECH`,
and others that nothing in `ModelCatalog.php` currently uses — see
`vendor/symfony/ai-platform/src/Capability.php` for the full enum. Do not treat
their absence as accidental scope-limiting; check the modality column before
adding one. As of the 2026-09-04 snapshot in `synchronize-mittwald-models`'s
`SKILL.md`, `Qwen3.5-122B-A10B-FP8` and `Qwen3.6-35B-A3B-FP8` are documented as
"Chat + reasoning + vision" upstream but carry no `Capability::THINKING` in the
catalog — flag this as drift rather than fixing it silently if you encounter it
outside of an explicit reasoning-capability task.

## Retiring a model

Removing a catalog entry is a breaking change for any caller still passing that
model name — the next call gets a `ModelNotFoundException` instead of a
degraded-but-working response. There is no persisted config to migrate (unlike
the Drupal module's `hook_update_N`), so the fix is entirely on the consumer
side:

- Remove the entry from `src/ModelCatalog.php` and every README/`composer.json`
  reference.
- Say so plainly in the commit message / PR description — name the retired
  model and that it now throws `ModelNotFoundException` — so a consumer
  upgrading the package notices before it breaks in production rather than
  after.
- If the retired model was used in a README code example, repoint the example
  at a model that is still offered.
