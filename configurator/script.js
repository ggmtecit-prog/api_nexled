/**
 * NexLed Configurator
 *
 * Loads the allowed option matrix from the API, builds the product reference,
 * and exports the datasheet request from the live UI state.
 */

const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const API_BASE_CANDIDATES = ["./api", "../api", "/api_nexled/api"];
const API_LOCALHOST_CANDIDATES = [
    "http://localhost/api_nexled/api",
    "http://127.0.0.1/api_nexled/api",
];
const LOCAL_HOSTNAMES = new Set(["localhost", "127.0.0.1"]);

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
const APP_LANGUAGE_EVENT = "nexled:app-language-change";

let descriptionRequestToken = 0;
let apiBasePromise = null;
let familyCombobox = null;
let selectDropdowns = new Map();
let activeSelectDropdown = null;
let selectDropdownEventsBound = false;
let syncConfiguredLanguageSelect = null;

document.addEventListener("DOMContentLoaded", () => {
    familyCombobox = setupFamilyCombobox();
    setupSelectDropdowns();
    bindDocumentLanguageControls();
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
    for (const base of getApiBaseCandidates()) {
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

function getApiBaseCandidates() {
    if (window.location.protocol === "file:") {
        return API_LOCALHOST_CANDIDATES.concat(API_BASE_CANDIDATES);
    }

    return API_BASE_CANDIDATES;
}

function getApiFailureMessage() {
    if (window.location.protocol === "file:") {
        return "Start Apache/XAMPP. This page needs the PHP API at http://localhost/api_nexled/api.";
    }

    if (LOCAL_HOSTNAMES.has(window.location.hostname)) {
        return "Start Apache/XAMPP. The local NexLed API is not responding.";
    }

    return "The NexLed API is not reachable from this page.";
}

function getFamilyPlaceholderMessage() {
    if (window.location.protocol === "file:") {
        return "Start localhost API to load families";
    }

    if (LOCAL_HOSTNAMES.has(window.location.hostname)) {
        return "Start Apache to load families";
    }

    return "Unable to load families";
}

function setupFamilyCombobox() {
    const combobox = document.getElementById("family-combobox");
    const input = document.getElementById("family-combobox-input");
    const clearButton = document.getElementById("family-combobox-clear");
    const panel = document.getElementById("family-combobox-panel");
    const list = document.getElementById("family-combobox-list");
    const emptyState = document.getElementById("family-combobox-empty");
    const valueField = document.getElementById("select-family");

    if (!combobox || !input || !clearButton || !panel || !list || !emptyState || !valueField) {
        return null;
    }

    let activeOption = null;

    input.setAttribute("role", "combobox");
    input.setAttribute("aria-controls", list.id);
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-autocomplete", "list");
    list.setAttribute("role", "listbox");

    const getOptions = () => Array.from(list.querySelectorAll("[data-family-option]"));

    const getVisibleOptions = () => getOptions().filter((option) => !option.hidden);

    const getSelectedOption = () => {
        const currentValue = valueField.value;
        return getOptions().find((option) => option.dataset.value === currentValue) || null;
    };

    const getSelectedLabel = () => {
        const option = getSelectedOption();
        return option ? option.dataset.label : "";
    };

    const setActiveOption = (option) => {
        activeOption = option;

        getOptions().forEach((currentOption) => {
            currentOption.classList.toggle("is-active", currentOption === option && !currentOption.hidden);
        });

        if (option && !option.hidden) {
            input.setAttribute("aria-activedescendant", option.id);
            option.scrollIntoView({ block: "nearest" });
            return;
        }

        input.removeAttribute("aria-activedescendant");
    };

    const updateClearState = () => {
        const shouldShowClear = !input.disabled && input.value.trim() !== "";
        clearButton.hidden = !shouldShowClear;
        clearButton.disabled = !shouldShowClear;
        clearButton.setAttribute("aria-hidden", shouldShowClear ? "false" : "true");
    };

    const syncSelectedState = () => {
        const currentValue = valueField.value;

        getOptions().forEach((option) => {
            option.setAttribute("aria-selected", option.dataset.value === currentValue ? "true" : "false");
        });

        combobox.classList.toggle("has-value", Boolean(currentValue));
        updateClearState();
    };

    const updateFilter = (query) => {
        const normalizedQuery = query.trim().toLowerCase();

        getOptions().forEach((option) => {
            const matches = normalizedQuery === "" || option.dataset.label.toLowerCase().includes(normalizedQuery);
            option.hidden = !matches;
        });

        const visibleOptions = getVisibleOptions();
        emptyState.hidden = visibleOptions.length !== 0;
        setActiveOption(visibleOptions[0] || null);
    };

    const closePanel = (restoreSelection = false) => {
        combobox.classList.remove("is-open");
        panel.hidden = true;
        panel.setAttribute("aria-hidden", "true");
        input.setAttribute("aria-expanded", "false");
        setActiveOption(null);

        if (restoreSelection) {
            input.value = getSelectedLabel();
        }

        updateFilter("");
        updateClearState();
    };

    const openPanel = () => {
        if (input.disabled) {
            return;
        }

        combobox.classList.add("is-open");
        panel.hidden = false;
        panel.setAttribute("aria-hidden", "false");
        input.setAttribute("aria-expanded", "true");
        updateFilter(valueField.value && input.value.trim() === getSelectedLabel() ? "" : input.value.trim());
    };

    const dispatchFamilyChange = () => {
        valueField.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const setSelection = (value, label, shouldTrigger = true) => {
        const previousValue = valueField.value;

        valueField.value = value;
        input.value = label;
        syncSelectedState();
        closePanel(false);

        if (shouldTrigger && previousValue !== value) {
            dispatchFamilyChange();
        }
    };

    const clearSelection = (shouldTrigger = true, clearInput = true) => {
        const previousValue = valueField.value;

        valueField.value = "";

        if (clearInput) {
            input.value = "";
        }

        syncSelectedState();
        updateFilter(input.value.trim());

        if (shouldTrigger && previousValue !== "") {
            dispatchFamilyChange();
        }
    };

    const renderOptions = (items) => {
        list.innerHTML = "";

        items.forEach((item, index) => {
            const option = document.createElement("button");
            const label = document.createElement("span");
            const check = document.createElement("i");

            option.type = "button";
            option.className = "combobox-option";
            option.id = "family-combobox-option-" + (index + 1);
            option.dataset.familyOption = "true";
            option.dataset.value = item.value;
            option.dataset.label = item.label;
            option.setAttribute("role", "option");
            option.setAttribute("aria-selected", "false");

            label.className = "combobox-option-label";
            label.textContent = item.label;

            check.className = "ri-check-line combobox-option-check";
            check.setAttribute("aria-hidden", "true");

            option.append(label, check);
            option.addEventListener("click", () => {
                setSelection(item.value, item.label, true);
                input.focus();
            });

            list.append(option);
        });

        syncSelectedState();
        updateFilter(input.value.trim());
    };

    const setDisabled = (isDisabled) => {
        input.disabled = isDisabled;
        input.setAttribute("aria-disabled", String(isDisabled));
        combobox.setAttribute("aria-disabled", String(isDisabled));

        if (isDisabled) {
            closePanel(true);
        }
    };

    input.addEventListener("focus", openPanel);
    input.addEventListener("click", openPanel);

    input.addEventListener("input", () => {
        if (valueField.value && input.value.trim() !== getSelectedLabel()) {
            clearSelection(true, false);
        }

        openPanel();
        updateFilter(input.value.trim());
        updateClearState();
    });

    input.addEventListener("keydown", (event) => {
        const visibleOptions = getVisibleOptions();

        if (event.key === "ArrowDown") {
            event.preventDefault();
            openPanel();

            if (visibleOptions.length === 0) {
                return;
            }

            const currentIndex = visibleOptions.indexOf(activeOption);
            const nextIndex = currentIndex === -1 ? 0 : (currentIndex + 1) % visibleOptions.length;
            setActiveOption(visibleOptions[nextIndex]);
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            openPanel();

            if (visibleOptions.length === 0) {
                return;
            }

            const currentIndex = visibleOptions.indexOf(activeOption);
            const nextIndex = currentIndex === -1 ? visibleOptions.length - 1 : (currentIndex - 1 + visibleOptions.length) % visibleOptions.length;
            setActiveOption(visibleOptions[nextIndex]);
        }

        if (event.key === "Enter") {
            if (!combobox.classList.contains("is-open") || !activeOption) {
                return;
            }

            event.preventDefault();
            setSelection(activeOption.dataset.value, activeOption.dataset.label, true);
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closePanel(true);
        }

        if (event.key === "Tab") {
            closePanel(true);
        }
    });

    clearButton.addEventListener("click", (event) => {
        event.preventDefault();
        clearSelection(true, true);
        input.focus();
    });

    document.addEventListener("click", (event) => {
        if (combobox.contains(event.target)) {
            return;
        }

        closePanel(true);
    });

    syncSelectedState();
    updateFilter("");

    return {
        clearSelection,
        getSelectedLabel,
        renderOptions,
        setDisabled,
        setPlaceholder(text) {
            input.placeholder = text;
        },
    };
}

function setupSelectDropdowns() {
    const selects = Array.from(document.querySelectorAll("select[id]")).filter((select) => select.id !== "select-family");

    selects.forEach((select) => {
        if (selectDropdowns.has(select.id)) {
            return;
        }

        const dropdown = createSelectDropdown(select);

        if (dropdown) {
            selectDropdowns.set(select.id, dropdown);
        }
    });

    bindSelectDropdownDocumentEvents();
}

function createSelectDropdown(select) {
    const label = document.querySelector('label[for="' + select.id + '"]');
    const labelText = label ? label.textContent.replace(/\*/g, "").trim() : "Options";
    const root = document.createElement("div");
    const trigger = document.createElement("button");
    const valueDisplay = document.createElement("span");
    const arrow = document.createElement("i");
    const menu = document.createElement("ul");
    let itemElements = [];

    if (label && !label.id) {
        label.id = select.id + "-label";
    }

    root.className = "dropdown dropdown-md w-full";
    root.dataset.selectDropdown = select.id;

    valueDisplay.className = "dropdown-value";
    valueDisplay.id = select.id + "-dropdown-value";

    trigger.type = "button";
    trigger.className = "dropdown-trigger";
    trigger.id = select.id + "-dropdown-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");

    if (label?.id) {
        trigger.setAttribute("aria-labelledby", label.id + " " + valueDisplay.id);
    } else {
        trigger.setAttribute("aria-label", labelText);
    }

    arrow.className = "ri-arrow-down-s-line dropdown-arrow";
    arrow.setAttribute("aria-hidden", "true");

    trigger.append(valueDisplay, arrow);

    menu.className = "dropdown-menu custom-scrollbar";
    menu.setAttribute("role", "listbox");
    menu.setAttribute("aria-label", labelText + " options");

    root.append(trigger, menu);
    select.insertAdjacentElement("afterend", root);

    select.hidden = true;
    select.setAttribute("aria-hidden", "true");
    select.tabIndex = -1;

    function getEnabledItems() {
        return itemElements.filter((item) => item.getAttribute("aria-disabled") !== "true");
    }

    function getSelectedItem() {
        return itemElements.find((item) => item.getAttribute("aria-selected") === "true") || null;
    }

    function updateValueDisplay(text, hasValue = true) {
        valueDisplay.textContent = text;
        root.classList.toggle("has-value", hasValue);
    }

    function closeDropdown({ restoreFocus = false } = {}) {
        root.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");

        if (activeSelectDropdown === api) {
            activeSelectDropdown = null;
        }

        if (restoreFocus) {
            trigger.focus();
        }
    }

    function openDropdown() {
        if (trigger.disabled) {
            return;
        }

        if (activeSelectDropdown && activeSelectDropdown !== api) {
            activeSelectDropdown.close();
        }

        root.classList.add("is-open");
        trigger.setAttribute("aria-expanded", "true");
        activeSelectDropdown = api;
    }

    function syncFromSelect() {
        const selectedOption = select.options[select.selectedIndex] || null;
        const hasSelection = Boolean(selectedOption && !selectedOption.disabled && selectedOption.value !== "");

        itemElements.forEach((item) => {
            item.setAttribute(
                "aria-selected",
                String(selectedOption && item.dataset.value === selectedOption.value)
            );
        });

        if (selectedOption) {
            updateValueDisplay(selectedOption.textContent || "", hasSelection);
        } else {
            updateValueDisplay("No options available", false);
        }

        const isDisabled = select.disabled || getEnabledItems().length === 0;
        trigger.disabled = isDisabled;
        trigger.setAttribute("aria-disabled", String(isDisabled));
        root.setAttribute("aria-disabled", String(isDisabled));

        if (isDisabled) {
            closeDropdown();
        }
    }

    function selectItem(item, shouldDispatch = true) {
        const nextValue = item.dataset.value || "";
        const previousValue = select.value;

        select.value = nextValue;
        syncFromSelect();
        closeDropdown({ restoreFocus: true });

        if (shouldDispatch && previousValue !== nextValue) {
            select.dispatchEvent(new Event("change", { bubbles: true }));
        }
    }

    function focusItem(item) {
        if (!item || item.getAttribute("aria-disabled") === "true") {
            return;
        }

        item.focus();
    }

    function bindItem(item) {
        item.addEventListener("focus", () => {
            item.scrollIntoView({ block: "nearest" });
        });

        item.addEventListener("click", () => {
            if (item.getAttribute("aria-disabled") === "true") {
                return;
            }

            selectItem(item, true);
        });

        item.addEventListener("keydown", (event) => {
            const enabledItems = getEnabledItems();
            const currentIndex = enabledItems.indexOf(item);

            if (enabledItems.length === 0) {
                return;
            }

            if (event.key === "ArrowDown") {
                event.preventDefault();
                focusItem(enabledItems[(currentIndex + 1) % enabledItems.length] || null);
            }

            if (event.key === "ArrowUp") {
                event.preventDefault();
                focusItem(enabledItems[(currentIndex - 1 + enabledItems.length) % enabledItems.length] || null);
            }

            if (event.key === "Home") {
                event.preventDefault();
                focusItem(enabledItems[0] || null);
            }

            if (event.key === "End") {
                event.preventDefault();
                focusItem(enabledItems[enabledItems.length - 1] || null);
            }

            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                item.click();
            }

            if (event.key === "Escape") {
                event.preventDefault();
                closeDropdown({ restoreFocus: true });
            }

            if (event.key === "Tab") {
                closeDropdown();
            }
        });
    }

    function refreshOptions() {
        menu.innerHTML = "";
        itemElements = [];

        Array.from(select.options).forEach((option) => {
            const item = document.createElement("li");
            const text = document.createElement("span");
            const check = document.createElement("i");

            item.className = "dropdown-item";
            item.setAttribute("role", "option");
            item.setAttribute("tabindex", "-1");
            item.dataset.value = option.value;
            item.setAttribute("aria-selected", String(option.selected));

            if (option.disabled) {
                item.setAttribute("aria-disabled", "true");
            }

            if (option.title) {
                item.title = option.title;
            }

            text.textContent = option.textContent || "";

            check.className = "ri-check-line dropdown-item-check";
            check.setAttribute("aria-hidden", "true");

            item.append(text, check);
            bindItem(item);
            menu.append(item);
            itemElements.push(item);
        });

        syncFromSelect();
    }

    trigger.addEventListener("click", () => {
        if (trigger.disabled) {
            return;
        }

        if (root.classList.contains("is-open")) {
            closeDropdown();
            return;
        }

        openDropdown();
    });

    trigger.addEventListener("keydown", (event) => {
        const enabledItems = getEnabledItems();

        if (enabledItems.length === 0 || trigger.disabled) {
            return;
        }

        if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            openDropdown();
            focusItem(getSelectedItem() || enabledItems[0]);
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            openDropdown();
            focusItem(getSelectedItem() || enabledItems[enabledItems.length - 1]);
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closeDropdown();
        }
    });

    select.addEventListener("change", syncFromSelect);

    const api = {
        close: () => closeDropdown(),
        refreshOptions,
        root,
        syncFromSelect,
    };

    refreshOptions();

    return api;
}

function bindSelectDropdownDocumentEvents() {
    if (selectDropdownEventsBound) {
        return;
    }

    selectDropdownEventsBound = true;

    document.addEventListener("click", (event) => {
        if (!activeSelectDropdown) {
            return;
        }

        if (activeSelectDropdown.root.contains(event.target)) {
            return;
        }

        activeSelectDropdown.close();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape" || !activeSelectDropdown) {
            return;
        }

        activeSelectDropdown.close();
    });
}

