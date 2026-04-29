# Downlights Family 30

Purpose:
- document family `30` code logic for parity work
- keep catalog mask, DB truth, runtime support, and datasheet asset notes in one place

Scope:
- family `30`
- Downlight quadrado

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [4_Downlights.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\4_Downlights.pdf), family `30` appears with this mask:

- `30TTTTLLLPAXX0000`

Best current reading:
- final `0000` is fixed in catalog presentation

## Current Internal Normalized Mask

Current live/internal system still uses normalized model:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `30`, current live API already supports this family as `downlight`.

## DB Truth

Confirmed family row:

- `30 = Downlight quadrado`

Confirmed `Luminos` identity examples:

| identity | product_id | led_id | description |
|---|---|---|---|
| `3011113118` | `DL/110/350/Q` | `WW273HE` | `LLED 4L 11x11cm WW273 HE PL5W` |
| `3011113128` | `DL/110/350/Q` | `WW273HEPRO` | `LLED 4L 11x11cm WW273 HE PRO PL5W` |
| `3011113218` | `DL/110/350/Q` | `WW303HE` | `LLED 4L 11x11cm WW303 HE PL5W` |
| `3011113228` | `DL/110/350/Q` | `WW303HEPRO` | `LLED 4L 11x11cm WW303 HE PRO PL5W` |
| `3011113418` | `DL/110/350/Q` | `NW403HE` | `LLED 4L 11x11cm NW403 HE PL5W` |

## Runtime State

Current live API:

- already maps family `30` to product type `downlight`

So family `30` is active runtime family already.

## Family 30 Segment Map

### Catalog-facing reading

| block | likely meaning |
|---|---|
| `30` | family |
| `TTTT` | size / body size block |
| `LLLP` | LED/CCT/CRI/power block |
| `A` | lens |
| `XX` | finish |
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

## Datasheet Asset Source

Likely sources:

- `appdatasheets/img/30/...`
- shared color graphs
- shared lens/diagram assets
- product/LED JSON descriptions

## Current Gaps

1. official fixed `0000` not yet proven as hard runtime rule
2. full old-page order parity still not verified

## Best Next Follow-Up

- compare live family `30` PDF against official square downlight sample
