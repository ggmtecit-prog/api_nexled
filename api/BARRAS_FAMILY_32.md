# Barras Family 32

Purpose:
- document family `32` code logic for parity work
- keep catalog mask, old runtime behavior, DB truth, and datasheet asset rules in one place

Scope:
- family `32`
- Barra BT 24V line

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `32` appears with this mask:

- `32TTTTLLLPA01YY00`

Simple reading:
- family `32` is more constrained than generic family `11`
- middle suffix includes fixed `01`
- final suffix appears fixed `00`

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal system still treats family `32` with normalized mask:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `32`, that means:

`32 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

So internal model is still generic.
But catalog mask strongly suggests some positions are effectively fixed or constrained for this family.

## Old Runtime Behavior

### Frontend code build

Old configurator builds family `32` reference from same pieces as other main bar families:

- `familia`
- `tamanho`
- `cor`
- `cri`
- `serie`
- `lente`
- `acabamento`
- `cap`
- `opcao`

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

### Validity rule

Old runtime validates family `32` same base way as family `11`:

1. build full code from selected option pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - old UI shows Luminos combination error

So for family `32`:
- **code validity source** = `Luminos` identity + allowed family option lists
- not images

## Family 32 Segment Map

### Internal normalized meaning

| segment | length | family 32 meaning | main truth source |
|---|---|---|---|
| `32` | 2 | family code | `Familias`, old runtime |
| `TTTT` | 4 | size / length | `Tamanhos` |
| `color` | 2 | color / LED code part 1 | `Cor` / `Luminos` |
| `cri` | 1 | CRI code | `CRI` / `Luminos` |
| `series` | 1 | series / product series | `Series` / `Luminos` |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | finish | `Acabamento` |
| `cap` | 2 | cap / end piece | `Cap` |
| `option` | 2 | option / connection-related suffix | `Opcao` |

### Catalog-facing meaning

Catalog mask:

`32TTTTLLLPA01YY00`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `32` | family |
| `TTTT` | size |
| `LLLP` | light/power block in catalog language |
| `A` | lens |
| fixed `01` | family-specific constrained suffix marker |
| `YY` | connection-related variable block |
| fixed `00` | fixed final option block |

Important:
- catalog view suggests lower suffix freedom than current generic normalized model
- later parity work should verify which internal segments map to fixed `01` and fixed `00`

## Concrete Example References

Family `32` sample refs seen in catalog reference text:

| reference | status | note |
|---|---|---|
| `32096036111011000` | PDF sample | Barra BT 24V example from catalog |
| `32024033222012300` | PDF sample | second catalog sample |

These are useful reference clues.
But they should still be runtime-verified against `Luminos` before treated as final test set.

## Invalid Example Rule

For family `32`, safest invalid rule is same as other validated bar families:

- **invalid if** first 10 chars do not exist in `Luminos`

So do not invent fake invalid samples yet.
Later:
- collect 2 or 3 real invalid refs after runtime check

## Family 32 Option Sources

Old family `32` options come from family-mapped reference tables:

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

Current live API follows same structure through family option loaders.

## Old Family 32 Special Runtime Behavior

Family `32` is in old "bar-like" runtime group:

- `11`
- `32`
- `55`
- `58`

So it inherits old bar UI behavior.

### Bar option -> cable auto-fill

Old frontend parses `opcao` description for bar families and auto-fills:

- cable connector
- cable length
- cable color

Family `32` included in that rule.

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

This matters because:
- family `32` suffix is not only static code text
- old runtime uses option description to derive extra cable state

### Family exception UI

Family `32` also belongs to old bar exception group.
Old UI exposes extra fields for it:

- `acrescimo`
- `tampa`
- `vedante`
- `caboligacao`
- `fixacao`

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

## Datasheet Asset Sources For Family 32

This is separate from code validity.

### Product hero image

Family `32` has one clear special rule in old bar datasheet code.

Source:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)

For family `32`, hero image candidate is:

- `{connectorcable}_{tipocabo}_{tampa}`

This is different from generic family `11` behavior.

Meaning:
- product hero image for `32` depends heavily on cable/tampa selection
- not mainly on finish + cap fallback rule

### Technical drawing

Family `32` drawing source:

- `/img/32/desenhos/`

Candidate names follow bar drawing logic:

- `{cap}_{connectorcable}_{tampa}`
- `{cap}_{tampa}`
- `{connectorcable}_{tampa}`
- `{cap}`

But size/dimension JSON is family-specific through current decoder grouping:

- family `32` -> `barras_bt`

So family `32` dimensions are not using generic `barras` sizes file.

### Finish image

Family `32` finish/lens asset source follows bar finish lookup:

- `/img/32/acabamentos/{lens}/`
- or `/img/32/acabamentos/clear/{series}/`

Candidate names:

- `{finish}_{cap}`
- `{finish}_{tampa}`

### Color graph

Shared source:

- `/img/temperaturas/{ID_Led}`
- `json/descricao/leds.json`

So readiness still depends on:
- `Luminos.ID_Led`
- graph file
- LED label JSON

### Lens / radiation diagram

Family `32` source:

- `/img/32/diagramas/{lens}.svg`
- `/img/32/diagramas/i/{lens}.svg`

### Optional fixing / connection assets

Family `32` may also depend on:

- `/img/32/fixacao/...`
- `/img/32/ligacao/...`

These affect datasheet readiness, not base code validity.

## Separation: Valid Code vs Ready Datasheet

For family `32`:

### Code valid

Means:
- first 10 chars exist in `Luminos`
- suffix pieces come from family option lists

Main sources:
- `Luminos`
- `tecit_referencias`
- old runtime JS/PHP

### Datasheet ready

Means:
- code valid
- family `32` product images, drawings, graphs, finish images, and diagrams exist

Main sources:
- `/img/32/...`
- shared graph/json assets
- legacy generator functions

So:
- image missing does **not** mean code invalid
- image missing means datasheet blocked or incomplete

## Current Gaps / Risks

### 1. Catalog mask more constrained than normalized internal mask

Catalog says:

- `32TTTTLLLPA01YY00`

Internal system still treats family `32` generically.

Need later verification:
- which normalized segment is catalog fixed `01`
- whether final `00` is always forced in runtime

### 2. Hero image rule is family-specific

Family `32` product image lookup depends on:

- connector cable
- cable type
- tampa

So family `32` has stronger coupling between suffix/options and hero asset than family `11`.

### 3. Size JSON rule differs from generic bars

Current decoder maps family `32` to:

- `barras_bt`

So technical drawing dimension logic must keep this family split.

### 4. Need real runtime verification set

Catalog sample refs help.
But for parity, still need:

- 3 to 5 DB-verified valid refs
- 2 invalid refs
- 1 valid-but-datasheet-blocked ref

## Recommended Next Follow-Up

Best next family-32 work:

1. runtime-check sample refs against `Luminos`
2. verify which internal suffix maps to catalog fixed `01`
3. verify if final `00` is always fixed in practice
4. document family-32 image folder contents
5. compare generated PDF vs old family-32 official page
