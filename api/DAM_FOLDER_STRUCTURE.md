# NexLed DAM Folder Structure

Canonical Cloudinary folder structure for NexLed's shared DAM.

This spec is based on the current repo and live NexLed surfaces:
- API consumers: store, website, internal tools, configurator
- Live hub pages: support, store, EPREL, configurator
- Legacy asset corpus in `appdatasheets/img/`
- Cloudinary dynamic-folder mode, which means the DAM must be driven by `asset_folder`, not only by `public_id`

## DAM Model

- The DAM is the canonical source for shared NexLed assets.
- Shared assets should live once in the DAM and be consumed by multiple projects.
- Project-specific folders exist only for files unique to that surface.
- Generated-file policy is mixed by file type:
  - product datasheets stay generated on demand and are not stored in DAM by default
  - EPREL, support, and compliance outputs that need durable storage are stored as versioned releases

## Why This Structure Exists

- Shared product and brand assets must not be duplicated across support, store, website, and internal tools.
- Consumer-specific folders must be reserved for assets that only exist because of that consumer.
- Product, support, store, website, and EPREL content all need clean boundaries, but they must still work as one DAM.
- The structure must be stable enough that the API can resolve folders from metadata instead of allowing arbitrary folder paths from clients.

## Target Cloudinary Folder Tree

```text
nexled/
  00_brand/
    logos/
    guidelines/
    presentations/
    campaigns/
    ui-system/

  10_products/
    shared/
      temperatures/
      icons/
      power-supplies/
      energy-labels/
    families/
      01_t8-ac/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      05_t5-vc/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      31_barra-rgb/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      40_barra-cct/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      11_barra-t5/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      29_downlight/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      30_downlight/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      32_barra-bt/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      48_dynamic/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      49_shelfled/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      55_barra/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/
      58_barra-hot/
        <product-slug>/
          media/
            packshots/
            lifestyle/
            thumbnails/
          technical/
            drawings/
            diagrams/
            finishes/
            mounting/
            wiring/
          documents/
            manuals/
            installation/
            reports/
            warnings/
            certificates/

  20_support/
    repair-guides/
      <family-folder>/
        <product-slug>/
    page-assets/
    faq-warranty/
    contact/

  30_store/
    hero/
    categories/
    collections/
    merchandising/
    campaigns/

  40_website/
    hub/
    landing-pages/
    campaigns/

  50_eprel/
    labels/
      <product-slug>/
        <locale>/
          vYYYYMMDD-HHmmss/
    fiches/
      <product-slug>/
        <locale>/
          vYYYYMMDD-HHmmss/
    zip-packages/
      <batch-or-product-slug>/
        vYYYYMMDD-HHmmss/

  60_configurator/
    ui-assets/
    placeholders/
    imports/

  90_archive/
    legacy-imports/
    replaced-assets/
    retired-campaigns/
```

## Routing Rules

- Shared brand assets always go in `00_brand`.
- Shared product assets always go in `10_products`, never under `20_support`, `30_store`, or `40_website`.
- Support downloads for product documents should point to `10_products/.../documents/...`, not to duplicated files under `20_support`.
- `20_support` is for support-only content such as repair guides, warranty and FAQ media, contact assets, and support page-specific assets.
- `30_store` is for commerce-only assets such as category banners, store hero visuals, merchandising assets, and store-specific campaigns.
- `40_website` is for main-site and hub-specific assets.
- `50_eprel` is for generated, versioned regulatory outputs.
- `60_configurator` is for configurator-only UI assets, placeholders, and imports.
- `90_archive` is non-live storage only and must not be used as a publish source.

## Naming Conventions

- Use lower-case kebab-case for folder names.
- Preserve family codes in family folders:
  - `01_t8-ac`
  - `05_t5-vc`
  - `11_barra-t5`
  - `29_downlight`
  - `30_downlight`
  - `31_barra-rgb`
  - `40_barra-cct`
  - `32_barra-bt`
  - `48_dynamic`
  - `49_shelfled`
  - `55_barra`
  - `58_barra-hot`
- Use stable product slugs. If the product code is needed for uniqueness, use `<product-code>_<product-slug>`.
- Add locale folders only when the asset actually varies by language.
- Generated release folders use `vYYYYMMDD-HHmmss`.
- Clients must not create arbitrary folder paths. The API must resolve `asset_folder` from validated metadata.
- File names should be descriptive, stable, and scoped to the asset itself, not to the page where it is used.

