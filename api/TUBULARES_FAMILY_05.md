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

Current live API gap:

- family `05` is not mapped in current `getProductType()`

So:
- family `05` exists in DB/catalog
- family `05` not yet first-class in live API runtime

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
- drawings
- product/LED description JSON
- common graphs/diagrams where relevant

## Current Gaps

1. no live API product-type mapping for `05`
2. no confirmed tubular asset path documented yet
3. official fixed `00` not yet proven as hard runtime rule

## Best Next Follow-Up

- trace whether old project had dedicated T5 runtime path
