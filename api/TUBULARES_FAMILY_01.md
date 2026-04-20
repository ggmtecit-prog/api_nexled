# Tubulares Family 01

Purpose:
- document family `01` code logic for parity work
- keep catalog mask, DB truth, runtime gap, and datasheet asset notes in one place

Scope:
- family `01`
- T8 AC

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [5_Tubulares.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\5_Tubulares.pdf), family `01` appears with this mask:

- `01TTTTLLLPAXXYY00`

Best current reading:
- final `00` is fixed in catalog presentation

## DB Truth

Confirmed family row:

- `01 = T8 AC`

Confirmed `Luminos` identity examples:

| identity | product_id | led_id | description |
|---|---|---|---|
| `0101800211` | `T8LED/23/3s` | `3528XN` | `T8 LED 23 cm` |
| `0101800611` | `T8LED/23/3s` | `3528HS` | `T8 LED 23 cm` |
| `0101801011` | `T8LED/23/3s` | `3528WW` | `T8 LED 23 cm` |
| `0101801211` | `T8LED/23/3s` | `3528WY12` | `T8 LED 23 cm` |
| `0101802511` | `T8/PC/22/3s` | `CW503` | `LLED T8 26 x 228mm CW503` |
| `0105442512` | `T8/AL/60/2s` | `CW503` | `LLED T8 26 x 590mm CW503 ECO` |
| `0105449111` | `T8PINK/AL/60/3s` | `2835PINK` | `LLED T8 26 x 590mm Talho HE` |
| `0105448111` | `T8PINK/60/3s` | `3014PINK` | `T8 LED 60 cm PINK` |

## Runtime State

Current live API state:

- family `01` is mapped as `tubular` in the live decoder/runtime
- datasheet runtime now allows family `01` to enter the PDF pipeline
- family `01` now resolves DAM-first product assets for multiple real branches
- family `01` now has multiple proven working branches
- family `01` still fails honestly when required tubular assets or graphs are missing

So:
- family `01` exists in DB/catalog
- family `01` is now first-class in live API runtime
- current blocker is real tubular asset/data completeness, not family mapping

## Family 01 Segment Map

### Catalog-facing reading

| block | likely meaning |
|---|---|
| `01` | family |
| `TTTT` | size / tube length block |
| `LLLP` | LED/CCT/CRI/power block |
| `A` | lens |
| `XX` | finish / body |
| `YY` | fixing / connection / mounting block |
| fixed `00` | fixed final suffix |

## Code Validity Rule

Best current rule:

- valid when first 10 chars exist in `Luminos`
- suffix values should come from family option lists

## Datasheet Asset Source

Current live runtime now uses:

- `appdatasheets/img/01/...` or T8-specific folders
- DAM family folder `nexled/10_products/families/01_t8-ac/...`
- drawings
- product/LED description JSON
- common graphs/diagrams where relevant

## Current Gaps

1. classic `3528HS` / `3528WW` / `3528WY12` rows still miss proven color-graph coverage
2. plain pink `3014PINK` still misses a proven color-graph mapping
3. official fixed `00` not yet proven as hard runtime rule
4. broader `01` size/lens/cap coverage still needs DAM import/mapping

## 2026-04-20 Smoke Verification

Family `01` was re-checked directly after first T8 asset import.

What is now confirmed:

- valid live T8 references do exist, for example:
  - `01018025111010100`
- this decodes and resolves to a real DB product:
  - `T8/PC/22/3s`
- first curated local asset set now exists in repo:
  - `appdatasheets/img/01/produto/...`
  - `appdatasheets/img/01/acabamentos/...`
  - `appdatasheets/img/01/desenhos/0180.svg`
  - `appdatasheets/img/01/diagramas/1.svg`
  - `appdatasheets/img/01/diagramas/2.svg`
- local DAM state is still empty for family `01`:
  - `dam_asset_links` count for family `01` = `0`
- sibling legacy app at `C:\xampp\htdocs\appDatasheets\img` also has no `01` folder
- local datasheet POST for `01018025111010100` now returns a real PDF successfully
- one PDF-path bug was found and fixed during this test:
  - local SVG assets must be passed to TCPDF as absolute paths, not inline base64 blobs

## 2026-04-20 Branch Verification

Family `01` was then expanded and re-checked for ECO and Pink branches using DAM-first lookups.

What is now confirmed:

- HE ECO rows are real and can be made datasheet-ready:
  - example `01054425121010100`
  - product id `T8/AL/60/2s`
  - graph `CW503`
- Talho HE Pink rows are real and can be made datasheet-ready:
  - example `01054491111010100`
  - product id `T8PINK/AL/60/3s`
  - graph `2835PINK`
- both of the above now resolve:
  - DAM packshot
  - DAM drawing
  - DAM finish
  - DAM color graph
  - diagram path
  - with strict validator passing
- live/public DAM delivery now uses the real verified asset cloud:
  - `dofqiejpw`
- deterministic DAM URLs are now HEAD-checked before use
- this prevents TCPDF from crashing on Cloudinary 404 HTML pages
- plain Pink rows are real in `Luminos` but still blocked honestly:
  - example `01054481111010100`
  - product id `T8PINK/60/3s`
  - led id `3014PINK`
  - current blocker: `Missing required data: color graph`

Meaning:

- family `01` support is now broader than base T8 only
- truthful working branches now include:
  - base HE T8
  - HE ECO T8
  - Talho HE Pink T8
- proven working refs:
  - `01018025111010100`
  - `01054425121010100`
  - `01054491111010100`
- plain Pink is **not** fully implemented yet because no proven `3014PINK` graph rule has been recovered

Meaning:

- family `01` is **not** blocked by decoder/runtime/product-ID logic anymore
- family `01` now has one working seeded sample path
- remaining work is broader asset coverage and parity, not first-runtime rescue

Operational rule:

- do not make family `01` DAM-primary until real T8 assets are recovered
- do not invent placeholder imports for T8 just to advance rollout state
- keep `3014PINK` blocked until a real graph source is proven
- use [PRODUCT_ONBOARDING_MEMORY.md](./PRODUCT_ONBOARDING_MEMORY.md) for future branch onboarding lessons

## Best Next Follow-Up

- expand/import more real T8 assets into local legacy tree or DAM family `01_t8-ac`
- compare one old-vs-new T8 gold sample
