/**
 * NexLed Configurator
 *
 * Loads the allowed option matrix from the API, builds the product reference,
 * and exports the datasheet request from the live UI state.
 */

const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const API_BASE_CANDIDATES = ["./api", "../api", "/api_nexled/api"];

const REF_LENGTHS = {
    size: 4,
    color: 3,
    cri: 2,
    series: 1,
    lens: 1,
    finish: 2,
    cap: 2,
    option: 5,
};

const REFERENCE_INPUT_IDS = [
    "select-size",
    "select-color",
    "select-cri",
    "select-series",
    "select-lens",
    "select-finish",
    "select-cap",
    "select-option",
    "input-extra-length",
];

const COPY_LABELS = {
    "output-reference": "reference",
    "output-description": "description",
};

const STATUS_BASE_CLASS = "text-body-xs min-h-16 text-center pt-16";
const STATUS_TONE_CLASS = {
    neutral: "text-grey-primary",
    loading: "text-blue-primary",
    success: "text-green-primary",
    error: "text-red-primary",
};
const API_BADGE_TONE_CLASS = {
    loading: "bg-blue-primary",
    success: "bg-green-primary",
    error: "bg-red-primary",
};
const NAV_GENERATE_IDS = ["nav-generate-desktop", "nav-generate-mobile"];

let descriptionRequestToken = 0;
let apiBasePromise = null;

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("select-family").addEventListener("change", handleFamilyChange);
    document.getElementById("btn-generate").addEventListener("click", generateDatasheet);

    bindShellActions();
    bindReferenceListeners();
    bindCopyButtons();
    resetConfiguratorState();
    loadFamilies();
});

async function getApiBase() {
    if (!apiBasePromise) {
        apiBasePromise = resolveApiBase();
    }

    return apiBasePromise;
}

async function resolveApiBase() {
    for (const base of API_BASE_CANDIDATES) {
        try {
            const response = await fetch(base + "/?endpoint=families", {
                headers: { "X-API-Key": API_KEY },
            });

            if (response.status !== 404) {
                return base;
            }
        } catch (error) {
            console.warn("API base probe failed for", base, error);
        }
    }

    throw new Error("Unable to resolve the NexLed API base URL.");
}

async function apiFetch(path) {
    const apiBase = await getApiBase();
    const response = await fetch(apiBase + path, {
        headers: { "X-API-Key": API_KEY },
    });

    if (!response.ok) {
        throw new Error("Request failed with status " + response.status);
    }

    return response.json();
}

