# NexLed Website Copy Recommendations

Date: 2026-04-21

## Scope

This document proposes copy changes for the live NexLed workspace pages:

- `index.html`
- `configurator.html`
- `code-explorer.html`
- `code-repair.html`
- `dam.html`
- `locales/en.js`

This is a copy audit only. It does not change implementation.

## Brand Rules Used

Source reviewed:

- `https://ggmtecit-prog.github.io/NexLed_Brand_Guidelines/brand.html`

Important brand direction from that page:

- NexLed should sound technical, clear, dependable, practical, and client-centered.
- Copy should read like a technical partner, not like campaign marketing.
- Start with the application or task context when possible.
- State the practical benefit in plain technical language.
- Use exact terms and verifiable claims.
- Avoid inflated language, vague innovation language, and generic filler.

## Brand Site Availability Note

I checked the public navigation targets shown in the Brand Guidelines site:

- `brand.html` was available and used.
- the linked subpages such as `logo.html`, `color_palette.html`, `typography.html`, `visual_elements.html`, `icons.html`, `imagery.html`, `persona.html`, and `assets.html` returned public GitHub Pages `404` responses during this audit.

Because of that, this review uses `brand.html` as the current public source of truth for brand voice.

## Global Copy Problems

These patterns appear across the workspace:

- too much internal language: `runtime`, `workflow`, `candidate`, `active source`, `valid option set`
- repeated words that do not add meaning: `current`, `live`, `right now`, `current runtime`
- long helper text where a short instruction would be clearer
- mixed naming for the same object: `PDF`, `datasheet`, `more details`, `showcase`, `export`
- headings and helper text that restate each other instead of advancing the task

## Global Copy Rules To Apply

- Prefer direct instruction over explanation.
- Use `datasheet` only when the user is dealing with the document output.
- Use `PDF` only for the file action itself.
- Use `reference` when the user is building or checking a Tecit code.
- Keep helper text to one job: what this field does, or what the next action is.
- Remove text that repeats what the user can already see in the UI.

## Implementation Rule

Approve the English copy first, then mirror the same meaning into `locales/pt.js`.

## Recommended Changes

### Home / Landing

File:

- `locales/en.js` -> `index`

Recommended replacements:

- `heroTitle`
  - Current: `Product References, Assets, and PDFs`
  - Proposed: `Build references, check datasheets, and manage assets`

- `heroSubtitle`
  - Current: `Open the configurator, review valid Tecit codes, and manage DAM assets from one NexLed workspace.`
  - Proposed: `Use one NexLed workspace to build Tecit references, check datasheet readiness, and manage DAM assets.`

- `configuratorText`
  - Current: `Build product references, validate manufacturing combinations, and generate product PDFs with guided selections.`
  - Proposed: `Build a Tecit reference, validate the selected combination, and generate the correct datasheet.`

- `codeExplorerText`
  - Current: `Review valid Tecit codes by family, compare configurator validity, and check datasheet readiness before generating a PDF.`
  - Proposed: `Search Tecit codes by family, compare validation status, and confirm datasheet readiness before export.`

- `codeRepairText`
  - Current: `Inspect one Tecit reference, review datasheet blockers, and repair asset links without leaving the workspace.`
  - Proposed: `Inspect one Tecit reference, see what blocks the datasheet, and repair the missing asset link.`

- `damText`
  - Current: `Browse product and brand assets, inspect folder structure, and keep visual files easy to find.`
  - Proposed: `Browse DAM folders, inspect assets, and keep the right files linked to the right products.`

- `syncText`
  - Current: `Planned workflow. Connect shared product data and keep records aligned across NexLed tools.`
  - Proposed: `Coming soon. Keep shared product data aligned across NexLed tools.`

- `bulkText`
  - Current: `Planned workflow. Queue repeated exports and publishing tasks when automation is ready.`
  - Proposed: `Coming soon. Queue repeated exports when batch workflows are available.`

### Configurator

Files:

- `locales/en.js` -> `configurator`
- `configurator.html`

Recommended replacements:

- `familyHint`
  - Current: `Select a family to load the valid option set.`
  - Proposed: `Select a product family to load the valid options for this reference.`

- `sections.dimensionsText`
  - Current: `Set the size and any extra length required for the product build.`
  - Proposed: `Set the product length and any extra length used in the build.`

- `sections.lightText`
  - Current: `Choose the light output, rendering profile, and compatible series code.`
  - Proposed: `Choose color, CRI, and series for the current product.`

- `sections.opticsText`
  - Current: `Define the lens behavior, finishing code, end cap, and option bundle.`
  - Proposed: `Set lens, finish, cap, and option codes.`

- `sections.cableText`
  - Current: `Configure the outgoing cable, connector family, and cable length used in the build.`
  - Proposed: `Set the outgoing cable, connector, and cable length.`

- `sections.mountingText`
  - Current: `Control the end cap, gasket, IP rating, and mechanical fixing profile.`
  - Proposed: `Set the end cap, gasket, IP rating, and fixing.`

