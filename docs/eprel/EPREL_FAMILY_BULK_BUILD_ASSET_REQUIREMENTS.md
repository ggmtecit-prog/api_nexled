# EPREL Family Bulk Build - Asset Requirements

Status:
- Planning/reference document for `C:\xampp\htdocs\epreltools_newd`
- Created on 2026-04-21
- No runtime changes in this document

Goal:
- explain the new asset-related changes required to make EPREL family bulk build work end to end
- clarify why staged model data is not enough by itself
- define the recommended Central API contract for PDFs and spectral images

## Short Version

Bulk family build is now split into two separate needs:

1. product data
2. product files

Product data is already moving:
- ready rows come from Central API `family-ready-products`
- EPREL can stage mapped page data page by page

Product files are still the critical missing piece:
- technical PDF for each ready reference
- spectral image for each ready reference

Without those files, EPREL cannot build the final ZIP correctly.

## Why This Matters

Current EPREL ZIP truth is:
- one generated XML file
- one technical PDF per product
- one spectral image per product

Code truth:
- `app/Service/XmlGenerator.php`
- `app/Service/ZipBuilder.php`

Current ZIP builder expects:
- `pdf_name`
- `img_name`
- and real files physically present in local attachments folders

So even if bulk build can stage product models, final ZIP is still incomplete unless asset files are also resolved.

## Manual Flow vs Bulk Flow

### Manual flow

Manual flow works because the user supplies or selects files:
- PDF chosen/uploaded manually
- spectral image chosen/uploaded manually

Then:
- XML references those filenames
- ZIP includes those local files

### Bulk flow

Bulk flow is different:
- no user is selecting files one by one
- backend must determine files automatically for each ready reference

So for every ready product reference, EPREL must know:
- which technical PDF belongs to the reference
- which spectral image belongs to the reference
- how to fetch or download those files
- what local filenames to store in `pdf_name` and `img_name`

## Exact Asset Meaning

### PDF

The PDF is the generated technical datasheet for the ready code/reference.

This is the file that belongs in:
- `Attachments/TechnicalDoc/<pdf_name>`

### IMG

The IMG is the spectral image shown in the:
- `Color Temperature - Color Spectrum`

section of the PDF.

This is the file that belongs in:
- `Attachments/spectral_graph/<img_name>`

## What Is Not Enough

These are not enough for bulk EPREL build:
- `datasheet_ready = true`
- `assets.header_image = true/false`
- `assets.color_graph = true/false`
- generic booleans only

Why:
- EPREL needs the actual file, not only proof that a file exists

So Central API must provide one of:
- downloadable file URL
- asset identifier that EPREL can exchange for file download
- direct file response endpoint

## Best Architecture

Central API should stay source of truth for:
- which references are ready
- which files belong to each reference

EPREL should not:
- guess filenames
- know internal DAM logic
- generate PDFs itself
- derive spectral image paths by convention unless that convention is officially guaranteed by Central API

So the right direction is:
- Central API gives EPREL the correct file references
- EPREL downloads files and stores them locally
- EPREL builds XML + ZIP from local staged data and local staged files

## Recommended Central API Change

Add asset delivery support to Central API.

There are 2 good ways to do it.

### Option A - enrich `family-ready-products`

Each ready row already returned by Central API should include asset refs or URLs.

Example:

```json
{
  "reference": "01018002111010100",
  "identity": "0101800211",
  "description": "T8 LED 23 cm",
  "product_type": "tubular",
  "product_id": "T8LED/23/3s",
  "led_id": "3528XN",
  "configurator_valid": true,
  "datasheet_ready": true,
  "pdf_file_name": "01018002111010100.pdf",
  "pdf_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-datasheet&reference=01018002111010100",
  "spectral_file_name": "01018002111010100.png",
  "spectral_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-spectral&reference=01018002111010100"
}
```

#### Pros
- simplest EPREL-side logic
- one source payload contains everything needed to stage a page
- no extra lookup per product for asset metadata

#### Cons
- response rows become heavier
- if URLs are expensive to compute, API may do more work per page

### Option B - add bulk details/assets endpoint

