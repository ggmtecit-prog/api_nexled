# Advanced Custom Field Overrides Matrix

Status: draft

Purpose: define the first safe field map for `Advanced Field Overrides`.

This matrix is the contract between:
- frontend labels
- request payload keys
- backend merge targets
- PDF-visible slots

Related docs:
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_IMPLEMENTATION_PLAN.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_IMPLEMENTATION_PLAN.md)

## 1. Rules

- every field key must map to one exact render slot
- no key may change base product resolution
- optional section fields only appear when that section exists
- text only

## 2. V1 matrix

| UI label | Request key | Render target | Type | Max | Notes |
| --- | --- | --- | --- | --- | --- |
| Reference | `display_reference` | `data.reference` display slot in luminotechnical/header usage | text | 40 | display only |
| Description | `display_description` | `context.document_title` and `data.description` display slot | text | 160 | display only |
| Size | `display_size` | visible size label slot when implemented in PDF copy | text | 40 | no lookup effect |
| Color | `display_color` | visible color label slot | text | 80 | no lookup effect |
| CRI | `display_cri` | visible cri label slot | text | 40 | no lookup effect |
| Series | `display_series` | visible series label slot | text | 80 | no lookup effect |
| Lens | `display_lens_name` | `data.lens_name` | text | 80 | safe direct target |
| Finish | `display_finish_name` | `data.finish.finish_name` | text | 120 | safe direct target |
| Cap | `display_cap` | visible cap label slot when implemented | text | 80 | display only |
| Option | `display_option_code` | visible option label slot when implemented | text | 120 | display only |
| Flux | `display_flux` | `data.luminotechnical.flux` | text | 40 | display only |
| Efficacy | `display_efficacy` | `data.luminotechnical.efficacy` | text | 40 | display only |
| CCT | `display_cct` | `data.luminotechnical.cct` | text | 60 | display only |
| Color Label | `display_color_label` | `data.luminotechnical.color_label` | text | 80 | display only |
| CRI Label | `display_cri_label` | visible cri display slot if separated | text | 40 | optional if needed |
| Drawing A | `drawing_dimension_A` | `data.drawing.A` | text | 32 | display only |
| Drawing B | `drawing_dimension_B` | `data.drawing.B` | text | 32 | display only |
| Drawing C | `drawing_dimension_C` | `data.drawing.C` | text | 32 | display only |
| Drawing D | `drawing_dimension_D` | `data.drawing.D` | text | 32 | display only |
| Drawing E | `drawing_dimension_E` | `data.drawing.E` | text | 32 | display only |
| Drawing F | `drawing_dimension_F` | `data.drawing.F` | text | 32 | display only |
| Drawing G | `drawing_dimension_G` | `data.drawing.G` | text | 32 | display only |
| Drawing H | `drawing_dimension_H` | `data.drawing.H` | text | 32 | display only |
| Drawing I | `drawing_dimension_I` | `data.drawing.I` | text | 32 | display only |
| Drawing J | `drawing_dimension_J` | `data.drawing.J` | text | 32 | display only |
| Fixing Name | `fixing_name` | `data.fixing.name` | text | 120 | optional section only |
| Power Supply Description | `power_supply_description` | `data.power_supply.description` | text | 1200 | optional section only |
| Connection Cable Description | `connection_cable_description` | `data.connection_cable.description` | text | 1200 | optional section only |

## 3. Deferred fields

These are allowed later, but should not be V1 unless a clear render target is finalized.

| UI label | Reason deferred |
| --- | --- |
| Company | mostly footer/header branding behavior, not one plain text slot |
| Language | changes whole document translation path, not one field |
| Purpose | currently affects generated header copy composition |
| Cable Connector | not always visible as plain text field |
| Cable Type | not always visible as plain text field |
| End Cap | depends on family and section visibility |
| Gasket | mostly affects technical setup, not always printed |
| IP Rating | may conflict with true tested/compliance info |
| Fixing Code | different from fixing name |
| Power Supply Code | different from description |
| Connection Cable Code | different from description |

## 4. UI grouping recommendation

### Group 1: Core

- Reference
- Description

### Group 2: Product labels

- Size
- Color
- CRI
- Series
- Lens
- Finish
- Cap
- Option

### Group 3: Luminotechnical

- Flux
- Efficacy
- CCT
- Color Label
- CRI Label

### Group 4: Drawing

- A
- B
- C
- D
- E
- F
- G
- H
- I
- J

### Group 5: Optional sections

- Fixing Name
- Power Supply Description
- Connection Cable Description

## 5. Availability rules

- `fixing_name` only when `data.fixing` exists
- `power_supply_description` only when `data.power_supply` exists
- `connection_cable_description` only when `data.connection_cable` exists
- drawing dimension fields only when key exists in `data.drawing`

## 6. Precedence

Per field:

1. `field_overrides`
2. section `copy_overrides`
3. `text_overrides`
4. official runtime value

## 7. Notes

- `display_size`, `display_series`, `display_cap`, and `display_option_code` may need one small PDF layout extension if the current PDF does not already print those values explicitly.
- That is acceptable. It is still safer than feeding free text into the base request.

