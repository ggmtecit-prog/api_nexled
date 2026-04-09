/**
 * Nexled Configurator — Script
 *
 * All communication with the API happens here.
 * No database connections. No PHP logic.
 * Just: ask the API → get data → update the page.
 */

const API_BASE = "/api_nexled/api";
const API_KEY  = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

// Reference code segment lengths (to pad codes with leading zeros)
const REF_LENGTHS = {
    size:   4,
    color:  3,
    cri:    2,
    series: 1,
    lens:   1,
    finish: 2,
    cap:    2,
    option: 5,
};



// ---------------------------------------------------------------------------
// API HELPERS
// ---------------------------------------------------------------------------

async function apiFetch(path) {
    const response = await fetch(API_BASE + path, {
        headers: { "X-API-Key": API_KEY }
    });
    return response.json();
}

async function apiPost(path, body) {
    const response = await fetch(API_BASE + path, {
        method:  "POST",
        headers: {
            "X-API-Key":    API_KEY,
            "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
    });
    return response;
}



// ---------------------------------------------------------------------------
// REFERENCE BUILDER
// ---------------------------------------------------------------------------

/**
 * Reads all selected values and builds the reference code string.
 * Also updates the reference and description display fields.
 */
function buildReference() {
    const family = get("select-family");
    if (!family) return;

    const size   = pad(get("select-size"),   REF_LENGTHS.size);
    const color  = pad(get("select-color"),  REF_LENGTHS.color);
    const cri    = pad(get("select-cri"),    REF_LENGTHS.cri);
    const series = pad(get("select-series"), REF_LENGTHS.series);
    const lens   = pad(get("select-lens"),   REF_LENGTHS.lens);
    const finish = pad(get("select-finish"), REF_LENGTHS.finish);
    const cap    = pad(get("select-cap"),    REF_LENGTHS.cap);
    const option = pad(get("select-option"), REF_LENGTHS.option);

    const reference = family + size + color + cri + series + lens + finish + cap + option;

    document.getElementById("output-reference").value = reference;

    // Fetch the description for this reference
    updateDescription(reference);
}

/**
 * Fetches the product description for a reference code and updates the field.
 */
async function updateDescription(reference) {
    if (reference.length < 10) return;

    const data = await apiFetch(`/?endpoint=reference&ref=${reference}`);
    const field = document.getElementById("output-description");

    if (data.description) {
        field.value = data.description;
    } else {
        field.value = "";
    }
}



// ---------------------------------------------------------------------------
// LOAD FAMILIES
// ---------------------------------------------------------------------------

async function loadFamilies() {
    const select = document.getElementById("select-family");
    const data   = await apiFetch("/?endpoint=families");

    select.innerHTML = '<option value="">Select a family</option>';

    if (data.error) {
        select.innerHTML = '<option value="">Error loading families</option>';
        return;
    }

    data.forEach(family => {
        const option = document.createElement("option");
        option.value       = family.codigo;
        option.textContent = family.nome;
        select.appendChild(option);
    });

    select.addEventListener("change", () => {
        if (select.value) loadOptions(select.value);
    });
}



// ---------------------------------------------------------------------------
// LOAD OPTIONS
// ---------------------------------------------------------------------------

async function loadOptions(familyCode) {
    setStatus("Loading options...");

    const data = await apiFetch(`/?endpoint=options&family=${familyCode}`);

    if (data.error) {
        setStatus("Error loading options.", true);
        return;
    }

    // Fill each dropdown
    fillSelect("select-size",   data.tamanho,   false);  // simple strings
    fillSelect("select-color",  data.cor,        true);   // [name, code]
    fillSelect("select-cri",    data.cri,        true);
    fillSelect("select-series", data.serie,      true);
    fillSelect("select-lens",   data.lente,      true);
    fillSelect("select-finish", data.acabamento, true);
    fillSelect("select-cap",    data.cap,        true);
    fillSelect("select-option", data.opcao,      true);

    // Show options section and output section
    show("step-options");
    show("step-output");

    // Update reference whenever any dropdown changes
    const watchedIds = [
        "select-size", "select-color", "select-cri", "select-series",
        "select-lens", "select-finish", "select-cap", "select-option",
        "input-extra-length",
    ];
    watchedIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener("change", buildReference);
    });

    buildReference();
    setStatus("");
}