- `sections.powerText`
  - Current: `Set the power supply and the connection cable details used in the datasheet export.`
  - Proposed: `Set the power supply and connection cable used in the export.`

- `sections.metadataText`
  - Current: `Choose the application context, branded export, and output language used in the PDF.`
  - Proposed: `Choose the application, logo, and PDF language.`

- `quickActions.title`
  - Current: `Output and Actions`
  - Proposed: `Output`

- `quickActions.liveReference`
  - Current: `Live Reference`
  - Proposed: `Live Tecit Reference`

- `quickActions.liveReferenceHint`
  - Current: `Built automatically from the selected manufacturing options.`
  - Proposed: `Built from the current configuration.`

- `quickActions.outputMode`
  - Current: `PDF Mode`
  - Proposed: `Document Type`

- `quickActions.modeDatasheetHint`
  - Current: `Generate one technical datasheet from the current live reference.`
  - Proposed: `Generate one technical datasheet for the current reference.`

- `quickActions.modeShowcaseHint`
  - Current: `Generate a grouped showcase PDF from baseline filters and expanded valid options.`
  - Proposed: `Generate one grouped showcase PDF from the selected scope and sections.`

- `quickActions.modeCustomHint`
  - Current: `Generate a custom datasheet from one exact product plus approved overrides.`
  - Proposed: `Generate one custom datasheet from the current reference and approved overrides.`

- `runtime.chooseFamilyToBegin`
  - Current: `Choose a family to begin.`
  - Proposed: `Select a product family to start.`

- `runtime.optionsLoaded`
  - Current: `Options loaded. The live reference now updates automatically.`
  - Proposed: `Options loaded. Complete the reference to enable the PDF.`

- `runtime.referenceReadyGeneric`
  - Current: `The current configuration is ready for PDF generation.`
  - Proposed: `The current configuration is ready to export.`

- `runtime.completeConfiguration`
  - Current: `Complete the configuration before generating the datasheet.`
  - Proposed: `Complete the required fields before generating the PDF.`

- `runtime.datasheetReady`
  - Current: `Datasheet ready. The PDF download has started.`
  - Proposed: `PDF ready. The download has started.`

### Code Explorer

Files:

- `locales/en.js` -> `codeExplorer`
- `code-explorer.html`

Recommended replacements:

- `heading`
  - Current: `Explore Valid Tecit Codes`
  - Proposed: `Explore Tecit Codes`

- `subtitle`
  - Current: `Review full 17-character Tecit codes and compare configurator validity with datasheet readiness.`
  - Proposed: `Search Tecit codes, compare validation status, and check datasheet readiness.`

- `searchInputLabel`
  - Current: `Search one or many codes / descriptions`
  - Proposed: `Search codes or descriptions`

- `searchInputPlaceholder`
  - Current: `One item per line: code or description`
  - Proposed: `Enter one code or description per line`

- `searchInputHint`
  - Current: `Paste one or many items. Use one line per code or description. Commas, semicolons, and tabs also work.`
  - Proposed: `Use one line per search. Commas, semicolons, and tabs also work.`

- `searchCodeLabel`
  - Current: `Search By Code`
  - Proposed: `Search by Tecit code`

- `searchDescriptionLabel`
  - Current: `Search By Description`
  - Proposed: `Search by description`

- `includeInvalidHint`
  - Current: `Off by default. Excludes invalid identities so results stay focused on codes the system can use.`
  - Proposed: `Shows identities that fail Luminos validation.`

- `drillDownBody`
  - Current: `Narrow the family by size, color, CRI, or series before exploring invalid combinations.`
  - Proposed: `Narrow the family by size, color, CRI, or series before you include invalid identities.`

- `emptyStateTitle`
  - Current: `No codes loaded yet`
  - Proposed: `No codes loaded`

- `emptyStateBody`
  - Current: `Use the panel to the right to search.`
  - Proposed: `Use the panel on the right to load codes.`

- `detailsPdfSpecsButton`
  - Current: `Show more details`
  - Proposed: `Load PDF details`

- `detailsBasicButton`
  - Current: `Show basic details`
  - Proposed: `Show basic details`
  - Note: keep as is

- `runtime.loadingBatchSearch`
  - Current: `Searching {count} codes or descriptions...`
  - Proposed: `Searching {count} items...`

- `runtime.noRows`
  - Current: `No rows match current filters.`
  - Proposed: `No codes match the current search.`

- `runtime.loadedRows`
  - Current: `Code Explorer results loaded.`
  - Proposed: `Results loaded.`

- `runtime.loadedBatchRows`
  - Current: `Batch search results loaded.`
  - Proposed: `Search results loaded.`

- `runtime.searchDescriptionNeedsFamily`
  - Current: `Choose one family in See by filters before searching by description.`
  - Proposed: `Select one family in See by filters before you search by description.`

