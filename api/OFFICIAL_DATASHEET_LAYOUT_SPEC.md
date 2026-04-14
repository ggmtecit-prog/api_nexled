# Official Datasheet Layout Spec

Source analyzed:
- `C:\Users\USER\Downloads\Datasheet_Barra_24V_10_WW273HE_Clear_pt.pdf`

Product analyzed:
- `Barra 24V 10`
- language: `pt`
- length: `8 pages`

Purpose:
- freeze official live PDF order
- list fields per page
- record gaps between official PDF and current generator
- use later as implementation checklist

## Global Structure

Common on every page:
- top title/header: `LED Barra 24V 10 WW273 HE Clear`
- bottom footer block:
  - company: `TEC IT, Tecnologia, Inteligência e Domótica, S.A.`
  - address
  - phone
  - e-mail
  - website
  - last update date
  - technical profile version
  - legal disclaimer
  - page number `Pag. X/8`

Visual rhythm:
- header title on top every page
- main content in middle
- footer/legal/page number on bottom every page

## Page Order

### Page 1

Order:
1. product title/header
2. hero product image
3. energy class label/image
4. long marketing description text
5. `Características` table
6. `Características luminotécnicas` table
7. footer

Fields found:

Marketing description:
- general product paragraph
- flexibility/customization paragraph
- installation/use paragraph
- fixing solutions paragraph
- food contact paragraph
- eye safety paragraph
- SDCM paragraph
- energy class regulation paragraph

Characteristics table:
- `Corrente`
- `Potência`
- `Nº de LEDs`
- `Fonte de Alimentação`
- `Voltagem`
- `Tempo de vida útil`
- `Temperatura de funcionamento`
- `Grau de protecção`

Luminotechnical table:
- `Referência`
- `Descrição`
- `Classe`
- `Fluxo (lm)`
- `Efi. (lm/W)`
- `Cor`
- `Luz`
- `CRI`

Notes:
- this page is dense and acts like product summary / commercial first page
- official PDF clearly starts with text-heavy sales/technical summary, not drawing first

### Page 2

Order:
1. product title/header
2. `Desenho Técnico`
3. technical drawing image
4. dimensions table
5. `Espectro de cor`
6. spectrum label text
7. color metrics text
8. color/spectrum graph image
9. footer

Fields found:

Technical drawing:
- dimensions labels: `A B C D E F G H I`
- dimensions values block: `Dimensões (mm)`

Color section:
- LED/color label, example: `Branco quente 2700K CRI >80`
- `Temperatura de cor`
- `CIE Colorimetric Parameters`
- `Chromaticity coordinates`
- `CCT`
- `Color Ratio`
- `CRI`
- graph axes / spectrum chart

Notes:
- official order puts technical drawing before spectrum graph
- graph section is same page as drawing, not later page

### Page 3

Order:
1. product title/header
2. `Diagrama de radiação`
3. lens name
4. opening angle / beam angle text
5. radiation diagram image
6. option-code explanation block
7. footer

Fields found:
- `Lente`
- `Ângulo de abertura`
- `Ângulo do feixe`
- diagram legend (`C0-C180`)

Option code explanation:
- `XXYYZZ - Código das opções`
- `Os últimos seis algarismos são opções adicionais`
- `XX - Acabamento do corpo`
- list of finish examples and code
- `YY - Modo de ligação`
- list of connection mode examples and code
- `ZZ - Conector do cabo`
- connector example and code

Notes:
- official sample uses this page to explain configurable suffixes
- current engine does not have this suffix-code explanation section

### Pages 4 to 8

Role:
- accessory / fixing / caps catalog pages

Observed order:

Page 4:
- `Opções de fixação`
- fixing item image + name + `Código`
- multiple fixing options on one page

Page 5:
- more fixing options
- each item has:
  - image
  - name
  - `Código`

Page 6:
- support + end cap items
- each item has:
  - image
  - name
  - white/grey code variants

Page 7:
- more supports/end caps
- same catalog structure

Page 8:
- final support/end cap items
- same catalog structure
- footer

Catalog item field pattern:
- item name
- product image/render
- one or more code lines
- sometimes color variant split:
  - `Código branca`
  - `Código cinzenta`
  - `Código branca direita`
  - `Código branca esquerda`
  - `Código cinzenta direita`
  - `Código cinzenta esquerda`

Notes:
- official PDF spreads accessories over many pages
- this is catalog-style, not single optional block
- current engine only supports single optional `fixing`, `power supply`, `connection cable` sections

## Repeated Official Footer Fields

Footer fields repeated every page:
- company legal name
- address
- phone
- email
- website
- `Última atualização`
- `Perfil técnico v.`
- disclaimer: `Especificações e desenho sujeitos a alterações sem aviso prévio.`
- page numbering

## Official Content Model

Official sample uses these content groups:

Required main groups:
- hero product block
- energy class
- long-form product description
- characteristics table
- luminotechnical table
- technical drawing
- dimensions
- color/spectrum graph
- radiation diagram
- option-code explanation
- accessory catalog

Accessory catalog subgroups:
- fixing clips
- rotating clips
- magnetic clips
- PVC supports
- silicone caps
- corner supports

## Gaps vs Current Generator

Current generator order in [pdf-layout.php](./lib/pdf-layout.php):
1. header
2. characteristics
3. luminotechnical
4. technical drawing
5. color graph
6. lens diagram
7. finish and lens
8. optional fixing
9. optional power supply
10. optional connection cable

Main differences:
- official page 1 includes large commercial description before tables
- official page 2 combines drawing + color graph
- official page 3 contains radiation diagram + option suffix explanation
- official sample does not show current `finish and lens` section in same way
- official sample uses multi-page accessory catalog, not one optional fixing block
- official sample item pages include many accessory cards, not a single chosen option

## Implementation Notes

If goal is "match official live PDF order", generator likely needs these layout phases:

Phase A:
- page 1 summary template
- commercial description block before tech tables

Phase B:
- page 2 drawing + spectrum combined layout

Phase C:
- page 3 radiation + option code explanation layout

Phase D:
- accessory catalog pages built from family catalog data, not just selected option

## Data Needed To Match Official PDF

Still needed or needs restructuring:
- commercial paragraph set per product/family
- dimensions block exactly like official
- spectrum/color metrics with graph and numeric values
- radiation diagram with beam/opening angle text
- option suffix explanation source:
  - finish code map
  - connection mode code map
  - connector code map
- accessory catalog source with:
  - name
  - image
  - code
  - optional color variant codes
  - pagination rules

## Suggested Build Plan Later

1. lock official page templates by product family
2. separate `selected option` sections from `catalog pages`
3. create dedicated builder for:
   - page 1 summary
   - page 2 drawing + spectrum
   - page 3 diagram + suffix explanation
   - pages 4+ accessory catalog
4. keep current engine as fallback until official template parity done

## Important Conclusion

Current engine can generate PDFs.

But current engine does **not** yet match official live datasheet structure.

Biggest mismatch is not only styling. It is **document architecture**:
- official PDF is page-programmed catalog format
- current generator is modular section format
