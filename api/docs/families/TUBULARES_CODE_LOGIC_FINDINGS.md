# Tubulares Code Logic Findings

Purpose:
- document code-logic clues from [5_Tubulares.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\5_Tubulares.pdf)
- connect catalog masks to DB/runtime truth

Scope:
- family `01` = T8 AC
- family `05` = T5 VC

## Source Priority

Use this order when sources disagree:

1. old runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Main Finding

Tubulares PDF shows two clear families:

- `01` = T8
- `05` = T5

Official masks seen in PDF:

- `01TTTTLLLPAXXYY00`
- `05TTTTLLLPAXXYY00`

Simple reading:
- both families use size + light block + lens + finish + connection block
- both appear to fix final `00`

## DB Truth Confirmed

From DB:

- `01 = T8 AC`
- `05 = T5 VC`

Sample `Luminos` identities exist for both.

## Current Runtime Gap

Current live API does **not** map families `01` or `05` in `getProductType()`.

So today:
- both families are real DB/catalog families
- but not yet first-class in live API runtime

## Code Validity vs Datasheet Readiness

Keep separate:

- code validity
  - first 10 chars in `Luminos`
  - family option lists

- datasheet readiness
  - images
  - drawings
  - descriptions
  - diagrams

## Useful Catalog Clues

Visible PDF product examples include:

- `T5 VC 15 x 1379mm WW303 Clear Alu`
- `T8 26 x 590mm NW403 HE Clear Alu Fixo`

This strongly suggests Tubular references encode:
- size
- LED/CCT/CRI block
- lens
- finish
- connection/fixing suffix

## Best Next Follow-Up

1. use family docs:
   - `TUBULARES_FAMILY_01.md`
   - `TUBULARES_FAMILY_05.md`
2. later patch API/runtime support for `01` and `05`
