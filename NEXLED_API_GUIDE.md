# Nexled API — Integration Guide for AI Assistants

## Context

The Nexled product API has been migrated to alwaysdata. All projects that previously called `tecit.pt` directly must now call the API below.

---

## API Base URL

```
https://nexled.alwaysdata.net/api/
```

## Authentication

Every request requires this header:

```
X-API-Key: 7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b
```

---

## Available Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `?endpoint=families` | GET | List all product families |
| `?endpoint=options` | GET | Product options for a family |
| `?endpoint=reference` | GET | Lookup a product reference |
| `?endpoint=decode-reference` | GET | Decode a full reference code |
| `?endpoint=datasheet` | GET | Generate a product PDF datasheet |
| `?endpoint=dam` | GET | Digital asset management (images) |
| `?endpoint=code-explorer` | GET | Explore valid product codes |
| `?endpoint=code-repair` | GET | Suggest fixes for invalid codes |
| `?endpoint=eprel-code-mappings` | GET | EPREL energy label code mappings |
| `?endpoint=health` | GET | API health check (no auth needed) |

---

## How to Call the API (JavaScript)

```js
const API_BASE = "https://nexled.alwaysdata.net/api/";
const API_KEY  = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

async function apiGet(params) {
    const url = API_BASE + "?" + new URLSearchParams(params);
    const res  = await fetch(url, {
        headers: { "X-API-Key": API_KEY }
    });
    if (!res.ok) throw new Error(`API error ${res.status}`);
    return res.json();
}

// Examples:
const families = await apiGet({ endpoint: "families" });
const options  = await apiGet({ endpoint: "options", family: "01" });
const ref      = await apiGet({ endpoint: "reference", ref: "11037581110010100" });
```

---

## What to Change in Existing Projects

If the project has any of these — replace them:

| Old (remove this) | New (replace with) |
|---|---|
| Any URL containing `tecit.pt` | `https://nexled.alwaysdata.net/api/` |
| Direct MySQL connections to tecit.pt | Use the API instead |
| Hardcoded database queries for product data | Use the API instead |
| Any reference to Railway or old API URLs | `https://nexled.alwaysdata.net/api/` |

---

## Task for the AI

1. Find every place in this project that fetches product data (API calls, database calls, hardcoded URLs).
2. Replace them with calls to `https://nexled.alwaysdata.net/api/` using the `X-API-Key` header above.
3. Use the `apiGet()` helper pattern shown above (or adapt it to the project's existing fetch style).
4. Do not change anything unrelated to API calls.
5. After changes, verify by calling `?endpoint=health` and checking that `"ok":true` is returned.