function normalizeAppLanguage(value) {
    const language = String(value || "").trim().toLowerCase();

    if (language === "pt" || language === "es") {
        return language;
    }

    return "en";
}

function getCurrentAppLanguage() {
    return normalizeAppLanguage(window.NexLedAppShell?.getLanguage?.() || "en");
}

function bindDocumentLanguageControls() {
    const languageSelect = document.getElementById("select-language");

    if (!languageSelect) {
        return;
    }

    syncConfiguredLanguageSelect = (language, shouldDispatch = false) => {
        const normalizedLanguage = normalizeAppLanguage(language);
        const nextOption = Array.from(languageSelect.options).find((option) => {
            return normalizeAppLanguage(option.value) === normalizedLanguage;
        });

        if (!nextOption) {
            return;
        }

        const previousValue = languageSelect.value;

        languageSelect.value = nextOption.value;
        selectDropdowns.get("select-language")?.syncFromSelect();

        if (shouldDispatch && previousValue !== nextOption.value) {
            languageSelect.dispatchEvent(new Event("change", { bubbles: true }));
        }
    };

    syncConfiguredLanguageSelect(getCurrentAppLanguage(), false);

    languageSelect.addEventListener("change", () => {
        selectDropdowns.get("select-language")?.syncFromSelect();

        const nextLanguage = normalizeAppLanguage(languageSelect.value);

        if (window.NexLedAppShell?.getLanguage?.() !== nextLanguage) {
            window.NexLedAppShell?.setLanguage?.(nextLanguage);
        }
    });

    window.addEventListener(APP_LANGUAGE_EVENT, (event) => {
        syncConfiguredLanguageSelect?.(event.detail?.language, false);
    });
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
    setApiBadge("loading", "Connecting to API");
    setStatus("Loading families...", "loading");
    familyCombobox?.setDisabled(true);
    familyCombobox?.setPlaceholder("Loading families...");

    try {
        const data = await apiFetch("/?endpoint=families");
        familyCombobox?.renderOptions(
            data.map((family) => ({
                value: family.codigo,
                label: family.nome,
            }))
        );
        familyCombobox?.setDisabled(false);
        familyCombobox?.setPlaceholder("Select a family");

        setApiBadge("success", "API ready");
        setStatus("Choose a family to begin.", "neutral");
    } catch (error) {
        familyCombobox?.renderOptions([]);
        familyCombobox?.setDisabled(true);
        familyCombobox?.setPlaceholder(getFamilyPlaceholderMessage());
        setApiBadge("error", "API unavailable");
        setStatus(getApiFailureMessage(), "error");
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
        selectDropdowns.get(id)?.refreshOptions();
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

    selectDropdowns.get(id)?.refreshOptions();
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
    const resetPanelButton = document.getElementById("reset-configurator-panel");

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
    const stepElement = document.getElementById("config-step");
    const titleElement = document.getElementById("output-title");
    const subtitleElement = document.getElementById("output-subtitle");

    if (!stepElement || !titleElement || !subtitleElement) {
        return;
    }

    stepElement.textContent = step;
    titleElement.textContent = title;
    subtitleElement.textContent = subtitle;
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
    if (id === "select-family") {
        return familyCombobox?.getSelectedLabel() || "";
    }

    const element = document.getElementById(id);

    if (!element) {
        return "";
    }

    if (element.tagName !== "SELECT") {
        return element.value || "";
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
    const field = document.getElementById("family-combobox-input") || document.getElementById("select-family");

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

    familyCombobox?.clearSelection(false, true);
    familySelect.value = "";

    document.querySelectorAll("#options-group select").forEach((element) => {
        if (element.options.length > 0) {
            element.selectedIndex = 0;
        }
    });

    document.querySelectorAll('#options-group input[type="number"]').forEach((element) => {
        element.value = "0";
    });

    selectDropdowns.forEach((dropdown) => {
        dropdown.syncFromSelect();
    });
    syncConfiguredLanguageSelect?.(getCurrentAppLanguage(), false);

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
