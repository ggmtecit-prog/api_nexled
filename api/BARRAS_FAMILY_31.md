# Barras Family 31

Purpose:
- document family `31` code logic for parity work
- keep catalog mask, DB truth, old runtime clues, and datasheet asset risks in one place

Scope:
- family `31`
- Barra RGB 24V VC line

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `31` appears with this mask:

- `31TTTT9111AXXYY00`

Simple reading:
- family `31` is one of most constrained Barra masks in catalog
- middle block `9111` looks fixed
- final `00` looks fixed
- only size, lens, finish, and cap/connection block stay visibly variable

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal system still uses normalized mask:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `31`, DB/runtime clues strongly suggest this resolves to:

`31 + size4 + 91 + 1 + 0 + lens1 + finish2 + cap2 + 00`

So family `31` is another strong constrained-family example where:

- internal normalized live mask still exists
- but many pieces are effectively fixed by family/runtime truth

## Old Runtime Behavior

### Frontend code build

Old configurator still builds references from same generic family-driven option flow:

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

Safest current rule for family `31`:

1. build full code from selected pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - invalid combination

So for family `31`:
- **code validity source** = `Luminos` identity + allowed family option lists
- not images

## Family 31 Segment Map

### Internal normalized meaning

| segment | length | family 31 meaning | main truth source |
|---|---|---|---|
| `31` | 2 | family code | `Familias`, DB truth |
| `TTTT` | 4 | size / length | `Tamanhos` |
| `color` | 2 | fixed RGB block `91` | `Cor`, `Luminos` |
| `cri` | 1 | fixed `1` in real identities seen so far | `Luminos` |
| `series` | 1 | fixed `0` in real identities seen so far | `Luminos`, `Series` options |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | finish | `Acabamento` |
| `cap` | 2 | cap / connector block | `Cap` |
| `option` | 2 | final option block, effectively `00` in current evidence | `Opcao` |

### Catalog-facing meaning

Catalog mask:

`31TTTT9111AXXYY00`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `31` | family |
| `TTTT` | size |
| fixed `9111` | RGB + constrained middle product block |
| `A` | lens |
| `XX` | finish |
| `YY` | cap / connection-related variable block |
| fixed `00` | fixed final option block |

Important:
- DB truth supports this being highly constrained
- `Cor` for family `31` is only `RGB` code `91`
- `Opcao` is only `Nada` code `0`
- `Series` currently exposes only `Nada` code `0`

## Concrete Example References

### Real `Luminos` identity examples

These first-10-char identities exist in `Luminos`:

| identity | description | product id |
|---|---|---|
| `3100809110` | `Barra LED RGB` | `BarraRGB/24v/10` |
| `3101609110` | `Barra LED RGB` | `BarraRGB/24v/20` |
| `3102409110` | `Barra LED RGB` | `BarraRGB/24v/25` |
| `3103209110` | `Barra LED RGB` | `BarraRGB/24v/35` |
| `3104009110` | `Barra LED RGB` | `BarraRGB/24v/40` |
| `3104809110` | `Barra LED RGB` | `BarraRGB/24v/50` |

### Safe expanded full-reference examples

Using valid family `31` option defaults:

| full reference | note |
|---|---|
| `31008091100010100` | built from valid identity `3100809110` + lens `0` + finish `01` + cap `01` + option `00` |
| `31016091100010100` | built from valid identity `3101609110` + same default suffix |

These are safe runtime-shaped examples.
They are **not** official gold samples yet.

## Invalid Example Rule

For family `31`, safest invalid rule is:

- **invalid if** first 10 chars do not exist in `Luminos`

Do not invent fake invalid full references yet.
Later:
- collect real invalid refs after runtime support restored

## Family 31 Option Sources

Family `31` options come from family-mapped reference tables:

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

Current option data gives strong constraints:

- `Cor` only:
  - `RGB` = `91`
- `Series` only:
  - `Nada` = `0`
- `Opcao` only:
  - `Nada` = `0`
- `Cap` currently behaves like connection-end selection:
  - no connector
  - `STras`
  - `SLado`
  - `1C4P`
  - `2C4P`
  - combo variants with top/rear/side outputs

This is one of strongest cases where family runtime is much tighter than generic live mask.

## Old Family 31 Special Runtime Behavior

### Not in strongest confirmed old bar UI group

Old special bar UI groups explicitly include:

- `11`
- `32`
- `55`
- `58`

Family `31` is **not** in that strongest confirmed group.

So current safest statement is:

- family `31` is catalog/DB-confirmed Barra family
- but old frontend special-case trace is weaker than for `11`, `32`, `55`, `58`

### RGB luminotechnical special case

Current live luminotechnical code already contains one family `31` special rule:

- efficacy calculation divides power by `3`

Source:
- [api/lib/luminotechnical.php](c:\xampp\htdocs\api_nexled\api\lib\luminotechnical.php)

Meaning:
- family `31` is treated as 3-channel RGB product in current technical calculations
- this is strong confirmation that family `31` needs family-specific parity handling later

### Current live runtime state

Current live `reference-decoder.php` now maps family `31` as `barra`.

Datasheet runtime now allows family `31` to enter the PDF pipeline, but it
fails honestly because required bar support data is still missing:

- no confirmed bar size profile mapping
- no visible `appdatasheets/img/31/...` asset tree
- no confirmed DAM family `31` assets loaded yet

## Datasheet Asset Sources For Family 31

This is separate from code validity.

### Expected product assets

Expected family asset roots would normally be under:

- `appdatasheets/img/31/...`

But current repo check shows:

- no family `31` asset folder is present yet under `appdatasheets/img/31`
- no family `31` DAM assets are currently loaded either

So family `31` currently has major datasheet-readiness risks:

- even valid codes may not be datasheet-ready until asset structure is restored or mapped elsewhere

### Common supporting assets

If family `31` is later restored into full runtime, it will also likely depend on:

- shared temperature/color graph assets
- lens/diagram assets
- technical drawings
- finish/cap visuals or placeholders

Because RGB family is special, some of these may need family-specific exceptions instead of generic Barra handling.

## Code-valid vs Datasheet-ready

These are **not** same thing.

### Code-valid

Family `31` code is valid when:

- first 10 chars exist in `Luminos`
- suffix pieces come from allowed family option tables

Main truth:
- DB + old runtime behavior

### Datasheet-ready

Family `31` code is datasheet-ready only when:

- code is valid
- required assets and sections exist

Current likely blockers:

- missing family `31` asset folder
- missing family `31` DAM assets
- missing technical drawing profile mapping
- unknown final drawing/image conventions for RGB line

## Current Gaps / Risks

Biggest open gaps for family `31`:

1. no confirmed bar size profile mapping for family `31`
2. old special frontend runtime behavior not fully traced
3. no `appdatasheets/img/31` asset folder currently present
4. no family `31` DAM assets are currently loaded
5. current live runtime correctly blocks family `31` with:
   - `Missing required data: technical drawing profile`

## Best Next Follow-Up

Best follow-up after this doc:

1. restore a truthful bar size profile mapping for family `31`
2. trace old generator/product-image logic for the RGB line
3. import/map real family `31` assets into local legacy paths or DAM
4. document RGB asset structure once source assets are found or restored
