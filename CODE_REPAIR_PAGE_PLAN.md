# Code Repair Page Plan

## What This Feature Is

Create one internal page that lets team inspect one Tecit code, see why it is blocked, and fix underlying data or assets needed to make it fully valid for datasheet generation.

This is **not** raw "code editor" for changing 17-character reference by hand.

Better concept:

- `Code Repair`
- `Datasheet Readiness Editor`
- `Reference Admin`

Best name for now: **Code Repair**

## Problem

Today `Code Explorer` tells user:

- code is `Configurator Valid` or not
- code is `Datasheet Ready` or blocked
- blocker reason, like:
  - `missing_header_data`
  - `missing_technical_drawing`
  - `missing_color_graph`
  - `missing_lens_diagram`
  - `missing_finish_image`
  - `unsupported_datasheet_runtime`

But page stops there.

User can see problem, but cannot fix problem from same workflow.

Example:

- code `11007502110010100` is valid product
- but datasheet blocked
- reason may be missing header image, missing lens diagram, missing drawing, missing finish image, or other source data

User needs one guided place to repair those missing pieces.

## Core Idea

Page should not edit raw code string.

Page should edit **sources behind code**.

One Tecit code pulls truth from multiple places:

- `Luminos` / product mapping
- family option tables
- local datasheet support data
- DAM asset links
- technical drawing data
- product header data

So page should work like this:

1. User selects one code
2. Page shows readiness summary
3. Page shows exact blockers
4. Each blocker becomes one repair card
5. Repair card writes to real source
6. Page revalidates code
7. User sees if code became `Datasheet Ready`

## Why This Matters

- turns explorer from passive report into repair workflow
- reduces guesswork
- gives non-developer team one practical admin tool
- makes missing datasheet assets visible and actionable
- helps migrate old local asset logic into DAM-backed workflow

## Existing Building Blocks Already In Project

### 1. Code Explorer already knows blockers

`api/lib/code-explorer.php` already computes datasheet readiness and returns failure reasons such as:

- `missing_header_data`
- `missing_technical_drawing`
- `missing_color_graph`
- `missing_lens_diagram`
- `missing_finish_image`
- `unsupported_datasheet_runtime`

So blocker detection logic already exists.

### 2. Code Explorer already has detail modal + PDF-spec preview

Current explorer UI already shows:

- code detail modal
- basic code metadata
- complex PDF-style preview data

So page already has strong read-only inspection foundation.

### 3. DAM already supports asset workflows

`api/endpoints/dam_link_model.php` already supports actions like:

- `tree`
- `list`
- `asset`
- `upload`
- `product-assets`
- `link`
- `unlink`

So project already has backend to upload assets and link them to family/product contexts.

## Recommended Product Shape

Do **not** make generic CRUD page with dozens of raw fields.

Make **guided repair page**.

### Main page flow

1. Search or open one code
2. Show top summary:
   - reference
   - description
   - family
   - product type
   - configurator status
   - datasheet status
3. Show blocker list
4. Each blocker has one focused repair panel
5. After save/upload/link, run validation again

### Example repair cards

#### Missing header data

Show:

- current header description
- current header image state
- missing fields

Actions:

- upload/link packshot image
- edit header description fields
- pick existing DAM asset

#### Missing technical drawing

Show:

- current drawing asset
- detected dimensions
- missing drawing state

Actions:

- upload SVG/PNG/PDF drawing
- link existing drawing asset
- mark family/product drawing source

#### Missing lens diagram

Show:

- lens segment from code
- whether lens requires diagram
- current linked asset

Actions:

- upload/link lens diagram

#### Missing finish image

Show:

- finish segment from code
- current finish asset

Actions:

- upload/link finish image

#### Missing color graph

Show:

- color/CCT segment
- current graph state

Actions:

- link existing graph
- upload missing graph if needed

## Page Sections

## 1. Header

- page title
- short explainer
- optional link back to `Code Explorer`

## 2. Code Search / Open

- search by full code
- optional open from query string if user came from explorer

## 3. Readiness Summary

- configurator status
- datasheet status
- top blocker
- family / product type / product ID

## 4. Source Map

Show where code currently gets truth from:

- Luminos identity
- product mapping
- header source
- drawing source
- DAM assets

