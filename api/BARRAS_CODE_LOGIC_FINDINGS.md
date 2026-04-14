# Barras Code Logic Findings

Source reviewed:
- [1_Barras.pdf](c:\xampp\htdocs\api_nexled\READING_DOCUMENTS\1_Barras.pdf)

Scope note:
- this PDF is **reference only**
- it helps understand catalog-facing code logic
- it does **not** define full system truth by itself
- final code rules must still be validated against:
  - old project behavior
  - `Luminos`
  - `tecit_referencias`
  - old PDF/app generation code

## Main Finding

Official Barras catalog reference does **not** present one single generic code rule for all bar products.

It presents **family-specific reference masks**.

So for later parity work:
- do **not** assume one universal Barra code pattern
- use PDF as support clue, not final truth
- document logic by family/subfamily
- current live decoder can still stay normalized internally, but official PDF logic must be mapped per family

## Strong Patterns Found

These masks appear in PDF text:

- `11TTTTLLLPAXXYYZZ`
- `32TTTTLLLPA01YY00`
- `40TTTTLLLPAXXYYZZ`
- `40TTTTLLLPAXXYY00`
- `11TTTTLLLPAXXYY01`
- `60TTTTLLLPA010100`
- `58TTTTLLL1101YY00`
- `31TTTT9111AXXYY00`
- `11TTTTLLLPAXX01ZZ`

Meaning:
- first 2 digits still act like family
- middle and suffix blocks change by subfamily
- some families have fixed suffix parts like `00`, `01`, `010100`, `1101`

## Common Segment Meaning Seen In PDF

Across Barras pages, these meanings repeat:

- `TTTT` = size / length block
- `LLL` = color temperature / LED family block
- `K` or CRI table appears separately in many sections
- `P` = power version
- `A` = lens block
- `XX` = body finish block
- `YY` = cable connector / connection-related block
- `ZZ` = final option / control / connection block, depending family

Important:
- official PDF groups some logic differently from current normalized decoder
- PDF often treats `LLL` and `CRI` as catalog table blocks, while current live code splits:
  - color = 2 chars
  - cri = 1 char
  - series = 1 char

So later documentation should keep both views:
- **official catalog mask**
- **internal normalized system mask**

## Family-Level Findings

### Family `11`

Seen masks:
- `11TTTTLLLPAXXYYZZ`
- `11TTTTLLLPAXXYY01`
- `11TTTTLLLPAXX01ZZ`

Likely used for:
- Barra 24V
- Barra CTRL / DL variants
- Barra Magnética variant

What this suggests:
- family `11` has multiple sub-rules
- same family code can still branch into different reference masks
- suffix meaning depends on product line, not only family

Observed behavior in PDF:
- `YY` and `ZZ` are not always same thing in all `11` pages
- one family `11` section ties final suffix to connection
- another family `11` section fixes one suffix block to `01`

### Family `32`

Seen mask:
- `32TTTTLLLPA01YY00`

Likely product:
- Barra BT 24V

What this suggests:
- `01` is fixed in middle suffix
- `YY` remains variable
- final `00` fixed

So this family is much more constrained than generic `11`

### Family `40`

Seen masks:
- `40TTTTLLLPAXXYYZZ`
- `40TTTTLLLPAXXYY00`

Likely products:
- Barra 24V CCT IR
- Barra 24V CCT Wi-Fi

What this suggests:
- same family `40` branches by control mode
- one variant keeps final `ZZ` variable
- another hard-fixes final `00`

This matches catalog behavior:
- IR control page exposes final option table
- Wi-Fi page fixes control suffix

### Family `55`

Seen mask:
- `55TTTTLLLPAXXYYZZ`

Likely product:
- Barra 12V

What this suggests:
- family `55` behaves similar to generic `11`
- but remains its own official family/mask in catalog

### Family `58`

Seen mask:
- `58TTTTLLL1101YY00`

Likely product:
- Barra 24V HOT

What this suggests:
- middle block has hardcoded sequence `1101`
- only `YY` still visibly variable near end
- final `00` fixed

This is one clearest signs that official code logic is family-specific, not globally uniform

### Family `60`

Seen mask:
- `60TTTTLLLPA010100`

Likely product:
- Barra 24V I45

What this suggests:
- very constrained family
- several suffix blocks hard-fixed
- only earlier blocks remain variable

### Family `31`

Seen mask:
- `31TTTT9111AXXYY00`

Likely product:
- Barra 24V RGB

What this suggests:
- `9111` behaves like hardcoded color/control family block
- final `00` fixed
- suffix variability lower than generic bars

## Big Architectural Finding

Official PDF logic appears to work like this:

1. family/subfamily chooses reference mask
2. each mask has:
   - variable slots
   - fixed slots
3. catalog tables explain only slots relevant to that product line

This is different from current normalized implementation, where code is treated more like:

- family
- size
- color
- cri
- series
- lens
- finish
- cap
- option

That normalized model is useful for API/configurator logic.
But official PDF/catalog parity needs extra mapping layer:

- `family/subfamily -> official mask`
- `official mask slot -> normalized internal segment(s)`

## Practical Use For Next Work

This PDF should be used for:

1. documenting official mask per Barra family
2. mapping official labels to internal normalized fields
3. deciding when suffix blocks are:
   - variable
   - fixed
   - repurposed by product line
4. rebuilding official PDF pages with correct code explanation tables

This PDF should **not** be used as only source for:

- exact DB validation rules
- final allowed combinations
- full suffix semantics for every family
- all hidden/internal exceptions

Those must come from code + DB + legacy behavior.

## Source Priority

Best source priority for future work:

1. old project runtime behavior
2. database truth (`Luminos`, `tecit_referencias`, related tables)
3. legacy generation code
4. catalog PDF reference

## Recommended Next Documentation

Create one follow-up matrix file:

- `api/BARRAS_CODE_MASK_MATRIX.md`

Columns:
- family
- product line name
- official PDF mask
- fixed blocks
- variable blocks
- meaning of each block
- current normalized internal mapping
- gaps / unknowns

## Current Safe Conclusion

Safe conclusion from `1_Barras.pdf`:

- official Barras code logic is **family-specific**
- some families share broad structure
- many families hard-fix pieces of suffix
- current system should not expose one fake “single Barra code logic” if goal is official parity
