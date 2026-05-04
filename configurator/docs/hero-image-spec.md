# Code Repair — Hero Product Image Specification

## Goal

Add the product packshot image to the hero summary section of the Code Repair page. The image should appear to the right of the hero info (reference, family/description, badges).

## Current State

The hero is rendered by `buildCodeRepairSummaryHeroMarkup()` in `code-repair.js`. It outputs a centered, text-only layout:

```
┌─────────────────────────────────────┐
│         29012022191010000           │
│       29 - Downlight redondo        │
│    [Configurator Valid] [Datasheet] │
└─────────────────────────────────────┘
```

The hero lives inside `#repair-summary-grid` which is a `grid xl:grid-cols-2`. The hero is the left column, the blocker status card is the right column.

## Target State

Image on the right of the text info:

```
┌──────────────────────────────────────────────┐
│  29012022191010000                 ┌──────┐  │
│  29 - Downlight redondo            │ IMG  │  │
│  [Configurator Valid] [Datasheet]  └──────┘  │
└──────────────────────────────────────────────┘
```

## Image Source

The image URL comes from the API payload: `payload.source_map.header.active`.

Use the existing helper to extract it:
```js
getCodeRepairPreviewUrl(payload?.source_map?.header?.active)
```

This is the same packshot image used by:
- The PDF header section (`buildHeader()` in `pdf-layout.php`)
- The Identity overview group (added in the overview-groups feature)
- The action card previews

## Implementation Steps

### 1. Add `imageUrl` parameter to `buildCodeRepairSummaryHeroMarkup()`

```js
function buildCodeRepairSummaryHeroMarkup({
    reference = "...",
    family = "...",
    configuratorMarkup = "",
    datasheetMarkup = "",
    gridSpanClass = "xl:col-span-1",
    imageUrl = "",  // <-- add this
} = {}) {
```

### 2. Branch the markup on `imageUrl`

When `imageUrl` is empty (no data loaded), keep the current centered text layout unchanged.

When `imageUrl` is present, switch to a `flex-row` layout with the text block on the left and the image on the right.

### 3. Image markup

Use the design-system media frame classes (same as action card previews):

```html
<div class="text-style-media-frame shrink-0 w-96 lg:w-120 max-w-120 aspect-square flex items-center justify-center">
    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(family)}" class="text-style-media-image h-full aspect-square">
</div>
```

Sizing guidance:
- `w-96 lg:w-120 max-w-120` was tested and looked good at the compact size
- The image must be `shrink-0` so it doesn't collapse
- `aspect-square` keeps it square
- `text-style-media-frame` + `text-style-media-image` are the design-system classes

### 4. Text block markup (when image present)

```html
<div class="flex flex-col gap-12 min-w-0 items-start text-left flex-1">
    <p class="text-h1 text-black break-all">REFERENCE</p>
    <p class="text-title-lg text-black break-words">FAMILY</p>
    <div class="flex flex-wrap items-center gap-10">
        <div>BADGE1</div>
        <div>BADGE2</div>
    </div>
</div>
```

Key differences from the no-image layout:
- `items-start text-left` instead of `items-center text-center`
- `flex-1` to take remaining space
- `gap-12` instead of `gap-16` between rows (tighter since image anchors the layout)

### 5. Article wrapper (when image present)

```html
<article class="${gridSpanClass} flex flex-row gap-24 min-w-0 items-center justify-self-center self-center w-full max-w-3xl">
```

Key differences from the no-image layout:
- `flex-row` instead of `flex-col`
- `gap-24` between text and image

### 6. Pass the image URL from `renderCodeRepairSummary()`

In `renderCodeRepairSummary()`, extract the URL and pass it:

```js
codeRepairElements.summaryGrid.innerHTML = buildCodeRepairSummaryHeroMarkup({
    reference: referenceValue,
    family: familyValue,
    configuratorMarkup,
    datasheetMarkup,
    gridSpanClass: "xl:col-span-1",
    imageUrl: getCodeRepairPreviewUrl(payload?.source_map?.header?.active),
}) + buildCodeRepairBlockerStatusHeroMarkup(payload);
```

## Responsive Behavior

- On mobile: the `flex-row` layout may be too tight. Consider `flex-col sm:flex-row` so the image stacks above on very small screens.
- The `max-w-120` cap prevents the image from dominating the hero on wide screens.

## Files to Change

| File | Change |
|---|---|
| `code-repair.js` | `buildCodeRepairSummaryHeroMarkup()` — add `imageUrl` param, branch markup |
| `code-repair.js` | `renderCodeRepairSummary()` — extract and pass `imageUrl` |

No HTML, locale, or API changes needed.
