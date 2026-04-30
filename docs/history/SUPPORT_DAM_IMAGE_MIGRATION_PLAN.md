# Support Page DAM Image Migration Plan

## Goal

Move the **support-page content images** from local files to the DAM for the NexLed Support Page project at:

- `C:\xampp\htdocs\Website_Suporte`

Keep small local brand assets in the project, including:

- `favicon.svg`
- local logo usage that already points to `favicon.svg`

This plan is only for **images**, not PDFs, not page structure, not UI redesign.

## Scope

### Move to DAM

- product thumbnails used in flyouts, repair cards, and downloads cards
- parts/tool images in repair guides
- step-by-step repair photos

### Keep local

- `favicon.svg`
- header/footer brand icon usage that already depends on `favicon.svg`
- external flags from `flagcdn.com`

## Current Reality

The support site is still built around local image paths.

### Hardcoded local images

- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:134)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:180)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:226)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:272)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:324)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:369)
- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:414)

### External URLs currently blocked

- [utils.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/utils.js:72)

Current code:

```js
sanitizeImagePath(src) {
    if (!src || src.startsWith('http') || src.startsWith('//')) return '';
    return src;
}
```

This means DAM URLs will be rejected today.

### Current image call sites

- flyout product thumbnails:
  - [support-site.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/support-site.js:316)
- repair page cards:
  - [repair.html](/C:/xampp/htdocs/Website_Suporte/repair.html:406)
- guide step images:
  - [steps.html](/C:/xampp/htdocs/Website_Suporte/steps.html:405)
  - [steps.html](/C:/xampp/htdocs/Website_Suporte/steps.html:518)

### Data files still point to local paths

- [flyouts.json](/C:/xampp/htdocs/Website_Suporte/data/flyouts.json)
- [repairs.json](/C:/xampp/htdocs/Website_Suporte/data/repairs.json)
- [DQ_1.json](/C:/xampp/htdocs/Website_Suporte/data/DQ/DQ_1.json)
- [B24V_60_1.json](/C:/xampp/htdocs/Website_Suporte/data/B24V/B24V_60/B24V_60_1.json)

Important detail:

- root-level product thumbnails use keys like `DL_Q.webp`
- guide JSON uses mixed relative paths like:
  - `../../img/photos_step/...`
  - `../img/photos_step/...`

## Recommended Strategy

### Use one manifest, not 19 JSON rewrites

Best v1 path:

1. upload support images to DAM
2. create one manifest file in support project
3. resolve old local paths into DAM URLs at runtime
4. only directly replace the static image refs in `downloads.html`

This is better than rewriting every JSON file now.

### Why this is best

- smaller diff
- safer rollback
- JSON content can stay as-is
- DAM cutover can happen in one place
- later you can still rewrite JSON if you want

## DAM Folder Shape

Use the DAM support buckets already present in roadmap:

- `nexled/media/support/page-assets`
- `nexled/media/support/repair-guides`

Suggested split:

- `nexled/media/support/page-assets/products/`
  - `DL_Q.webp`
  - `DL_R.webp`
  - `led_bar.webp`
- `nexled/media/support/repair-guides/parts/`
  - `cabo-asqc2-branco.webp`
  - `screwdriver.webp`
  - `danger.webp`
- `nexled/media/support/repair-guides/steps/global/`
- `nexled/media/support/repair-guides/steps/DL_Q/`
- `nexled/media/support/repair-guides/steps/DL_R/`
- `nexled/media/support/repair-guides/steps/B24V/`

## URL Strategy

Assumption for this plan:

- DAM images expose stable public URLs

If DAM images require auth, stop. Public support page should not ship secret tokens.

## New File To Add In Support Project

Add:

- `C:\xampp\htdocs\Website_Suporte\data\dam-images.json`

This file should map **normalized local paths** to DAM public URLs.

Example shape:

```json
{
  "DL_Q.webp": "https://dam.example/.../support/page-assets/products/DL_Q.webp",
  "DL_R.webp": "https://dam.example/.../support/page-assets/products/DL_R.webp",
  "led_bar.webp": "https://dam.example/.../support/page-assets/products/led_bar.webp",
  "img/parts/cabo-asqc2-branco.webp": "https://dam.example/.../support/repair-guides/parts/cabo-asqc2-branco.webp",
  "img/screwdriver.webp": "https://dam.example/.../support/repair-guides/parts/screwdriver.webp",
  "img/photos_step/global/G1_Step1.webp": "https://dam.example/.../support/repair-guides/steps/global/G1_Step1.webp",
  "img/photos_step/DL_Q/1/G1_Step2.webp": "https://dam.example/.../support/repair-guides/steps/DL_Q/1/G1_Step2.webp",
  "img/photos_step/B24V/B24V_60/G1_Step2.png": "https://dam.example/.../support/repair-guides/steps/B24V/B24V_60/G1_Step2.png"
}
```

## Normalization Rule

Resolver should normalize all legacy image paths before lookup:

- keep `DL_Q.webp` as `DL_Q.webp`
- convert `../img/...` to `img/...`
- convert `../../img/...` to `img/...`
- reject unknown external URLs unless they match trusted DAM host

## File-By-File Plan

### 1. `src/scripts/utils.js`

File:

