# Barras Family 11

Purpose:
- document family `11` code logic for parity work
- keep official catalog mask, old runtime behavior, DB truth, and datasheet asset sources in one place

Scope:
- family `11`
- product line cluster around Barra 24V
- includes catalog variants that appear to branch inside same family:
  - base Barra 24V
  - CTRL / DL style variant
  - Magnética-style variant

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. legacy PHP datasheet logic
4. catalog PDF reference

## Official Catalog Reference

From [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf), family `11` appears with more than one mask:

- `11TTTTLLLPAXXYYZZ`
- `11TTTTLLLPAXXYY01`
- `11TTTTLLLPAXX01ZZ`

Meaning:
- family `11` does **not** have one single catalog explanation mask
- suffix logic changes by product sub-line
- some suffix blocks become fixed depending on variant

Important:
- this PDF is only clue/reference
- it does not replace runtime validation rules

## Current Internal Normalized Mask

Current live/internal system uses:

`[Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]`

For family `11`, that means:

`11 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

Example:

- `11037581110010100`
- `11007502111010100`

## Old Runtime Behavior

### Frontend code build

Old configurator builds family `11` reference from these parts:

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

Important old behavior:
- user selects option lists loaded per family
- old frontend concatenates `ref` fragments from each select
- first 10 chars are then checked in `Luminos`

### Validity rule

Old runtime validates family `11` code like this:

1. build full reference from selected option pieces
2. take first 10 chars
3. query `Luminos`
4. if row exists:
   - code identity valid
   - description loads
5. if row missing:
   - error shown:
   - `A combinação da família, tamanho, cor, CRI e série não exite na view Luminos`

Source:
- [appdatasheets/funcoes/getDescricaoProduto.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\getDescricaoProduto.php)
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

So for family `11`:
- **code validity source** = `Luminos` identity check + allowed family option lists
- not image existence

## Family 11 Segment Map

### Internal normalized meaning

| segment | length | family 11 meaning | main truth source |
|---|---|---|---|
| `11` | 2 | family code | `Familias`, old runtime |
| `TTTT` | 4 | size / length | `Tamanhos` via family option loading |
| `color` | 2 | color / LED code part 1 | `Cor` / `Luminos` |
| `cri` | 1 | CRI code | `CRI` / `Luminos` |
| `series` | 1 | series / product series | `Series` / `Luminos` |
| `lens` | 1 | lens / acrylic | `Acrilico` |
| `finish` | 2 | body finish | `Acabamento` |
| `cap` | 2 | cap / end piece | `Cap` |
| `option` | 2 | option / connection-related suffix in current live system | `Opcao` |

### Catalog-facing meaning

Catalog mask for family `11` tends to present:

- `TTTT` = size
- `LLL` = light/color family block
- `P` = power version
- `A` = lens
- `XX` = finish
- `YY` = cap / connection-related block
- `ZZ` = final option / control / connection block

Important mismatch:
- catalog `LLLP` is not same presentation as current normalized split
- internally we split:
  - `color 2`
  - `cri 1`
  - `series 1`
- catalog compresses/explains that block differently

So for parity:
- keep internal normalized mask for code processing
- add family-11 mapping layer for official PDF explanation

## Concrete Valid Example References

Known family `11` examples already used in repo/runtime:

| reference | status | note |
|---|---|---|
| `11037581110010100` | valid example | current live sample / placeholder |
| `11007502111010100` | valid example | current live sample with lens |

These are safe examples for docs and testing.

## Invalid Example Rule

For family `11`, invalid code currently means:

- first 10 chars do not exist in `Luminos`

So invalidity is not best documented as one random hardcoded example yet.
Better rule:

- **invalid if** `11 + TTTT + color2 + cri1 + series1` does not resolve in `Luminos`

Action:
- later add 2 or 3 verified invalid family-11 refs after explorer/runtime check
- do not invent fake invalid examples in docs

## Family 11 Option Sources

Old family `11` options come from these reference tables via family mapping:

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

Current live API mirrors same idea in:
- [api/lib/code-explorer.php](c:\xampp\htdocs\api_nexled\api\lib\code-explorer.php)
- current `options` endpoint family loaders

## Old Family 11 Special Runtime Behavior

Family `11` is treated as bar family in old UI logic.

### Bar option -> cable auto-fill

Old frontend grouped bars as:

- `11`
- `32`
- `55`
- `58`

For these families, when `opcao` changed:
- old JS parsed option description
- auto-set:
  - cable connector
  - cable length
  - cable color

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

This matters for family `11` because:
- option suffix is not only display field
- it drives extra cable-related form state

### Family exception UI

For family `11`, old UI exposed extra fields:

- `acrescimo`
- `tampa`
- `vedante`
- `caboligacao`
- `fixacao`

Source:
- [appdatasheets/script.js](c:\xampp\htdocs\api_nexled\appdatasheets\script.js)

So parity for family `11` requires:
- not only code mask
- but also old conditional UI/extra field logic

## Datasheet Asset Sources For Family 11

This is separate from code validity.

### Product hero image

Source pattern:
- [appdatasheets/funcoes/datasheets/barra.php](c:\xampp\htdocs\api_nexled\appdatasheets\funcoes\datasheets\barra.php)

Family `11` uses:
- `/img/11/produto/{lens}/`
- or `/img/11/produto/clear/{series}/`

Candidate names depend on:
- finish
- connector cable
- cable type
- end cap
- fallback to finish + cap

### Technical drawing

Family `11` drawing source:
- `/img/11/desenhos/`

Candidate names:
- `{cap}_{connectorcable}_{tampa}`
- `{cap}_{tampa}`
- `{connectorcable}_{tampa}`
- `{cap}`

Dimension values come from bar sizes JSON chosen by family group.

For family `11`, current old decoder groups it under:
- `barras`

### Finish image

Family `11` finish/lens asset source:
- `/img/11/acabamentos/{lens}/`
- or `/img/11/acabamentos/clear/{series}/`

Candidate names:
- `{finish}_{cap}`
- `{finish}_{tampa}`

### Color graph

Shared source:
- `/img/temperaturas/{ID_Led}`
- plus `json/descricao/leds.json`

So family `11` graph readiness depends on:
- `Luminos.ID_Led`
- matching graph file
- matching LED description entry

### Lens / radiation diagram

Family `11` source:
- `/img/11/diagramas/{lens}.svg`
- `/img/11/diagramas/i/{lens}.svg`

If lens code has no file:
- code may still be valid
- datasheet may be blocked or section hidden

### Optional fixing / cable / extras

Family `11` optional assets may also use:

- `/img/11/fixacao/...`
- `/img/11/ligacao/...`

These affect datasheet completeness, not base code validity.

## Separation: Valid Code vs Ready Datasheet

For family `11`:

### Code valid

Means:
- first 10 chars exist in `Luminos`
- suffix values come from allowed option lists

Main sources:
- `Luminos`
- `tecit_referencias`
- old runtime JS/PHP

### Datasheet ready

Means:
- code valid
- required images/graphs/drawings/diagrams exist
- required data rows exist for PDF sections

Main sources:
- `appdatasheets/img/11/...`
- `appdatasheets/json/...`
- generator functions

So image missing does **not** mean family `11` code invalid.
It means:
- datasheet blocked
- or section incomplete

## Current Gaps / Risks

### 1. Catalog mask vs normalized mask mismatch

Official family `11` explanation mask:
- `11TTTTLLLPAXXYYZZ`
- and family variants

Current internal mask:
- `11 + size4 + color2 + cri1 + series1 + lens1 + finish2 + cap2 + option2`

Need mapping layer.

### 2. Same family has multiple official suffix variants

Family `11` in catalog is not one single suffix rule.
Need sub-line mapping for:
- base Barra 24V
- CTRL / DL-like line
- Magnética-like line

### 3. Old option-driven cable behavior still matters

If parity goal is strict:
- family `11` docs must include option -> cable autofill behavior
- not only pure code syntax

### 4. Datasheet blockers often asset-side

Typical family `11` datasheet blockers:
- missing color graph
- missing lens diagram
- missing technical drawing
- missing finish image

These are readiness issues, not code validity issues.

## Recommended Next Follow-Up

Best next family-11 work:

1. make `BARRAS_FAMILY_11_SUBLINES.md`
   - split:
     - base
     - CTRL / DL
     - Magnética

2. verify real family-11 valid refs from DB/runtime
   - add 5 to 10 confirmed examples

3. map official `LLLPAXXYYZZ` blocks to internal normalized fields

4. document which suffix parts are:
   - fixed
   - variable
   - repurposed by sub-line
