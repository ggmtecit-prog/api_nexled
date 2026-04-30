# Advanced Custom Copy Editable Matrix

Status: draft

Purpose: define exactly which section copy blocks may become editable, what they map to, and what must stay locked.

Related docs:
- [ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md](./ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md)
- [ADVANCED_CUSTOM_COPY_IMPLEMENTATION_PLAN.md](./ADVANCED_CUSTOM_COPY_IMPLEMENTATION_PLAN.md)
- [CUSTOM_DATASHEET_OVERRIDE_MATRIX.md](./CUSTOM_DATASHEET_OVERRIDE_MATRIX.md)

## 1. Reading this document

Columns:
- `Section`: visual datasheet block
- `Field`: canonical field name inside `copy_overrides`
- `Meaning`: what user edits
- `V1`: whether first version should ship it
- `Validation`: main rule
- `Fallback`: official behavior if absent
- `Notes`: implementation constraints

## 2. Recommended V1 Editable Copy

| Section | Field | Meaning | V1 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `header` | `intro` | opening marketing copy under product identity | Yes | plain text, multiline, max 1200 | keep official | safest high-value slot |
| `characteristics` | `intro` | lead-in text above characteristics block | Yes | plain text, multiline, max 800 | keep official | only if slot exists in current family/layout |
| `option_codes` | `intro` | explanation of option-code area | Yes | plain text, multiline, max 800 | keep official | useful for customer-specific wording |
| `footer` | `note` | short internal/footer note | Yes | plain text, single/multiline short, max 160 | keep official custom footer note | may overlap with old `footer_note` behavior; define precedence |

## 3. Recommended Wave 2 Editable Copy

| Section | Field | Meaning | V2 | Validation | Fallback | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `luminotechnical` | `intro` | text above luminotechnical table | Later | plain text, max 800 | keep official | must not affect table content |
| `drawing` | `intro` | text above drawing block | Later | plain text, max 800 | keep official | useful but lower priority |
| `finish` | `intro` | text above finish/visual block | Later | plain text, max 800 | keep official | family support varies |

## 4. Not Recommended for V1

These are possible later, but too risky or too ambiguous now.

| Section | Field | Reason to delay |
| --- | --- | --- |
| `header` | `title` | overlaps current `document_title` and filename rules |
| `characteristics` | `heading` | heading slots may be shared or translated centrally |
| `luminotechnical` | `heading` | easy to damage technical section consistency |
| `drawing` | `heading` | low value compared to intro-only editing |
| `finish` | `heading` | depends on family-specific render flow |
| `option_codes` | `heading` | code identity should stay consistent |

## 5. Forbidden Editable Targets

These must not become text fields.

| Target | Reason |
| --- | --- |
| table column labels | technical consistency |
| numeric luminotechnical values | measured truth |
| dimensions values | technical truth |
| energy class | compliance-sensitive |
| product reference code | product identity |
| legal footer base | legal/compliance risk |
| family/model identity labels from technical runtime | product truth |

## 6. Payload Mapping

Recommended payload shape:

```json
{
  "custom": {
    "copy_mode": "advanced",
    "copy_overrides": {
      "header": {
        "intro": "..."
      },
      "characteristics": {
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
}
```

## 7. Preview Snapshot Mapping

Recommended preview snapshot shape:

```json
{
  "editable_copy": {
    "header": {
      "intro": "Official text..."
    },
    "characteristics": {
      "intro": "Official text..."
    },
    "option_codes": {
      "intro": "Official text..."
    },
    "footer": {
      "note": ""
    }
  }
}
```

Only return sections that actually exist for current product/family context.

## 8. Validation Policy

For all advanced copy fields:
- strings only
- trim whitespace
- normalize line breaks
- reject HTML
- reject unknown nested keys
- empty string means no override

## 9. Precedence Rules

Need one clear precedence model.

Recommended:
1. `copy_overrides` value if present
2. older simple text override value if relevant
3. official default snapshot value

Example:
- `copy_overrides.footer.note` overrides old `text_overrides.footer_note`
- if absent, old `footer_note` still works

## 10. Family Support Rule

Advanced editable sections should be:
- globally designed once
- conditionally available by current runtime section presence

So frontend must not hardcode same visible editors for every family.

## 11. Acceptance Rule

Section is safe to ship only when:
- preview can provide default snapshot for it
- render merge has one explicit target slot for it
- family QA shows no overflow/regression
