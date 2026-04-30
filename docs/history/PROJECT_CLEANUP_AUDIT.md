# Project Cleanup Audit

Date: 2026-04-20
Scope: identify legacy/stale files (MD, zips, backups, scratch dirs) safe to remove or archive.

**Not in scope:** `appdatasheets/` — stays untouched (still used by non-primary families at runtime).

## Category 1 — Legacy planning docs (work is done)

These describe work that has already shipped. Safe to archive or delete.

| File | Size | Status | Suggestion |
|------|------|--------|------------|
| `DAM_ROADMAP.md` | 24KB | DAM shipped | Archive |
| `DAM_IMPLEMENTATION_GUIDE.md` | 44KB | DAM shipped (guide for another AI, job done) | Archive |
| `DAM_CUTOVER_CHECKLIST.md` | 6KB | Cutover done | Archive |
| `CODE_EXPLORER_NEXT_AI_PROMPT.md` | 7KB | `code-explorer` endpoint + UI live | Archive |
| `CODE_REPAIR_PAGE_PLAN.md` | 9KB | `code-repair` endpoint + page live | Archive |
| `MEDIA_DAM_WEBSITE_IMPLEMENTATION_PROMPT.md` | 9KB | External-site prompt, not used here | Archive or move |

**Archive option:** move them into `docs/history/` (or `archive/`) so they stay in git history for reference without cluttering the root.

## Category 2 — Research notes (keep, but clean)

`api/*.md` — 23 research/planning files. All useful, but should live together.

Families / code logic:
- `BARRAS_*.md` (7 files)
- `DOWNLIGHTS_*.md` (4 files)
- `SHELF_*.md` (3 files)
- `TUBULARES_*.md` (4 files)
- `ACESSORIOS_LOGIC_FINDINGS.md`

Specs / plans:
- `DAM_API_CONTRACT.md`
- `DAM_FOLDER_STRUCTURE.md`
- `OFFICIAL_DATASHEET_LAYOUT_SPEC.md`
- `NEXT_STEPS_DATASHEET_PARITY.md`
- `CODE_VALIDITY_EXPLORER_PLAN.md`
- `T8_ASSET_INVENTORY.md`, `T8_FAMILY_ONBOARDING_PLAN.md`
- `PLAN.md`, `PROMPT.md`, `README.md`

**Suggestion:** move all into `api/docs/` with subfolders (`families/`, `specs/`). Keeps `api/` root clean — only PHP stays at the top.

## Category 3 — Backups and archives

| File | Size | Status |
|------|------|--------|
| `appdatasheets.zip` | 69MB | Old snapshot of `appdatasheets/` folder. Duplicated data. |
| `dam_backup_pre_link_model_2026-04-16.sql` | 24KB | Pre-cutover DB backup. |

- `appdatasheets.zip` — **delete** if the unpacked folder is current. Zip is ignored by git anyway (`.gitignore`), so it's just taking disk space.
- DB backup — **keep 2–4 weeks**, then delete. Cheap insurance.

## Category 4 — Scratch / untracked folders

| Folder | Size | Status |
|--------|------|--------|
| `new_data_img/` | 995MB | Untracked (git status). New product photos waiting to be ingested. |
| `NEW_COLOR_SPECTHER/` | — | 27 SVG color spec files, not referenced in canonical docs. |
| `READING_DOCUMENTS/` | — | Source PDFs (Barras, Shelf, etc.) used for research. |
| `tmp/pdfs/` | empty | Scratch dir — can stay, harmless. |
| `output/pdf/` | — | 7 generated family PDFs — regeneratable from the API. |

- `new_data_img/` — ingest into DAM, then delete. 995MB is a lot to carry around.
- `NEW_COLOR_SPECTHER/` — decide: are these live assets or scratch? If scratch, delete. If live, move to `appdatasheets/img/` or upload to DAM.
- `READING_DOCUMENTS/` — keep as read-only research.
- `output/pdf/` — delete, regenerate on demand.

## Category 5 — Generator scripts

`scripts/` — 7 files, 1639 lines total. All DAM import scripts.

| Script | Purpose | Still needed? |
|--------|---------|---------------|
| `import-dam-dynamic-family-assets.php` | One-shot family import | Likely one-shot, done |
| `import-dam-family-datasheet-assets.php` | One-shot datasheet import | Likely one-shot, done |
| `import-dam-family-technical-assets.php` | One-shot technical import | Likely one-shot, done |
| `import-dam-shared-assets.php` | Shared assets import | Likely one-shot, done |
| `rasterize-pdf-assets.sh` | PDF → raster | Keep if still run |
| `rasterize-svg-assets.py` | SVG → raster | Keep if still run |
| `smoke-dam-datasheets.ps1` | Smoke test | Keep |

**Suggestion:** move one-shot imports to `scripts/archive/`. Keep rasterizers + smoke test in `scripts/`.

## Category 6 — Keep as-is

- `CLAUDE.md` — project guidelines
- `PROJECT_MEMORY.md` — canonical hub
- `DAM_FINDINGS.md` — just created
- `FULLY_VALID_TECIT_CODES_BY_FAMILY.md` — reference data
- `appdatasheets/` — **do not touch** (runtime dependency)
- `api/` PHP files — all active
- `configurator/` — active UI

## Proposed clean-up order (surgical)

1. **Move, don't delete.** Create `docs/history/` and move Category 1 files there. Create `api/docs/` and move Category 2.
2. **Delete `appdatasheets.zip`** — gitignored duplicate.
3. **Decide `NEW_COLOR_SPECTHER/` and `new_data_img/`** — you know if they're live or scratch.
4. **Delete `output/pdf/`** — regenerable.
5. **Archive one-shot scripts** into `scripts/archive/`.
6. **Update `PROJECT_MEMORY.md`** — fix the two links that point to moved files (lines 585–586).

Everything above is reversible via git. No runtime risk because `appdatasheets/` and `api/endpoints/` are untouched.

## Estimated savings

- Disk: ~1.1 GB (zip + new_data_img + output)
- Root MD files: 10 → 4
- `api/` MD files: 23 → 0 (moved into `api/docs/`)

Project root becomes readable at a glance.