/**
 * Fills a <select> element with options from the API.
 *
 * @param {string}  id       Element ID
 * @param {Array}   items    Array of strings or [name, code, desc] arrays
 * @param {boolean} isArray  True when items are arrays (have a code + name)
 */
function fillSelect(id, items, isArray) {
    const select = document.getElementById(id);
    if (!select || !items) return;

    select.innerHTML = "";

    items.forEach(item => {
        const option = document.createElement("option");

        if (isArray) {
            option.value       = item[1]; // code
            option.textContent = item[0]; // display name
            if (item[2]) option.title = item[2]; // description as tooltip
        } else {
            option.value       = item;
            option.textContent = item;
        }

        select.appendChild(option);
    });
}



// ---------------------------------------------------------------------------
// GENERATE DATASHEET
// ---------------------------------------------------------------------------

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("btn-generate").addEventListener("click", generateDatasheet);
});

async function generateDatasheet() {
    const btn       = document.getElementById("btn-generate");
    const reference = document.getElementById("output-reference").value;
    const description = document.getElementById("output-description").value;

    if (!reference) {
        setStatus("Please select all options first.", true);
        return;
    }

    btn.disabled = true;
    setStatus("Generating PDF...");

    const body = {
        referencia:            reference,
        descricao:             description,
        idioma:                get("select-language"),
        empresa:               get("select-company"),
        lente:                 getDisplayText("select-lens"),
        acabamento:            get("select-finish"),
        opcao:                 get("select-option"),
        conectorcabo:          get("select-connector-cable"),
        tipocabo:              get("select-cable-type"),
        tampa:                 get("select-end-cap"),
        vedante:               get("select-gasket"),
        acrescimo:             get("input-extra-length") || "0",
        ip:                    get("select-ip"),
        fixacao:               get("select-fixing"),
        fonte:                 get("select-power-supply"),
        caboligacao:           get("select-connection-cable"),
        conectorligacao:       get("select-connection-connector"),
        tamanhocaboligacao:    get("input-connection-cable-length") || "0",
        finalidade:            get("select-purpose"),
    };

    const response = await apiPost("/?endpoint=datasheet", body);

    btn.disabled = false;

    if (response.ok) {
        // PDF received — trigger a download
        const blob = await response.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement("a");
        a.href     = url;
        a.download = reference + ".pdf";
        a.click();
        URL.revokeObjectURL(url);
        setStatus("Done.");
    } else {
        const error = await response.json();
        setStatus("Error: " + (error.error || "Unknown error"), true);
    }
}



// ---------------------------------------------------------------------------
// UTILITIES
// ---------------------------------------------------------------------------

/** Gets the value of a form element by ID. */
function get(id) {
    const el = document.getElementById(id);
    return el ? el.value : "";
}

/** Gets the display text (not value) of a select element. */
function getDisplayText(id) {
    const el = document.getElementById(id);
    if (!el) return "";
    return el.options[el.selectedIndex]?.text || "";
}

/** Pads a value with leading zeros to reach the target length. */
function pad(value, length) {
    let s = String(value || "0");
    while (s.length < length) s = "0" + s;
    return s;
}

/** Shows an element by removing the 'hidden' class. */
function show(id) {
    document.getElementById(id)?.classList.remove("hidden");
}

/** Updates the status message. */
function setStatus(message, isError = false) {
    const el = document.getElementById("status-message");
    el.textContent = message;
    el.className   = isError ? "error" : "";
}



// ---------------------------------------------------------------------------
// INIT
// ---------------------------------------------------------------------------

loadFamilies();
