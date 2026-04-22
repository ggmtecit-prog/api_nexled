# Advanced Custom Copy Feature Spec

Status: draft

Purpose: define the "advanced custom copy" extension for `Custom Datasheet` before implementation.

This document covers:
- product goal
- UI toggle behavior
- editable section model
- API contract extension
- validation and layout guardrails
- phased delivery scope

Related docs:
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md](./CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md)
- [CUSTOM_DATASHEET_OVERRIDE_MATRIX.md](./CUSTOM_DATASHEET_OVERRIDE_MATRIX.md)
- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md)

## 1. Summary

Current `Custom Datasheet` already supports:
- small approved text overrides
- small approved image overrides
- optional section visibility toggles

New requested feature:
- a toggle in `Custom` mode
- when enabled, editable PDF copy appears as section-level text fields
- user can prepare a more customized PDF without changing technical truth

Recommended product name:
- `Advanced Custom Copy`

Recommended UI label:
- `Advanced Copy Editing`

## 2. Product Positioning

This feature is:
- an extension of `Custom Datasheet`
- still one exact product
- still based on the official datasheet snapshot
- still rendered in the official datasheet layout

This feature is not:
- a free-form PDF editor
- a layout editor
- a family-level showcase tool
- permission to edit technical numeric truth

## 3. Why this should exist

Current custom mode is useful, but narrow.

The internal team may need:
- customer-specific opening copy
- campaign-specific section intros
- alternative wording for non-technical blocks
- more control than title/header/footer only

This feature should provide that without damaging:
- official datasheet trust
- PDF layout stability
- technical correctness

## 4. Core Recommendation

Do not implement "fully customize PDF" literally.

Best implementation:
- add a toggle in `Custom` mode
- toggle unlocks only editable copy sections
- technical tables and measured values remain locked

In practice:
- section text becomes editable
- section images may still be overridden only through approved asset controls
- section layout remains official

## 5. Goals

- Keep the official datasheet structure intact.
- Let users rewrite approved non-technical copy blocks.
- Keep technical data immutable.
- Keep custom payload explicit and auditable.
- Reuse the current custom datasheet base snapshot flow.
- Make the new mode discoverable in the configurator.

## 6. Non-goals

- Rebuilding the PDF page layout from scratch.
- Making every visible string editable in V1.
- Allowing user HTML, Markdown, or styled rich text.
- Allowing user edits to luminotechnical values, dimensions, compliance, or legal text.
- Replacing `Showcase` for marketing catalog use cases.

## 7. UX Model

## 7.1 Toggle

Add one toggle in `Custom` mode:
- off: current simple custom flow
- on: advanced copy fields appear

Recommended wording:
- label: `Advanced Copy Editing`
- helper: `Turn editable marketing copy blocks into text fields while keeping technical data locked.`

## 7.2 When toggle is off

Keep current UI:
- document title
- header copy
- footer note
- image asset overrides
- optional section visibility

## 7.3 When toggle is on

Show section-based copy editors.

Recommended UI blocks:
- `Header Copy`
- `Characteristics Copy`
- `Luminotechnical Copy`
- `Drawing Copy`
- `Finish / Option Copy`
- `Footer Copy`

Each block should support:
- `Use default copy` checkbox
- one or more text fields / textareas
- current character count
- reset-to-default action

## 7.4 Important UX rule

Advanced copy fields must load from the current official datasheet snapshot.

That means:
- user sees current effective copy as baseline
- not empty fields
- edits are deltas on top of real runtime text

## 8. Data Model

## 8.1 Existing custom model

Current custom payload uses:
- `text_overrides`
- `asset_overrides`
- `section_visibility`
- `footer`

## 8.2 Recommended extension

Add:
- `copy_mode`
- `copy_overrides`

Example:

```json
{
  "base_request": {
    "referencia": "29012032291010100",
    "idioma": "pt",
    "empresa": "0"
  },
  "custom": {
    "mode": "custom",
    "copy_mode": "advanced",
    "text_overrides": {
      "document_title": "Customer Special Downlight"
    },
    "copy_overrides": {
      "header": {
        "intro": "Prepared for customer presentation."
      },
      "characteristics": {
        "intro": "Main product highlights for this proposal."
      },
      "option_codes": {
        "intro": "Available code options for this customer package."
      },
      "footer": {
        "note": "Prepared by internal sales team."
      }
    }
  }
}
```

## 8.3 Why separate `copy_overrides`

Do not overload `text_overrides` forever.

Reason:
- current keys are small global overrides
- advanced copy is section-based and nested
- separate namespace is clearer and easier to validate

## 9. Editable Copy Strategy

Recommended rule:
- only text blocks that already exist as descriptive copy should become editable

Examples of good editable targets:
- section intro paragraph
- short section heading supplement
- option explanation paragraph
- footer note

