---
name: add-model
description: Add support for one specific new AI model to the mittwald Symfony AI platform bridge — give it a ModelCatalog entry with the right class and capabilities, and update every place a model ID appears. Use when a named model should become available. For a full audit of the lineup, or for removing retired models, use synchronize-mittwald-models instead.
allowed-tools: Read, Edit, Bash, Grep, Glob, WebFetch
---

# Add a mittwald model

Make one named model available through `mittwald/symfony-ai-platform`. This is
the targeted counterpart to `synchronize-mittwald-models`: that skill
reconciles the whole lineup and handles removals; this one adds a single known
model.

**Never guess a model ID or a capability.** A model ID that does not exist
throws `ModelNotFoundException` the first time anyone calls it — silent right
up until then, since nothing at catalog-definition time checks it against
reality. Every ID and every capability claim has to trace back to the mittwald
documentation or an observed API response.

## Step 1: Verify the model against the documentation

Fetch the model table and read the row for this model **verbatim**:

https://developer.mittwald.de/docs/v2/platform/aihosting/models/

Ask for the exact ID, the type, and the modality column — a summarising
prompt drops detail, and the modality column is what decides the capability
claims. Take the ID's exact spelling from here, including the parameter size
and casing; `AbstractModelCatalog::getModel()` matches the catalog key
exactly, so a casing mismatch doesn't error, it just means the string a caller
has to pass differs from what mittwald itself documents.

If the model is not in that table, stop. A speculatively added model doesn't
fail loudly here — `ModelNotFoundException` only fires the first time someone
actually calls it, which can be long after the PR merged.

If the model implies an operation type this bridge does not implement yet (a
new endpoint — rerank, text-to-speech, OCR — rather than a new chat/
embeddings/whisper model), that is `synchronize-mittwald-models` territory: it
covers endpoint probing and what a new `ModelClient`/`ResultConverter`/`Model`
subclass triple needs. Say so rather than half-implementing it here.

## Step 2: Decide what it should be routed to

From the modality column, not the family name, determine:

- **existing operation type or new one** — does an existing `ChatModel` /
  `EmbeddingModel` / `WhisperModel` fit, or does this model need a class and
  client this bridge doesn't have yet (see Step 1's escalation note)?
- **capabilities** — pick from `Symfony\AI\Platform\Capability`
  (`vendor/symfony/ai-platform/src/Capability.php`). For a chat model this
  usually means `INPUT_MESSAGES`, `INPUT_TEXT`, `OUTPUT_TEXT`,
  `OUTPUT_STREAMING`, plus `INPUT_IMAGE` only if the modality column lists
  image input, `TOOL_CALLING` only if it lists tool-calling, and
  `THINKING` only if the type column says "+ reasoning".

A family is not uniformly capable. `Qwen3.5-0.8B` is text-only while
`Qwen3.5-122B-A10B-FP8` is vision- and reasoning-capable, and both are
Qwen3.5. Do not copy another model's `capabilities` array as a starting point
without checking this specific model's row.

## Step 3: Apply the change everywhere the ID belongs

Read
`.claude/skills/synchronize-mittwald-models/references/model-touchpoints.md`.
It carries the full inventory of locations. For an addition, the rows that
usually apply are `src/ModelCatalog.php` itself, `tests/ModelCatalogTest.php`
(see Step 4), the README's model table and usage examples, and
`composer.json`'s `keywords` if this is a new model family.
`src/Factory.php`, `tests/ModelRoutingTest.php`'s `OPERATION_TYPES`, and a new
`ModelClient`/`ResultConverter`/`Model` triple only apply if Step 1 escalated
this to a new operation type.

Add the new entry to the array literal in `ModelCatalog.php` — do not
introduce a second array, a conditional branch, or a regex; every existing
entry is a plain `'model-id' => ['class' => ..., 'capabilities' => [...]]`
row, so match that shape exactly.

## Step 4: Verify

Follow
`.claude/skills/synchronize-mittwald-models/references/verifying.md`.

Add a row for the new model to `modelClassProvider()` in
`tests/ModelCatalogTest.php` — `composer test` fails until you do, because
every catalogued model has to have its intended class stated there. Assert the
capabilities Step 2 established alongside it, following the per-capability
tests already in that file.

`composer test` then also checks, via `tests/ModelRoutingTest.php`, that
exactly one `ModelClient` and one `ResultConverter` claim the new model. Read
the diff to confirm no other model's row changed.

`scripts/compare_lineup.php` in the `synchronize-mittwald-models` skill is not
needed for a single addition — it diffs the whole lineup, which is that
skill's job.

`vendor/bin/phpstan analyse` is a clean, real signal in this repo — a new
error here is worth explaining, not filtering past.

## Step 5: Commit

Reference the GitHub issue number if there is one, following this repo's
existing commit style (see recent `git log`, e.g. `feat: add <MODEL_NAME> to
ModelCatalog`).

## Step 6: Report

State the model ID as documented, the operation type and capabilities it now
has, the touchpoints changed, and anything deliberately left out of scope —
in particular any drift noticed in passing but not fixed.
