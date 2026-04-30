# Custom Datasheet Override Matrix

Status: draft

Purpose: define exactly which fields may be customized, how they map to the current datasheet runtime, and what validation rules apply.

Related docs:
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md](./CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md)
- [ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md](./ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md)

## 1. Reading this document

Columns:
- `Area`: UI group and runtime group
- `Override key`: canonical request key under `custom`
- `Current source`: where official data comes from now
- `V1`: whether first release should support it
- `Validation`: core rule
- `Fallback`: what happens if override absent
- `Notes`: implementation constraints

## 2. V1 Text Overrides

| Area | Override key | Current source | V1 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Title | `text_overrides.document_title` | current configured description / runtime naming | Yes | plain text, max 120 chars | keep official title | affects visible heading/filename only |
| Header copy | `text_overrides.header_copy` | [product-header.php](./lib/product-header.php) JSON description assembly | Yes | plain text, max 1200 chars, newline-safe | keep official header copy | should replace only the marketing copy block, not technical data |
| Footer note | `text_overrides.footer_note` | none today | Yes | plain text, max 160 chars | no extra note | appended to existing footer sentence area |

## 3. Future Text Overrides

| Area | Override key | Current source | V2 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Characteristics intro | `text_overrides.characteristics_intro` | [datasheet.json](./json/datasheet.json) | Later | plain text | keep official | requires layout slot |
| Luminotechnical intro | `text_overrides.luminotechnical_intro` | [datasheet.json](./json/datasheet.json) | Later | plain text | keep official | should not change numeric data |
| Drawing intro | `text_overrides.drawing_intro` | [datasheet.json](./json/datasheet.json) | Later | plain text | keep official | optional only |
| Finish intro | `text_overrides.finish_intro` | [datasheet.json](./json/datasheet.json) | Later | plain text | keep official | useful for custom campaigns |
| Option note | `text_overrides.option_note` | section notes / JSON text | Later | plain text | keep official | if section exists |

## 4. V1 Image Overrides

| Area | Override key | Current source | V1 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Product image | `asset_overrides.header_image` | [product-header.php](./lib/product-header.php) + [images.php](./lib/images.php) | Yes | DAM or controlled local asset ref only | keep official image | highest-value custom image |
| Technical drawing | `asset_overrides.drawing_image` | [sections.php](./lib/sections.php) / technical drawing fetcher | Yes | DAM or controlled local asset ref only | keep official drawing | must stay PDF-safe |
| Finish image | `asset_overrides.finish_image` | [sections.php](./lib/sections.php) `getFinishAndLens()` | Yes | DAM or controlled local asset ref only | keep official finish image | useful for customer-specific visual presentation |

## 5. Future Image Overrides

| Area | Override key | Current source | V2 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Color graph | `asset_overrides.color_graph_image` | [sections.php](./lib/sections.php) `getColorGraph()` | Later | controlled asset ref only | keep official graph | must not imply changed technical truth |
| Lens diagram | `asset_overrides.lens_diagram_image` | [sections.php](./lib/sections.php) `getLensDiagram()` | Later | controlled asset ref only | keep official diagram | use only if approved internally |
| Fixing image | `asset_overrides.fixing_image` | [sections.php](./lib/sections.php) `getFixing()` | Later | controlled asset ref only | keep official | only when section visible |
| Fixing render | `asset_overrides.fixing_render_image` | [sections.php](./lib/sections.php) `getFixing()` | Later | controlled asset ref only | keep official | paired with fixing image |
| Power supply image | `asset_overrides.power_supply_image` | [sections.php](./lib/sections.php) `getPowerSupply()` | Later | controlled asset ref only | keep official | optional section |
| Power supply drawing | `asset_overrides.power_supply_drawing_image` | [sections.php](./lib/sections.php) `getPowerSupply()` | Later | controlled asset ref only | keep official | optional section |
| Connection cable image | `asset_overrides.connection_cable_image` | [sections.php](./lib/sections.php) `getConnectionCable()` | Later | controlled asset ref only | keep official | optional section |

## 6. V1 Section Visibility Overrides

V1 should only allow hiding optional sections. It should not allow hiding core sections.

