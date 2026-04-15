# Acessorios Logic Findings

Purpose:
- document what [3_Acessorios.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\3_Acessorios.pdf) does and does not give us
- avoid over-claiming code logic from accessory catalog pages

Scope:
- accessory catalog PDF only

## Main Finding

Unlike Barras, Downlights, Shelf, and Tubulares:

- `3_Acessorios.pdf` does **not** expose one clean family-code mask set
- no strong product-family reference format was recovered from current local extraction

So best current reading:

- this PDF is accessory/support reference
- not primary source for main product-family code masks

## What This PDF Is Still Good For

It is still useful for:

- understanding accessory naming
- understanding fixing / connection / support components
- later mapping accessory visuals used by datasheets

## What This PDF Is Not Good For

Do **not** treat it as main truth for:

- family code validity
- primary product identity masks
- `Luminos` identity rules

## Source Priority

For accessory logic later, use:

1. old runtime behavior
2. DB truth
3. legacy accessory asset paths / option tables
4. accessory PDF reference

## Best Next Follow-Up

Only return to this PDF when:

- fixing logic needs deeper parity work
- accessory pages need official layout matching
- connection/fixing codes need catalog names
