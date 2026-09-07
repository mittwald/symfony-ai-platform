---
name: synchronize-mittwald-models
description: Reconcile this bridge's ModelCatalog with the models mittwald AI Hosting currently offers, and assert that every offered model is routed to the right operation type with the right capabilities. Use when models are added or removed upstream, when a capability list looks stale, or as a periodic audit. For adding one specific known model, use add-model instead.
allowed-tools: Read, Edit, Bash, Grep, Glob, WebFetch
---

# Synchronize mittwald models

Bring `ModelCatalog.php` back in sync with what mittwald AI Hosting actually
offers, and prove the result. This is an audit-and-reconcile workflow: it ends
with a report of what matches, what drifted, and what was changed.

**Never guess a model ID, an endpoint, or a capability.** Every claim in this
repo has to trace back to the mittwald documentation or to an observed API
response. A guessed model ID that does not exist matches nothing in the
catalog either — `getModel()` just throws `ModelNotFoundException` for it —
but a guessed *capability* on a real model is worse: it claims the model can
do something it can't, and nothing catches that until a caller relies on it.

## Step 1: Collect the sources

### mittwald documentation

The URL layout is not guessable — derive it from the sitemap rather than
assembling paths by hand:

```bash
curl -s https://developer.mittwald.de/sitemap.xml | grep -o '[^<]*aihosting[^<]*'
```

The pages that matter:

| Page | URL |
| --- | --- |
| Model table | `https://developer.mittwald.de/docs/v2/platform/aihosting/models/` |
| Supported endpoints | `https://developer.mittwald.de/docs/v2/platform/aihosting/api-endpoints/supported-endpoints/` |
| Deviations and limitations | `https://developer.mittwald.de/docs/v2/platform/aihosting/api-endpoints/deviations-and-limitations/` |
| Errors | `https://developer.mittwald.de/docs/v2/platform/aihosting/api-endpoints/errors/` |

Note the segment is `api-endpoints/`, not `endpoints/`. The plausible-looking
`.../aihosting/endpoints/supported/` returns 404.

When fetching the model table, ask for it **verbatim, every row**. A
summarising prompt will silently drop models, and a dropped model reads
exactly like a removed one. Ask for the exact IDs, the type, and the
modalities column — modality is what decides capability claims, not the
family name.

If `developer.mittwald.de` is blocked by sandbox network policy, ask the user
to run `sbx policy allow network developer.mittwald.de` on the host — do not
proceed on a guessed or remembered table.

### symfony/ai-platform documentation

The dependency is vendored via composer, so read it from disk rather than the
web — it is guaranteed to match the installed version:

- `vendor/symfony/ai-platform/src/Capability.php` — the full capability enum;
  `ModelCatalog.php` currently only uses four of its cases (see
  `references/model-touchpoints.md`).
- `vendor/symfony/ai-platform/src/Model.php`, `ModelClientInterface.php`,
  `ResultConverterInterface.php` — the contract a new operation type has to
  satisfy.
- `vendor/symfony/ai-platform/src/ModelCatalog/AbstractModelCatalog.php` — how
  catalog lookup actually works, including the `base:variant` and
  `?query=string` parsing every model name goes through.
- `vendor/symfony/ai-platform/src/Platform.php` — how `invoke()` picks a
  `ModelClient` for a resolved `Model` (the first one whose `supports()`
  returns true, across **all** registered providers — see the over-match note
  in `references/model-touchpoints.md` about more than one client claiming a
  model class).

## Step 2: Probe for undocumented endpoints

The supported-endpoints page can lag reality. Distinguish "missing from the
docs" from "does not exist" by status code — an unauthenticated request is
enough, no API key needed:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST \
  https://llm.aihosting.mittwald.de/v1/<endpoint> \
  -H "Content-Type: application/json" -d '{}'
