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

## Runtime State

Current live API state:

- family `01` is mapped as `tubular` in the live decoder/runtime
- datasheet runtime now allows family `01` to enter the PDF pipeline
- family `01` still fails honestly when required tubular assets are missing

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

Still not traced in live runtime.
Future readiness likely depends on:

- `appdatasheets/img/01/...` or T8-specific folders
- drawings
- product/LED description JSON
- common graphs/diagrams where relevant

## Current Gaps

1. no visible `appdatasheets/img/01` asset tree exists in repo now
2. no current DAM tubular assets are documented/mapped
3. official fixed `00` not yet proven as hard runtime rule

## Best Next Follow-Up

- restore/import real T8 assets
- compare one old-vs-new T8 gold sample
