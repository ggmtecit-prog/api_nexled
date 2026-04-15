# Downlights Family 29

Purpose:
- document family `29` code logic for parity work
- keep catalog mask, DB truth, runtime support, and datasheet asset notes in one place

Scope:
- family `29`
- Downlight redondo

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [4_Downlights.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\4_Downlights.pdf), family `29` appears with this mask:

- `29TTTTLLLPAXX0000`

Best current reading:
- final `0000` is fixed in catalog presentation

## Current Internal Normalized Mask

Current live/internal system still uses normalized model:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `29`, current live API already supports this family as `downlight`.

## DB Truth

Confirmed family row:

- `29 = Downlight redondo`

Confirmed `Luminos` identity examples:

| identity | product_id | led_id | description |
|---|---|---|---|
| `2901202219` | `DL/120/200/R` | `WW303` | `LLED Downlight 120mm WW303 PL6.5W` |
| `2901202419` | `DL/120/200/R` | `NW403` | `LLED Downlight 120mm NW403 PL6.5W` |
| `2901202619` | `DL/120/200/R` | `CW573` | `LLED Downlight 120mm CW573 PL6,5W` |
| `2901203119` | `DL/120/200/R` | `WW273HE` | `LLED Downlight 120mm WW273 HE PL6.5W` |
| `2901203129` | `DL/120/200/R` | `WW273HEPRO` | `LLED Downlight 120mm WW273 HE PRO PL6.5W` |

## Runtime State

Current live API:

- already maps family `29` to product type `downlight`

So family `29` is not just research.
It is active runtime family already.

## Family 29 Segment Map

### Catalog-facing reading

| block | likely meaning |
|---|---|
| `29` | family |
| `TTTT` | size / cutout / body size block |
| `LLLP` | LED/CCT/CRI/power block |
| `A` | lens |
| `XX` | finish / body finish |
| fixed `0000` | constrained final suffix |

### Runtime-safe reading

Until deeper old-code trace says otherwise:

- first 10 chars define identity in `Luminos`
- normalized live suffix fields still exist
- catalog likely compresses those suffixes into a tighter fixed tail

## Code Validity Rule

Best current rule:

- valid when first 10 chars exist in `Luminos`
- suffix values come from family option tables

Not from:
- images
- drawings
- PDF sections

## Datasheet Asset Source

Likely sources:

- `appdatasheets/img/29/...`
- shared color graphs
- shared lens/diagram assets
- product/LED JSON descriptions

## Current Gaps

1. official fixed `0000` not yet proven as hard runtime rule
2. full old-page order parity still not verified

## Best Next Follow-Up

- compare live family `29` PDF against official downlight sample
