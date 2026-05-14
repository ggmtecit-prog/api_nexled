# AlwaysData Deployment Guide

Deploy the NexLed API to nexled.alwaysdata.net.
Replace Railway. Free tier. ~34MB total.

## What to upload (via SFTP)

Upload to the alwaysdata server root (`/home/nexled/www/`):

| Local path | Upload to | Size | Notes |
|------------|-----------|------|-------|
| `api/` | `api/` | 22MB | All PHP — includes .htaccess + .user.ini |
| `assets/img/logos/` | `assets/img/logos/` | 360KB | PDF header/footer logos |
| `assets/img/icones/` | `assets/img/icones/` | 140KB | Datasheet icons |
| `assets/img/classe-energetica/` | `assets/img/classe-energetica/` | 116KB | Energy label images |
| `assets/img/temperaturas/` | `assets/img/temperaturas/` | 4.6MB | Temperature graphs |
| `assets/img/01/` | `assets/img/01/` | 6.5MB | Family 01 local images |
| `assets/img/favicon.ico` | `assets/img/favicon.ico` | tiny | |
| `assets/img/loading.gif` | `assets/img/loading.gif` | tiny | |
| `assets/img/placeholders/` | `assets/img/placeholders/` | tiny | |
| `configurator/` | `configurator/` | — | Already there |

**Do NOT upload:**
- `assets/img/11/`, `29/`, `30/`, `32/`, `48/`, `55/`, `58/` — primary DAM families, served by Cloudinary
- `new_data_img/` — local only
- `appdatasheets.zip` — deleted
- `vendor/` at root — Composer deps, not needed (API uses api/vendor/tcpdf directly)
- `.env.local`, `.env.railway` — credentials, never upload

**Total upload size: ~34MB**

## Files to create manually on the server (never in git)

### 1. `/home/nexled/www/api/auth.php`
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

### 2. `/home/nexled/www/.env.local`
```
# Cloudinary
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

# DAM + EPREL databases
# If alwaysdata can reach tecit.pt:3306 for all DBs, use same host.
# If nexled_dam is local to alwaysdata MySQL, use: DAM_DB_HOST=localhost
DAM_DB_HOST=tecit.pt
DAM_DB_USER=<nexled_dam_user>
DAM_DB_PASS=<nexled_dam_pass>
```

> **First test**: check if alwaysdata can reach `tecit.pt:3306`.
> SSH into alwaysdata and run: `mysql -h tecit.pt -u ref -p tecit_referencias`
> If it connects: all DBs use tecit.pt. If it times out: you need to import nexled_dam to alwaysdata MySQL.

## URL after deploy

API: `https://nexled.alwaysdata.net/api/?endpoint=families`
Frontend: `https://nexled.alwaysdata.net/configurator/configurator.html`

The `NX_DEFAULT_API_BASE` in `app-shell.js` already points to this URL (updated in this commit).

## PHP version

Set to **PHP 8.2** in alwaysdata admin panel:
Admin → Web Sites → your site → PHP version → 8.2

## Test checklist after upload

- [ ] `https://nexled.alwaysdata.net/api/?endpoint=health` → `{"ok":true,...}`
- [ ] `https://nexled.alwaysdata.net/api/?endpoint=families` → array of families
- [ ] `https://nexled.alwaysdata.net/api/?endpoint=options&family=11` → options object
- [ ] Configurator page loads and shows families
- [ ] Code Explorer loads
- [ ] Generate one PDF (family 11) — checks memory + time limits
- [ ] EPREL formgen.html connects

## If PDF generation fails (memory)

Contact alwaysdata support and request `memory_limit = 256M` for your account.
They often grant this via ticket for PHP-heavy applications.

## SFTP credentials (alwaysdata)

Host: `ssh-nexled.alwaysdata.net`
User: `nexled`
Password: your alwaysdata password
Port: 22
