# Product Onboarding Memory

Purpose:
- keep durable lessons from recent family/code onboarding work
- stop future AI sessions from repeating the same DAM/runtime mistakes
- give one practical checklist for making new codes and products work in API + configurator + datasheet

Audience:
- AI agents
- engineers onboarding new families, product branches, and DAM assets

Last Updated:
- 2026-04-20

## Core Rule

Do not confuse these:

- code-valid
- datasheet-ready

Meaning:

- code-valid = first 10 chars exist in `Luminos` and suffix comes from valid family option sets
- datasheet-ready = valid code + real assets/data exist for PDF generation

Images do **not** validate the code.
Images only validate datasheet readiness.

## Source Of Truth Order

When sources disagree:

1. old runtime behavior in `appdatasheets/`
2. live DB truth
3. current API implementation
4. official/reference PDFs
5. narrow docs

## Product Onboarding Workflow

When adding a new family branch or product branch:

1. prove DB truth
   - confirm `Familias` row
   - confirm real `Luminos` identities
   - confirm real `product_id`
   - confirm real LED ids
2. classify runtime
   - `barra`
   - `downlight`
   - `dynamic`
   - `shelf`
   - `tubular`
   - or truly new runtime
3. make code-valid path work
   - `decode-reference`
   - `reference`
   - `options`
   - `families`
4. make datasheet runtime work
   - product image
   - drawing
   - finish image
   - color graph
   - lens diagram
   - energy label
5. keep strict validation
   - if required data missing, block honest
   - do not invent values
6. lock gold sample refs
   - at least one working ref per new branch

## DAM Rules Learned

### DAM First, Local Fallback

Current expected behavior:

- DAM first
- local fallback second

But only if DAM URL is real.

Do **not** hand TCPDF a guessed Cloudinary URL unless it was verified to exist.
Otherwise TCPDF reads a Cloudinary 404 HTML page and crashes.

### Real Asset Cloud

Current verified DAM asset cloud:

- `dofqiejpw`

Important:

- some environments still had `CLOUDINARY_CLOUD_NAME=NexledApi`
- that cloud did **not** contain the real DAM media set
- deterministic DAM URLs then returned `404 Resource not found`

So:

- API public DAM delivery must use the real asset cloud
- if env uses a placeholder cloud name, public URL generation must still resolve to the verified asset cloud

### DAM Metadata DB Can Be Down

Current live Railway health showed:

- `nexled_dam` DB broken
- `info_nexled_2024` also broken

So do not assume DAM metadata DB is available.

The runtime must survive when:

- DAM DB is down
- deterministic public Cloudinary URL still exists

## Family 01 T8 Lessons

### Scope Truth

Current T8 rollout scope:

- `01 = T8 AC` active
- `02 = T8 VC` legacy, ignore
- `03 = T8 CC` legacy, ignore

### Proven Working Branches

These refs are now the proven `01` working set:

- `01018025111010100` → base HE T8
- `01054425121010100` → HE ECO T8
- `01054491111010100` → Talho HE Pink T8

These are useful gold-sample style refs for future regression checks.

### Honest Remaining Blocker

Still blocked honestly:

- `01054481111010100`

Reason:

- plain Pink uses `3014PINK`
- no proven real color graph mapping has been recovered for `3014PINK`

Do not fake this with a random alias.

## Family 05 T5 Lessons

### Scope Truth

Current T5 rollout scope:

- `05 = T5 VC` active
- other T5 families stay legacy/out of current onboarding scope

### Proven Working Branches

These refs are now the proven base `05` working set:

- `05025725111010100`
- `05025727111010100`
- `05025732111010100`

This proves:

- base T5 VC code-valid path works
- base T5 VC DAM asset path works
- base T5 VC PDF generation works

### Special-Branch Audit

Live truth checked next:

- Pink HE is real:
  - `05025791111010100`
  - description: `LLED T5 VC 15 x 288mm Talho HE`
- base ECO is not proven for the checked branch:
  - `05025725121010100` -> `invalid_luminos_combination`
- Pink HE ECO is not proven for the checked branch:
  - `05025791121010100` -> `invalid_luminos_combination`
- plain Pink is not proven for the checked branch:
  - `05025781111010100` -> `invalid_luminos_combination`

So current safe rule:

- base T5 VC = working
- Pink HE = code-valid candidate
- ECO = not proven, keep out
- plain Pink = not proven, keep out

### Asset Rule

For family `05`, base assets are now in DAM/shared paths:

- generic T5 packshots
- clear/frost finishes
- T5 drawings
- clear/frost diagrams

Do not onboard T5 Pink/ECO only because image candidates exist in `new_data_img`.
Need live `Luminos` truth first, then branch-specific DAM mapping.

## Deterministic DAM Asset Pattern

For special T8 branches, runtime now depends on deterministic DAM-style assets such as:

- packshots
- finishes
- drawings
- temperature graphs
- energy labels

The helper path is:

- build deterministic public id
- try Cloudinary Admin/API exact lookup
- if lookup fails, build public asset URL using real asset cloud
- HEAD-check the URL
- if missing, return `null`
- then local fallback or strict blocker can take over

This avoids:

- fake URLs
- TCPDF crashes on HTML error pages

## Checklist For Future New Codes / Products

Before saying a new product branch “works”, verify all of these:

1. sample 17-char reference decodes correctly
2. first 10 chars exist in `Luminos`
3. `product_id` resolves correctly
4. runtime class is correct
5. product image resolves
6. technical drawing resolves
7. finish image resolves
8. color graph resolves
9. lens diagram resolves when required
10. energy label resolves
11. PDF generates without TCPDF image errors
12. no fake aliases or fake fallback values were introduced

## Best Next Habit

For every future family or special product branch:

- add one short family/product note here
- record one proven working ref
- record one honest blocker if something is still missing

This keeps future onboarding faster and safer.