```

`401`/`403` means the endpoint exists and rejected the unauthenticated
request. `404` means it does not exist. Always probe a known-good path
(`/v1/embeddings`) and a nonsense path in the same run, so the two codes are
calibrated against that day's gateway behaviour. If the sandbox network policy
blocks `llm.aihosting.mittwald.de`, ask the user to allow it before trusting a
probe result — a blanket `403` from the proxy looks identical to an
authenticated-endpoint `403` and will misclassify every path as "exists".

If an endpoint is real but undocumented, its optional parameters are unknown.
Send only what a working example needs and record the uncertainty — do not
invent parameters.

## Step 3: Inventory every place a model ID appears

Model IDs hide in more places than `ModelCatalog.php`. The full inventory —
every location and why `PlatformFactory::create()`'s client-wiring list is the
one that bites — lives in `references/model-touchpoints.md`. Read it and visit
every row before concluding anything is in sync.

That reference is shared with the `add-model` skill, so a location discovered
during an audit is immediately in force for additions too. Add newly found
locations there rather than here.

## Step 4: Reconcile the catalog

For every model in the documented lineup, confirm the catalog entry exists
with the right `class` and exactly the capabilities its modality column
supports, and that retired models are absent.

Rules:

- **Retired model** — remove its entry from `ModelCatalog.php`, plus its
  README and `composer.json` mentions. If it appeared in a README code
  example, repoint the example at a surviving model. There is no update-hook
  equivalent to write (see `references/model-touchpoints.md` — "Retiring a
  model"); state the removal plainly in the commit/PR instead, since it is a
  breaking change for any caller still passing that model name.
- **New model** — out of scope here unless explicitly asked. Use the
  `add-model` skill, which walks the same touchpoints for a single addition.
- **Capability claims must come from the model table's modality column**, not
  from the family name or from a sibling entry. A family is not uniformly
  capable — see `references/model-touchpoints.md`'s Qwen3.5 example.
- **A model whose operation type this bridge doesn't implement yet** (a new
  endpoint like rerank, text-to-speech, or OCR, rather than a new chat/
  embeddings/whisper model) needs a new `Model` subclass, a new
  `ModelClient`/`ResultConverter` pair, and a new entry in
  `PlatformFactory::create()`'s client list — not just a catalog row. Flag it
  and scope it explicitly rather than half-wiring it.

## Step 5: Verify

Follow `references/verifying.md`: `scripts/verify_catalog.php` first, then
`vendor/bin/phpstan analyse`. Update the `$current` and `$retired` arrays in
`verify_catalog.php` to the lineup fetched in Step 1 before reading its
output — that is what turns it from a static check into an audit.

A clean `phpstan analyse` here is a real signal — `symfony/ai-platform`
resolves as a normal, fully-typed dependency, so there is no pre-existing
noise to route around.

## Step 6: Report

State plainly:

- models offered upstream vs. models this catalog routes, and any gap either
  way
- capability claims that do not match the documented modalities
- endpoints offered upstream but not implemented, and vice versa
- what changed, and what was deliberately left out of scope

Flag drift you did not fix rather than silently widening scope.

## Snapshot: 2026-09-04

The lineup at the time this skill was written, for drift comparison. **Re-fetch
rather than trusting this table** — it is a baseline, not a source.

| Model | Type | Modalities |
| --- | --- | --- |
| `gpt-oss-120b` | Chat + reasoning | text, tool-calling |
| `Qwen3.5-0.8B` | Chat + reasoning | text, tool-calling |
| `Ministral-3-14B-Instruct-2512` | Chat + vision | text, image, tool-calling |
| `Qwen3.5-122B-A10B-FP8` | Chat + reasoning + vision | text, image, tool-calling |
| `Qwen3.6-35B-A3B-FP8` | Chat + reasoning + vision | text, image, tool-calling |
| `Qwen3.8-27B-NVFP4` | Chat + reasoning + vision | text, image, tool-calling |
| `GLM-OCR` | Document OCR | PDF, DOCX, PPTX, XLSX, HTML, SVG, image to text — despite the "Document OCR" type label, its own doc page confirms it is served via the existing `/v1/chat/completions` endpoint (mittwald's document proxy converts input pages to PNG before forwarding as a normal chat request), so it is a `ChatModel` catalog entry, not a new operation type |
| `Qwen3-Embedding-8B` | Embedding | text to vector |
| `Qwen3-VL-Reranker-2B` | Reranking | text, image to score |
| `whisper-large-v3-turbo` | Speech-to-Text | audio to text |
| `Qwen3-TTS-12Hz-1.7B-CustomVoice` | Text-to-Speech | text to audio |

Supported endpoints documented at the same date: `/v1/models`,
`/v1/chat/completions`, `/v1/completions`, `/v1/responses` (experimental,
OpenAI Responses API shape), `/v1/embeddings`, `/v1/audio/transcriptions`,
`/v1/audio/speech`. `/v1/rerank` was not on that page despite
`Qwen3-VL-Reranker-2B` being offered in the model table — treat rerank support
as needing the Step 2 probe before relying on it, not as confirmed by this
table.

Known drift observed at that date, verified with `verify_catalog.php` (not
guessed):

- `Qwen3.5-0.8B`, `Qwen3.8-27B-NVFP4`, `Qwen3-VL-Reranker-2B`,
  `Qwen3-TTS-12Hz-1.7B-CustomVoice`, and `whisper-large-v3-turbo` (the
  lowercase upstream ID) are offered upstream but have no `ModelCatalog.php`
  entry. (`GLM-OCR` was in this list too; it now has a `ChatModel` catalog
  entry — see above.)
- `Devstral-Small-2-24B-Instruct-2512` is in `ModelCatalog.php` but does not
  appear in the current upstream table — possibly retired. Confirm with
  mittwald or a fresh probe before removing it; absence from one fetch of the
  table is a signal, not confirmation.
- `Qwen3.5-122B-A10B-FP8` and `Qwen3.6-35B-A3B-FP8` are documented as
  "Chat + reasoning + vision" but their catalog entries carry no
  `Capability::THINKING`.
- The catalog's `Whisper-Large-V3-Turbo` key differs in case from the
  documented `whisper-large-v3-turbo`; confirm whether the mittwald API
  matches model IDs case-insensitively before treating this as a bug — if it
  does not, every whisper call through this bridge is silently broken.

None of the above was fixed as part of porting this skill — it is exactly the
kind of finding a `synchronize-mittwald-models` run should act on.
