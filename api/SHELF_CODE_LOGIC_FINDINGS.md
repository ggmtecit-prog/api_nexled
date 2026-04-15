# Shelf Code Logic Findings

Purpose:
- document code-logic clues from [2_Shelf.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\2_Shelf.pdf)
- separate catalog/reference clues from DB/runtime truth
- prepare later API parity work for Shelf family

Scope:
- Shelf catalog PDF only
- current known family: `49`

## Source Priority

Use this order when sources disagree:

1. old runtime behavior
2. DB truth:
   - `Luminos`
   - `tecit_referencias`
3. legacy code
4. catalog PDF reference

## Main Finding

Shelf logic appears to be one main family:

- `49` = `ShelfLED`

Catalog PDF shows one strong code mask:

- `49TTTTLLLPA01YY00`

Simple reading:
- family `49`
- size block `TTTT`
- light block `LLLP`
- lens block `A`
- fixed `01`
- variable `YY`
- fixed final `00`

Important:
- this is strong catalog clue
- not final runtime truth by itself

## DB Truth Confirmed

From repo DB lookups:

- family `49` name = `ShelfLED`
- sample `Luminos` identities:
  - `4904502411`
  - `4904502415`
  - `4904502421`
  - `4904502425`
  - `4904502511`

Sample product IDs:
- `ShelfLED/24v/47`
- `ShelfLED/24v/47/Eco`

So:
- Shelf is real DB family
- not only catalog art

## Current Runtime Gap

Current live API does **not** map family `49` in `getProductType()`.

So today:
- family `49` is documented in DB/catalog
- but not yet first-class in live API/runtime like `11`, `29`, `30`, `32`, `48`, `55`, `58`, `60`

## Code Validity vs Datasheet Readiness

Keep these separate:

- code validity
  - likely first 10 chars checked in `Luminos`
  - then suffix constrained by family option tables

- datasheet readiness
  - depends on Shelf asset folders, drawings, graphs, and descriptions

Images do **not** prove code valid.
Images only prove PDF can render.

## Best Current Reading

Shelf family likely behaves like:

- one main family `49`
- identity source = first 10 chars in `Luminos`
- official/catalog suffix tighter than generic normalized API mask
- likely fixed:
  - middle `01`
  - final `00`

## Useful Catalog Clues

Visible product naming in PDF includes:

- `Shelf LED 24V`
- `ShelfLED 85 WW303 HE Frost Alu ASQC2 0.17`

This suggests Shelf references mix:
- size
- LED/CCT/CRI block
- lens
- body finish
- connection suffix

## Best Next Follow-Up

1. write family doc:
   - `SHELF_FAMILY_49.md`
2. later patch API/runtime support for `49`
3. verify if old project had dedicated Shelf flow or reused generic builder
