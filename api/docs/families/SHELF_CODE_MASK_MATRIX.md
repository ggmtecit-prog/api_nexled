# Shelf Code Mask Matrix

Purpose:
- document Shelf code logic per family
- separate:
  - code validity source
  - datasheet asset source

Scope:
- [2_Shelf.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\2_Shelf.pdf)
- current confirmed family from DB/catalog: `49`

## Source Priority

Use this order when family rule conflicts:

1. old runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Key Separation

- `code_validity_source`
  - answers: "is code allowed by system?"
  - main truth = `Luminos` + family option lists + old runtime rules

- `datasheet_asset_source`
  - answers: "can valid code render full PDF?"
  - main truth = drawings/images/graphs/diagrams/icons used by generator

So:
- image existing does not prove code valid
- image missing does not mean code invalid

## Current Matrix

| family | product_line | official_pdf_mask | internal_normalized_mask | fixed_blocks_seen_in_pdf | variable_blocks_seen_in_pdf | code_validity_source | datasheet_asset_source | notes / unknowns |
|---|---|---|---|---|---|---|---|---|
| `49` | ShelfLED / Shelf LED 24V | `49TTTTLLLPA01YY00` | likely still maps to normalized live model when later supported: `family2 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | fixed `01`, fixed final `00` | `TTTT`, `LLLP`, `A`, `YY` | likely first 10 chars in `Luminos` + family option lists from `tecit_referencias` | future Shelf asset folders / product images / drawings / graphs / descriptions | current live API does not yet expose family `49` as product type; catalog suggests tighter suffix rules than generic normalized model |

## Best Next Follow-Up

- use [SHELF_FAMILY_49.md](c:\xampp\htdocs\api_nexled\api\SHELF_FAMILY_49.md) as family detail sheet
- later trace if Shelf had dedicated legacy runtime path
