# NexLed Configurator — Feature Status

Generated: 2026-05-27
Scope: `configurator/` frontend + `api/` backend it consumes.

---

## Legend

- ✅ **Done** — fully implemented, working in production.
- 🟡 **Partial** — works but missing data, polish, or coverage.
- 🔴 **Not implemented** — planned/scaffolded but no runtime.

---

## Pages

### `index.html` — Workspace home
- ✅ Static landing page with nav drawer/sidebar
- ✅ Shared header/footer/language selector
- ✅ API health badge

### `configurator.html` — Product Configurator
- ✅ Family combobox loads from API
- ✅ Option dropdowns (size, color, CRI, series, lens, finish, cap, option)
- ✅ Live reference builder (17-char Tecit code)
- ✅ Reference paste-and-load ("Load Code" button) with sanitize + truncate
- ✅ Description field auto-populated with lens/finish/option hints
- ✅ Cable auto-fill from selected option (asqc2/c1m/dc24/dcj/c2 + length + colour)
- ✅ Three output modes: Official, Showcase, Custom (segmented control)
- ✅ Tecit Code Logic modal (segment legend, examples, family codes)
- ✅ PDF loading overlay with success state
- ✅ Toast/status messages with i18n
- ✅ Cache observability (Refresh data button)
- 🟡 Language switcher: now fetches data per language. Backend `options.php` returns only Portuguese labels — needs lang-aware columns server-side. Frontend ready.
- 🟡 Custom datasheet: text/image/section/copy overrides UI complete; backend runtime implemented for all datasheet-supported families.

### `code-explorer.html` — Code Explorer
- ✅ Valid-only mode (lists valid codes per family)
- 🟡 Invalid full-family matrix mode — exists but scales poorly on large families
- ✅ Detail modal with section-by-section diagnostics
- ✅ Tecit code logic modal embedded
- ✅ Strict packshot validation applied (no wrong images)

### `code-repair.html` — Code Repair
- ✅ Loads any reference, shows section-by-section repair status
- ✅ DAM search modal for picking replacement assets
- ✅ Description / overview / database diagnosis sections
- ✅ Strict packshot validation applied (correct missing-image state shown)

### `dam.html` — Digital Asset Management
- 🟡 Folder/asset tree browsing — works
- 🟡 Upload, link, unlink, create folder — works
- 🟡 Asset details modal — works
- 🔴 Bulk rename / move — not implemented
- 🔴 Asset versioning — not implemented

---

## Backend endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `families` | ✅ | Returns family code, name, runtime metadata. Now accepts `&lang=` (frontend sends it). |
| `options` | ✅ | Returns all dropdown options for a family. Accepts `&lang=` (backend currently ignores; needs lang-aware columns). |
| `reference` | ✅ | Decodes 10-char identity → description. Accepts `&lang=` (backend currently returns single `desc` column). |
| `decode-reference` | ✅ | Full 17-char decode into segments. |
| `datasheet` | ✅ | Official PDF, all supported families. Strict packshot validation enabled. |
| `custom-datasheet-preview` | ✅ | Snapshot for the Custom UI preview pane. |
| `custom-datasheet-pdf` | ✅ | Custom PDF with overrides. |
| `showcase-preview` | ✅ | Variant count + estimated pages for current scope. |
| `showcase-pdf` | ✅ | Renderers implemented for: downlight, tubular, barra, dynamic, shelf. |
| `code-explorer` | ✅ | Used by Code Explorer page. |
| `code-repair` | ✅ | Used by Code Repair page. |
| `family-ready-products` | ✅ | EPREL/import ready-only rows, paginated. |
| `family-ready-filters` | ✅ | EPREL filter narrowing. |
| `eprel-code-mappings` | ✅ | GET registered mappings. |
| `eprel-code-mappings-save` | ✅ | POST single mapping. |
| `eprel-code-mappings-batch` | ✅ | POST many mappings. |
| `dam` | 🟡 | Link-model cutover live; still evolving. |
| `assets` | 🟡 | Older coarse endpoint, transitional. |
| `health` | ✅ | DB + service status. |
| `svg-diagnostics` | ✅ | Asset resolution debugger. |
| `file-datasheet`, `file-spectral` | 🟡 | Support endpoints, working. |

---

## Datasheet families

Source of truth: `api/lib/family-registry.php`.

### ✅ Datasheet runtime supported + DAM rollout complete
`11`, `29`, `30`, `32`, `48`, `55`, `58` — DAM-first asset resolution, smoke-tested.

### ✅ Datasheet runtime supported, local-asset paths
`01` (T8 AC, first sample), `04` (T5 CC), `05` (T5 VC base proven), `06` (T5 AC), `07` (PLL), `09` (S14), `10` (Barra CC), `31`, `40`, `49` (Shelf), `56` (BT 12V), `59` (NEON), `60` (B 24V I45)

### 🔴 Family selectable but datasheet runtime not mapped
`02`, `03`, `08`, `12`–`28`, `33`–`39`, `41`–`47`, `50`–`54`, `57`

