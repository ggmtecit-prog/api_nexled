# Barras Family 60

Purpose:
- document family `60` code logic for parity work
- keep catalog mask, DB truth, old runtime clues, and datasheet asset risks in one place

Scope:
- family `60`
- Barra 24V I45 line

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `60` appears with this mask:

- `60TTTTLLLPA010100`

Simple reading:
- family `60` is very constrained in catalog
- tail `010100` looks hard-fixed
- only earlier product-definition blocks stay variable

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal system still uses normalized mask:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `60`, best current reading is:

`60 + size4 + color2 + cri1 + series1 + lens1 + 01 + 01 + 00`

This is one case where catalog mask and normalized live mask align fairly well if read like:

- `LLLPA` = `color2 + cri1 + series1 + lens1`
- fixed `010100` = finish `01` + cap `01` + option `00`

Important current gap:

- family `60` exists in DB and catalog
- but current live `reference-decoder.php` does **not** classify `60` as supported `barra`

## Old Runtime Behavior

### Frontend code build

Old configurator still loads family `60` options through generic family-driven flow:

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

Safest current rule for family `60`:

1. build full code from selected pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - invalid combination

So for family `60`:
- **code validity source** = `Luminos` identity + allowed family option lists
- not images

## Family 60 Segment Map

### Internal normalized meaning

| segment | length | family 60 meaning | main truth source |
|---|---|---|---|
| `60` | 2 | family code | `Familias`, DB truth |
| `TTTT` | 4 | size / length | `Tamanhos` |
| `color` | 2 | LED / color block | `Cor`, `Luminos` |
| `cri` | 1 | CRI code | `CRI`, `Luminos` |
| `series` | 1 | series / sub-line | `Series`, `Luminos` |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | finish, strongly suggested fixed `01` in catalog | `Acabamento`, catalog mask |
| `cap` | 2 | cap / connector block, strongly suggested fixed `01` in catalog | `Cap`, catalog mask |
| `option` | 2 | final option block, strongly suggested fixed `00` in catalog | `Opcao`, catalog mask |

### Catalog-facing meaning

Catalog mask:

`60TTTTLLLPA010100`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `60` | family |
| `TTTT` | size |
| `LLLPA` | product-definition block matching `color2 + cri1 + series1 + lens1` |
| fixed `01` | finish |
| fixed `01` | cap / DCJ-like connector block |
| fixed `00` | final option block |

Important:
- this family may be one of cleanest examples where catalog mask maps directly onto normalized runtime segments
- but current DB options still show some broader flexibility than catalog explains

## Concrete Example References

### Real `Luminos` identity examples

These first-10-char identities exist in `Luminos`:

| identity | description | product id |
|---|---|---|
| `6003003321` | `LLED B 24V I45 30 NW353 HE Pro` | `BI45/24v/30` |
| `6003003431` | `LLED B 24V I45 30 NW403 HE Thrive` | `BI45/24v/30` |
| `6003753121` | `LLED B 24V I45 40 WW273 HE Pro` | `BI45/24v/40` |
| `6006003421` | `LLED B 24V I45 60 NW403 HE Pro` | `BI45/24v/60` |
| `6011253425` | `LLED B 24V I45 115 NW403 HE Pro ECO DL` | `BI45/24v/115/Eco` |

### Safe expanded full-reference examples

Using catalog-shaped defaults:

| full reference | note |
|---|---|
| `60030033210010100` | built from valid identity `6003003321` + lens `0` + fixed `01 01 00` tail |
| `60030034310010100` | built from valid identity `6003003431` + same fixed tail |
| `60060034210010100` | third runtime-shaped example for I45 line |

These are safe documentation examples.
They are **not** official gold samples yet.

## Invalid Example Rule

For family `60`, safest invalid rule is:

- **invalid if** first 10 chars do not exist in `Luminos`

Do not invent fake invalid full references yet.
Later:
- collect real invalid refs after runtime support restored

## Family 60 Option Sources

Family `60` options come from family-mapped reference tables:

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

Current option data gives useful clues:

- sizes:
  - `75` through `2475`
- colors:
  - broad standard + HE + special color list
- series:
  - `1` through `9` available
- cap:
  - only `0 = Nada`
  - and `1 = DCJ`
- option:
  - only `0 = Nada`
  - and `1 = Fita 3M`

Important runtime reading:
- DB/options suggest family `60` may allow more than catalog mask advertises
- catalog still points to published/default branch with fixed `01 01 00` tail

## Old Family 60 Special Runtime Behavior

### Not in strongest confirmed old bar UI group

Old special bar UI groups explicitly include:

- `11`
- `32`
- `55`
- `58`

Family `60` is **not** in that strongest confirmed group.

So current safest statement is:

- family `60` is catalog/DB-confirmed Barra family
- but old frontend special-case trace is weaker than for `11`, `32`, `55`, `58`

### No direct current live decoder support

Current live `reference-decoder.php` still maps bar families only as:

- `11`
- `32`
- `55`
- `58`

So family `60` is another case where:

- catalog truth exists
- DB truth exists
- but current live decoder/runtime support is still incomplete

## Datasheet Asset Sources For Family 60

This is separate from code validity.

### Expected product assets

Expected family asset roots would normally be under:

- `appdatasheets/img/60/...`

But current repo check shows:

- no family `60` asset folder is present yet under `appdatasheets/img/60`

So family `60` currently has one major datasheet-readiness risk:

- even valid codes may not be datasheet-ready until asset structure is restored or mapped elsewhere

### Product-ID clue

Real `Luminos` IDs use:

- `BI45/24v/...`

This is strong evidence that family `60` is its own I45 asset/product branch, not generic Barra reuse.

## Code-valid vs Datasheet-ready

These are **not** same thing.

### Code-valid

Family `60` code is valid when:

- first 10 chars exist in `Luminos`
- suffix pieces come from allowed family option tables

Main truth:
- DB + old runtime behavior

### Datasheet-ready

Family `60` code is datasheet-ready only when:

- code is valid
- required assets and sections exist

Current likely blockers:

- missing family `60` asset folder
- incomplete live decoder/runtime mapping
- unknown final drawing/image conventions for I45 line

## Current Gaps / Risks

Biggest open gaps for family `60`:

1. current live decoder does not classify `60` as supported `barra`
2. no family `60` asset folder exists in repo now
3. old special frontend/runtime behavior not fully traced
4. DB options appear broader than published catalog mask
5. family `60` still has no gold sample runtime parity test

## Best Next Follow-Up

Best follow-up after this doc:

1. add family `60` to parity investigation queue after `40`
2. trace old generator/product-image logic for I45 line
3. verify whether family `60` should be added to live `reference-decoder.php` bar map
4. confirm whether catalog fixed tail `010100` is strict runtime rule or published default branch only