- [utils.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/utils.js:72)

Change:

- stop using local-only sanitizer
- add one image resolver that:
  - loads `data/dam-images.json`
  - normalizes legacy local paths
  - returns DAM URL when mapped
  - falls back to local path when no mapping exists
  - allows trusted DAM host URLs

Target result:

- central image logic in one place
- no DAM URL logic spread across pages

### 2. `src/scripts/support-site.js`

Files:

- [support-site.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/support-site.js:177)
- [support-site.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/support-site.js:316)
- [support-site.js](/C:/xampp/htdocs/Website_Suporte/src/scripts/support-site.js:630)

Change:

- keep `flyouts.json` and `repairs.json` as they are
- change flyout image rendering to use new resolver instead of raw `sanitizeImagePath()`

Impact:

- flyout thumbnails on home, repair, downloads pages can move to DAM without editing the JSON

### 3. `repair.html`

File:

- [repair.html](/C:/xampp/htdocs/Website_Suporte/repair.html:406)

Change:

- change repair card image rendering to use new resolver instead of raw `sanitizeImagePath()`

Impact:

- `repairs.json` card thumbnails can stay untouched

### 4. `steps.html`

Files:

- [steps.html](/C:/xampp/htdocs/Website_Suporte/steps.html:405)
- [steps.html](/C:/xampp/htdocs/Website_Suporte/steps.html:518)

Change:

- replace current `resolveGuideImagePath()` with DAM-aware resolver
- keep fallback to `favicon.svg` only if image missing

Impact:

- all guide step photos can move to DAM
- no need to rewrite guide JSON on day one

Note:

- current visible rendering uses `step.image`
- I did not find active rendering for `part.image` in current page code
- still include part images in manifest now, because future UI may use them

### 5. `downloads.html`

File:

- [downloads.html](/C:/xampp/htdocs/Website_Suporte/downloads.html:134)

Change:

- directly replace the 7 product thumbnail `src` values with DAM URLs
- or convert them to `data-image-key` and resolve them through the same helper

Recommendation:

- for simplicity, replace these 7 directly
- keep `favicon.svg` local

### 6. `data/flyouts.json`

File:

- [flyouts.json](/C:/xampp/htdocs/Website_Suporte/data/flyouts.json)

v1 plan:

- no change required if manifest maps:
  - `DL_Q.webp`
  - `DL_R.webp`
  - `led_bar.webp`

### 7. `data/repairs.json`

File:

- [repairs.json](/C:/xampp/htdocs/Website_Suporte/data/repairs.json)

v1 plan:

- no change required if manifest maps:
  - `DL_Q.webp`
  - `DL_R.webp`
  - `led_bar.webp`

### 8. Guide JSON files under `data/`

Examples:

- [DQ_1.json](/C:/xampp/htdocs/Website_Suporte/data/DQ/DQ_1.json)
- [B24V_60_1.json](/C:/xampp/htdocs/Website_Suporte/data/B24V/B24V_60/B24V_60_1.json)

Current count:

- `19` guide JSON files total

v1 plan:

- no mass rewrite
- keep legacy local image paths
- let resolver normalize and map them to DAM URLs

This is the main reason the migration stays manageable.

## Minimal Implementation Order

### Phase 1

- upload DAM assets
- create `dam-images.json`
- update resolver in `utils.js`

### Phase 2

- wire resolver into:
  - `support-site.js`
  - `repair.html`
  - `steps.html`

### Phase 3

- replace `downloads.html` card thumbnails

### Phase 4

- visual QA on:
  - `index.html`
  - `repair.html`
  - `downloads.html`
  - one `DQ` guide
  - one `DR` guide
  - one `B24V` guide

## Verification Checklist

- flyout thumbnails load from DAM
- repair cards load from DAM
- downloads page thumbnails load from DAM
- step photos load from DAM
- missing DAM mapping falls back safely
- `favicon.svg` remains local
- no browser auth needed for image fetches
- no broken relative paths remain in visible support flows

## Complexity

### Easy parts

- DAM upload itself
- direct `downloads.html` thumbnail swap
- keeping favicon/local brand files unchanged

### Medium parts

- replacing local-only image sanitizer
- normalizing mixed `../img/` and `../../img/` guide paths
- building complete manifest once

### Hard parts

- only hard if DAM URLs are not public

## Recommendation

Implement this as a **manifest-based cutover**, not a JSON rewrite campaign.

That gives:

- smallest risk
- fastest rollout
- easy rollback
- one clear boundary between support site and DAM

## Expected Touch List In Support Project

Likely files to edit:

- `C:\xampp\htdocs\Website_Suporte\src\scripts\utils.js`
- `C:\xampp\htdocs\Website_Suporte\src\scripts\support-site.js`
- `C:\xampp\htdocs\Website_Suporte\repair.html`
- `C:\xampp\htdocs\Website_Suporte\steps.html`
- `C:\xampp\htdocs\Website_Suporte\downloads.html`
- `C:\xampp\htdocs\Website_Suporte\data\dam-images.json` new

Likely files **not** needed for v1:

- `C:\xampp\htdocs\Website_Suporte\data\flyouts.json`
- `C:\xampp\htdocs\Website_Suporte\data\repairs.json`
- most guide JSON files