(See `family-registry.php` for full status per family.)

---

## Showcase PDF runtime (renderers)

- ✅ `downlight` (29, 30)
- ✅ `tubular` (01, 04, 05, 06, 07, 09)
- ✅ `barra` (10, 11, 31, 32, 40, 55, 56, 58, 59, 60)
- ✅ `dynamic` (48)
- ✅ `shelf` (49)
- 🔴 `spot`, `decor`, `highbay`, `luminaire`, `panel`, `canopy` — registered in config, no renderer yet.

---

## Image / asset system

- ✅ Lens-prefixed naming scheme (`l{lens}s{series}_...`) implemented in code
- ✅ Strict packshot validation flag wired through code-explorer, code-repair, PDF generation
- ✅ Strict mode blocks wrong-finish images (filename match mandatory)
- ✅ `scripts/dam-rename-lens-prefix.php` migration script written
- 🟡 DAM rename script NOT executed on live for family 11 yet — strict will block until done
- 🔴 Alu PT (finish 04) packshots for family 11 don't exist anywhere yet — PDFs blocked until images sourced
- ✅ Cloudinary palette PNG → true-color RGB conversion (TCPDF rendering fix)
- ✅ Energy class labels rendered
- ✅ Color temperature spectra (CIE coordinates + spectrum charts)
- ✅ Lens diagrams (polar + cone)
- ✅ Technical drawings with dimension labels A–J

---

## Internationalization (i18n)

- ✅ Frontend keys: en, pt
- 🔴 ES locale file missing (`es.js`) — `value="es"` exists in PDF language dropdown but no UI translations
- ✅ Frontend now sends `&lang=` to API
- 🟡 Backend `options.php`/`reference.php` do not return lang-specific values (only single `desc` column). Translation layer needed in DB schema.
- 🟡 `families` endpoint returns DB family names (likely PT only).

---

## Custom datasheet feature

- ✅ Text overrides (document title, header copy, footer note)
- ✅ Image overrides (header, drawing, finish) with DAM browser modal
- ✅ Section visibility toggles (fixing, power supply, connection cable)
- ✅ Advanced copy mode — editable section text
- ✅ Inline field override editors injected into output panel
- ✅ Reset overrides button
- ✅ Preview state machine with debounce and signature tracking

---

## Showcase feature

- ✅ Scope locking (color, cri, series, lens fields)
- ✅ Expand toggles for unlocked segments
- ✅ Section selection (overview, luminotechnical, spectra, drawings, lens diagrams, finishes, options)
- ✅ Variant count + estimated pages preview
- ✅ Auto-fire guard (requires at least one locked scope value to prevent server crash on large families)
- ✅ Server-side guard (>200 identities without locked identity segment returns empty)
- ✅ Generate button disabled until preview signature matches current selection

---

## EPREL integration

- ✅ Central API `family-ready-products` and `family-ready-filters` endpoints
- ✅ Code mappings persistence (`nexled_eprel` DB, `eprel_code_mappings` table)
- ✅ Save / batch / list endpoints for mappings
- 🔴 No EPREL UI in the configurator app — external import client consumes the API directly

---

## Known issues / pending work

### High priority
- 🟡 **DAM rename for family 11** — run `php scripts/dam-rename-lens-prefix.php --family=11` (then 29, 30, 32, 48, 55, 58). Until done, family 11 official PDFs will block with `packshot_not_found`.
- 🔴 **Alu PT images missing** — source from photography studio, import to DAM, then PDFs unblock.
- 🟡 **Datasheet parity** — generator is section-based; legacy PDFs are page-template programs. Architectural gap.
- 🟡 **Backend options language support** — needs schema columns for EN/ES on Acabamento, Cap, Opcao, Cor, Series tables.

### Medium priority
- 🟡 **`api/README.md` and `api/PLAN.md`** — historical baselines, partially outdated.
- 🟡 **Old missing-data validator** from `appdatasheets/` not fully restored.
- 🟡 **Code Explorer invalid-mode** — doesn't scale; needs drill-down redesign.
- 🟡 **DAM page** — bulk operations, versioning missing.

### Low priority
- 🔴 **ES locale** file — only nav and PDF language dropdown reference it.
- 🔴 **Spot/decor/highbay/luminaire/panel/canopy showcase renderers** — registered but no PHP implementation.

---

## Security / hardening

- ✅ API key auth on all endpoints (except `health`)
- ✅ GitHub Actions deploys to alwaysdata on push to main
- ⚠️ API key hardcoded in `configurator/script.js:8` — visible to anyone with browser dev tools. Pre-existing design; needs session-based auth to fix properly.

---

## Reference

- Source of truth: `PROJECT_MEMORY.md` (architecture, datasheets, DAM rollout history).
- Active spec: `api/docs/specs/NEXT_STEPS_DATASHEET_PARITY.md`.
- Family-by-family research: `api/docs/families/*.md`.
- EPREL spec: `api/docs/eprel/*.md`.
