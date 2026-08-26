# Hosting an App on AlwaysData — Step-by-Step

## Account info (this project)
- Panel: https://admin.alwaysdata.com
- Current account: **nexled**
- MySQL host: `mysql-nexled.alwaysdata.net`
- Existing site: `nexled.alwaysdata.net`

---

## Step 1 — Create a new site

1. Log into https://admin.alwaysdata.com
2. Go to **Web → Sites**
3. Click **Add a site**
4. Fill in:
   - **Name**: your app name (e.g. `myapp`)
   - **Addresses**: the domain or subdomain (e.g. `myapp.alwaysdata.net` or `www.myapp.com`)
   - **Type**: choose your stack (see Step 2)
   - **Root path**: folder where your app lives (e.g. `/www/myapp`)
5. Save

> One AlwaysData account can host **multiple sites**. Each site gets its own root folder and domain.

---

## Step 2 — Choose your app type

### PHP app (WordPress, Laravel, plain PHP)
- Type: **Apache** or **PHP**
- Root path: `/www/myapp/public` (or `/www/myapp` for plain PHP)
- No extra config needed — PHP runs automatically

### Node.js app (Express, Next.js, etc.)
- Type: **Node.js**
- Command: `npm start` or `node server.js`
- Port: AlwaysData assigns a port via `$PORT` env variable
- Your app must listen on `process.env.PORT`

```js
// server.js — must use $PORT
const port = process.env.PORT || 3000;
app.listen(port);
```

### Python app (Flask, Django, FastAPI)
- Type: **uWSGI** or **Gunicorn**
- Entry point: your WSGI file (e.g. `app:app`)

### Static site (HTML/CSS/JS only)
- Type: **Static files**
- Root path: your build folder (e.g. `/www/myapp/dist`)

---

## Step 3 — Upload your files

**Option A — SFTP (easiest)**
- Host: `ssh-nexled.alwaysdata.net`
- User: `nexled`
- Password: your panel password
- Port: `22`
- Upload to `/www/myapp/`

Use FileZilla, Cyberduck, or VS Code SFTP extension.

**Option B — SSH + Git (recommended for code)**
```bash
ssh nexled@ssh-nexled.alwaysdata.net
cd /www
git clone https://github.com/youruser/yourrepo.git myapp
```

**Option C — FTP**
- Host: `ftp-nexled.alwaysdata.net`
- Same user/pass as panel

---

## Step 4 — Set up a database (if needed)

1. Panel → **Databases → MySQL**
2. Click **Add a database**
   - Name: `nexled_myapp` (prefix must match your account username)
   - Character set: `utf8mb4` / `utf8mb4_unicode_ci`
3. If you need a new DB user: **Databases → Users → Add a user**
4. Grant the user access to the new database

Connection details:
```
Host:     mysql-nexled.alwaysdata.net
Port:     3306
Database: nexled_myapp
User:     nexled (or new user)
```

---

## Step 5 — Set environment variables

1. Panel → **Web → Sites** → click your site → **Environment**
2. Add key/value pairs there (safer than `.env` files in web root)

Or SSH in and create a `.env` file in your app folder:
```bash
ssh nexled@ssh-nexled.alwaysdata.net
nano /www/myapp/.env
```

---

## Step 6 — Point a custom domain (optional)

1. Panel → **Domains** → **Add a domain**
2. Enter your domain (e.g. `myapp.com`)
3. AlwaysData gives you DNS records to set at your registrar:
   - Type `A` → AlwaysData IP
   - Or use their nameservers for full DNS management
4. Go back to **Web → Sites** → edit your site → add the domain to **Addresses**
5. SSL: Panel → **Web → SSL Certificates** → request a Let's Encrypt cert

---

## Step 7 — Node.js / Python — install dependencies

SSH in and run:
```bash
ssh nexled@ssh-nexled.alwaysdata.net
cd /www/myapp

# Node.js
npm install

# Python
pip install -r requirements.txt --user
```

---

## Step 8 — Restart the site

After any config change or deploy:
1. Panel → **Web → Sites** → your site → click **Restart**
2. Or via SSH:
```bash
# Not needed for PHP — auto-restarts on every request
# For Node/Python — restart via panel or:
kill $(cat /run/myapp.pid)  # AlwaysData handles restart automatically
```

---

## Common gotchas

| Problem | Fix |
|---|---|
| 500 error on Node.js | App not listening on `$PORT` |
| Files not found | Wrong root path in site config |
| DB connection refused | Using `localhost` instead of `mysql-nexled.alwaysdata.net` |
| Permission denied on SSH | Use account username `nexled`, not `root` |
| Domain not resolving | DNS propagation takes up to 24h |
| SSL cert fails | Domain DNS must point to AlwaysData before requesting cert |

---

## Quick reference

| Thing | Value |
|---|---|
| Panel | https://admin.alwaysdata.com |
| SSH | `ssh nexled@ssh-nexled.alwaysdata.net` |
| SFTP host | `ssh-nexled.alwaysdata.net` |
| FTP host | `ftp-nexled.alwaysdata.net` |
| MySQL host | `mysql-nexled.alwaysdata.net` |
| Files root | `/www/` |