## Migration Mapping From Current Assets

### Shared legacy groups

| Current location | Target DAM location |
|---|---|
| `appdatasheets/img/temperaturas` | `nexled/10_products/shared/temperatures` |
| `appdatasheets/img/icones` | `nexled/10_products/shared/icons` |
| `appdatasheets/img/fontes` | `nexled/10_products/shared/power-supplies` |
| `appdatasheets/img/classe-energetica` | `nexled/10_products/shared/energy-labels` |

### Family roots

| Current location | Target DAM location |
|---|---|
| `appdatasheets/img/11` | `nexled/10_products/families/11_barra-t5` |
| `appdatasheets/img/29` | `nexled/10_products/families/29_downlight` |
| `appdatasheets/img/30` | `nexled/10_products/families/30_downlight` |
| `appdatasheets/img/32` | `nexled/10_products/families/32_barra-bt` |
| `appdatasheets/img/48` | `nexled/10_products/families/48_dynamic` |
| `appdatasheets/img/49` | `nexled/10_products/families/49_shelfled` |
| `appdatasheets/img/55` | `nexled/10_products/families/55_barra` |
| `appdatasheets/img/58` | `nexled/10_products/families/58_barra-hot` |

### Nested legacy buckets

| Legacy bucket | Target branch | Notes |
|---|---|---|
| `produto` | `media/packshots` by default | Move to `media/lifestyle` only when the file is clearly contextual or marketing-led |
| `desenhos` | `technical/drawings` | Technical drawings and dimension drawings |
| `diagramas` | `technical/diagrams` | Functional or explanatory diagrams |
| `acabamentos` | `technical/finishes` | Finish and material variants |
| `ligacao` | `technical/wiring` | Connection and cable-related visuals |
| `fixacao` | `technical/mounting` | Mounting and fixing assets |

## Example Final Paths

Support repair guide asset:

```text
nexled/20_support/repair-guides/29_downlight/square-downlight-200/replace-driver-step-01.webp
```

Support downloadable manual or report:

```text
nexled/10_products/families/55_barra/24v-bar-model-120/documents/manuals/installation-manual_en.pdf
```

Store category or hero asset:

```text
nexled/30_store/categories/downlights-category-banner.webp
nexled/30_store/hero/store-home-spring-campaign-2026.webp
```

Website hub asset:

```text
nexled/40_website/hub/nexled-hub-support-card.webp
```

EPREL label release:

```text
nexled/50_eprel/labels/square-downlight-200/en/v20260410-HHmmss/energy-label-a.png
```

EPREL fiche release:

```text
nexled/50_eprel/fiches/square-downlight-200/en/v20260410-HHmmss/product-fiche.pdf
```

Shared product technical drawing:

```text
nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings/cutout-drawing.svg
```

## API Implications

- The current `assets.type` model in `api/endpoints/assets.php` with only `photo`, `drawing`, and `datasheet` is too coarse for the target DAM.
- Future DAM uploads must resolve `asset_folder` from richer validated metadata, not from raw client input.
- The minimum future metadata set should include:
  - `family_code`
  - `product_code` or `product_slug`
  - `asset_kind`
  - `consumer_scope` when relevant
  - `locale` when relevant
  - `version` for stored generated outputs
- `asset_kind` should be able to distinguish at least:
  - product media
  - technical drawings
  - diagrams
  - finishes
  - mounting
  - wiring
  - manuals
  - installation
  - reports
  - warnings
  - certificates
  - support repair guides
  - store assets
  - website assets
  - EPREL labels
  - EPREL fiches
  - EPREL ZIP packages
- Product datasheets are excluded from persisted DAM storage by default unless policy changes later.
- For Cloudinary dynamic folders, the API must treat `asset_folder` as the primary DAM location control and keep `public_id` independent from business taxonomy.

## Validation Checklist

- Support repair guides map to `20_support/repair-guides`.
- Support product downloads map to `10_products/.../documents/...`.
- Store merchandising and catalog assets map to `30_store`.
- Website hub and main-site assets map to `40_website`.
- EPREL labels, fiches, and ZIP outputs map to `50_eprel`.
- Configurator-only assets map to `60_configurator`.
- Shared product and brand assets are not duplicated across support, store, and website branches.
- Legacy shared groups and family folders each map to one target branch with no ambiguity.
- The structure is compatible with Cloudinary dynamic folders and can be implemented through `asset_folder`.
