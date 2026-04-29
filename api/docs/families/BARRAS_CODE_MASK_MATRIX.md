# Barras Code Mask Matrix

Purpose:
- document Barra code logic **per family**
- separate:
  - code validity source
  - datasheet asset source
- keep official PDF mask and internal normalized mask side by side

Scope note:
- [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf) is reference only
- final truth still comes from old runtime behavior + DB + legacy code

## Source Priority

Use this priority when family rule conflicts:

1. old project runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Key Separation

These are **not** same thing:

- `code_validity_source`
  - answers: "is code allowed by system?"
  - main truth = `Luminos` + family option lists + old runtime rules

- `datasheet_asset_source`
  - answers: "can valid code render full PDF?"
  - main truth = drawings/images/graphs/diagrams/icons used by generator

So:
- image existing does **not** prove code valid
- image missing does **not** mean code invalid
- image missing means datasheet may be blocked or incomplete

## Internal Normalized Live Mask

Current normalized live system still uses:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

Example:

`11 0375 81 1 1 0 01 01 00`

This is good for API/configurator internals.
But official catalog masks differ by family.

## Matrix

| family | product_line | official_pdf_mask | internal_normalized_mask | fixed_blocks_seen_in_pdf | variable_blocks_seen_in_pdf | code_validity_source | datasheet_asset_source | notes / unknowns |
|---|---|---|---|---|---|---|---|---|
| `11` | Barra 24V / CTRL / Magnética variants | `11TTTTLLLPAXXYYZZ`, `11TTTTLLLPAXXYY01`, `11TTTTLLLPAXX01ZZ` | `11 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | sometimes `01` fixed in final suffix, depends sub-line | `TTTT`, `LLL/P`, `A`, `XX`, `YY`, `ZZ` | old runtime: first 10 chars validated in `Luminos`; options from `tecit_referencias`; old JS/runtime behavior for bars | `appdatasheets/img/11/...`, JSON support files, section fetchers for product image / drawing / graph / lens / finish | biggest family drift; same family code branches into multiple catalog masks |
| `32` | Barra BT 24V | `32TTTTLLLPA01YY00` | normalized live mask still applies internally | `01` fixed mid-suffix, final `00` fixed | `TTTT`, `LLL/P`, `A`, `YY` | old runtime bars group includes `32`; first 10 chars + family options | `appdatasheets/img/32/...` plus common graph/diagram assets | official PDF suggests much tighter rule than generic mask |
| `40` | Barra 24V CCT IR / Wi-Fi | `40TTTTLLLPAXXYYZZ`, `40TTTTLLLPAXXYY00` | current normalized mask not yet clearly mapped in live decoder docs | Wi-Fi variant fixes final `00`; IR variant keeps `ZZ` variable | `TTTT`, `LLL/P`, `A`, `XX`, `YY`, `ZZ` | likely old product-line logic + DB lookup; needs legacy code trace to confirm exact family handling | `appdatasheets/img/40/...` if present, plus CCT control-related assets | PDF shows family-specific control logic; needs old code trace to confirm if current system models `40` as Barra family or separate subtype |
| `55` | Barra 12V | `55TTTTLLLPAXXYYZZ` | `55 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | no obvious fixed block in PDF sample | `TTTT`, `LLL/P`, `A`, `XX`, `YY`, `ZZ` | old runtime bars group includes `55`; first 10 chars + family options | `appdatasheets/img/55/...` plus common graphs/diagrams | closest to generic `11` style, but still own family |
| `58` | Barra 24V HOT | `58TTTTLLL1101YY00` | normalized live mask still applies internally where supported | hardcoded `1101`, final `00` fixed | `TTTT`, `LLL`, `YY` | old runtime bars group includes `58`; current decoder maps `58` to bar/hot size files | `appdatasheets/img/58/...`, `barras_hot` size-related data, common graph assets | one clearest proof that official mask is family-specific |
| `60` | Barra 24V I45 | `60TTTTLLLPA010100` | current normalized mask not clearly documented for this family | `010100` hard-fixed tail | `TTTT`, `LLL`, maybe `P` | needs old runtime/code trace; not in current simplified Barra family map in `reference-decoder.php` | `appdatasheets/img/60/...` if family assets exist | likely highly constrained product line; current live normalized support may be incomplete |
| `31` | Barra 24V RGB | `31TTTT9111AXXYY00` | current normalized mask not clearly documented for this family | `9111` fixed middle block, final `00` fixed | `TTTT`, `AXX`, `YY` | needs old runtime/code trace; not in current simplified Barra family map in `reference-decoder.php` | `appdatasheets/img/31/...` if family assets exist, plus RGB-specific visuals | likely special control/color family; current live normalized support may be incomplete |

## What We Already Know From Old Code

Old frontend/runtime clearly treats these as "bar-like" families in user flow:

- `11`
- `32`
- `55`
- `58`

Evidence:
- old option/cable logic grouped bars as `["11", "32", "55", "58"]`
- old UI exceptions also grouped bars as `["11", "32", "55", "58"]`

So for now:
- `11`, `32`, `55`, `58` = strongest confirmed runtime Barra families
- `31`, `40`, `60` = confirmed by catalog PDF reference, but still need legacy code trace for exact runtime parity

## How To Use This Matrix

For each family:

1. validate code rule from old runtime
2. confirm identity rule in `Luminos`
3. confirm suffix option lists in `tecit_referencias`
4. map official PDF mask to normalized internal fields
5. separately audit datasheet assets

Do **not** collapse these into one rule.

## Best Next Follow-Up

Make one family doc at a time:

- `BARRAS_FAMILY_11.md`
- `BARRAS_FAMILY_32.md`
- `BARRAS_FAMILY_55.md`
- `BARRAS_FAMILY_58.md`
- then investigate `31`, `40`, `60`

For each family doc, capture:

- exact valid example refs
- invalid example refs
- `Luminos` identity rule
- suffix option rule
- old code files/functions
- asset folders used by datasheet
- gaps between catalog mask and current normalized implementation
