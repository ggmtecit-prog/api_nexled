# Caching Implementation Plan

Goal: cache hot, slow-changing endpoints. ~3x throughput. Zero breakage.

## Why

Every request hits the database. `families` and `options` change rarely but are queried on every page load. APCu unavailable on Railway/local — must use file-based cache.

## Scope (first pass)

Cache **two endpoints only**:

1. `families` — full response, 1 hour TTL
2. `options&family=N` — full response per family, 1 hour TTL

Skip everything else for now. These two cover ~80% of UI traffic.

## Design rules

- **Transparent.** No endpoint signature changes. No frontend changes.
- **Killable.** `CACHE_ENABLED=0` env var disables caching globally. Code falls back to direct DB.
- **Self-healing.** Corrupt cache file = silent miss + delete. Never throw.
- **Versioned.** Cache key includes a version string. Bump = clear all.
- **No new infra.** File-based, uses `sys_get_temp_dir()`. Works on XAMPP, Railway, anywhere.

## Files to add

**`api/lib/cache.php`** — caveman helper, ~30 lines:

- `cacheEnabled()` — checks env var
- `cacheGet($key)` — read + TTL check + auto-delete on expiry
- `cacheSet($key, $val, $ttl)` — serialize + write
- `cacheRemember($key, $ttl, $fn)` — get-or-compute idiom

## Files to edit

1. `api/endpoints/families.php` — wrap query block in `cacheRemember()`, add `Cache-Control: public, max-age=3600`
2. `api/endpoints/options.php` — wrap, key by family, same header

That's it. Two files. ~10 lines added per file.

## Cache key strategy

```
v1:families
v1:options:11
v1:options:55
```

Version prefix `v1` lets us bust everything by bumping the constant.

## Storage location

`sys_get_temp_dir() . "/api_nexled_cache/"` — ephemeral on Railway (resets on deploy = automatic invalidation), persistent on local dev.

Cache directory created on first write. Permissions 0755.

## TTL choices

| Endpoint | TTL | Why |
|----------|-----|-----|
| families | 3600s (1h) | Almost never changes, but want recovery within an hour if it does |
| options | 3600s (1h) | Same — dropdown options rarely change |

Conservative. Can extend to 24h later if it proves stable.

## What NOT to cache (yet)

- `reference` — changes per code, no benefit
- `decode-reference` — pure compute, fast already
- `code-explorer` — query parameters too varied, low hit rate
- `datasheet` / PDF endpoints — produce binaries, complex caching
- `dam` — write operations, mutating
- `health` — must always check live DB

## Cache invalidation strategy

**First pass:** TTL only. No manual invalidation. If admin changes family data, wait up to 1 hour OR bump `CACHE_VERSION` constant in `cache.php` and redeploy.

**Future:** add `?cache=bust` query param admin endpoint if needed.

## Risk control

| Risk | Mitigation |
|------|-----------|
| File write fails | `@file_put_contents()` — silent fail, falls through to direct query |
| Corrupt cache file | `@unserialize()` — silent fail, treated as miss |
| Cache dir unwritable | Falls through to direct query |
| Stale data after admin update | TTL caps it at 1 hour; bump version for instant bust |
| Bug breaks endpoint | Set `CACHE_ENABLED=0` in env, redeploy. Fully bypasses cache. |

## Verification checklist

- [ ] `families` endpoint returns same JSON before/after caching
- [ ] `options&family=11` returns same JSON before/after caching
- [ ] First request creates file in cache dir
- [ ] Second request reads from cache (compare timing)
- [ ] `CACHE_ENABLED=0` bypasses cache cleanly
- [ ] Bumping `CACHE_VERSION` invalidates all keys
- [ ] No PHP warnings in error log

## Out of scope (next iterations)

1. ETag / `If-None-Match` headers
2. Frontend `localStorage` cache (separate change)
3. APCu support when available (auto-detect, fallback to file)
4. Cache for `code-explorer` static slices
5. Cache for `family-ready-products` and `family-ready-filters`
