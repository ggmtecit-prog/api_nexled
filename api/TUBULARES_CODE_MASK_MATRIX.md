# Tubulares Code Mask Matrix

Purpose:
- document Tubulares code logic per family
- separate:
  - code validity source
  - datasheet asset source

Scope:
- [5_Tubulares.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\5_Tubulares.pdf)
- current confirmed families: `01`, `05`

## Source Priority

Use this order when family rule conflicts:

1. old runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Current Matrix

| family | product_line | official_pdf_mask | internal_normalized_mask | fixed_blocks_seen_in_pdf | variable_blocks_seen_in_pdf | code_validity_source | datasheet_asset_source | notes / unknowns |
|---|---|---|---|---|---|---|---|---|
| `01` | T8 AC | `01TTTTLLLPAXXYY00` | likely still maps to normalized live model when later supported: `family2 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | final `00` fixed | `TTTT`, `LLLP`, `A`, `XX`, `YY` | likely first 10 chars in `Luminos` + family option lists from `tecit_referencias` | future T8 asset folders / drawings / graphs / descriptions | current live API does not yet expose family `01` as product type |
| `05` | T5 VC | `05TTTTLLLPAXXYY00` | likely still maps to normalized live model when later supported: `family2 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | final `00` fixed | `TTTT`, `LLLP`, `A`, `XX`, `YY` | likely first 10 chars in `Luminos` + family option lists from `tecit_referencias` | future T5 asset folders / drawings / graphs / descriptions | current live API does not yet expose family `05` as product type |

## Best Next Follow-Up

- patch runtime support for `01` and `05` only after family docs are reviewed
