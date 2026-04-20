# T8 Asset Inventory

Purpose:
- record real asset shape found in `new_data_img/T8`
- separate usable assets from likely duplicates/legacy files
- support family `01` onboarding first
- avoid blind copy into runtime paths

Scope:
- `new_data_img/T8`
- immediate runtime target: family `01`
- later review target: families `02` and `03`

## Current Known DB Truth

Current best confirmed state:

- family `01` = `T8 AC`
- family `02` = `T8 VC`
- family `03` = `T8 CC`

From current runtime and previous audits:

- `01` has real `Luminos` identities
- `02` currently has no confirmed `Luminos` identities
- `03` currently has no confirmed `Luminos` identities

Operational consequence:

- `01` can be made fully datasheet-ready now
- `02` and `03` should not be treated as fully valid until DB truth changes

## Folder Shape

Observed top-level folders in `new_data_img/T8`:

- `2025`
- `acabamentos`
- `antigo`
- `desenhos`
- `diagramas`
- `Pink`

Observed root-level product renders also exist directly in `new_data_img/T8`.

## Asset Groups

### 1. Product renders

Root-level product image candidates:

- `T8 Clear Alu Fixo.png`
- `T8 Clear Alu Rotativo.png`
- `T8 Clear Alu Rotativo (2).png`
- `T8 Clear Alu Rotativo LB.png`
- `T8 Clear Alu Rotativo LB (2).png`
- `T8 Clear Alu Rotativo LB (3).png`
- `T8 Frost Alu Rotativo.png`
- `T8 Frost Alu Rotativo (2).png`
- `T8 Frost Alu Rotativo LB.png`
- `T8 Frost Alu Rotativo LB (2).png`
- `T8 Eco Clear Alu Rotativo.png`

Likely meaning:

- `Clear` / `Frost` = lens
- `Alu` = finish/body
- `Fixo` / `Rotativo` = cap or mounting subtype
- `LB` likely line/block variant, needs DB/runtime confirmation
- `Eco` likely separate product subline, needs DB/runtime confirmation

Current risk:

- filenames are catalog-facing, not runtime-ready
- duplicates with `(2)` and `(3)` must not be imported blindly

### 2. 2025 variants

Files:

- `2025/T8.png`
- `2025/T8_01.png`
- `2025/T8_ECO.png`

Likely role:

- newer marketing/product renders

Current risk:

- naming too generic for direct runtime use
- may overlap with root-level product renders

### 3. Finish images

Files:

- `acabamentos/acabamento t8 alu.png`
- `acabamentos/acabamento t8 alu antigo.png`

Likely role:

- finish section image for aluminium body

Current risk:

- only one clear finish family visible
- no runtime-safe naming yet
- `antigo` variant likely legacy fallback only

### 4. Legacy / old product images

Files:

- `antigo/T8_clear_+Leds_00.png`
- `antigo/T8_clear_ajust_+Leds_00.png`

Likely role:

- old runtime/export naming experiments
- may encode historical cap/adjustable variants

Current risk:

- naming does not match current normalized API rules
- should be treated as reference assets, not default import set

### 5. Technical drawings

Files in `desenhos/catalogo`:

- `desenho t8.svg`
- `t8 fixo.svg`
- `t8 fn curto.svg`
- `t8 fn sem pinos.svg`

Likely role:

- base technical drawing
- fixed-cap variant
- short-end/connector variant
- no-pin variant

Current risk:

- runtime family/cap mapping to these drawings not yet proven
- names are descriptive, not code-based

### 6. Lens diagrams

Files:

- `diagramas/diagrama t8 clear.svg`
- `diagramas/diagrama t8 frost.svg`

Likely role:

- clear lens radiation diagram
- frost lens radiation diagram

Good signal:

- clean one-file-per-lens structure
- likely easiest T8 section to map

### 7. Pink variant

Files:

- `Pink/T8_Pink.png`
- `Pink/T8_Pink_tecto.png`

Likely role:

- pink/special LED product image set

Current risk:

- not enough evidence yet whether this belongs to family `01`, separate color option, or separate subline

## Immediate Import Guidance

Safe rule:

- do **not** bulk-copy `new_data_img/T8` into runtime paths

Reason:

- too many duplicate and catalog-facing names
- direct import would create ambiguous product-image selection

Safer import order:

1. choose one gold sample from family `01`
2. map only assets needed for that sample
3. prove runtime naming
4. then expand coverage

## Best First Runtime Mapping Candidates

For family `01`, most likely first-pass candidates are:

### Product image

Prefer these first:

- `T8 Clear Alu Rotativo.png`
- `T8 Frost Alu Rotativo.png`
- `T8 Clear Alu Fixo.png`

Do not prefer first:

- files with `(2)` / `(3)`
- `2025` files
- `antigo` files

### Finish image

Prefer:

- `acabamentos/acabamento t8 alu.png`

Fallback:

- `acabamentos/acabamento t8 alu antigo.png`

### Drawing

Start with:

- `desenhos/catalogo/desenho t8.svg`

Variant candidates later:

- `t8 fixo.svg`
- `t8 fn curto.svg`
- `t8 fn sem pinos.svg`

### Lens diagram

Prefer:

- `diagramas/diagrama t8 clear.svg`
- `diagramas/diagrama t8 frost.svg`

## Blockers

1. no runtime-safe filename scheme exists yet for these assets
2. duplicate product renders need manual selection
3. `02` and `03` still lack proven `Luminos` support
4. cap/mounting mapping for `fixo`, `rotativo`, `fn curto`, `sem pinos` not yet proven against DB/options

## Recommended Next Step

Use this inventory to onboard family `01` only:

1. pick one real `01` code
2. map one product image
3. map one finish image
4. map one base drawing
5. map clear/frost diagrams
6. verify strict tubular validator passes only with real assets

Do **not** widen to `02/03` until DB truth exists.
