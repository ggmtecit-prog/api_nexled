# Next AI Prompt: Code Explorer Only

You are working in the NexLed repo at `c:\xampp\htdocs\api_nexled`.

Your scope is **only** the `code-explorer` page and its backend logic.

This is a hard boundary:

- Work on `configurator/code-explorer.html`
- Work on `configurator/code-explorer.js`
- Work on `api/endpoints/code-explorer.php`
- Work on `api/lib/code-explorer.php`
- Work on explorer-related locale strings if needed

Do **not** work on datasheet/PDF runtime logic in this task.

- Do not patch `api/lib/pdf-engine.php`
- Do not patch `api/lib/product-header.php`
- Do not patch `api/lib/technical-drawing.php`
- Do not patch `api/lib/sections.php`
- Do not patch other datasheet-generation files unless an explorer-only change strictly requires a tiny safe adjustment

This separation matters:

- **This chat/thread is for datasheet logic**
- **Your task is only code-explorer backend/frontend logic**

## Mission

Improve the Code Explorer so it can inspect NexLed Tecit code validity safely and usefully, without changing existing endpoint behavior outside the explorer.

The explorer should help answer:

- which codes are valid
- which codes are blocked
- why they are blocked
- which families are fully supported
- which families are recognized but still unsupported for datasheet runtime

## Mandatory reading order

Read these first:

1. [PROJECT_MEMORY.md](PROJECT_MEMORY.md)
2. [api/CODE_VALIDITY_EXPLORER_PLAN.md](api/CODE_VALIDITY_EXPLORER_PLAN.md)
3. [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)

Then read these explorer implementation files:

1. [configurator/code-explorer.html](configurator/code-explorer.html)
2. [configurator/code-explorer.js](configurator/code-explorer.js)
3. [api/endpoints/code-explorer.php](api/endpoints/code-explorer.php)
4. [api/lib/code-explorer.php](api/lib/code-explorer.php)

Then read UI system docs before changing page UI:

1. [configurator/UI_SYSTEM/RTK.md](configurator/UI_SYSTEM/RTK.md)
2. [configurator/UI_SYSTEM/COMPONENTS.md](configurator/UI_SYSTEM/COMPONENTS.md)
3. [configurator/UI_SYSTEM/docs/README.md](configurator/UI_SYSTEM/docs/README.md)
4. [configurator/UI_SYSTEM/docs/guides/PAGE_RECIPES.md](configurator/UI_SYSTEM/docs/guides/PAGE_RECIPES.md)
5. [configurator/UI_SYSTEM/docs/guides/RESPONSIVE_RULES.md](configurator/UI_SYSTEM/docs/guides/RESPONSIVE_RULES.md)
6. [configurator/UI_SYSTEM/src/nexled.css](configurator/UI_SYSTEM/src/nexled.css)
7. [configurator/UI_SYSTEM/src/nexled.js](configurator/UI_SYSTEM/src/nexled.js)

If you need family/code context for explorer reasoning, use these as references:

- [api/BARRAS_CODE_MASK_MATRIX.md](api/BARRAS_CODE_MASK_MATRIX.md)
- [api/SHELF_CODE_MASK_MATRIX.md](api/SHELF_CODE_MASK_MATRIX.md)
- [api/DOWNLIGHTS_CODE_MASK_MATRIX.md](api/DOWNLIGHTS_CODE_MASK_MATRIX.md)
- [api/TUBULARES_CODE_MASK_MATRIX.md](api/TUBULARES_CODE_MASK_MATRIX.md)

## RTK requirement

Use RTK for shell work.

- Read: [C:\Users\USER\.codex\RTK.md](C:\Users\USER\.codex\RTK.md)
- Prefer `rtk` command wrappers for git, search, diffs, and checks

Examples:

- `rtk git status --short`
- `rtk git diff -- api/lib/code-explorer.php`
- `rtk rg "code-explorer" configurator api`

## Skill / working style requirements

Use these repo/user preferences:

- Use `$caveman` style for user-facing updates: short, direct, low-noise
- Respect repo instructions in `AGENTS.md` and `RTK.md`
- Use `apply_patch` for manual edits
- Do not revert unrelated work
- There may be unrelated dirty files in the worktree; do not stage them

## Current explorer truth

The explorer already exists.

Current key behavior:

- New endpoint: `GET /api/?endpoint=code-explorer`
- Families can be:
  - fully supported for datasheet runtime
  - recognized but not yet runtime-supported
  - documented but not yet mapped
- Explorer can already surface:
  - `unsupported_datasheet_runtime`

Current known explorer problem areas:

- invalid full-family matrix explodes for large families
- family-only invalid scans are too large
- valid-only mode is the practical default
- explorer must remain separate from datasheet runtime work

## Hard rules

1. Do not change existing non-explorer endpoint behavior.
2. One new explorer-only read path is fine; broad API behavior drift is not.
3. Do not invent product data.
4. Do not use image existence to decide code validity.
5. Keep the separation:
   - code validity
   - datasheet readiness
6. If you need to expose new failure reasons, do it through explorer-safe logic.

## What “valid” means

Keep this distinction clear:

- **Code valid**
  - determined by family logic + `Luminos` + allowed option logic
- **Datasheet ready**
  - valid code plus enough real data/assets to build a datasheet

Images do **not** validate code validity.
Images validate datasheet readiness only.

## Best next explorer work

Preferred direction:

1. Keep `Include invalid combinations` off by default
2. Make valid-only mode fast and stable
3. Add narrower invalid exploration only when user gives more filters
4. Avoid full-family invalid Cartesian explosions
5. Consider identity-first drill-down if needed

Good explorer improvements:

- better pagination
- narrower invalid filtering
- size/color/CRI/series drill-down
- clearer failure reasons
- better family summary
- CSV/export only if it stays explorer-only

Bad improvements:

- broad datasheet runtime patches
- fake fallback data
- changes to normal configurator flow that are unrelated to explorer

## Current family runtime snapshot

Use this as working truth unless docs update later:

- Datasheet runtime supported:
  - `11`, `32`, `55`, `58`, `60`, `29`, `30`, `48`
- Recognized, but datasheet runtime not mapped yet:
  - `49`, `01`, `05`
- Documented/researched, but not yet mapped in live runtime:
  - `31`, `40`

## Files you are most likely allowed to change

- `configurator/code-explorer.html`
- `configurator/code-explorer.js`
- `configurator/locales/en.js`
- `configurator/locales/pt.js`
- `api/endpoints/code-explorer.php`
- `api/lib/code-explorer.php`

## Files you should avoid

- `api/lib/pdf-engine.php`
- `api/lib/product-header.php`
- `api/lib/technical-drawing.php`
- `api/lib/sections.php`
- other datasheet layout/render files

## Git safety

The repo may already have unrelated dirty files.

Before committing:

1. inspect `git status`
2. stage only explorer-related files you changed
3. do not include unrelated PNG/assets/docs unless task explicitly needs them

## Expected output style

When reporting back:

- be short
- say what was changed
- say what was verified
- say what still blocked explorer
- keep datasheet-runtime issues clearly out of scope unless they directly affect explorer output