| Area | Override key | Current source | V1 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Fixing section | `section_visibility.fixing` | section presence in current datasheet runtime | Yes | boolean only | keep current section behavior | may hide optional section |
| Power supply section | `section_visibility.power_supply` | section presence in current datasheet runtime | Yes | boolean only | keep current section behavior | may hide optional section |
| Connection cable section | `section_visibility.connection_cable` | section presence in current datasheet runtime | Yes | boolean only | keep current section behavior | may hide optional section |

## 7. Forbidden Section Visibility Overrides in V1

These must not be user-hideable in V1.

| Section | Reason |
| --- | --- |
| `header` | core identity block |
| `characteristics` | core technical content |
| `luminotechnical` | core technical truth |
| `drawing` | official datasheet structure depends on it |
| `finish` | official datasheet visual product identity |

## 8. Technical Truth Fields That Must Stay Immutable

These fields must not accept user override in V1.

| Field / group | Current source | Reason |
| --- | --- | --- |
| `luminotechnical.flux` | Luminos / DB | measured technical truth |
| `luminotechnical.efficacy` | Luminos / DB | measured technical truth |
| `luminotechnical.cct` | Luminos / DB | technical truth |
| `luminotechnical.color_label` | Luminos / JSON mapping | technical classification |
| `luminotechnical.cri` | Luminos / DB | technical truth |
| `energy_class` | runtime calculation / DB | compliance-sensitive |
| `characteristics.*` values | DB + JSON + derived logic | technical truth |
| legal footer base text | current footer builder | compliance / legal risk |
| product reference structure | reference decoder | must remain exact |

## 9. Asset Source Policy

## 9.1 Allowed in V1

| Source type | Example | V1 | Notes |
| --- | --- | --- | --- |
| DAM asset ID | `asset_123` | Yes | preferred |
| controlled local asset key | `custom/customer-a/downlight-01` | Yes, optional | only if a registry is added |

## 9.2 Not allowed in V1

| Source type | Example | Reason |
| --- | --- | --- |
| arbitrary remote URL | `https://example.com/image.png` | weak reliability and security |
| raw absolute file path from user | `C:\temp\foo.png` | unsafe and inconsistent |
| inline base64 image blob | `data:image/png;base64,...` | too heavy and hard to audit |

## 10. Text Sanitization Policy

| Field group | Format | HTML allowed | Max length | Notes |
| --- | --- | --- | ---: | --- |
| `document_title` | plain text | No | 120 | one-line title |
| `header_copy` | plain text with line breaks | No | 1200 | server converts line breaks safely |
| `footer_note` | plain text | No | 160 | short internal note only |

Rules:
- trim leading/trailing spaces
- collapse dangerous whitespace
- escape HTML
- reject unknown markup

## 11. Merge Policy

Override merge must be field-by-field and explicit.

Recommended rules:
- text override replaces only the mapped display field
- image override replaces only the mapped image path
- hidden optional section removes only that optional section from final render
- absent override means keep official value

Forbidden rule:
- no generic recursive merge of arbitrary user JSON into datasheet data

## 12. UI Mapping Recommendation

Suggested custom tab blocks:

| UI block | Request keys | Notes |
| --- | --- | --- |
| `Custom Text` | `text_overrides.*` | title, header copy, footer note |
| `Custom Images` | `asset_overrides.*` | three V1 image targets |
| `Optional Sections` | `section_visibility.*` | boolean toggles only |
| `Reset Customizations` | clears all | UI-only action |

## 13. Suggested Validation Errors

| Error code | Trigger |
| --- | --- |
| `custom_datasheet_unsupported_field` | unknown override key |
| `custom_datasheet_text_too_long` | text exceeds allowed length |
| `custom_datasheet_invalid_override` | wrong type or malformed payload |
| `custom_datasheet_asset_not_found` | asset ref cannot be resolved |
| `custom_datasheet_asset_unusable` | asset resolves but not PDF-safe |
| `custom_datasheet_section_forbidden` | user attempts to hide core section |

## 14. Rollout Recommendation

V1:
- only rows marked `Yes` in this matrix

V2:
- some rows marked `Later`

V3:
- broader non-technical editable text model, only after V1 stability is proven
