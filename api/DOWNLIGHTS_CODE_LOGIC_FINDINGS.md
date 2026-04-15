# Downlights Code Logic Findings

Purpose:
- document code-logic clues from [4_Downlights.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\4_Downlights.pdf)
- connect catalog masks to DB/runtime truth

Scope:
- round downlights family `29`
- square downlights family `30`

## Source Priority

Use this order when sources disagree:

1. old runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Main Finding

Downlights PDF shows two clear families:

- `29` = round downlight
- `30` = square downlight

Official masks seen in PDF:

- `29TTTTLLLPAXX0000`
- `30TTTTLLLPAXX0000`

Simple reading:
- both families use size + light block + lens + finish
- both appear to fix final `0000`

## DB Truth Confirmed

From DB:

- `29 = Downlight redondo`
- `30 = Downlight quadrado`

Sample `Luminos` identities exist for both.

So:
- downlight families are real, active DB families
- not only catalog placeholders

## Current Runtime State

Current live API already maps:

- `29` -> `downlight`
- `30` -> `downlight`

So downlights are in better runtime shape than Shelf or Tubulares.

## Code Validity vs Datasheet Readiness

Keep separate:

- code validity
  - first 10 chars in `Luminos`
  - family option lists

- datasheet readiness
  - images
  - drawings
  - diagrams
  - descriptions

## Best Current Reading

Downlight families likely behave like:

- first 10 chars = real `Luminos` identity
- final 4 chars often fixed `0000` in official catalog mask
- runtime may still use normalized suffix fields internally

## Useful Catalog Clues

Visible PDF product examples include:

- `Downlight 230mm NW403 PL15W FrostC BR`
- `Downlight 120mm WW273 HE PL6,5W Clear BR`
- `Downlight LED R`
- `Downlight LED Q`

Best current reading:
- `R` = round family group (`29`)
- `Q` = square family group (`30`)

## Best Next Follow-Up

1. use family docs:
   - `DOWNLIGHTS_FAMILY_29.md`
   - `DOWNLIGHTS_FAMILY_30.md`
2. later compare live PDF output vs official order/layout
