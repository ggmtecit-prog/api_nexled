# Cache Observability + Refresh Button Plan

Goal: make the existing 4-layer cache visible (X-Cache header) and give users
a way to force-refresh when admin updates data (refresh button).
Closes the caching story cleanly.

## Why

We shipped: server response cache, frontend localStorage, PDF datasheet
prefetch, PDF showcase prefetch. All work, all invisible.

- **Devs:** can't tell from DevTools whether server cache is hitting.
- **Users:** if admin updates a family in the DB, users wait up to 1h
  (the TTL) before seeing the change. No way to force-refresh.

This pass adds:

- `X-Cache: HIT|MISS|BUST` response header on cached endpoints
- `?cache=bust` query param to skip server cache (and refresh it)
- `nxRefreshData()` global JS function to bust both layers and reload
- One refresh button in `configurator.html` as a working example

## Scope

Two files change in backend, three in frontend, one HTML page gets a button.

**Backend:**
- `api/lib/cache.php` — add header + bust handling

**Frontend:**
- `configurator/app-shell.js` — add `nxRefreshData`, `nxClearApiCache`,
  auto-detect bust on load, modify `apiCacheRemember`
- `configurator/script.js` — modify `apiFetch` to append `&cache=bust`
  when bust flag is set
- `configurator/code-explorer.js` — same modification
- `configurator/configurator.html` — add one refresh button as example

## Behavior flow

User clicks "Refresh data" button:

1. Frontend: `nxRefreshData()`
   - Removes all `nx-api-cache:*` entries from localStorage
   - Reloads page with `?cache=bust` in URL
2. New page load
   - `app-shell.js` runs first: detects `?cache=bust`, sets
     `window.__nxCacheBustOnce = true`, strips param from URL via
     `history.replaceState` (so a future browser refresh doesn't
     keep busting)
   - `script.js` (or `code-explorer.js`) runs: `apiFetch()` reads the
     flag and appends `&cache=bust` to the API URL
3. Server side
   - `cacheRemember()` sees `?cache=bust`, deletes the file cache
     entry, sets `X-Cache: BUST`, fetches fresh from DB, caches fresh
   - All future users (without bust) see the fresh data
4. Frontend caches fresh data in localStorage
5. User sees fresh data

After this flow:
- localStorage has fresh data (1h TTL)
- Server file cache has fresh data (1h TTL)
- URL is clean (no `?cache=bust` lingering)
- Browser refresh = normal cached path

## X-Cache header values

| Value | Meaning |
|-------|---------|
| `HIT` | Cache hit, returned cached value |
| `MISS` | Cache miss, fetched fresh, stored in cache |
| `BUST` | User-requested bust, deleted entry, fetched fresh, stored fresh |

DevTools → Network tab → click request → Response Headers → see X-Cache.

## Risk control

| Risk | Mitigation |
|------|-----------|
| Header fails (headers already sent) | `if (!headers_sent())` guard |
| `?cache=bust` lingers in URL | `history.replaceState` strips it on first load |
| User refreshes after bust → keeps busting | URL stripped means next refresh is normal |
| Bust used by adversary to skip cache forever | Internal tool, not a DoS vector here |
| `apiFetch` modification breaks existing calls | Only appends a query param when flag is set; transparent otherwise |
| `nxRefreshData` not defined on pages without app-shell.js | Every page already loads app-shell.js first |
| localStorage clearing partially fails | `try/catch` silent fail, page reload still happens |

## Adoption (in this commit)

Only `configurator.html` gets a button. Other pages can add later by
copying the same pattern:

```html
<button type="button" class="btn btn-secondary btn-icon btn-sm" onclick="nxRefreshData()" title="Refresh data">
  <i class="ri-refresh-line icon icon-sm" aria-hidden="true"></i>
</button>
```

Function works on every page that loads `app-shell.js` (all of them today).

## Verification checklist

- [ ] Server cache HIT: second request to `families` shows `X-Cache: HIT`
- [ ] Server cache MISS: first request after deploy/restart shows `MISS`
- [ ] Server cache BUST: request with `?cache=bust` shows `BUST` and refreshes file
- [ ] `nxRefreshData()` clears `nx-api-cache:*` entries from localStorage
- [ ] After button click: page reloads, fresh data fetched, URL is clean
- [ ] `apiFetch` appends `&cache=bust` only when flag is set
- [ ] Browser refresh after bust: normal cached path (URL has no `cache=bust`)
- [ ] Existing endpoints unaffected (only `families` and `options` use the cache helper)
- [ ] PHP syntax clean, JS syntax clean

## Out of scope (next iterations)

- Per-key TTL adjustment via UI
- Admin endpoint to invalidate specific cache keys
- Refresh button in code-explorer / code-repair / dam pages
- Server-Sent Events to push cache invalidation
- ETag / If-None-Match headers
