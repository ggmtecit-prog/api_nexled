# Showcase PDF Family Matrix

Status: draft

Purpose: map every known family to a showcase renderer group and an initial implementation status.

Related docs:
- [SHOWCASE_PDF_FEATURE_SPEC.md](./SHOWCASE_PDF_FEATURE_SPEC.md)
- [SHOWCASE_PDF_IMPLEMENTATION_PLAN.md](./SHOWCASE_PDF_IMPLEMENTATION_PLAN.md)

## 1. Reading Guide

Columns:
- `family`: family code
- `name`: current family name from registry
- `product_type`: current registry class
- `renderer`: planned showcase renderer group
- `phase_order`: suggested rollout order
- `status`: planned initial state
- `notes`: implementation notes

Status values:
- `planned_first_wave`: strong first implementation target
- `planned_later_wave`: supported by architecture, but not first ship
- `blocked_until_mapped`: keep blocked until rules, samples, and assets are mapped

## 2. Matrix

| family | name | product_type | renderer | phase_order | status | notes |
| --- | --- | --- | --- | ---: | --- | --- |
| `01` | T8 AC | tubular | tubular | 3 | planned_first_wave | reuse tubular grouped spectra and drawing logic |
| `02` | T8 VC | tubular | tubular | 3 | planned_later_wave | same renderer family, needs family rules |
| `03` | T8 CC | tubular | tubular | 3 | planned_later_wave | same renderer family, needs family rules |
| `04` | T5 CC | tubular | tubular | 3 | planned_first_wave | |
| `05` | T5 VC | tubular | tubular | 3 | planned_first_wave | |
| `06` | T5 AC | tubular | tubular | 3 | planned_first_wave | |
| `07` | PLL | tubular | tubular | 3 | planned_later_wave | |
| `08` | PLC | tubular | tubular | 3 | planned_later_wave | |
| `09` | S14 | tubular | tubular | 3 | planned_later_wave | |
| `10` | Barra CC | barra | barra | 2 | planned_first_wave | strong option and accessory coverage needed |
| `11` | Barra 24V | barra | barra | 2 | planned_first_wave | |
| `12` | Spot | spot | spot | 6 | blocked_until_mapped | needs first official sample and section rules |
| `13` | AR111 | spot | spot | 6 | blocked_until_mapped | |
| `14` | AR111 CC | spot | spot | 6 | blocked_until_mapped | |
| `15` | AR111 COB | spot | spot | 6 | blocked_until_mapped | |
| `16` | PAR 30 | spot | spot | 6 | blocked_until_mapped | |
| `17` | PAR 30 CC | spot | spot | 6 | blocked_until_mapped | |
| `18` | PAR 30 COB | spot | spot | 6 | blocked_until_mapped | |
| `19` | PAR 38 | spot | spot | 6 | blocked_until_mapped | |
| `20` | PAR 38 CC | spot | spot | 6 | blocked_until_mapped | |
| `21` | PAR 38 COB | spot | spot | 6 | blocked_until_mapped | |
| `22` | Decoracao | decor | decor | 7 | blocked_until_mapped | likely lighter commercial showcase layout |
| `23` | Projetores | dynamic | dynamic | 5 | planned_later_wave | subtype-aware dynamic renderer |
| `24` | Campanulas | highbay | highbay | 8 | blocked_until_mapped | |
| `25` | Luminarias | luminaire | luminaire | 9 | blocked_until_mapped | |
| `26` | Retrofit redondo | downlight | downlight | 1 | planned_later_wave | same renderer family as downlight |
| `27` | Retrofit quadrado | downlight | downlight | 1 | planned_later_wave | |
| `28` | Retrofit spot | downlight | downlight | 1 | planned_later_wave | needs exact section rules |
| `29` | Downlight redondo | downlight | downlight | 1 | planned_first_wave | strongest current sample |
| `30` | Downlight quadrado | downlight | downlight | 1 | planned_first_wave | |
| `31` | Barra RGB 24V VC | barra | barra | 2 | planned_first_wave | |
| `32` | Barra 24V T | barra | barra | 2 | planned_first_wave | special connector logic may affect option sections |
| `33` | DL Quadrado COB | downlight | downlight | 1 | planned_later_wave | |
| `34` | DL Redondo COB | downlight | downlight | 1 | planned_later_wave | |
| `35` | Armadura emb | luminaire | luminaire | 9 | blocked_until_mapped | |
| `36` | Armadura ext | luminaire | luminaire | 9 | blocked_until_mapped | |
| `37` | Painel | panel | panel | 10 | blocked_until_mapped | |
| `38` | Painel Embutir | panel | panel | 10 | blocked_until_mapped | |
| `39` | Retrofit armadura | luminaire | luminaire | 9 | blocked_until_mapped | |
| `40` | Barra 24V CCT | barra | barra | 2 | planned_first_wave | |
| `41` | Projetor CCT | dynamic | dynamic | 5 | planned_later_wave | |
| `42` | BT CCT | barra | barra | 2 | planned_later_wave | |
| `43` | Decoracao2 | decor | decor | 7 | blocked_until_mapped | |
| `45` | BT45 24V | barra | barra | 2 | planned_later_wave | |
| `46` | Projetor 2 | dynamic | dynamic | 5 | planned_later_wave | |
| `47` | Retrofit campanula | highbay | highbay | 8 | blocked_until_mapped | |
| `48` | Dynamic | dynamic | dynamic | 5 | planned_first_wave | |
| `49` | ShelfLED | shelf | shelf | 4 | planned_first_wave | |
| `50` | Armadura IP | luminaire | luminaire | 9 | blocked_until_mapped | |
| `51` | Village | decor | decor | 7 | blocked_until_mapped | |
| `52` | DualTop embutir | luminaire | luminaire | 9 | blocked_until_mapped | |
| `53` | DualTop saliente | luminaire | luminaire | 9 | blocked_until_mapped | |
| `54` | Canopy | canopy | canopy | 11 | blocked_until_mapped | |
| `55` | Barra 12V | barra | barra | 2 | planned_first_wave | |
| `56` | BT 12V | barra | barra | 2 | planned_later_wave | |
| `57` | Projetor 3 | dynamic | dynamic | 5 | planned_later_wave | |
| `58` | B 24V HOT | barra | barra | 2 | planned_first_wave | |
| `59` | NEON 24V | barra | barra | 2 | planned_later_wave | may need dedicated visual treatment inside barra renderer |
| `60` | B 24V I45 | barra | barra | 2 | planned_later_wave | |

## 3. Rollout Notes

Best first-wave groups:
- downlight
- barra
- tubular
- shelf
- dynamic

Reason:
- strongest current code understanding
- most reusable current API helpers
- highest overlap with current datasheet runtime and existing docs

Later-wave groups:
- spot
- decor
- highbay
- luminaire
- panel
- canopy

Reason:
- need first official showcase references
- need section and asset rules defined before implementation

## 4. Registry Decision Rule

During implementation, every family should resolve one of these states:
- supported and mapped to a renderer
- known but blocked honestly
- unknown and rejected

No family should silently fall back to the wrong renderer.
