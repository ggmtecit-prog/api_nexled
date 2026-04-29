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
- base family `05` DAM asset path is now implemented for:
  - product image
  - finish image
  - technical drawing
  - lens diagram

So:
- family `05` exists in DB/catalog
- family `05` is now first-class in live API runtime
- base T5 VC path is no longer blocked by missing core assets

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

Current proven base family `05` runtime uses:

- DAM-first deterministic shared asset paths:
  - `nexled/datasheet/packshots/generic`
  - `nexled/datasheet/finishes/clear`
  - `nexled/datasheet/finishes/frost`
  - `nexled/datasheet/drawings`
  - `nexled/datasheet/diagrams`
- shared DAM/local fallback assets for:
  - color graphs
  - energy labels
  - logos/icons
- local fallback remains allowed when DAM/shared asset is missing

## Current Gaps

1. official fixed `00` not yet proven as hard runtime rule
2. T5 Pink HE is code-valid in live truth, but special Pink packshot path is not onboarded yet
3. T5 ECO is not yet proven in live `Luminos`, so it must stay out of scope

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

## 2026-04-20 Base T5 VC Rollout

Family `05` was then completed for the base T5 VC branch.

What is now proven:

- these refs generate real PDFs successfully:
  - `05025725111010100`
  - `05025727111010100`
  - `05025732111010100`
- the base product id remains:
  - `T5/24v/30/3s`
- shared graphs already resolve for:
  - `CW503`
  - `CW653`
  - `WW303HE`
- T5 VC DAM assets were uploaded into the real asset cloud `dofqiejpw`
- proven asset classes now in DAM:
  - base clear/frost packshots
  - base finish image
  - base drawings
  - clear/frost diagrams

Meaning:

- family `05` base T5 VC path is now datasheet-ready
- family `05` is no longer just runtime-supported; it has a proven working PDF branch

## 2026-04-20 Pink / ECO Audit

Special T5 branches were checked next against live API truth.

What is proven:

- Pink HE row is real in live `Luminos`:
  - `05025791111010100`
  - description: `LLED T5 VC 15 x 288mm Talho HE`
- plain Pink is **not** proven for this family/size:
  - `05025781111010100` returns `invalid_luminos_combination`
- ECO base branch is **not** proven for this family/size:
  - `05025725121010100` returns `invalid_luminos_combination`
- Pink HE ECO is also **not** proven for this family/size:
  - `05025791121010100` returns `invalid_luminos_combination`

Asset candidates do exist in source dump:

- `new_data_img/T5/Pink/T5_Pink.png`
- `new_data_img/T5/Pink/T5_Pink_tecto.png`
- `new_data_img/T5/2025/T5.png`
- `new_data_img/T5/2025/T5_01.png`

But rule stays:

- images alone do not make a branch valid
- Pink/ECO onboarding for family `05` must wait for proven live `Luminos` rows and then branch-specific asset mapping

## Best Next Follow-Up

- lock one base T5 VC gold sample old-vs-new
- if user wants more T5 coverage, onboard Pink HE next
- keep ECO out of scope until live `Luminos` truth is proven
