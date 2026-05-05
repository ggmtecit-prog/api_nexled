# Error Handling + Logging Plan

Goal: foundation for consistent API error responses and forensic error logs.
Conservative scope. Build the tools. Wire them into the safest spot first
(router-level errors). Existing endpoint code untouched — adoption can grow
over time without breaking anything.

## Why

- Today, error responses across endpoints have inconsistent shapes
  (some `{"error": "..."}`, some text, some no body at all). Frontend
  can't rely on a single error format.
- Errors disappear into `error_log()` and are rarely read. When something
  breaks in production, the only signal is "users said it was broken."
- Internal tool with a small team — you ARE the support team. Forensic
  logs save hours per debug.

## Scope (first pass)

**Build, don't migrate.** Add two new lib files. Use them in two safe
places. Leave every existing endpoint alone.

### What we ship

1. `api/lib/logger.php` — caveman file logger
2. `api/lib/responses.php` — caveman `respondError` + `respondJson`
3. `api/bootstrap.php` — `require_once` both new files
4. `api/index.php` — use `respondError` for the 400 (no endpoint) and 404
   (unknown endpoint) cases. Same status codes, additive response shape.
5. `.gitignore` — add `api/logs/`

### What we do NOT touch

- Any endpoint file (showcase, custom-datasheet, EPREL, families, options,
  reference, dam, code-explorer, code-repair, etc.) — they keep working
  as-is. The user can adopt the helpers per-endpoint over time.
- `DatasheetRequestException` and other custom error classes — they work.
- Frontend code — backend-only foundation pass.

## Standardized error response shape

```json
{
  "error": "Human-readable message",
  "error_code": "machine_readable_slug",
  "details": { "any": "extra context" }
}
```

- `error` — string, the human message (matches today's most common shape)
- `error_code` — short machine slug. Lets the frontend branch on cause.
- `details` — optional structured context. Empty object when not relevant.

This is **additive** to today's shape. Anyone reading `response.error`
still works. Adopting endpoints simply add `error_code` + `details`.

## Logger contract

```php
logError($endpoint, $message, $context = []);
logWarn($endpoint, $message, $context = []);
logInfo($endpoint, $message, $context = []);
```

Each call writes one JSON line:

```
{"ts":"2026-04-30T11:00:00+00:00","level":"error","endpoint":"families","message":"DB error","context":{...}}
```

Storage: `api/logs/YYYY-MM-DD.log`. One file per UTC day.
Override directory via env `LOG_DIR=/path/to/logs`.

Disable globally: `LOG_ENABLED=0`.

## Risk control

| Risk | Mitigation |
|------|-----------|
| Existing endpoints break | We don't touch them |
| Log file fills up disk | Daily rotation by filename, manual cleanup |
| `respondError` collides with existing helpers | New name, no collisions |
| Logger crashes on write failure | `@file_put_contents` silent fallback |
| Feature flag breaks app | `LOG_ENABLED=0` disables fully |
| Railway ephemeral disk | Logs reset on each deploy. Acceptable for v1; pipe to stdout/CloudWatch later. |

## Verification checklist

- [ ] `respondError(400, "no_endpoint", "No endpoint specified")` returns
      proper JSON with all 3 fields, status 400
- [ ] `respondJson(["foo" => "bar"])` returns clean JSON, status 200
- [ ] `logError("test", "smoke", ["a" => 1])` writes one JSON line to
      `api/logs/YYYY-MM-DD.log`
- [ ] `LOG_ENABLED=0` bypasses logger silently
- [ ] Existing endpoints (families, options, reference) return same JSON
      shape as before — no regressions
- [ ] PHP syntax clean on all 4 changed/added files

## Adoption guidance (for future commits)

Per-endpoint adoption is opt-in. To migrate an endpoint:

```php
// Before
http_response_code(400);
echo json_encode(["error" => "Missing field"]);
exit();

// After
respondError(400, "missing_field", "Missing field", ["field" => "family"]);
```

That's it. One call per error case. Logger fires automatically.

## Out of scope (next iterations)

- Per-endpoint migration to `respondError` (gradual, on-demand)
- Sentry/Bugsnag integration (use logger.php first, decide if external
  service worth the cost)
- Request timing wrapper (next pass)
- Slow query detection
- Frontend error UI standardization based on new `error_code` field
- Log shipping to CloudWatch / Loki / similar
