# Barras Family 58

Purpose:
- document family `58` code logic for parity work
- keep catalog mask, old runtime behavior, DB truth, and datasheet asset rules in one place

Scope:
- family `58`
- Barra 24V HOT line

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `58` appears with this mask:

- `58TTTTLLL1101YY00`

Simple reading:
- family `58` is one of the most constrained Barra masks in the catalog
- middle block includes hardcoded `1101`
- only `YY` still appears variable near the end
- final `00` is fixed

Important:
- this is catalog-facing clue
- not full runtime truth by itself

## Current Internal Normalized Mask

Current live/internal system still treats family `58` with the normalized mask:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `58`, that means:

`58 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

This is one of the clearest places where:

- internal normalized live logic
- and official catalog explanation

do not line up one-to-one.

## Old Runtime Behavior

### Frontend code build

Old configurator still builds family `58` reference from same pieces as the other main bar families:

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

Old runtime validates family `58` same base way as families `11`, `32`, and `55`:

1. build full code from selected option pieces
2. take first 10 chars
3. query `Luminos`
4. if identity exists:
   - code identity valid
   - description loads
5. if identity missing:
   - old UI shows Luminos combination error

So for family `58`:
- **code validity source** = `Luminos` identity + allowed family option lists
- not images

## Family 58 Segment Map

### Internal normalized meaning

| segment | length | family 58 meaning | main truth source |
|---|---|---|---|
| `58` | 2 | family code | `Familias`, old runtime |
| `TTTT` | 4 | size / length | `Tamanhos` |
| `color` | 2 | color / LED code part 1 | `Cor` / `Luminos` |
| `cri` | 1 | CRI code | `CRI` / `Luminos` |
| `series` | 1 | series / product series | `Series` / `Luminos` |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | finish | `Acabamento` |
| `cap` | 2 | cap / end piece / cable form | `Cap` |
| `option` | 2 | option / final suffix | `Opcao` |

### Catalog-facing meaning

Catalog mask:

`58TTTTLLL1101YY00`

Best current interpretation:

| catalog block | likely meaning |
|---|---|
| `58` | family |
| `TTTT` | size |
| `LLL` | light/color family block |
| hardcoded `1101` | family-specific constrained middle suffix |
| `YY` | cap / connection-related variable block |
| fixed `00` | fixed final option block |

Important:
- current runtime still decodes `cri`, `series`, `lens`, and `finish` as separate fields
- catalog compresses that area into a fixed family explanation block
- family `58` therefore needs a mapping layer when aiming for official PDF/code-table parity

## Concrete Example References

### Real `Luminos` identity examples

These first-10-char identities exist in `Luminos`:

| identity | description | product id |
|---|---|---|
| `5800753214` | `LLED B 24V HOT 10 WW303 HE DL` | `BHOT/24v/10/3s` |
| `5800753414` | `LLED B 24V HOT 10 NW403 HE DL` | `BHOT/24v/10/3s` |
| `5801503214` | `LLED B 24V HOT 17 WW303 HE DL` | `BHOT/24v/17/3s` |

### Catalog-shaped expanded examples

Because family `58` is clearly constrained in catalog form, these are useful **reference candidates**, not yet locked gold samples:

| full reference | note |
|---|---|
| `58007532141010000` | built from real identity `5800753214` + HOT-style fixed-looking suffix |
| `58015034141010000` | second HOT-style candidate using a real `Luminos` identity |

These should be runtime-tested before being treated as parity gold references.

## Invalid Example Rule

For family `58`, safest invalid rule is same as the other verified bar families:

- **invalid if** first 10 chars do not exist in `Luminos`

So do not invent fake invalid full references yet.
Later:
- collect 2 or 3 real invalid refs after runtime/explorer check

## Family 58 Option Sources

Old family `58` options come from family-mapped reference tables:

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

Important runtime clue from current option data:

- family `58` real `Luminos` identities currently end in `...14`
- so current runtime clearly allows family `58` values that do not read like the catalog’s literal `1101` middle block

That means:
- catalog explanation and runtime code shape diverge here more than in families `11` or `55`

## Old Family 58 Special Runtime Behavior

Family `58` is in old "bar-like" runtime group:

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

Family `58` is included in that rule.

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

### Family exception UI

Family `58` also belongs to old bar exception group.
Old UI exposes extra fields for it:

- `acrescimo`
- `tampa`
- `vedante`
- `caboligacao`
- `fixacao`

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

## Datasheet Asset Sources For Family 58

This is separate from code validity.

### Product hero image

Family `58` has its own old HOT-specific hero-image rule.

Source:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)
- [api/lib/product-header.php](c:\xampp\htdocs\api_nexled\api\lib\product-header.php)

For family `58`, hero image candidate is:

- `{finish}_{tampa}`

This is different from:

- generic bars, which use `finish + connector + cable type + tampa` or `finish + cap`
- BT bars (`32`), which use connector-focused naming only

Folder pattern:

- clear lens:
  - `appdatasheets/img/58/produto/clear/{series}/`
- non-clear lens:
  - `appdatasheets/img/58/produto/{lens}/`

### Technical drawing

Family `58` uses bar drawing logic, but with HOT-specific size file mapping.

Sources:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)
- [api/lib/technical-drawing.php](c:\xampp\htdocs\api_nexled\api\lib\technical-drawing.php)
- [api/lib/reference-decoder.php](c:\xampp\htdocs\api_nexled\api\lib\reference-decoder.php)

Current live mapping:

- bar sizes file = `barras_hot`

Drawing candidates still follow generic bar pattern:

- `{cap}_{connectorcable}_{tampa}`
- `{cap}_{tampa}`
- `{connectorcable}_{tampa}`
- `{cap}`

So:
- family `58` is HOT-specific in image logic
- but not fully custom in drawing-candidate shape

### Color graph

Family `58` uses common LED graph assets:

- `appdatasheets/img/temperaturas/...`

Source:
- [api/lib/sections.php](c:\xampp\htdocs\api_nexled\api\lib\sections.php)

### Lens diagram

Family `58` uses family-specific diagram assets:

- `appdatasheets/img/58/diagramas/...`
- `appdatasheets/img/58/diagramas/i/...`

Source:
- [api/lib/sections.php](c:\xampp\htdocs\api_nexled\api\lib\sections.php)

### Finish image

Family `58` currently follows generic bar finish image rule in the new API.

Expected folder shape:

- `appdatasheets/img/58/acabamentos/{lens}/...`

Current live code does **not** have a dedicated family `58` finish-image special case like family `32`.

This may or may not be sufficient; it still needs runtime parity confirmation.

### Product IDs / line naming

Real `Luminos` product IDs for family `58` use:

- `BHOT/...`

This is important because family `58` is not just "generic Barra" in DB naming.
It already behaves as its own HOT line.

## Code-Valid vs Datasheet-Ready

For family `58`:

- **code-valid**
  - first 10 chars resolve in `Luminos`
  - remaining suffix comes from allowed family option sets

- **datasheet-ready**
  - valid code
  - plus:
    - HOT hero image exists
    - drawing exists
    - graph exists
    - lens diagram exists when required
    - finish image exists
    - optional fixing / connection assets exist when selected

So:
- a family `58` code can be valid even if a HOT-specific product image is missing
- missing image blocks datasheet quality/readiness, not code validity

## Current Gaps / Risks

- family `58` is one of the strongest examples of catalog/runtime mask divergence
- no locked official family `58` old-vs-new PDF parity sample exists yet
- family `58` finish image logic may still need dedicated parity confirmation
- current runtime normalized model may hide family-specific HOT constraints that the catalog explains as fixed

## Best Next Follow-Up

1. choose one real family `58` sample as gold test ref
2. generate old/new PDF for that sample
3. compare:
   - HOT hero image
   - drawing
   - finish image
   - graph
   - lens diagram
   - text/spec sections
4. verify whether family `58` needs extra API family-specific logic beyond current HOT image + `barras_hot` sizing behavior
