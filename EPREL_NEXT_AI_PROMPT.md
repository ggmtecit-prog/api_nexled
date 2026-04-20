# Next AI Prompt: EPREL Family Import

You are working across two NexLed systems:

- Central API repo: `C:\xampp\htdocs\api_nexled`
- EPREL tool repo: separate system/client

Your mission is to keep both systems aligned for **family import by database truth**.

This is the hard rule:

- Central API owns truth
- EPREL owns import workflow

Do not blur them.

## Read First

1. [PROJECT_MEMORY.md](PROJECT_MEMORY.md)
2. [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)
3. [api/PRODUCT_ONBOARDING_MEMORY.md](api/PRODUCT_ONBOARDING_MEMORY.md)
4. [api/DAM_API_CONTRACT.md](api/DAM_API_CONTRACT.md)

Then inspect:

1. [api/index.php](api/index.php)
2. [api/endpoints/code-explorer.php](api/endpoints/code-explorer.php)
3. [api/lib/code-explorer.php](api/lib/code-explorer.php)
4. [api/endpoints/families.php](api/endpoints/families.php)

If working in EPREL too, keep that repo scoped to:

- import UI
- paging
- caching
- retry/resume

Do not rebuild NexLed validation logic there.

## What Already Exists

Central API already has:

- `families`
- `reference`
- `decode-reference`
- `code-explorer`
- datasheet readiness checks

`code-explorer` can already filter:

- `configurator_valid`
- `datasheet_ready`
- blocked reasons

But current family-wide explorer flow is not the final EPREL import contract because it still expands suffix combinations in ways that are not safe enough for whole-family import.

## What Needs To Be Built

### Central API

Build a clean endpoint such as:

- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Rules:

- start from real `Luminos` identities
- do not brute-force giant synthetic family matrices
- return only rows where:
  - `configurator_valid = true`
  - `datasheet_ready = true`
- paginate
- return empty rows safely when family has zero ready products

Optional later:

- `POST /api/?endpoint=family-ready-details`
- exact refs in, bulk detail rows out

### EPREL

Use the Central API endpoint above.

Rules:

- import page by page
- save imported rows
- allow resume/retry
- do not generate codes locally
- do not guess readiness locally

## Hard Rules

1. No fake products.
2. No synthetic giant family combinations.
3. No EPREL-side recreation of NexLed code-valid logic.
4. No using image existence as code-valid proof.
5. Keep:
   - `configurator_valid`
   - `datasheet_ready`
   clearly separate.

## Desired Response Shape

Use something like:

```json
{
  "family": {
    "code": "01",
    "name": "T8 AC"
  },
  "summary": {
    "total_ready_products": 123
  },
  "pagination": {
    "page": 1,
    "page_size": 100,
    "total_pages": 2,
    "total_rows": 123
  },
  "rows": [
    {
      "reference": "01018025111010100",
      "identity": "0101802511",
      "description": "LLED T8 26 x 228mm CW503",
      "product_type": "tubular",
      "product_id": "T8/PC/22/3s",
      "led_id": "CW503",
      "configurator_valid": true,
      "datasheet_ready": true
    }
  ]
}
```

## Current Safe Principle

If Central API cannot prove a row is both:

- real
- configurator valid
- datasheet ready

then EPREL must not import it.

## Verification

Success means:

1. one family request returns only real ready refs
2. no giant option explosion
3. pagination works
4. EPREL can import page by page without guessing
5. empty families return valid empty response, not errors

## User-Facing Summary Style

Use short, direct updates.

Say:

- what was changed
- what was verified
- what is still blocked

Keep noise low.
