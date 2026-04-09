# Nexled API

A centralized REST API for Nexled/Tecit LED product data. Built to serve multiple consumers — the product store, main website, internal tools, and the standalone configurator — from a single source of truth.

---

## Why this API exists

Before this, all product data and logic lived inside the `appdatasheets/` app. If another project (the store, the main site) needed the same product families, reference codes, or datasheets, it had to duplicate the database queries and PHP logic.

This API centralizes that. Every project talks to the same endpoints, gets the same data, and stays in sync.

### Consumers

| Project         | What it uses                                       |
|-----------------|----------------------------------------------------|
| **Store**       | Families, options, reference codes, descriptions   |
| **Main page**   | Product catalog, datasheet downloads               |
| **Internal tool** | Everything                                       |
| **Configurator** | All endpoints — the new standalone config page    |

---

## Endpoints

Base URL: `http://localhost/api_nexled/api/`

### `GET /api/?endpoint=families`

Returns all product families (e.g., LED bars, downlights, dynamic).

**Response:**
```json
[
  { "codigo": "11", "nome": "Barra T5" },
  { "codigo": "29", "nome": "Downlight" },
  ...
]
```

### `GET /api/?endpoint=options&family={code}`

Returns all selectable options for a product family. This is the data that populates the dropdowns in the configurator.

**Parameters:**
- `family` (required) — the family code (e.g., `11`, `29`, `48`)

**Response:**
```json
{
  "tamanho": ["438", "590", ...],
  "cor": [["Warm White", "WW"], ["Cool White", "CW"], ...],
  "cri": [["CRI 80", "8"], ["CRI 90", "9"]],
  "serie": [["Serie A", "A"], ...],
  "lente": [["Opaco", "1", "120°"], ...],
  "acabamento": [["Aluminio", "01", "Alu"], ...],
  "cap": [["Standard", "01", "Cap standard"], ...],
  "opcao": [["Sem cabo", "00", null], ["ASQC2 1m", "01", "asqc2 1"], ...]
}
```

Each option is either:
- A **string** (just the code) — for sizes
- An **array** `[displayName, code, description]` — for everything else

### `GET /api/?endpoint=reference&ref={reference}`

Returns the product description for a given reference code.

**Parameters:**
- `ref` (required) — the full reference code (e.g., `1104WW8A0100`)

**Response:**
```json
{
  "description": "LLED Barra T5 438mm Warm White CRI80 ..."
}
```

Returns `{"error": "Reference not found"}` if the combination doesn't exist in the Luminos database.

### `POST /api/?endpoint=datasheet`

Generates and returns a PDF datasheet.

**Body (JSON):**
```json
{
  "referencia": "1104WW8A010100",
  "descricao": "LLED Barra T5 438mm ...",
  "acrescimo": "0",
  "lente": "1",
  "acabamento": "01",
  "cap": "01",
  "opcao": "00",
  "conectorcabo": "0",
  "tipocabo": "branco",
  "tamanhocabo": "0",
  "tampa": "0",
  "vedante": "5",
  "ip": "0",
  "fonte": "0",
  "caboligacao": "0",
  "conectorligacao": "0",
  "tamanhocaboligacao": "0",
  "fixacao": "0",
  "finalidade": "0",
  "empresa": "0",
  "idioma": "pt"
}
```

**Response:** PDF file download (binary), or JSON error:
```json
{ "error": "Missing drawing for this product" }
```

---

## Authentication

Every request must include an API key, either as:
- Header: `X-API-Key: your_key_here`
- Query param: `?api_key=your_key_here`

Keys are defined in `auth.php` — one key per consumer project.

---

## Data sources

The API reads from three MySQL databases:

| Database             | Contains                                             |
|----------------------|------------------------------------------------------|
| `tecit_referencias`  | Product families, sizes, colors, CRI, series, lenses, finishes, caps, options |
| `tecit_lampadas`     | Luminos data — reference descriptions, luminotechnical specs |
| `info_nexled_2024`   | Extended product info (characteristics, drawings, diagrams) |

---

## Product types

Products are grouped into types based on their family code (see `correspondenciaProdutos.json`):

| Type        | Families       | Notes                        |
|-------------|----------------|------------------------------|
| **Barra**   | 11, 55         | LED bar strips               |
| **Barra BT** | 32            | Low-voltage LED bars         |
| **Barra Hot** | 58           | Hot environment LED bars     |
| **Downlight** | 29, 30       | Recessed downlights          |
| **Dynamic** | 48             | Dynamic/configurable lights  |

---

## Reference code structure

A product reference is built from concatenated segments:

```
[Family 2d][Size 4d][Color 2d][CRI 1d][Series 1d][Lens 1d][Finish 2d][Cap 2d][Option 2d]
```

Example: `1104WW8A010100`
- `11` → Family (Barra T5)
- `04` → Size (438mm)
- `WW` → Color (Warm White)
- `8` → CRI (80)
- `A` → Series
- `0` → Lens
- `01` → Finish (Aluminio)
- `01` → Cap (Standard)
- `00` → Option (none)