Keep `family-ready-products` light, then add a second bulk endpoint.

Suggested shape:

- `POST /api/?endpoint=family-ready-assets`

Request body:

```json
{
  "references": [
    "01018002111010100",
    "01018002111010101"
  ]
}
```

Response:

```json
{
  "rows": [
    {
      "reference": "01018002111010100",
      "pdf_file_name": "01018002111010100.pdf",
      "pdf_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-datasheet&reference=01018002111010100",
      "spectral_file_name": "01018002111010100.png",
      "spectral_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-spectral&reference=01018002111010100"
    },
    {
      "reference": "01018002111010101",
      "pdf_file_name": "01018002111010101.pdf",
      "pdf_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-datasheet&reference=01018002111010101",
      "spectral_file_name": "01018002111010101.png",
      "spectral_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-spectral&reference=01018002111010101"
    }
  ]
}
```

#### Pros
- better for performance and separation
- easy to request assets in page-sized batches
- avoids inflating every ready-products row if not needed

#### Cons
- one more API call per page
- slightly more EPREL-side wiring

## What Not To Do

Avoid this design:
- one API call for product page
- one API call per product for PDF metadata
- one API call per product for spectral image metadata
- one API call per product for PDF download
- one API call per product for image download

Why:
- too many requests for families like `01`
- performance and timeout risk grow fast

So recommended pattern is:
- page-based asset lookup
- or asset URLs already included in ready-products rows

## Suggested File Delivery Contract

For each reference, Central API should provide:
- `pdf_file_name`
- `pdf_url`
- `spectral_file_name`
- `spectral_url`

Or equivalent download endpoints.

### Important

Central API should provide the actual downloadable file, not just:
- booleans
- loose metadata
- a human description

EPREL needs:
- bytes of the PDF
- bytes of the spectral image

## EPREL-Side Bulk Build Flow With Assets

Recommended future flow per page:

1. fetch ready rows page from `family-ready-products`
2. fetch asset refs for that page if not already included
3. download each PDF and spectral image for that page
4. save files locally into job staging folders
5. set `pdf_name` and `img_name` on each staged model
6. stage page JSON with complete model data
7. continue to next page
8. when all pages staged, assemble final XML
9. build final ZIP

## Recommended Local Job Staging Structure

Per job:

```text
output/family-build-jobs/{jobId}/
  job.json
  build/
    pages/
      page-00001.json
    attachments/
      TechnicalDoc/
        01018002111010100.pdf
      spectral_graph/
        01018002111010100.png
  reports/
    failed-products.json
  final/
    family-build.zip
```

Reason:
- keeps bulk-build files isolated from manual uploads
- avoids mixing family job assets into shared global attachment folders too early
- makes cleanup and debugging easier

## Question To Lock Before Phase 3

Before implementing final ZIP creation, the team must lock this:

For each ready reference, what exact Central API contract gives EPREL:
- technical PDF file
- spectral image file

That contract must specify:
- endpoint or field name
- auth rules
- content type
- filename
- missing-file behavior
- error shape

## Best Recommendation

Best recommendation for long-term architecture:

1. keep `family-ready-products` as ready-reference source
2. add bulk asset support in Central API
3. stage files page by page in EPREL
4. only then finalize XML + ZIP

If the API team wants the cleanest path:
- enrich `family-ready-products` with file refs/URLs

If the API team wants lighter response rows:
- add `family-ready-assets` bulk endpoint

Both are valid.

## Acceptance Criteria

This asset part is solved when:
- EPREL can resolve a real technical PDF for each ready reference
- EPREL can resolve a real spectral image for each ready reference
- EPREL can save those files locally during batch processing
- staged page models contain valid `pdf_name` and `img_name`
- final ZIP can be built without missing attachment failures for ready references

## Final Conclusion

The main data-flow problem is mostly solved.

The remaining big requirement is now file delivery:
- technical PDF
- spectral image

That is why a new Central API feature is justified.

Without it:
- EPREL can stage data
- but final family ZIP remains incomplete or fragile

With it:
- EPREL bulk build becomes clean, deterministic, and realistic.
