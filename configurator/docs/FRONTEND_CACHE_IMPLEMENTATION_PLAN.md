# Frontend localStorage Cache Implementation Plan

Goal: cache families and options in browser. Page reloads feel instant.
Pairs with the server-side cache shipped previously. Three layers: browser → server → DB.

## Why

Server cache helps the *first* user. Frontend cache helps every *returning* user — no network round-trip needed.

Currently, every page load re-fetches families and options from the API. After this change: cached for 1 hour, cleared by version bump.

## Scope

Cache **two endpoints only**:

1. `families` — full list, 1h TTL
2. `options&family=N` — per-family options, 1h TTL

Same scope as server-side cache. Mirrors that decision.

## Design rules

- **Transparent.** No call-site signature changes. Existing `apiFetch()` untouched.
- **Killable.** Bumping `API_CACHE_VERSION` invalidates all cached entries.
- **Self-healing.** Corrupt JSON or expired TTL = silent miss + delete. Never throws.
- **No new files.** Helpers live in `app-shell.js` (already loaded everywhere).
- **Caveman.** 3 functions, single responsibility each.

## Files to edit

1. **`configurator/app-shell.js`** — append 3 functions (`apiCacheGet`, `apiCacheSet`, `apiCacheRemember`) as global helpers. ~25 lines.
2. **`configurator/script.js`** — wrap 2 call sites:
   - line 5105: families fetch on configurator init
   - line 5210: options fetch in `loadOptions()`
3. **`configurator/code-explorer.js`** — wrap 2 call sites:
   - line 322: options fetch on family change
   - line 2290: families fetch on explorer init

## API surface

```js
apiCacheRemember(key, ttlSeconds, async () => apiFetch(path))
```

Single function. Replaces direct `apiFetch()` calls for cacheable paths.

## Cache key strategy

```
nx-api-cache:v1:families
nx-api-cache:v1:options:11
nx-api-cache:v1:options:55
```

Prefix `nx-api-cache:v1:` lets us scope to this app and bust everything by bumping `v1`.

## Storage

`localStorage` — synchronous, ~5MB per origin, persists across sessions.

Each entry: `{ exp: <epoch ms>, val: <payload> }`.

## TTL choices

| Endpoint | TTL | Why |
|----------|-----|-----|
| families | 3600s (1h) | Same as server cache, won't show stale data longer than server |
| options | 3600s (1h) | Same |

Aligned with server cache TTL on purpose.

## What NOT to cache

- `reference`, `decode-reference` — change per code, no benefit
- `code-explorer` filtered queries — too varied
- `health`, `dam` writes — never
- PDF endpoints — binary, complex caching

## Risk control

| Risk | Mitigation |
|------|-----------|
| `localStorage` quota exceeded | `try/catch` on `setItem`, silent fail, falls through to network |
| Corrupt entry | `JSON.parse` in try/catch, treated as miss, removed |
| Stale data after admin update | TTL caps at 1h; user can hard-refresh to bypass |
| Cache bug breaks page | Bump `API_CACHE_VERSION` to bust all keys |
| User on private/incognito | `localStorage` may throw — silent fail, network fallback |

## Behavior matrix

| State | First load | Reload within 1h | Reload after 1h |
|-------|-----------|------------------|------------------|
| Network | Fetch + cache | Read cache | Fetch + cache |
| Time | ~200ms | ~5ms | ~200ms |
| User sees | Slight wait | Instant | Slight wait |

## Verification checklist

- [ ] First page load: families fetched from network, cache entry written to localStorage
- [ ] DevTools → Application → Local Storage shows `nx-api-cache:v1:families` key
- [ ] Reload: no `endpoint=families` request in Network tab (cache hit)
- [ ] Family selection: first time fetches options, second time cached
- [ ] After 1h: cache entry expires, fresh fetch
- [ ] Bumping `API_CACHE_VERSION` to `"v2"` and reloading: fresh fetch (old keys ignored)
- [ ] Quota error doesn't crash page
- [ ] Incognito mode works (with or without localStorage)

## Out of scope (future)

1. Cache `family-ready-products` and `family-ready-filters`
2. Add manual "refresh" button to bust cache from UI
3. Cache invalidation event from server (websocket/SSE)
4. Service Worker for offline access