Examples of bad editable targets:
- column headers for technical tables
- numeric values
- reference code
- energy class
- legal/compliance footer base text

## 10. Canonical Editable Sections

V1 should define a small stable set.

Recommended V1 editable section groups:
- `header`
- `characteristics`
- `luminotechnical`
- `drawing`
- `finish`
- `option_codes`
- `footer`

These map to visual sections already present in official/custom datasheet flow.

## 11. Field-Level Recommendation

Each section should expose only the minimum editable text fields.

Example shape:

```json
{
  "copy_overrides": {
    "header": {
      "intro": "..."
    },
    "characteristics": {
      "intro": "..."
    },
    "luminotechnical": {
      "intro": "..."
    },
    "drawing": {
      "intro": "..."
    },
    "finish": {
      "intro": "..."
    },
    "option_codes": {
      "intro": "..."
    },
    "footer": {
      "note": "..."
    }
  }
}
```

Avoid V1 fields like:
- `heading`
- `subheading`
- `caption_1`
- `caption_2`

unless the runtime actually has stable slots for them.

## 12. Snapshot Requirement

The preview endpoint must be extended to return editable copy snapshot data.

Recommended preview response addition:

```json
{
  "editable_copy": {
    "header": {
      "intro": "Official header text..."
    },
    "characteristics": {
      "intro": "Official characteristics intro..."
    },
    "luminotechnical": {
      "intro": "Official luminotechnical intro..."
    },
    "drawing": {
      "intro": "Official drawing intro..."
    },
    "finish": {
      "intro": "Official finish text..."
    },
    "option_codes": {
      "intro": "Official option-code note..."
    },
    "footer": {
      "note": ""
    }
  }
}
```

This is important because frontend should edit:
- real current text
- not hardcoded placeholders

## 13. Validation Rules

## 13.1 Format

All advanced copy fields in V1 must be:
- plain text
- newline-safe
- HTML-free

## 13.2 Limits

Each field needs a strict maximum.

Recommended V1 max lengths:
- section intro: 1200 chars
- short footer note: 160 chars

## 13.3 Sanitization

Server must:
- trim surrounding whitespace
- normalize line endings
- collapse pathological whitespace
- escape HTML-sensitive characters before render

## 13.4 Empty override behavior

If edited field becomes empty:
- treat as absent override
- fall back to default copy

Do not render blank visible blocks unless explicitly designed.

## 14. Layout Rules

Advanced copy must keep official layout stable.

Recommended rules:
- no new sections added by user
- no user-defined order
- no user-defined typography
- no arbitrary rich text
- no user-controlled HTML tags

Render rule:
- override text should flow only into existing slots

If text is too long:
- preview should warn if possible
- final render should wrap safely
- never overflow outside page boxes silently

## 15. Technical Truth Boundary

These remain immutable even in advanced mode:
- luminotechnical numeric rows
- dimensions values
- CCT / CRI measured values
- energy class
- reference code
- product family / model identity
- legal footer base text

This boundary is mandatory.

## 16. Configurator Behavior

Recommended frontend flow:

1. user builds one exact valid product
2. user switches to `Custom`
3. user enables `Advanced Copy Editing`
4. frontend requests preview snapshot
5. editable sections appear with current default text
6. user edits approved copy blocks
7. preview summary updates
8. user generates custom PDF

## 17. Preview Responsibilities

Preview endpoint should report:
- advanced copy mode enabled or not
- editable sections available for this family
- current default copy snapshot
- applied override counts
- fields rejected by validation
- maybe estimated overflow risk later

## 18. Family Support Model

Best rule:
- any family already supported by `Custom Datasheet` may use advanced copy
- but only for sections that exist in that family runtime

So support is:
- global architecture
- section availability per family runtime

Frontend should hide editors for sections that do not exist in the preview snapshot.

## 19. Error Handling

Recommended new errors:
- `custom_datasheet_copy_unsupported_field`
- `custom_datasheet_copy_too_long`
- `custom_datasheet_copy_invalid_type`
- `custom_datasheet_copy_section_unavailable`

## 20. Delivery Recommendation

## V1

Ship:
- toggle in Custom mode
- preview snapshot of editable copy
- `copy_overrides` validation
- section intro editing for a small stable set
- same official layout render

Do not ship in V1:
- section heading redesign
- arbitrary all-text editing
- rich text / HTML
- per-family special advanced UI

## V2

Possible later additions:
- more editable copy slots
- per-section length hints from renderer
- visual diff between default and custom copy
- saved custom presets

## 21. Acceptance Criteria

Feature is ready when:
- current `Custom Datasheet` still works with toggle off
- toggle on loads real editable copy snapshot from backend
- edited copy renders only in approved slots
- technical tables remain unchanged
- unsupported sections are hidden or rejected cleanly
- PDF layout remains stable across supported families
