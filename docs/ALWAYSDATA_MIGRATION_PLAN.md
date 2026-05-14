# AlwaysData Migration Plan

Migration from Railway to alwaysdata.net.
Reason: Railway free trial expired.
New API base: `https://nexled.alwaysdata.net/api`

---

## 1. What changed in this repo (already done)

| File | Change |
|------|--------|
| `configurator/app-shell.js` | `NX_DEFAULT_API_BASE` → alwaysdata URL |
| `api/lib/pdf-engine.php` | memory_limit 640M→256M, set_time_limit 0→120 |
| `api/.htaccess` | PHP limits for Apache |
| `api/.user.ini` | PHP limits for PHP-FPM |
| `.github/workflows/deploy-alwaysdata.yml` | Fixed: removed appdatasheets, added assets deploy |
| `PROJECT_MEMORY.md` | API URL updated |

**Deploy is automatic**: push to `main` → GitHub Actions runs → rsync to alwaysdata.

---

## 2. One-time server setup (do once, not in git)

These files live on the alwaysdata server only — never committed.

### 2a. Create `api/auth.php` on the server

SSH into alwaysdata and create:
```
/home/nexled/www/api/auth.php
```
Content:
```php
<?php
$provided = $_SERVER["HTTP_X_API_KEY"] ?? "";
$validKeys = [
    "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b",
];
if (!in_array($provided, $validKeys, true)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
return;
```

### 2b. Create `.env.local` on the server

Location: `/home/nexled/www/.env.local`

```ini
# Cloudinary (DAM)
DAM_CLOUDINARY_URL=cloudinary://384322826149234:IJp9oQQm37RhxoQjIFcLcaK6CiA@dofqiejpw
DAM_CLOUDINARY_ADMIN_URL=cloudinary://384322826149234:IJp9oQQm37RhxoQjIFcLcaK6CiA@dofqiejpw

# Remote databases on tecit.pt
DB_HOST=tecit.pt
DB_USER_REF=ref
DB_PASS_REF=ref01
DB_USER_LAMP=lampadas
DB_PASS_LAMP=ledlamp74621
DB_USER_INF=nexled_2024
DB_PASS_INF=nexled_2024

# DAM database — on alwaysdata MySQL (nexled_dam was local, migrated here)
DAM_DB_HOST=mysql-nexled.alwaysdata.net
DAM_DB_USER=nexled
DAM_DB_NAME=nexled_dam
# DAM_DB_PASS=<set on server, not in git>
```

### 2c. Set PHP version to 8.2 on alwaysdata

AlwaysData admin panel → Sites → your site → PHP → select **8.2**

---

## 3. Critical test: DB connectivity from alwaysdata

SSH into alwaysdata and run:
```bash
mysql -h tecit.pt -P 3306 -u ref -p tecit_referencias
```

If it connects: all databases are accessible remotely. ✓

If it times out: tecit.pt blocks alwaysdata's IP range. In that case:
- Option A: Ask tecit.pt admin to whitelist alwaysdata IP range
- Option B: Import `nexled_dam` to alwaysdata's own MySQL and use `DAM_DB_HOST=localhost`

---

## 4. GitHub Secrets required (already set up)

Verify these exist in GitHub → Settings → Secrets → Actions:

| Secret | Value |
|--------|-------|
| `ALWAYSDATA_SSH_KEY` | SSH private key for alwaysdata |
| `ALWAYSDATA_HOST` | `ssh-nexled.alwaysdata.net` |
| `ALWAYSDATA_PORT` | `22` |
| `ALWAYSDATA_USER` | `nexled` |
| `ALWAYSDATA_TARGET_DIR` | `/home/nexled/www` (or similar) |

---

## 5. Deploy flow (after secrets are set)

```
git push origin main
  → GitHub Actions triggers
  → rsync: configurator/ → /www/configurator/
  → rsync: api/         → /www/api/          (excludes auth.php, .env*)
  → rsync: assets/img/logos, icones, etc. → /www/assets/img/...
  → rsync: assets/img/01/ → /www/assets/img/01/
```

**No manual steps after first-time setup.**

---

## 6. Verification checklist

After first deploy, test each:

- [ ] `https://nexled.alwaysdata.net/api/?endpoint=health`
  Expected: `{"ok":true,"services":{...},"databases":{...}}`

- [ ] `https://nexled.alwaysdata.net/api/?endpoint=families`
  Expected: JSON array of product families

- [ ] `https://nexled.alwaysdata.net/api/?endpoint=options&family=11`
  Expected: JSON object with tamanho, cor, cri, etc.

- [ ] `https://nexled.alwaysdata.net/configurator/configurator.html`
  Expected: page loads, families dropdown populates, no "API unavailable"

- [ ] `https://nexled.alwaysdata.net/configurator/code-explorer.html`
  Expected: families load in explorer

- [ ] `https://nexled.alwaysdata.net/configurator/code-repair.html`
  Expected: repair page connects

- [ ] Generate one datasheet PDF (family 11, a DAM primary family)
  Expected: PDF downloads with logos and Cloudinary images

- [ ] Generate one datasheet PDF (family 01, uses local images)
  Expected: PDF downloads (may have fewer images if DB unreachable)

---

## 7. Other projects that consume the API

### 7a. `nexled.alwaysdata.net/eprel/formgen.html`

This is a separate codebase on alwaysdata. It calls the NexLed API.

**What to change in that project:**
- Find the API base URL in the JS (likely hardcoded as Railway URL)
- Change it to: `https://nexled.alwaysdata.net/api`
- The API key stays the same: `7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b`
- CORS: already open (`Access-Control-Allow-Origin: *`) — no server-side change needed

Endpoints used by EPREL tool:
- `?endpoint=eprel-code-mappings` (GET) — read mappings
- `?endpoint=eprel-code-mappings-save` (POST) — save mapping
- `?endpoint=eprel-code-mappings-batch` (POST) — bulk save
- `?endpoint=families` (GET) — family list
- `?endpoint=file-datasheet&reference=...` (GET) — PDF file
- `?endpoint=file-spectral&reference=...` (GET) — spectral data

### 7b. Any other external API consumers

For any project that calls the old Railway URL:

**Old:** `https://apinexled-production.up.railway.app/api`
**New:** `https://nexled.alwaysdata.net/api`

API key, endpoint structure, response format — **all unchanged**.

---

## 8. Local development (unchanged)

`.env.local` stays as-is on each developer's machine with localhost DB credentials.
`nxResolveApiBase()` in `app-shell.js` auto-detects localhost and uses the local API.
No changes needed for local dev.

---

## 9. What's NOT deployed (intentionally excluded)

| Path | Why excluded |
|------|-------------|
| `api/auth.php` | Contains API key — manual server file |
| `.env*` | Contains DB passwords — manual server files |
| `configurator/UI_SYSTEM/` | Git submodule — already deployed separately |
| `assets/img/11/`, `29/`, `30/`, `32/`, `48/`, `55/`, `58/` | Primary DAM families — served by Cloudinary |
| `new_data_img/` | Local staging only |

---

## 10. Rollback plan

If alwaysdata deployment breaks:
1. Switch `NX_DEFAULT_API_BASE` back to Railway URL in `app-shell.js`
2. Push — GitHub Actions deploys the change to alwaysdata
3. Upgrade Railway plan if needed

Git has full history of both the Railway and alwaysdata configurations.
