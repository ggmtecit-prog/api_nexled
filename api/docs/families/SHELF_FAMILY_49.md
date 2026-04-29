# Shelf Family 49

Purpose:
- document family `49` code logic for parity work
- keep catalog mask, DB truth, runtime gap, and datasheet asset risk in one place

Scope:
- family `49`
- ShelfLED / Shelf LED 24V

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [2_Shelf.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\2_Shelf.pdf), family `49` appears with this mask:

- `49TTTTLLLPA01YY00`

Simple reading:
- Shelf family is tightly constrained
- fixed middle `01`
- fixed final `00`

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal project generally uses:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `49`, safest current reading is:

`49 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

But catalog strongly suggests:
- some suffix blocks are effectively fixed for Shelf

## DB Truth

Confirmed family row:

- `49 = ShelfLED`

Confirmed `Luminos` identity examples:

| identity | product_id | led_id | description |
|---|---|---|---|
| `4904502411` | `ShelfLED/24v/47` | `NW403` | `LLED ShelfLED 47 NW403` |
| `4904502415` | `ShelfLED/24v/47/Eco` | `NW403` | `LLED ShelfLED 47 NW403 ECO DL` |
| `4904502421` | `ShelfLED/24v/47` | `NW403Pro` | `LLED ShelfLED 47 NW403 Pro` |
| `4904502425` | `ShelfLED/24v/47/Eco` | `NW403Pro` | `LLED ShelfLED 47 NW403 Pro ECO DL` |
| `4904502511` | `ShelfLED/24v/47` | `CW503` | `LLED ShelfLED 47 CW503` |

## Old Runtime / Live Runtime State

Current live API state:

- family `49` is mapped as `shelf` in the live decoder/runtime
- datasheet runtime now allows Shelf to enter the PDF pipeline
- Shelf asset lookup now checks local legacy paths first and DAM product assets second
- Shelf still fails honestly when required Shelf assets are missing

So today:
- family `49` exists in DB
- family `49` exists in catalog reference
- family `49` is now first-class in live API runtime
- Shelf datasheets are still blocked by missing real assets, not by family mapping

## Family 49 Segment Map

### Catalog-facing reading

| block | likely meaning |
|---|---|
| `49` | family |
| `TTTT` | size |
| `LLLP` | LED / CCT / CRI / power-style block |
| `A` | lens |
| fixed `01` | constrained family-specific suffix block |
| `YY` | connection / mount / terminal-related variable block |
| fixed `00` | fixed final option block |

### Runtime-safe reading

Until runtime support is traced, safest truth is:

- first 10 chars define identity in `Luminos`
- remaining suffix should come from family option tables
- catalog may collapse some of those suffix blocks into fixed values

## Concrete Catalog Clues

Visible product naming in PDF includes:

- `Shelf LED 24V`
- `ShelfLED 85 WW303 HE Frost Alu ASQC2 0.17`

This strongly suggests Shelf references encode:
- size
- LED/CCT/CRI block
- lens
- finish
- connection/terminal suffix

## Code Validity Rule

Best current rule:

- family `49` code validity should come from:
  - first 10 chars in `Luminos`
  - allowed family option lists

Not from:
- images
- drawings
- PDF layout

## Datasheet Asset Source

Still unknown / not yet traced in repo.

Future Shelf datasheet readiness likely depends on:
- product image assets
- technical drawing assets
- color graph assets
- lens diagrams if used
- product/LED description JSON

## Current Gaps

1. no confirmed Shelf legacy datasheet path documented yet
2. no confirmed Shelf asset folder structure documented yet
3. current repo has no visible `appdatasheets/img/49` asset tree
4. current local DAM metadata also has no Shelf assets yet
5. catalog mask likely tighter than generic normalized model

## 2026-04-17 Blocker Verification

Family `49` was re-checked directly before DAM rollout work resumed.

What is now confirmed:

- valid live Shelf references do exist, for example:
  - `49045024110010100`
  - `49045024112010100`
  - `49045024111010100`
- these decode and resolve to real DB products such as:
  - `ShelfLED/24v/47`
- live datasheet runtime reaches the strict missing-data gate and fails honestly with:
  - `Missing required data: product image`
- local DAM state is still empty for Shelf:
  - `dam_asset_links` count for family `49` = `0`
  - no `dam_assets` rows were found with Shelf-like names
- current repo still has no `appdatasheets/img/49` tree
- sibling legacy app at `C:\xampp\htdocs\appDatasheets\img` also has no `49` folder

Meaning:

- family `49` is **not** blocked by decoder/runtime/product-ID logic anymore
- family `49` is blocked by missing real image source files
- safe next move is asset recovery/discovery, not API rewrite

Operational rule:

- do not make family `49` DAM-primary until real Shelf assets are recovered
- do not invent placeholder imports for Shelf just to advance rollout state
- skip to next family batch until a real Shelf asset source is provided

## Best Next Follow-Up

1. trace if old project had dedicated Shelf runtime flow
2. inspect Shelf family option tables in `tecit_referencias`
3. ingest/restore real Shelf image assets into legacy tree or DAM
4. compare one old-vs-new Shelf gold sample