This part important. User must know what they are editing.

## 5. Repair Cards

One card per blocker.

Each card should contain:

- problem summary
- current state
- repair action
- save/upload/link action

## 6. Revalidate Result

After every change:

- rerun readiness check
- refresh blocker list
- show green success if code now ready

## Source Of Truth Rules

This page must be strict about what it edits.

### Should edit

- asset links
- upload targets
- datasheet support metadata
- header metadata
- family/product source mappings where project already stores them

### Should not edit in v1

- Tecit code generator rules
- family option schema
- Luminos raw identity truth
- deep decoder logic

If code is invalid because identity does not exist in Luminos, page should show that clearly, but not pretend user can fix it from same screen unless project later adds safe mapping tools.

## V1 Scope

Good first version:

### Read

- load one code
- show readiness + blockers
- show current linked assets and source values

### Write

- upload asset to DAM
- link/unlink asset for code/family role
- edit small metadata fields needed for header/support data

### Validate

- rerun existing readiness logic after each save

## V2 Scope

Later version can add:

- batch repair for many codes in same family
- repair by blocker type
- "find existing reusable asset" suggestions
- family-level fix propagation
- audit log of who changed what

## Suggested Technical Plan

## Phase 1. Define exact repair contract

Create one read endpoint for repair context, for example:

`GET /api/?endpoint=code-repair&reference=11007502110010100`

Return:

- decoded code segments
- configurator validity
- datasheet readiness
- blocker list
- current source map
- current DAM-linked assets by role
- editable metadata fields

### Why

Frontend needs one normalized payload.
Do not make frontend stitch 5 unrelated endpoints by itself.

## Phase 2. Create repair backend actions

Add small safe write actions, for example:

- `action=save_header`
- `action=link_asset`
- `action=unlink_asset`
- `action=upload_asset`
- `action=save_support_data`
- `action=revalidate`

Where possible, reuse existing DAM endpoints instead of duplicating upload/link logic.

## Phase 3. Build new internal page

Suggested page:

- `configurator/code-repair.html`
- `configurator/code-repair.js`

Use same UI system and modal/list/card patterns already used by explorer.

## Phase 4. Connect Explorer -> Repair

From `Code Explorer`, add:

- `Repair this code` button in row or modal

That button opens repair page with selected reference.

## Phase 5. Revalidation loop

After every write:

1. save change
2. call repair context again
3. refresh status
4. show resolved or remaining blockers

## Frontend UX Plan

## Basic state

Show:

- code summary
- blocker badges
- "repair actions" area

## Loading state

Show skeletons or soft loading placeholders.

## Empty state

If code already fully valid:

- show green ready state
- still allow user to inspect linked sources

## Error state

If save/upload/link fails:

- show exact error
- keep current page state
- do not wipe form

## Permissions / Safety

This page changes production-like data.

Need guardrails:

- internal-only page
- strict input validation
- explicit save actions
- no silent auto-save
- no raw SQL editing

If possible later:

- add audit log
- show last updated by / at

## Main Risks

### 1. Source-of-truth confusion

Same visual asset may exist:

- locally
- in DAM
- at family level
- at product level

Page must show exact active source, or users will edit wrong thing.

### 2. Too much generic CRUD

If page becomes giant form, usability dies.

Need blocker-first workflow.

### 3. Partial fixes

Uploading one asset may not be enough.

Page must rerun readiness after every action.

### 4. Unsupported runtime

If family runtime itself not supported, asset upload will not solve problem.

Page must say that clearly.

## Non-Goals

For v1, this page should **not**:

- rebuild whole datasheet generator
- edit every possible product table
- replace DAM admin
- replace Code Explorer

It should sit between them:

- Explorer finds problem
- Code Repair fixes problem
- DAM stores assets

## Recommended First Deliverable

Best first milestone:

1. open one code
2. see blockers
3. upload/link missing image assets
4. rerun readiness
5. turn blocked code into ready code when assets were only missing problem

That alone would already make feature valuable.

## Short Summary

This feature should be built as **guided datasheet readiness repair page**, not raw code editor.

User goal is not "change code."
User goal is "make this code fully valid."

Best implementation:

- read one code
- show blockers
- expose repair action per blocker
- write to real source
- revalidate immediately
