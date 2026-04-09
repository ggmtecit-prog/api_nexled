# API Development Plan

## Architecture

The API is the brain. Apps are just the face.

```
[ Configurator ]  [ Store ]  [ Website ]  [ Other tools ]
        ↓               ↓          ↓              ↓
              ┌─────── THE API ────────┐
              │  All logic lives here  │
              │  PDF generation        │
              │  Product data          │
              └────────────────────────┘
                     ↓        ↓        ↓
            [referencias]  [lampadas]  [info_nexled]
```

Apps do NOT talk to the database directly. They ask the API, the API does the work, the API answers.

---

## Ground rules for the rewrite

- `appdatasheets/` is **reference only** — never modified, just studied
- All new code uses **English** for naming (variables, functions, classes, everything)
- Same visual output as before — design changes come later
- Multilanguage support (PT/EN/etc.) is kept
- Goal is cleaner, better structured code — not just a copy

---

## Phase 1: Working API endpoints

**Goal:** The API owns all logic and returns correct data from the databases.

### Step 1.1 — Families endpoint ✅
- [x] Query `SELECT nome, codigo FROM Familias ORDER BY codigo` from `tecit_referencias`
- [x] Return JSON array of `{ codigo, nome }`
- [x] Tested and working

### Step 1.2 — Options endpoint ✅
- [x] Accept `?family=` parameter
- [x] Return all 8 option types: tamanhos, cores, cri, series, lentes, acabamentos, caps, opcoes
- [x] Tested and working

### Step 1.3 — Reference endpoint ✅
- [x] Accept `?ref=` parameter, use first 10 characters
- [x] Query `tecit_lampadas` for the product description
- [x] Return `{ description }` or `{ error }`
- [x] Tested and working

### Step 1.4 — Datasheet endpoint (reverse engineer + rewrite)

**Approach:** Study `appdatasheets/` to understand the logic deeply, then rewrite it cleanly inside the API. Same output, better code.

**Rewrite rules:**
- All naming in English (no more Portuguese variable/function/class names)
- Cleaner structure — one job per function
- Same multilanguage support
- Same PDF visual output (for now)

#### Step 1.4a — Study & map the existing logic
- [ ] Map all product types and which families belong to each
- [ ] Understand how the reference code is decoded (getDigitos)
- [ ] Understand how each product type generates a different PDF
- [ ] Document findings before writing any new code

#### Step 1.4b — Build the new datasheet engine inside the API
- [ ] Create `api/lib/` folder for shared logic
- [ ] `lib/pdf.php` — PDF generation setup (TCPDF wrapper)
- [ ] `lib/datasheet.php` — main orchestrator, replaces gerarDatasheet.php
- [ ] `lib/datasheet-data.php` — all data-fetching functions (replaces funcoesDatasheet.php)
- [ ] `lib/datasheet-layout.php` — HTML structure builder (replaces estruturaDatasheet.php)
- [ ] `lib/products/barra.php`, `downlight.php`, `dynamic.php` — product-specific logic

#### Step 1.4c — Wire up the endpoint
- [ ] Update `endpoints/datasheet.php` to call the new engine
- [ ] Accept POST with product configuration as JSON
- [ ] Return PDF download or JSON error
- [ ] Test with real configurations for each product type

---

## Phase 2: Auth & security

### Step 2.1 — API keys
- [ ] Generate real API keys (random strings) for each consumer
- [ ] Update `auth.php` with real keys
- [ ] Add `auth.php` to `.gitignore`
- [ ] Create `auth.example.php` with placeholder keys

### Step 2.2 — Input validation
- [ ] Sanitize family codes (must be numeric)
- [ ] Sanitize reference codes (alphanumeric only)
- [ ] Use prepared statements for SQL queries

---

## Phase 3: New configurator tool

**Goal:** Replace `appdatasheets/` with a clean new tool that talks to the API. No database connections, no PHP logic — just a frontend.

### Step 3.1 — Basic structure
- [ ] Create `configurator/` folder
- [ ] `index.html` — the form with all dropdowns
- [ ] `style.css` — based on the existing styles
- [ ] `script.js` — all API calls, no direct database access

### Step 3.2 — Connect to API
- [ ] Load families → `GET /api/?endpoint=families`
- [ ] Load options → `GET /api/?endpoint=options&family=X`
- [ ] Get description → `GET /api/?endpoint=reference&ref=X`
- [ ] Generate datasheet → `POST /api/?endpoint=datasheet`
- [ ] All requests include the API key

### Step 3.3 — Polish
- [ ] Error handling for API failures
- [ ] Loading states
- [ ] Test all product families and combinations

### Step 3.4 — Retire old app
- [ ] Confirm new configurator works for all product types
- [ ] Archive or remove `appdatasheets/`

---

## Phase 4: Digital Asset Manager (DAM)

**Goal:** Centralize all product files (images, PDFs, drawings) in one place. Any app can fetch files through the API.

**Where files are stored:** Cloudinary (25GB free). Files are NOT stored on the server — the API receives the file, sends it to Cloudinary, and saves the link.

### Step 4.1 — Set up Cloudinary
- [ ] Create a free Cloudinary account at cloudinary.com
- [ ] Add credentials to `config.php` (already gitignored)
- [ ] Install the Cloudinary PHP library

### Step 4.2 — Upload endpoint
- [ ] `POST /api/?endpoint=assets&action=upload`
- [ ] Accept a file + product code + type (photo / drawing / datasheet)
- [ ] Send the file to Cloudinary, organized by product family
- [ ] Save the returned URL in the database
- [ ] Return the file's public URL

### Step 4.3 — Fetch endpoint
- [ ] `GET /api/?endpoint=assets&action=get&product=XX` — all files for a product
- [ ] `GET /api/?endpoint=assets&action=get&product=XX&type=photos` — filter by type
- [ ] Return a list of file names and URLs

### Step 4.4 — Delete endpoint
- [ ] `DELETE /api/?endpoint=assets&action=delete&file=filename`
- [ ] Delete from Cloudinary AND remove from database
- [ ] Only allow with a valid API key

### Step 4.5 — Connect generated datasheets
- [ ] When the datasheet endpoint generates a PDF, upload it to Cloudinary automatically
- [ ] Save the link so it can be re-downloaded without regenerating

---

## Phase 5: Future improvements (not now)

- **Rate limiting** — prevent abuse if the API goes public-facing
- **Caching** — families and options rarely change, could be cached
- **Versioning** — `/api/v1/` prefix for breaking changes
- **Database connection pooling** — each function currently opens its own connection
- **Logging** — track which consumers call what, and how often

---

## File structure when done

```
api_nexled/
├── api/
│   ├── README.md
│   ├── PLAN.md
│   ├── index.php             ← router
│   ├── auth.php              ← API keys (gitignored)
│   ├── auth.example.php      ← template
│   └── endpoints/
│       ├── families.php
│       ├── options.php
│       ├── reference.php
│       ├── datasheet.php
│       └── assets.php        ← DAM (files on Cloudinary)
└── configurator/
    ├── index.html
    ├── style.css
    └── script.js
```

`appdatasheets/` is retired once Phase 3 is complete.
