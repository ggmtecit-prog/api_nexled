# Barras Family 40

Purpose:
- document family `40` code logic for parity work
- keep catalog masks, DB truth, old runtime clues, and current implementation gaps in one place

Scope:
- family `40`
- Barra 24V CCT line
- includes at least two catalog-facing branches:
  - CCT IR
  - CCT Wi-Fi / fixed-control variant

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `40` appears with two masks:

- `40TTTTLLLPAXXYYZZ`
- `40TTTTLLLPAXXYY00`

Simple reading:
- same family `40` branches by control mode
- one branch keeps final `ZZ` variable
- one branch fixes the final suffix to `00`

Best current catalog interpretation:

- `40TTTTLLLPAXXYYZZ` = more open / IR-style branch
- `40TTTTLLLPAXXYY00` = more fixed / Wi-Fi-style branch

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

If family `40` is normalized to the current live model, the shape would be:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `40`, that would mean:

`40 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

Important current gap:

- family `40` exists in DB and catalog
- but current live `reference-decoder.php` does **not** classify `40` as a supported `barra` family
- so family `40` is one of the clearest examples where documentation/DB truth is ahead of live runtime support

## Old Runtime Behavior

### Frontend code build

Old configurator still loads family `40` options generically through the same family-driven option loading flow as other products:

- `familia`
- `tamanho`
- `cor`
- `cri`
- `serie`
- `lente`
- `acabamento`
- `cap`
- `opcao`

Sources:
- [appdatasheets/funcoes/getOpcoesProduto.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\getOpcoesProduto.php)
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

### Validity rule

The old reference-description path validates by the first 10 chars through `Luminos`.

So for family `40`, safest current rule is:

1. build full code from selected pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - invalid combination

This is consistent with the generic old description lookup flow, even though family `40` does not appear in the special bar UI exception groups.

## Family 40 Segment Map

### Internal normalized meaning

| segment | length | family 40 meaning | main truth source |
|---|---|---|---|
| `40` | 2 | family code | `Familias`, DB truth |
| `TTTT` | 4 | size / length | `Tamanhos` |
| `color` | 2 | color / CCT program block part 1 | `Cor` / `Luminos` |
| `cri` | 1 | CRI code | `CRI` / `Luminos` |
| `series` | 1 | series / product sub-line | `Series` / `Luminos` |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | finish | `Acabamento` |
| `cap` | 2 | cap / connector/control end piece | `Cap` |
| `option` | 2 | option / control suffix | `Opcao` |

### Catalog-facing meaning

Catalog masks:

- `40TTTTLLLPAXXYYZZ`
- `40TTTTLLLPAXXYY00`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `40` | family |
| `TTTT` | size |
| `LLLP` | CCT/control light block in catalog language |
| `A` | lens |
| `XX` | finish |
| `YY` | cap / connection / control interface block |
| `ZZ` or fixed `00` | final option / control block |

Important:
- catalog strongly suggests family `40` is control-mode-sensitive
- current live runtime has not yet been fully mapped to those two catalog branches

## Concrete Example References

### Real `Luminos` identity examples

These first-10-char identities exist in `Luminos`:

| identity | description | product id |
|---|---|---|
| `4000750110` | `LLED Barra 24V CCT 10 27-57` | `BarraCCT/24v/10` |
| `4000750120` | `LLED Barra 24V CCT 10 27-57 Pro` | `BarraCCT/24v/10` |
| `4000750712` | `LLED Barra 24V CCT 10 PK-50 ECO` | `BarraCCT/24v/10/Eco` |
| `4001500110` | `LLED Barra 24V CCT 17 27-57` | `BarraCCT/24v/17` |

### Safe expanded full-reference examples

Using valid family `40` option defaults:

| full reference | note |
|---|---|
| `40007501100010000` | built from valid identity `4000750110` + lens `0` + finish `01` + cap `00` + option `00` |
| `40007507120010000` | built from valid identity `4000750712` + default suffix example |

These are safe runtime-shaped examples.
They are **not** official gold samples yet.

## Invalid Example Rule

For family `40`, safest invalid rule is:

- **invalid if** first 10 chars do not exist in `Luminos`

Do not invent fake invalid full references yet.
Later:
- collect real invalid refs once family `40` is exercised in runtime/explorer work

## Family 40 Option Sources

Family `40` options come from family-mapped reference tables:

- `Tamanhos`
- `Cor`
- `CRI`
- `Series`
- `Acrilico`
- `Acabamento`
- `Cap`
- `Opcao`

Source:
- [appdatasheets/funcoes/getOpcoesProduto.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\getOpcoesProduto.php)

Current option data has some important runtime clues:

- colors are CCT-program values like `27-57`, `PK-75`, `PK-50`
- series includes:
  - `3 Series` = code `0`
  - `2 Series` = code `2`
- options include explicit control modes:
  - `IR`
  - `BT`
  - `IR+PROX`

This supports the PDF reading that family `40` is not a simple generic bar family.

## Old Family 40 Special Runtime Behavior

### Not in the strongest confirmed bar UI group

Unlike families `11`, `32`, `55`, and `58`, family `40` does **not** currently appear in the old special bar UI groups that controlled:

- extra visible fields like `acrescimo`, `tampa`, `vedante`, `caboligacao`, `fixacao`
- option-driven cable auto-fill behavior

Evidence:
- old `appdatasheets/script.js` special groups only name:
  - `11`
  - `32`
  - `55`
  - `58`

This means:
- family `40` likely has distinct runtime behavior
- and should not be assumed to behave like the confirmed main bar families without deeper trace

### Current live runtime state

Current `api/lib/reference-decoder.php` now maps family `40` as `barra`.

Datasheet runtime now allows family `40` to enter the PDF pipeline, but it
fails honestly because required bar support data is still missing:

- no confirmed bar size profile mapping
- no visible `appdatasheets/img/40/...` asset tree
- no confirmed DAM family `40` assets loaded yet

## Datasheet Asset Sources For Family 40

This is separate from code validity.

### Current best assumption

Family `40` likely uses its own asset tree:

- `appdatasheets/img/40/...`

Potential categories:

- product hero images
- drawings
- finish assets
- control-related visuals

This matches both:

- the catalog/product identity naming (`BarraCCT/...`)
- the matrix note in [api/BARRAS_CODE_MASK_MATRIX.md](c:\xampp\htdocs\api_nexled\api\BARRAS_CODE_MASK_MATRIX.md)

### Current certainty level

Asset-source certainty for family `40` is lower than for families `11`, `32`, `55`, and `58`.

Why:

- family `40` now has runtime support, but no family-40-specific size profile is mapped
- no family-40-specific runtime asset tree is present locally
- old bar-specific helper groups do not explicitly include `40`

So current datasheet asset rule should be documented as:

- **expected family asset source** = `appdatasheets/img/40/...`
- **runtime implementation status** = live runtime support exists, but strict blockers still stop incomplete PDFs

## Code-Valid vs Datasheet-Ready

For family `40`:

- **code-valid**
  - first 10 chars resolve in `Luminos`
  - remaining suffix comes from allowed family option sets

- **datasheet-ready**
  - valid code
  - plus:
    - correct family `40` asset path exists
    - correct drawing exists
    - any required control-related imagery/data exists
    - any family-specific finish / lens / cap visuals exist

So:
- family `40` code validity can be confirmed before full datasheet parity exists
- family `40` datasheet readiness is still partially unknown because required size/asset support is incomplete

## Current Gaps / Risks

- no confirmed bar size profile mapping for family `40`
- old frontend special-family behavior for `40` is not yet clearly traced
- family `40` likely branches into IR vs Wi-Fi style sub-lines, but runtime mapping for that is still not documented
- no `appdatasheets/img/40` asset tree is currently present
- no family `40` DAM assets are currently loaded
- current live runtime correctly blocks family `40` with:
  - `Missing required data: technical drawing profile`

## Best Next Follow-Up

1. restore a truthful bar size profile mapping for family `40`
2. trace any old datasheet asset paths for `40`
3. import/map real family `40` assets into local legacy paths or DAM
4. choose one IR-style and one fixed-`00` sample as gold references
