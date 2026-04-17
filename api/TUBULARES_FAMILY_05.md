# Tubulares Family 05

Purpose:
- document family `05` code logic for parity work
- keep catalog mask, DB truth, runtime gap, and datasheet asset notes in one place

Scope:
- family `05`
- T5 VC

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [5_Tubulares.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\5_Tubulares.pdf), family `05` appears with this mask:

- `05TTTTLLLPAXXYY00`

Best current reading:
- final `00` is fixed in catalog presentation

## DB Truth

Confirmed family row:

- `05 = T5 VC`

Confirmed `Luminos` identity examples:

| identity | product_id | led_id | description |
|---|---|---|---|
| `0500753411` | `T5/24v/10/3s` | `NW403HE` | `LLED T5 VC 15 x 90mm NW403 HE` |
| `0502253411` | `T5/24v/25/3s` | `NW403HE` | `LLED T5 VC 15 x 252mm NW403 HE` |
| `0502572511` | `T5/24v/30/3s` | `CW503` | `LLED T5 24V 15 x 288mm CW503` |
| `0502572711` | `T5/24v/30/3s` | `CW653` | `LLED T5 24V 15 x 288mm CW653` |
| `0502573211` | `T5/24v/30/3s` | `WW303HE` | `LLED T5 24V 15 x 288mm WW303 HE` |

## Runtime State

Current live API state:

- family `05` is mapped as `tubular` in the live decoder/runtime
- datasheet runtime now allows family `05` to enter the PDF pipeline
- family `05` still fails honestly when required tubular assets are missing

So:
- family `05` exists in DB/catalog
- family `05` is now first-class in live API runtime
- current blocker is real tubular asset/data completeness, not family mapping

## Family 05 Segment Map

### Catalog-facing reading

| block | likely meaning |
|---|---|
| `05` | family |
| `TTTT` | size / tube length block |
| `LLLP` | LED/CCT/CRI/power block |
| `A` | lens |
| `XX` | finish / body |
| `YY` | connection / fixing block |
| fixed `00` | fixed final suffix |

## Code Validity Rule

Best current rule:

- valid when first 10 chars exist in `Luminos`
- suffix values should come from family option lists

## Datasheet Asset Source

Still not traced in live runtime.
Future readiness likely depends on:

- `appdatasheets/img/05/...` or T5-specific folders
- DAM family folder `nexled/10_products/families/05_t5-vc/...`
- drawings
- product/LED description JSON
- common graphs/diagrams where relevant

## Current Gaps

1. no visible `appdatasheets/img/05` asset tree exists in repo now
2. family `05` now has DAM path mapping, but no confirmed DAM T5 assets have been loaded yet
3. official fixed `00` not yet proven as hard runtime rule

## 2026-04-17 Blocker Verification

Family `05` was re-checked directly during DAM rollout continuation.

What is now confirmed:

- valid live T5 references do exist, for example:
  - `05025725111010100`
- this decodes and resolves to a real DB product:
  - `T5/24v/30/3s`
- live datasheet runtime reaches the strict missing-data gate and fails honestly with:
  - `Missing required data: product image`
- local DAM state is still empty for family `05`:
  - `dam_asset_links` count for family `05` = `0`
- no `appdatasheets/img/05` tree exists in this repo
- sibling legacy app at `C:\xampp\htdocs\appDatasheets\img` also has no `05` folder

Meaning:

- family `05` is **not** blocked by decoder/runtime/product-ID logic anymore
- family `05` is blocked by missing real T5 image source files
- safe next move is asset recovery/discovery, not API rewrite

Operational rule:

- do not make family `05` DAM-primary until real T5 assets are recovered
- do not invent placeholder imports for T5 just to advance rollout state

## Best Next Follow-Up

- restore/import real T5 assets into local legacy tree or DAM family `05_t5-vc`
- compare one old-vs-new T5 gold sample