- `coverageDetailTitle`
  - Current: `Full codes in this identity`
  - Proposed: `Codes in this identity`

- `failure.none`
  - Current: `No blocking reason.`
  - Proposed: `No blocker found.`

### Code Repair

Files:

- `locales/en.js` -> `codeRepair`
- `code-repair.html`

Recommended replacements:

- `runtimeAwaitingReference`
  - Current: `Enter a full Tecit reference to inspect blockers and sources.`
  - Proposed: `Enter a full Tecit reference to load the repair context.`

- `runtimeLoadedReady`
  - Current: `Repair context loaded. Datasheet is ready.`
  - Proposed: `Repair context loaded. Datasheet is ready.`
  - Note: keep as is

- `runtimeLoadedBlocked`
  - Current: `Repair context loaded. Top blocker: {blocker}.`
  - Proposed: `Repair context loaded. Current blocker: {blocker}.`

- `blockTitle`
  - Current: `Active blockers`
  - Proposed: `Current blockers`

- `blockNoneTitle`
  - Current: `No blockers`
  - Proposed: `No blockers found`

- `blockNoneBody`
  - Current: `This reference is configurator-valid and datasheet-ready in the current runtime.`
  - Proposed: `This reference is valid and ready for the datasheet in the current runtime.`

- `sourcesEmpty`
  - Current: `Load a reference to inspect the current active sources, local checks, and DAM candidates.`
  - Proposed: `Load a reference to inspect current sources, local checks, and DAM candidates.`

- `openConfigurator`
  - Current: `Open in Configurator`
  - Proposed: `Open in configurator`

- `preview`
  - Current: `Preview`
  - Proposed: `Preview asset`

- `copyPath`
  - Current: `Copy path`
  - Proposed: `Copy source path`

### DAM

Files:

- `locales/en.js` -> `dam`
- `dam.html`

Recommended replacements:

- `searchPlaceholder`
  - Current: `Search assets...`
  - Proposed: `Search folders and assets...`

- `topFolderControl`
  - Current: `Select main folder`
  - Proposed: `Library section`

- `emptyFolder`
  - Current: `This folder is empty`
  - Proposed: `No assets in this folder`

- `noAssetSelected`
  - Current: `Select an asset to inspect details.`
  - Proposed: `Select an asset to view details.`

- `copyAssetUrl`
  - Current: `Copy Asset URL`
  - Proposed: `Copy asset URL`

- `hideAssetLinks`
  - Current: `Hide linking`
  - Proposed: `Hide linking fields`

- `showAssetLinks`
  - Current: `Show linking`
  - Proposed: `Show linking fields`

- `noLinks`
  - Current: `No links yet.`
  - Proposed: `No asset links yet.`

- `folderPolicyIntro`
  - Current: `The DAM structure is ready in cloud storage, but current PDF assets still stay local until the PDF workflow moves.`
  - Proposed: `The DAM folder structure is ready, but the PDF workflow still reads local assets.`

- `folderPolicyKeepBody`
  - Current: `Keep nexled/datasheet and nexled/media as live DAM roots, with uploads routed into seeded child folders.`
  - Proposed: `Keep nexled/datasheet and nexled/media as the live DAM roots, with uploads routed into the existing child folders.`

- `folderPolicyLaterBody`
  - Current: `Do not switch PDF workflow to DAM direct reads yet. Local datasheet assets still remain active fallback.`
  - Proposed: `Do not switch the PDF workflow to DAM reads yet. Local datasheet assets still act as the fallback.`

- `folderPolicyLocalBody`
  - Current: `Do not move or delete the current local PDF assets yet. They still support PDF generation.`
  - Proposed: `Do not move or delete the local PDF assets yet. They still support PDF generation.`

### Shared / Shell

Files:

- `locales/en.js` -> `shared`

Recommended replacements:

- `shared.badge.workspaceReady`
  - Current: `NexLed Workspace`
  - Proposed: `Workspace`

- `shared.actions.copyReference`
  - Current: `Copy reference`
  - Proposed: `Copy reference`
  - Note: keep as is

- `shared.actions.resetConfigurator`
  - Current: `Reset configurator`
  - Proposed: `Clear fields`

## Copy To Remove Entirely

These strings are better removed than rewritten if they are still visible anywhere in the UI:

- repeated helper text that explains the obvious state of a visible component
- `Page Status`
- `Repair Workflow`
- subtitles that restate the page title without adding a task
- long helper text inside toggles, empty states, or small side panels

## Suggested Rollout Order

1. Update `locales/en.js` with the approved English changes.
2. Mirror approved meaning into `locales/pt.js`.
3. Remove redundant helper text that no longer adds value.
4. Recheck every empty state, toast, modal heading, and CTA after the copy pass.

## Final Recommendation

The copy should move one clear step toward:

- more technical
- more direct
- less internal
- less repetitive
- more action-led

The current product already has the right structure. The main gap is tone discipline, not content volume.