async function apiPost(path, body) {
    const apiBase = await getApiBase();

    return fetch(apiBase + path, {
        method: "POST",
        headers: {
            "X-API-Key": API_KEY,
            "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
    });
}

async function loadFamilies() {
    const select = document.getElementById("select-family");

    setApiBadge("loading", "Connecting to API");
    setStatus("Loading families...", "loading");

    try {
        const data = await apiFetch("/?endpoint=families");

        select.innerHTML = '<option value="">Select a family</option>';

        data.forEach((family) => {
            const option = document.createElement("option");
            option.value = family.codigo;
            option.textContent = family.nome;
            select.appendChild(option);
        });

        setApiBadge("success", "API ready");
        setStatus("Choose a family to begin.", "neutral");
    } catch (error) {
        select.innerHTML = '<option value="">Unable to load families</option>';
        setApiBadge("error", "API unavailable");
        setStatus("Unable to load product families.", "error");
        console.error(error);
    }
}

async function handleFamilyChange() {
    const familyCode = get("select-family");

    if (!familyCode) {
        resetConfiguratorState();
        return;
    }

    setSummaryState("Step 2", "Loading valid options", "Pulling the allowed manufacturing matrix for the selected family.");
    setStatus("Loading options...", "loading");

    try {
        await loadOptions(familyCode);
    } catch (error) {
        resetConfiguratorState();
        setStatus("Unable to load options for this family.", "error");
        console.error(error);
    }
}

async function loadOptions(familyCode) {
    const data = await apiFetch("/?endpoint=options&family=" + encodeURIComponent(familyCode));

    fillSelect("select-size", data.tamanho, false);
    fillSelect("select-color", data.cor, true);
    fillSelect("select-cri", data.cri, true);
    fillSelect("select-series", data.serie, true);
    fillSelect("select-lens", data.lente, true);
    fillSelect("select-finish", data.acabamento, true);
    fillSelect("select-cap", data.cap, true);
    fillSelect("select-option", data.opcao, true);

    setHidden("options-group", false);
    setHidden("output-fields", false);

    buildReference();

    setSummaryState(
        "Step 2",
        "Reference builder active",
        "Adjust the configuration. The live output on the right updates automatically."
    );
    setStatus("Options loaded. The reference now updates automatically.", "success");
}

function fillSelect(id, items, isArray) {
    const select = document.getElementById(id);

    if (!select) {
        return;
    }

    select.innerHTML = "";

    if (!Array.isArray(items) || items.length === 0) {
        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = "No options available";
        select.appendChild(emptyOption);
        return;
    }

    items.forEach((item) => {
        const option = document.createElement("option");

        if (isArray) {
            option.value = item[1];
            option.textContent = item[0];

            if (item[2]) {
                option.title = item[2];
            }
        } else {
            option.value = item;
            option.textContent = item;
        }

        select.appendChild(option);
    });
}

function bindReferenceListeners() {
    REFERENCE_INPUT_IDS.forEach((id) => {
        const element = document.getElementById(id);

        if (!element || element.dataset.referenceBound === "true") {
            return;
        }

        const primaryEvent = element.tagName === "INPUT" ? "input" : "change";

        element.addEventListener(primaryEvent, buildReference);

        if (primaryEvent !== "change") {
            element.addEventListener("change", buildReference);
        }

        element.dataset.referenceBound = "true";
    });
}

function buildReference() {
    const family = get("select-family");

    if (!family) {
        clearOutputValues();
        syncGenerateButton();
        return;
    }

    const size = pad(get("select-size"), REF_LENGTHS.size);
    const color = pad(get("select-color"), REF_LENGTHS.color);
    const cri = pad(get("select-cri"), REF_LENGTHS.cri);
    const series = pad(get("select-series"), REF_LENGTHS.series);
    const lens = pad(get("select-lens"), REF_LENGTHS.lens);
    const finish = pad(get("select-finish"), REF_LENGTHS.finish);
    const cap = pad(get("select-cap"), REF_LENGTHS.cap);
    const option = pad(get("select-option"), REF_LENGTHS.option);

    const reference = family + size + color + cri + series + lens + finish + cap + option;

    document.getElementById("output-reference").value = reference;

    syncGenerateButton();
    syncCopyButtons();

    updateDescription(reference);
}

async function updateDescription(reference) {
    const outputField = document.getElementById("output-description");
    const requestToken = ++descriptionRequestToken;

    if (reference.length < 10) {
        outputField.value = "";
        syncCopyButtons();
        return;
    }

    try {
        const data = await apiFetch("/?endpoint=reference&ref=" + encodeURIComponent(reference));
        const familyName = getDisplayText("select-family");

        if (requestToken !== descriptionRequestToken) {
            return;
        }

        outputField.value = data.description || "";

        setSummaryState(
            "Ready",
            "Reference ready",
            familyName
                ? familyName + " is ready for datasheet generation."
                : "The current configuration is ready for datasheet generation."
        );

        syncCopyButtons();
    } catch (error) {
        if (requestToken !== descriptionRequestToken) {
            return;
        }

        outputField.value = "";
        setStatus("The reference was built, but the description could not be loaded.", "error");
        console.error(error);
    }
}

async function generateDatasheet() {
    const reference = document.getElementById("output-reference").value;
    const description = document.getElementById("output-description").value;

    if (!reference) {
        setStatus("Complete the configuration before generating the datasheet.", "error");
        return;
    }

    const body = {
        referencia: reference,
        descricao: description,
        idioma: get("select-language"),
        empresa: get("select-company"),
        lente: getDisplayText("select-lens"),
        acabamento: get("select-finish"),
        opcao: get("select-option"),
        conectorcabo: get("select-connector-cable"),
        tipocabo: get("select-cable-type"),
        tampa: get("select-end-cap"),
        vedante: get("select-gasket"),
        acrescimo: get("input-extra-length") || "0",
        ip: get("select-ip"),
        fixacao: get("select-fixing"),
        fonte: get("select-power-supply"),
        caboligacao: get("select-connection-cable"),
        conectorligacao: get("select-connection-connector"),
        tamanhocaboligacao: get("input-connection-cable-length") || "0",
        finalidade: get("select-purpose"),
    };

    setGenerateControlsDisabled(true);
    setStatus("Generating datasheet...", "loading");

    try {
        const response = await apiPost("/?endpoint=datasheet", body);

        setGenerateControlsDisabled(false);
        syncGenerateButton();

        if (!response.ok) {
            const contentType = response.headers.get("content-type") || "";
            let message = "Unknown error";

            if (contentType.includes("application/json")) {
                const error = await response.json();
                message = error.error || message;
            }

            setStatus("Datasheet generation failed: " + message, "error");
            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = reference + ".pdf";
        link.click();

        URL.revokeObjectURL(url);
        setStatus("Datasheet ready. The PDF download has started.", "success");
    } catch (error) {
        setGenerateControlsDisabled(false);
        syncGenerateButton();
        setStatus("Datasheet generation failed.", "error");
        console.error(error);
    }
}

function bindCopyButtons() {
    document.querySelectorAll("[data-copy-target]").forEach((button) => {
        button.addEventListener("click", () => copyField(button));
    });

    syncCopyButtons();
}

function bindShellActions() {
    const focusButton = document.getElementById("focus-family");
    const resetTabButton = document.getElementById("reset-configurator");
    const resetPanelButton = document.getElementById("reset-configurator-panel");

    focusButton?.addEventListener("click", focusFamilyField);
    resetTabButton?.addEventListener("click", resetAllSelections);
    resetPanelButton?.addEventListener("click", resetAllSelections);

    NAV_GENERATE_IDS.forEach((id) => {
        const button = document.getElementById(id);

        button?.addEventListener("click", () => {
            if (!button.disabled) {
                generateDatasheet();
            }
        });
    });
}

async function copyField(button) {
    const targetId = button.getAttribute("data-copy-target");
    const field = targetId ? document.getElementById(targetId) : null;
    const label = targetId ? (COPY_LABELS[targetId] || "value") : "value";

    if (!field || !field.value) {
        setStatus("Nothing to copy yet.", "error");
        return;
    }

    try {
        await navigator.clipboard.writeText(field.value);
        setStatus("Copied " + label + ".", "success");
    } catch (error) {
        setStatus("Clipboard copy failed. Please copy manually.", "error");
        console.error(error);
    }
}

function resetConfiguratorState() {
    descriptionRequestToken += 1;
    setHidden("options-group", true);
    setHidden("output-fields", true);
    clearOutputValues();
    syncGenerateButton();
    syncCopyButtons();
    setSummaryState("Step 1", "Start with a family", "Select a family to unlock the valid option set.");
    setStatus("Choose a family to begin.", "neutral");
}

function clearOutputValues() {
    document.getElementById("output-reference").value = "";
    document.getElementById("output-description").value = "";
}

function syncGenerateButton() {
    const button = document.getElementById("btn-generate");
    const hasReference = document.getElementById("output-reference").value.length > 0;

    button.disabled = !hasReference;

    NAV_GENERATE_IDS.forEach((id) => {
        const navButton = document.getElementById(id);

        if (!navButton) {
            return;
        }

        navButton.disabled = !hasReference;
        navButton.setAttribute("aria-disabled", String(!hasReference));
    });
}

function setGenerateControlsDisabled(isDisabled) {
    const primaryButton = document.getElementById("btn-generate");

    if (primaryButton) {
        primaryButton.disabled = isDisabled;
    }

    NAV_GENERATE_IDS.forEach((id) => {
        const navButton = document.getElementById(id);

        if (!navButton) {
            return;
        }

        navButton.disabled = isDisabled;
        navButton.setAttribute("aria-disabled", String(isDisabled));
    });
}

function syncCopyButtons() {
    document.querySelectorAll("[data-copy-target]").forEach((button) => {
        const targetId = button.getAttribute("data-copy-target");
        const field = targetId ? document.getElementById(targetId) : null;
        button.disabled = !field || !field.value;
    });
}

function setSummaryState(step, title, subtitle) {
    document.getElementById("config-step").textContent = step;
    document.getElementById("output-title").textContent = title;
    document.getElementById("output-subtitle").textContent = subtitle;
}

function setHidden(id, shouldHide) {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    element.classList.toggle("hidden", shouldHide);
}

function setStatus(message, tone = "neutral") {
    const element = document.getElementById("status-message");
    const toneClass = STATUS_TONE_CLASS[tone] || STATUS_TONE_CLASS.neutral;

    element.textContent = message;
    element.className = STATUS_BASE_CLASS + " " + toneClass;
}

function get(id) {
    const element = document.getElementById(id);
    return element ? element.value : "";
}

function getDisplayText(id) {
    const element = document.getElementById(id);

    if (!element) {
        return "";
    }

    return element.options[element.selectedIndex]?.text || "";
}

function pad(value, length) {
    let output = String(value || "0");

    while (output.length < length) {
        output = "0" + output;
    }

    return output;
}

function focusFamilyField() {
    const field = document.getElementById("select-family");

    if (!field) {
        return;
    }

    field.scrollIntoView({ behavior: "smooth", block: "center" });
    field.focus({ preventScroll: true });
}

function resetAllSelections() {
    const familySelect = document.getElementById("select-family");

    if (!familySelect) {
        return;
    }

    familySelect.value = "";

    document.querySelectorAll("#options-group select").forEach((element) => {
        if (element.options.length > 0) {
            element.selectedIndex = 0;
        }
    });

    document.querySelectorAll('#options-group input[type="number"]').forEach((element) => {
        element.value = "0";
    });

    resetConfiguratorState();
    focusFamilyField();
}

function setApiBadge(tone, text) {
    const dotClass = API_BADGE_TONE_CLASS[tone] || API_BADGE_TONE_CLASS.error;

    document.querySelectorAll("[data-api-badge-dot]").forEach((dot) => {
        dot.classList.remove(
            API_BADGE_TONE_CLASS.loading,
            API_BADGE_TONE_CLASS.success,
            API_BADGE_TONE_CLASS.error
        );
        dot.classList.add(dotClass);
    });

    document.querySelectorAll("[data-api-badge-text]").forEach((label) => {
        label.textContent = text;
    });
}
