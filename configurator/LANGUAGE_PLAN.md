# NexLed Language Rollout Plan

## Goal

Add UI translations without breaking the current configurator flow or the datasheet export language selector.

## Recommended rollout

1. Keep `app-shell.js` as the single source of truth for the app language preference and store it in local storage.
2. Extract page copy into per-language dictionaries such as `locales/en.js`, `locales/pt.js`, and `locales/es.js`.
3. Mark translatable UI nodes with stable keys, for example `data-i18n="configurator.referenceSetup.title"`.
4. Add a small translation bootstrap that reads the current language, swaps copy on page load, and updates the page when the sidebar selector changes.
5. Keep export language separate from UI language only if the product team needs that distinction. Otherwise sync the datasheet language field to the app language by default.

## Implementation order

1. Home, configurator shell, and DAM navigation labels.
2. Configurator headings, hints, button labels, and status messages.
3. DAM card labels, quick actions, and empty states.
4. API error messages and export feedback strings.

## Guardrails

- Do not hardcode translated strings inside interaction logic once dictionaries exist.
- Keep API payload values stable even when the visible labels change.
- Translate labels and help text first; leave product codes and references untouched.
- Add a fallback to English when a key is missing.

## Validation

- Switching language updates the same shared labels on `index.html`, `configurator.html`, and `dam.html`.
- Datasheet generation still sends valid language codes.
- No untranslated placeholders remain in the active language for the edited pages.
