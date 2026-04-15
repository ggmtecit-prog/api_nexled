# Downlights Code Mask Matrix

Purpose:
- document Downlight code logic per family
- separate:
  - code validity source
  - datasheet asset source

Scope:
- [4_Downlights.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\4_Downlights.pdf)
- current confirmed families: `29`, `30`

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
  - main truth = `Luminos` + family option lists

- `datasheet_asset_source`
  - answers: "can valid code render full PDF?"
  - main truth = drawings/images/graphs/diagrams/icons used by generator

## Current Matrix

| family | product_line | official_pdf_mask | internal_normalized_mask | fixed_blocks_seen_in_pdf | variable_blocks_seen_in_pdf | code_validity_source | datasheet_asset_source | notes / unknowns |
|---|---|---|---|---|---|---|---|---|
| `29` | Downlight redondo | `29TTTTLLLPAXX0000` | live/internal normalized mask still used by current API: `family2 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | fixed final `0000` | `TTTT`, `LLLP`, `A`, `XX` | first 10 chars in `Luminos` + family option lists + current downlight runtime | `appdatasheets/img/29/...`, shared graphs/diagrams/descriptions | round downlight family, already supported in live API |
| `30` | Downlight quadrado | `30TTTTLLLPAXX0000` | live/internal normalized mask still used by current API: `family2 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2` | fixed final `0000` | `TTTT`, `LLLP`, `A`, `XX` | first 10 chars in `Luminos` + family option lists + current downlight runtime | `appdatasheets/img/30/...`, shared graphs/diagrams/descriptions | square downlight family, already supported in live API |

## Best Next Follow-Up

- compare current live downlight PDFs against official page order/layout
- verify whether official fixed `0000` should become hard runtime constraint or stay catalog-facing
