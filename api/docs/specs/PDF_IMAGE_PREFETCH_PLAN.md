# PDF Image Pre-Fetch Plan

Goal: cut PDF generation time by ~50-70% on datasheets that pull DAM images.
Same disk cache, same renderer — just download all remote URLs in parallel before the layout starts.

## Why

Today, `renderDatasheetPdfBinaryFromContext()` walks the layout HTML and calls `getPdfSafeAssetPath()` for every image. For Cloudinary-backed assets, that triggers `cacheRemoteAssetForPdf()` — a sequential `curl_exec`. 10 images = 10 × ~200ms blocked.

`cacheRemoteAssetForPdf()` already has a disk cache (sha1 of URL). It's fast on cache hit, slow on miss. We exploit this: pre-warm the disk cache in parallel before render.

## Scope

**One insertion point: top of `renderDatasheetPdfBinaryFromContext()`** in `api/lib/pdf-engine.php`.

That function is also called by `custom-datasheet/render.php` — both flows benefit from one change.

Showcase (`api/lib/showcase/render.php`) uses a different path. Out of scope for first pass.

## Design rules

- **Transparent.** Render output is byte-identical. Same PDF, same disk cache files.
- **Killable.** `PDF_PREFETCH_ENABLED=0` env var bypasses prefetch, falls back to current sequential behavior.
- **Self-healing.** Per-URL fetch failure is silent — sequential `cacheRemoteAssetForPdf()` still runs as fallback at render time.
- **No new infra.** Uses PHP-native `curl_multi_*` (already required by other parts of the code).
- **Bounded parallelism.** Cap at 8 concurrent connections to avoid thundering Cloudinary.
- **Skip already-cached.** Don't re-download URLs already on disk.
- **Caveman.** 2 functions: collector + parallel fetcher.

## Files to add

**`api/lib/images.php`** — append two functions:

1. `collectRemoteAssetUrls($value): array` — recursive walker. Takes any value (array/object/scalar). Returns all unique strings matching `https?://` that look like image URLs.
2. `prefetchPdfRemoteAssets(array $urls): void` — caveman parallel fetcher. Skips disk-cached URLs. Uses `curl_multi_*` with concurrency=8. Writes to same disk cache layout as `cacheRemoteAssetForPdf()`.

Plus a small constant: `PDF_PREFETCH_ENABLED` env-checked toggle.

## Files to edit

**`api/lib/pdf-engine.php`** — inside `renderDatasheetPdfBinaryFromContext()`, before `$html = ...`:

```php
prefetchPdfRemoteAssets(collectRemoteAssetUrls($context["data"] ?? []));
```

That's the only logic change. ~1 line.

## URL collection

Walk `$context["data"]` recursively. For each scalar that:
- starts with `http://` or `https://`
- ends with `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.webp` (with optional `?...` query)
- OR contains `cloudinary.com`

…add to URL list. Deduplicate. Apply `getCloudinaryRasterizedUrl()` transformation (SVG→PNG) so the cache key matches what `cacheRemoteAssetForPdf()` will look up.

## Parallel fetch behavior

- Open `curl_multi_init()`
- For each URL not yet on disk:
  - `curl_init` with same options as existing single-URL path
  - Add to multi handle, max 8 active at once
- Loop `curl_multi_exec` + `curl_multi_select` until done
- For each completed handle:
  - On success: write content to `$cacheDir/sha1($url).$ext`
  - On failure: silent skip (sequential fallback at render time will retry)

## Risk control

| Risk | Mitigation |
|------|-----------|
| Cloudinary rate-limits us | Cap at 8 concurrent |
| Network blip kills one image | Silent skip — sequential fallback at render |
| Walker misses some URLs | Sequential `cacheRemoteAssetForPdf()` still works at render |
| Walker collects too many (false positives) | At worst, wasted bandwidth — disk cache absorbs it |
| Prefetch breaks something | `PDF_PREFETCH_ENABLED=0` instant kill switch |
| Extra memory from collection | URLs are short strings, hundreds at most — negligible |

## Verification checklist

- [ ] Same datasheet renders byte-identical PDF before/after
- [ ] Cache directory contains expected files after first prefetch run
- [ ] Second render of same datasheet uses disk cache (existing behavior unchanged)
- [ ] Timing: render time on cold cache improves measurably (target: 50%+ faster)
- [ ] `PDF_PREFETCH_ENABLED=0` reverts to original sequential behavior
- [ ] No new errors in PHP error log
- [ ] PDF image quality unchanged

## Out of scope (next iterations)

1. Apply same prefetch to showcase (`showcase/render.php`)
2. Add timing logs (X-Pdf-Prefetch-Ms header) for production observability
3. Pre-resolve product DAM lookups upfront so all URLs are known before context build
4. CDN-fronted asset cache (Cloudflare in front of Cloudinary)
