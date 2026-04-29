# Barras Family 55

Purpose:
- document family `55` code logic for parity work
- keep catalog mask, old runtime behavior, DB truth, and datasheet asset rules in one place

Scope:
- family `55`
- Barra 12V line

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `55` appears with this mask:

- `55TTTTLLLPAXXYYZZ`

Simple reading:
- family `55` is cataloged like a generic Barra family
- no obvious fixed suffix block is shown in the PDF sample
- suffix appears more open than families like `32` or `58`

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal system treats family `55` with the normalized mask:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `55`, that means:

`55 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

This makes family `55` one of the closest Barra families to the generic live code model.

## Old Runtime Behavior

### Frontend code build

Old configurator builds family `55` reference from same pieces as the other main bar families:

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

Old runtime validates family `55` same base way as family `11` and `32`:

1. build full code from selected option pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - old UI shows Luminos combination error

So for family `55`:
- **code validity source** = `Luminos` identity + allowed family option lists
- not images

## Family 55 Segment Map

### Internal normalized meaning

| segment | length | family 55 meaning | main truth source |
|---|---|---|---|
| `55` | 2 | family code | `Familias`, old runtime |
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

`55TTTTLLLPAXXYYZZ`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `55` | family |
| `TTTT` | size |
| `LLLP` | light/power block in catalog language |
| `A` | lens |
| `XX` | finish |
| `YY` | cap / connection-related variable block |
| `ZZ` | option / final suffix block |

Important:
- catalog view is compatible with the generic live split, but not identical
- current runtime still uses the normalized internal segments

## Concrete Example References

### Real `Luminos` identity examples

These first-10-char identities exist in `Luminos`:

| identity | description | product id |
|---|---|---|
| `5500752411` | `LLED Barra 12V 10 NW403` | `Barra/12v/10/3s` |
| `5500753211` | `LLED Barra 12V 10 WW303 HE` | `Barra/12v/10/3s` |
| `5501502411` | `LLED Barra 12V 17 NW403` | `Barra/12v/17/3s` |

### Safe expanded full-reference examples

Using valid family `55` option defaults:

| full reference | note |
|---|---|
| `55007524110010100` | built from valid identity `5500752411` + lens `0` + finish `01` + cap `01` + option `00` |
| `55007532110010100` | built from valid identity `5500753211` + default suffix example |

These are safe documentation examples for the normalized live model.
They should still be treated as runtime examples, not official gold samples yet.

## Invalid Example Rule

For family `55`, safest invalid rule is same as the other verified bar families:

- **invalid if** first 10 chars do not exist in `Luminos`

So do not invent fake invalid full references yet.
Later:
- collect 2 or 3 real invalid refs after runtime/explorer check

## Family 55 Option Sources

Old family `55` options come from family-mapped reference tables:

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

## Old Family 55 Special Runtime Behavior

Family `55` is in old "bar-like" runtime group:

- `11`
- `32`
- `55`
- `58`

So it inherits old bar UI/runtime behavior.

### Bar option -> cable auto-fill

Old frontend parses `opcao` description for these bar families and auto-fills:

- cable connector
- cable length
- cable color

Family `55` is included in that rule.

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

This matters because:
- option suffix is not only display text
- old runtime may derive extra cable state from option description

### Family exception UI

Family `55` also belongs to old bar exception group.
Old UI exposes extra fields for it:

- `acrescimo`
- `tampa`
- `vedante`
- `caboligacao`
- `fixacao`

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

## Datasheet Asset Sources For Family 55

This is separate from code validity.

### Product hero image

Family `55` follows generic bar product-image rule, not the special BT (`32`) rule and not the HOT (`58`) rule.

Source:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)
- [api/lib/product-header.php](c:\xampp\htdocs\api_nexled\api\lib\product-header.php)

Generic bar candidates:

- `{finish}_{connectorcable}_{tipocabo}_{tampa}`
- `{finish}_{cap}`

Folder pattern:

- clear lens:
  - `appdatasheets/img/55/produto/clear/{series}/`
- non-clear lens:
  - `appdatasheets/img/55/produto/{lens}/`

### Technical drawing

Family `55` uses generic bar drawing logic and shares bar size-file mapping with family `11`.

Sources:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)
- [api/lib/technical-drawing.php](c:\xampp\htdocs\api_nexled\api\lib\technical-drawing.php)
- [api/lib/reference-decoder.php](c:\xampp\htdocs\api_nexled\api\lib\reference-decoder.php)

Current live mapping:

- bar sizes file = `barras`

Drawing candidates follow generic bar pattern:

- `{cap}_{connectorcable}_{tampa}`
- `{cap}_{tampa}`
- `{connectorcable}_{tampa}`
- `{cap}`

### Color graph

Family `55` uses common LED graph assets:

- `appdatasheets/img/temperaturas/...`

Source:
- [api/lib/sections.php](c:\xampp\htdocs\api_nexled\api\lib\sections.php)

### Lens diagram

Family `55` uses family-specific diagram assets:

- `appdatasheets/img/55/diagramas/...`

Source:
- [api/lib/sections.php](c:\xampp\htdocs\api_nexled\api\lib\sections.php)

### Finish image

Family `55` follows generic bar finish image rule, not the family `32` special code-based rule.

Expected folder shape:

- `appdatasheets/img/55/acabamentos/{lens}/...`

Source:
- [api/lib/sections.php](c:\xampp\htdocs\api_nexled\api\lib\sections.php)

### Optional fixing / connection assets

Because family `55` is part of old bar UI group, fixing and connection cable sections may appear depending on form state.

These affect datasheet readiness, not code validity.

## Code-Valid vs Datasheet-Ready

For family `55`:

- **code-valid**
  - first 10 chars resolve in `Luminos`
  - remaining suffix comes from allowed family option sets

- **datasheet-ready**
  - valid code
  - plus:
    - product image exists
    - drawing exists
    - graph exists
    - lens diagram exists when required
    - finish image exists
    - optional fixing / connection assets exist when selected

So:
- a family `55` code can be valid even if a family `55` image is missing
- missing image blocks datasheet quality/readiness, not code validity

## Current Gaps / Risks

- family `55` has not been traced as deeply as family `11` or `32`
- no official family `55` gold PDF sample has been locked yet
- family `55` likely behaves close to generic bars, but that still needs old-vs-new PDF comparison
- family `55` option-driven cable derivation may still matter for perfect parity

## Best Next Follow-Up

1. choose one real family `55` sample as gold test ref
2. generate old/new PDF for that sample
3. compare:
   - product image
   - drawing
   - finish image
   - graph
   - lens diagram
   - text/spec sections
4. patch only if family `55` shows real drift from generic bar logic
