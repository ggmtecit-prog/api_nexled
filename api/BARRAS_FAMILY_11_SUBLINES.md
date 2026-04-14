# Barras Family 11 Sublines

Purpose:
- split family `11` into catalog-facing sublines
- separate what looks confirmed vs what is still inference
- prepare later parity work for family `11` official PDF logic

Scope:
- family `11` only
- sublines seen or implied in current docs/reference:
  - base 24V
  - CTRL / DL
  - Magnética

Important:
- this file is **mapping aid**
- not full source of truth by itself
- final validation still comes from old runtime + DB

## Shared Base Rule

All family `11` sublines still appear to share same internal validity core:

- first 10 chars are identity:
  - `11 + size4 + color2 + cri1 + series1`
- identity must exist in `Luminos`
- remaining suffix pieces come from family option lists

So all sublines inherit:

- same family root = `11`
- same first-step validity source = `Luminos`

## Sublines Overview

| subline | official mask clue | likely meaning | confidence |
|---|---|---|---|
| base 24V | `11TTTTLLLPAXXYYZZ` | standard Barra 24V logic with variable tail | high |
| CTRL / DL | `11TTTTLLLPAXXYY01` | control/DL-like line with fixed final `01` | medium |
| Magnética | `11TTTTLLLPAXX01ZZ` | magnetic-like line with fixed middle `01` before last block | medium |

## 1. Base 24V

### Official mask clue

`11TTTTLLLPAXXYYZZ`

### What looks stable

- `11` = family
- `TTTT` = size
- `LLLP` = light/power block in catalog language
- `A` = lens
- `XX` = finish
- `YY` = cap / connection-related block
- `ZZ` = option / final suffix block

### Best internal mapping

Base 24V likely maps most cleanly to current normalized internal system:

| catalog block | likely internal mapping |
|---|---|
| `11` | `family` |
| `TTTT` | `size` |
| `LLLP` | `color + cri + series` viewed as one catalog block |
| `A` | `lens` |
| `XX` | `finish` |
| `YY` | `cap` or connection-related code |
| `ZZ` | `option` |

### What this means for parity

For base 24V:
- current normalized internal decoder is closest fit
- later official PDF code explanation can likely be built as:
  - normalized runtime data
  - plus family-11 display mapping layer

### Main sources

- code validity:
  - `Luminos`
  - option tables
  - old configurator build logic
- datasheet assets:
  - `/img/11/produto/...`
  - `/img/11/desenhos/...`
  - `/img/11/acabamentos/...`
  - `/img/11/diagramas/...`
  - `/img/11/fixacao/...`
  - `/img/11/ligacao/...`

## 2. CTRL / DL

### Official mask clue

`11TTTTLLLPAXXYY01`

### What seems different

- final block appears fixed to `01`
- so this line is **not** using fully variable `ZZ`
- likely catalog/control-specific product variant

### Strong interpretation

This probably means:
- most of family `11` still behaves same up to `YY`
- but last suffix part is constrained by subline rule
- subline selection may not be explicitly shown as separate family
- it may come from:
  - product line
  - series behavior
  - hidden catalog grouping

### Best internal mapping hypothesis

| catalog block | likely internal mapping |
|---|---|
| `11` | `family` |
| `TTTT` | `size` |
| `LLLP` | `color + cri + series` |
| `A` | `lens` |
| `XX` | `finish` |
| `YY` | `cap` / connection-related block |
| fixed `01` | constrained `option` or control suffix |

### What to verify later

- whether old runtime ever hard-fixed `option = 01` for specific family-11 line
- whether DL/Ctrl line is actually encoded through:
  - `series`
  - `option`
  - both

### Practical doc rule for now

Treat CTRL / DL as:
- family `11` base rule
- plus final fixed suffix variant

## 3. Magnética

### Official mask clue

`11TTTTLLLPAXX01ZZ`

### What seems different

- middle suffix block before final `ZZ` appears fixed as `01`
- final `ZZ` remains variable
- this is different from CTRL / DL pattern

### Strong interpretation

This suggests:
- magnetic line keeps family `11`
- but reserves one suffix slot as fixed marker
- last block still changes by option

### Best internal mapping hypothesis

| catalog block | likely internal mapping |
|---|---|
| `11` | `family` |
| `TTTT` | `size` |
| `LLLP` | `color + cri + series` |
| `A` | `lens` |
| `XX` | `finish` |
| fixed `01` | constrained cap / connection / subline marker |
| `ZZ` | option / final variable tail |

### What to verify later

- whether fixed `01` belongs to:
  - `cap`
  - `option`
  - hidden subline marker shown in catalog grouping

### Practical doc rule for now

Treat Magnética as:
- family `11` base rule
- plus one fixed middle suffix block
- final suffix still variable

## What All Three Share

These seem shared across family `11` sublines:

- family code remains `11`
- first 10-char identity still drives validity
- first 10-char validity still comes from `Luminos`
- family option data still comes from `tecit_referencias`
- datasheet readiness still depends on assets under `/img/11/...`

So sublines mostly affect:
- how catalog explains suffix
- which suffix parts are fixed or variable
- which product line user sees

## What Still Needs Proof

These points still need runtime/code trace, not just PDF clue:

1. exact internal meaning of `YY` in each subline
2. exact internal meaning of `ZZ` in each subline
3. whether fixed `01` belongs to:
   - `cap`
   - `option`
   - hidden subline rule
4. whether CTRL / DL and Magnética are:
   - true runtime variants
   - or only catalog presentation variants

## Best Next Validation Tasks

To move this from "good mapping" to "confirmed parity":

1. collect 3 real refs per subline
2. decode each with current normalized system
3. compare against old app output
4. confirm which segment holds fixed `01`
5. document:
   - confirmed block meaning
   - inferred block meaning

## Safe Use Right Now

Safe use of this file:

- explain family `11` is not one single catalog mask
- guide later parity implementation
- keep subline differences visible

Unsafe use right now:

- treating every fixed `01` as fully proven semantic truth
- coding hard business rules only from this file

Use this file together with:

- [api/BARRAS_FAMILY_11.md](c:\xampp\htdocs\api_nexled\api\BARRAS_FAMILY_11.md)
- [api/BARRAS_CODE_MASK_MATRIX.md](c:\xampp\htdocs\api_nexled\api\BARRAS_CODE_MASK_MATRIX.md)
- old runtime behavior
