/**
 * NexLed Configurator
 *
 * Loads the allowed option matrix from the API, builds the product reference,
 * and exports the datasheet request from the live UI state.
 */

const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";

const REF_LENGTHS = {
    size: 4,
    // The live API and legacy PDF engine use [color 2][cri 1].
    color: 2,
    cri: 1,
    series: 1,
    lens: 1,
    finish: 2,
    cap: 2,
    option: 2,
};

const DECODED_SEGMENT_FIELDS = {
    size: { id: "select-size", length: REF_LENGTHS.size, labelKey: "configurator.fields.size", fallback: "Size" },
    color: { id: "select-color", length: REF_LENGTHS.color, labelKey: "configurator.fields.color", fallback: "Color" },
    cri: { id: "select-cri", length: REF_LENGTHS.cri, labelKey: "configurator.fields.cri", fallback: "CRI" },
    series: { id: "select-series", length: REF_LENGTHS.series, labelKey: "configurator.fields.series", fallback: "Series" },
    lens: { id: "select-lens", length: REF_LENGTHS.lens, labelKey: "configurator.fields.lens", fallback: "Lens" },
    finish: { id: "select-finish", length: REF_LENGTHS.finish, labelKey: "configurator.fields.finish", fallback: "Finish" },
    cap: { id: "select-cap", length: REF_LENGTHS.cap, labelKey: "configurator.fields.cap", fallback: "Cap" },
    option: { id: "select-option", length: REF_LENGTHS.option, labelKey: "configurator.fields.option", fallback: "Option" },
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

const COPY_LABEL_KEYS = {
    "output-reference": "shared.actions.reference",
    "output-description": "shared.actions.description",
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
const API_SERVICE_FAILURE_STATUSES = new Set([401, 403]);
const NAV_GENERATE_IDS = ["nav-generate-desktop", "nav-generate-mobile"];
const CONFIGURATOR_I18N_EVENT = "nexled:i18n-applied";

let descriptionRequestToken = 0;
let apiBasePromise = null;
let familyCombobox = null;
let selectDropdowns = new Map();
let activeSelectDropdown = null;
let selectDropdownEventsBound = false;
let syncConfiguredLanguageSelect = null;
let apiBadgeState = {
    tone: "error",
    key: "shared.badge.apiUnavailable",
    fallback: "API unavailable",
};
let hasSuccessfulApiContact = false;
let apiHealthState = {
    checked: false,
    ok: false,
    services: {
        datasheet: true,
    },
};
let statusState = {
    tone: "neutral",
    key: "configurator.runtime.chooseFamilyToBegin",
    fallback: "Choose a family to begin.",
};
let summaryState = {
    stepKey: "configurator.runtime.step1",
    titleKey: "configurator.runtime.startWithFamily",
    subtitleKey: "configurator.runtime.selectFamilyToUnlock",
    fallbacks: {
        step: "Step 1",
        title: "Start with a family",
        subtitle: "Select a family to unlock the valid option set.",
    },
};
let familyPlaceholderState = {
    key: "configurator.runtime.familyLoading",
    fallback: "Loading families...",
};

document.addEventListener("DOMContentLoaded", () => {
    try {
        initializeConfigurator();
    } catch (error) {
        handleConfiguratorInitError(error);
    }
});

function initializeConfigurator() {
    familyCombobox = setupFamilyCombobox();
    setupSelectDropdowns();
    bindDocumentLanguageControls();
    bindTecitCodeLoader();
    document.getElementById("select-family").addEventListener("change", handleFamilyChange);
    document.getElementById("btn-generate").addEventListener("click", generateDatasheet);

    bindShellActions();
    bindReferenceListeners();
    bindCopyButtons();
    resetConfiguratorState();
    loadFamilies();
}

function handleConfiguratorInitError(error) {
    const message = error && error.message ? error.message : String(error);
    document.documentElement.dataset.configuratorInit = "failed";
    document.documentElement.dataset.configuratorInitError = message;

    const statusElement = document.getElementById("status-message");

    if (statusElement) {
        applyStatusText("Configurator init failed: " + message, "error");
    }

    console.error("Configurator init failed.", error);
}

async function getApiBase() {
    if (!apiBasePromise) {
        apiBasePromise = Promise.resolve(resolveApiBase());
    }

    return apiBasePromise;
}

function resolveApiBase() {
    return DEFAULT_API_BASE.replace(/\/+$/, "");
}

function setApiHealthState(nextState = {}) {
    apiHealthState = {
        checked: Boolean(nextState.checked),
        ok: Boolean(nextState.ok),
        services: {
            datasheet: nextState.services?.datasheet !== false,
        },
    };
    syncGenerateButton();
}

function noteSuccessfulApiContact() {
    hasSuccessfulApiContact = true;
}

function isDatasheetServiceAvailable() {
    return !apiHealthState.checked || apiHealthState.services.datasheet !== false;
}

function markApiUnavailable() {
    if (hasSuccessfulApiContact) {
        markApiDegraded();
        return;
    }

    setApiHealthState({
        checked: true,
        ok: false,
        services: apiHealthState.services,
    });
    setApiBadgeKey("error", "shared.badge.apiUnavailable", "API unavailable");
}

function markApiDegraded(serviceName = "") {
    const nextServices = {
        ...apiHealthState.services,
    };

    if (serviceName) {
        nextServices[serviceName] = false;
    }

    setApiHealthState({
        checked: true,
        ok: false,
        services: nextServices,
    });
    setApiBadgeKey("error", "shared.badge.apiDegraded", "API degraded");
}

function isApiServiceFailureStatus(status) {
    return status >= 500 || API_SERVICE_FAILURE_STATUSES.has(status);
}

async function fetchApiHealth() {
    const apiBase = await getApiBase();
    const response = await fetch(apiBase + "/?endpoint=health", {
        headers: { "X-API-Key": API_KEY },
    });
    noteSuccessfulApiContact();
    const contentType = response.headers.get("content-type") || "";
    let data = {};

    if (contentType.includes("application/json")) {
        data = await response.json();
    }

    return {
        ok: response.ok && data.ok !== false,
        status: response.status,
        data,
    };
}

function getApiHealthStatusMeta(health) {
    const services = health?.data?.services || {};

    if (services.families && services.reference && services.datasheet === false) {
        return {
            key: "configurator.runtime.datasheetServiceUnavailable",
            fallback: "Reference data loaded, but datasheet generation is currently unavailable.",
        };
    }

    return {
        key: "configurator.runtime.apiDegraded",
        fallback: "Some API services are currently unavailable.",
    };
}

function applyApiHealthResult(health) {
    if (!health) {
        return;
    }

    if (health.ok) {
        setApiHealthState({
            checked: true,
            ok: true,
            services: health.data?.services || {},
        });
        setApiBadgeKey("success", "shared.badge.apiReady", "API ready");
        return;
    }

    markApiDegraded();
    setApiHealthState({
        checked: true,
        ok: false,
        services: health.data?.services || {},
    });
}

function getApiFailureMessageKey() {
    return "configurator.runtime.apiFailureRemote";
}

function getApiFailureFallback(key) {
    const fallbacks = {
        "configurator.runtime.apiFailureFile": "The NexLed Railway API is not responding.",
        "configurator.runtime.apiFailureLocal": "The NexLed Railway API is not responding.",
        "configurator.runtime.apiFailureRemote": "The NexLed Railway API is not reachable from this page.",
    };

    return fallbacks[key] || "";
}

function getFamilyPlaceholderMessageKey() {
    return "configurator.runtime.familyPlaceholderRemote";
}

function getFamilyPlaceholderFallback(key) {
    const fallbacks = {
        "configurator.runtime.familyPlaceholderFile": "Unable to load families from the Railway API",
        "configurator.runtime.familyPlaceholderLocal": "Unable to load families from the Railway API",
        "configurator.runtime.familyPlaceholderRemote": "Unable to load families from the Railway API",
    };

    return fallbacks[key] || "";
}

function promptFamilySelectionFromList() {
    setStatusKey(
        "configurator.runtime.familySelectFromList",
        "neutral",
        {},
        "Select a specific family from the list to continue."
    );
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
        const shouldPromptSelection = restoreSelection && input.value.trim() !== "" && !valueField.value;

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

        if (shouldPromptSelection) {
            promptFamilySelectionFromList();
        }
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

        if (input.value.trim() !== "") {
            promptFamilySelectionFromList();
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
        selectByValue(value, shouldTrigger = true) {
            const option = getOptions().find((item) => item.dataset.value === value);

            if (!option) {
                return false;
            }

            setSelection(option.dataset.value, option.dataset.label, shouldTrigger);
            return true;
        },
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
    const root = document.createElement("div");
    const trigger = document.createElement("button");
    const valueDisplay = document.createElement("span");
    const arrow = document.createElement("i");
    const menu = document.createElement("ul");
    let itemElements = [];

    if (label && !label.id) {
        label.id = select.id + "-label";
    }

    function getLabelText() {
        return label ? label.textContent.replace(/\*/g, "").trim() : "Options";
    }

    function syncAccessibleText() {
        const labelText = getLabelText();

        if (label?.id) {
            trigger.setAttribute("aria-labelledby", label.id + " " + valueDisplay.id);
        } else {
            trigger.setAttribute("aria-label", labelText);
        }

        menu.setAttribute("aria-label", labelText);
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

    arrow.className = "ri-arrow-down-s-line dropdown-arrow";
    arrow.setAttribute("aria-hidden", "true");

    trigger.append(valueDisplay, arrow);

    menu.className = "dropdown-menu custom-scrollbar";
    menu.setAttribute("role", "listbox");

    root.append(trigger, menu);
    select.insertAdjacentElement("afterend", root);
    syncAccessibleText();

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
            updateValueDisplay(t("configurator.runtime.noOptionsAvailable", {}, "No options available"), false);
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
        syncAccessibleText();

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

function t(key, variables = {}, fallback = "") {
    return window.NexLedI18n?.t?.(key, variables, fallback) || fallback;
}

function resolveTranslatedVariables(state) {
    const variables = { ...(state?.variables || {}) };
    const variableKeys = state?.variableKeys || {};

    Object.keys(variableKeys).forEach((token) => {
        const entry = variableKeys[token];

        if (typeof entry === "string") {
            variables[token] = t(entry, {}, variables[token] || "");
            return;
        }

        if (entry && typeof entry === "object") {
            variables[token] = t(entry.key, entry.variables || {}, entry.fallback || variables[token] || "");
        }
    });

    return variables;
}

function setFamilyPlaceholderKey(key, fallback = "") {
    familyPlaceholderState = { key, fallback };
    applyFamilyPlaceholderState();
}

function applyFamilyPlaceholderState() {
    if (!familyCombobox || !familyPlaceholderState) {
        return;
    }

    familyCombobox.setPlaceholder(t(familyPlaceholderState.key, {}, familyPlaceholderState.fallback));
}

function setStatusKey(key, tone = "neutral", variables = {}, fallback = "", variableKeys = {}) {
    statusState = {
        tone,
        key,
        variables,
        variableKeys,
        fallback,
    };
    applyStatusState();
}

function applyStatusState() {
    if (!statusState) {
        return;
    }

    if (statusState.key) {
        applyStatusText(
            t(statusState.key, resolveTranslatedVariables(statusState), statusState.fallback),
            statusState.tone
        );
        return;
    }

    applyStatusText(statusState.text || "", statusState.tone);
}

function setSummaryStateKeys(stepKey, titleKey, subtitleKey, variables = {}, fallbacks = {}) {
    summaryState = {
        stepKey,
        titleKey,
        subtitleKey,
        variables,
        fallbacks,
    };
    applySummaryState();
}

function applySummaryState() {
    const stepElement = document.getElementById("config-step");
    const titleElement = document.getElementById("output-title");
    const subtitleElement = document.getElementById("output-subtitle");

    if (!summaryState || !stepElement || !titleElement || !subtitleElement) {
        return;
    }

    const variables = resolveTranslatedVariables(summaryState);

    stepElement.textContent = summaryState.stepKey
        ? t(summaryState.stepKey, variables, summaryState.fallbacks?.step || "")
        : summaryState.step || "";
    titleElement.textContent = summaryState.titleKey
        ? t(summaryState.titleKey, variables, summaryState.fallbacks?.title || "")
        : summaryState.title || "";
    subtitleElement.textContent = summaryState.subtitleKey
        ? t(summaryState.subtitleKey, variables, summaryState.fallbacks?.subtitle || "")
        : summaryState.subtitle || "";
}

function setApiBadgeKey(tone, key, fallback = "") {
    apiBadgeState = {
        tone,
        key,
        fallback,
    };
    applyApiBadgeState();
}

function applyApiBadgeState() {
    if (!apiBadgeState) {
        return;
    }

    const dotClass = API_BADGE_TONE_CLASS[apiBadgeState.tone] || API_BADGE_TONE_CLASS.error;
    const text = apiBadgeState.key
        ? t(apiBadgeState.key, resolveTranslatedVariables(apiBadgeState), apiBadgeState.fallback)
        : apiBadgeState.text || "";

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

function refreshLocalizedControls() {
    applyFamilyPlaceholderState();
    selectDropdowns.forEach((dropdown) => {
        dropdown.refreshOptions();
    });
    applyStatusState();
    applySummaryState();
    applyApiBadgeState();
}

window.addEventListener(CONFIGURATOR_I18N_EVENT, refreshLocalizedControls);

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
    });
}

async function apiFetch(path) {
    const apiBase = await getApiBase();
    let response;

    try {
        response = await fetch(apiBase + path, {
            headers: { "X-API-Key": API_KEY },
        });
        noteSuccessfulApiContact();
    } catch (error) {
        markApiUnavailable();
        throw error;
    }

    if (!response.ok) {
        if (isApiServiceFailureStatus(response.status)) {
            markApiDegraded();
        }
        throw new Error("Request failed with status " + response.status);
    }

    return response.json();
}

async function apiPost(path, body) {
    const apiBase = await getApiBase();

    try {
        const response = await fetch(apiBase + path, {
            method: "POST",
            headers: {
                "X-API-Key": API_KEY,
                "Content-Type": "application/json",
            },
            body: JSON.stringify(body),
        });
        noteSuccessfulApiContact();
        return response;
    } catch (error) {
        markApiUnavailable();
        throw error;
    }
}

async function loadFamilies() {
    setApiBadgeKey("loading", "shared.badge.apiConnecting", "Connecting to API");
    setStatusKey("configurator.runtime.familyLoading", "loading", {}, "Loading families...");
    familyCombobox?.setDisabled(true);
    setFamilyPlaceholderKey("configurator.runtime.familyLoading", "Loading families...");

    const [healthResult, familiesResult] = await Promise.allSettled([
        fetchApiHealth(),
        apiFetch("/?endpoint=families"),
    ]);

    try {
        if (familiesResult.status !== "fulfilled") {
            throw familiesResult.reason;
        }

        const data = familiesResult.value;
        familyCombobox?.renderOptions(
            data.map((family) => ({
                value: family.codigo,
                label: family.nome,
            }))
        );
        familyCombobox?.setDisabled(false);
        setFamilyPlaceholderKey("configurator.runtime.familySelect", "Select a family");

        if (healthResult.status === "fulfilled") {
            applyApiHealthResult(healthResult.value);

            if (!healthResult.value.ok) {
                const healthStatus = getApiHealthStatusMeta(healthResult.value);
                setStatusKey(healthStatus.key, "error", {}, healthStatus.fallback);
                return;
            }
        } else {
            setApiBadgeKey("success", "shared.badge.apiReady", "API ready");
            console.error(healthResult.reason);
        }

        setStatusKey("configurator.runtime.chooseFamilyToBegin", "neutral", {}, "Choose a family to begin.");
    } catch (error) {
        familyCombobox?.renderOptions([]);
        familyCombobox?.setDisabled(true);
        setFamilyPlaceholderKey(getFamilyPlaceholderMessageKey(), getFamilyPlaceholderFallback(getFamilyPlaceholderMessageKey()));
        markApiUnavailable();
        setStatusKey(getApiFailureMessageKey(), "error", {}, getApiFailureFallback(getApiFailureMessageKey()));
        console.error(error);
    }
}

async function handleFamilyChange() {
    const familyCode = get("select-family");

    if (!familyCode) {
        resetConfiguratorState();
        return;
    }

    setSummaryStateKeys(
        "configurator.runtime.step2",
        "configurator.runtime.loadingValidOptions",
        "configurator.runtime.loadingValidOptionsSubtitle",
        {},
        {
            step: "Step 2",
            title: "Loading valid options",
            subtitle: "Pulling the allowed manufacturing matrix for the selected family.",
        }
    );
    setStatusKey("configurator.runtime.loadingOptions", "loading", {}, "Loading options...");

    try {
        await loadOptions(familyCode);
    } catch (error) {
        resetConfiguratorState();
        setStatusKey("configurator.runtime.unableToLoadOptions", "error", {}, "Unable to load options for this family.");
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

    setSummaryStateKeys(
        "configurator.runtime.step2",
        "configurator.runtime.referenceBuilderActive",
        "configurator.runtime.referenceBuilderSubtitle",
        {},
        {
            step: "Step 2",
            title: "Reference builder active",
            subtitle: "Adjust the configuration. The live output on the right updates automatically.",
        }
    );
    setStatusKey("configurator.runtime.optionsLoaded", "success", {}, "Options loaded. The reference now updates automatically.");
}

function bindTecitCodeLoader() {
    const input = document.getElementById("input-tecit-code");
    const button = document.getElementById("btn-load-tecit-code");

    if (!input || !button || input.dataset.tecitBound === "true") {
        return;
    }

    input.addEventListener("input", () => {
        input.value = sanitizeTecitCode(input.value);
    });

    input.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") {
            return;
        }

        event.preventDefault();
        loadTecitCodeIntoForm();
    });

    button.addEventListener("click", loadTecitCodeIntoForm);

    input.dataset.tecitBound = "true";
}

function sanitizeTecitCode(value) {
    return String(value || "")
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, "");
}

function setTecitCodeControlsDisabled(isDisabled) {
    const input = document.getElementById("input-tecit-code");
    const button = document.getElementById("btn-load-tecit-code");

    if (input) {
        input.disabled = isDisabled;
    }

    if (button) {
        button.disabled = isDisabled;
    }
}

function hasLoadedFamilyOptions() {
    return document.querySelectorAll("#family-combobox-list [data-family-option]").length > 0;
}

function normalizeCodeValue(value) {
    const text = String(value ?? "").trim();

    if (text === "") {
        return "";
    }

    const normalized = text.replace(/^0+(?=\d)/, "");
    return normalized === "" ? "0" : normalized;
}

function resolveSelectValueFromSegment(select, segment, length) {
    const target = String(segment ?? "").trim();
    const options = Array.from(select?.options || []);

    if (!target || options.length === 0) {
        return null;
    }

    const exactMatch = options.find((option) => option.value === target);

    if (exactMatch) {
        return exactMatch.value;
    }

    const normalizedTarget = normalizeCodeValue(target);
    const paddedMatch = options.find((option) => {
        const optionValue = String(option.value || "");
        return normalizeCodeValue(optionValue) === normalizedTarget || pad(optionValue, length) === target;
    });

    return paddedMatch ? paddedMatch.value : null;
}

function applyDecodedSegmentsToForm(segments) {
    const unresolved = [];

    Object.entries(DECODED_SEGMENT_FIELDS).forEach(([key, meta]) => {
        const select = document.getElementById(meta.id);
        const resolvedValue = resolveSelectValueFromSegment(select, segments?.[key], meta.length);

        if (!select || resolvedValue === null) {
            unresolved.push(t(meta.labelKey, {}, meta.fallback));
            return;
        }

        select.value = resolvedValue;
        selectDropdowns.get(meta.id)?.syncFromSelect();
    });

    return unresolved;
}

async function applyDecodedReferenceToForm(data) {
    const familyCode = data?.segments?.family || "";
    const familyMatched = familyCombobox?.selectByValue(familyCode, false) || false;

    if (!familyMatched) {
        return {
            familyMatched: false,
            unresolved: [],
            reference: "",
        };
    }

    await loadOptions(familyCode);

    const unresolved = applyDecodedSegmentsToForm(data.segments || {});
    const extraLengthField = document.getElementById("input-extra-length");

    if (extraLengthField) {
        extraLengthField.value = "0";
    }

    buildReference();

    return {
        familyMatched: true,
        unresolved,
        reference: get("output-reference"),
    };
}

async function loadTecitCodeIntoForm() {
    const input = document.getElementById("input-tecit-code");
    const reference = sanitizeTecitCode(input?.value || "");

    if (!input) {
        return;
    }

    input.value = reference;

    if (!reference) {
        setStatusKey("configurator.runtime.tecitCodeMissing", "error", {}, "Enter a Tecit code first.");
        input.focus();
        return;
    }

    setTecitCodeControlsDisabled(true);
    setSummaryStateKeys(
        "configurator.runtime.step2",
        "configurator.runtime.decodingTecitCode",
        "configurator.runtime.decodingTecitCodeSubtitle",
        {},
        {
            step: "Step 2",
            title: "Decoding Tecit code",
            subtitle: "Checking code structure and loading matching manufacturing options.",
        }
    );
    setStatusKey("configurator.runtime.decodingTecitCode", "loading", {}, "Decoding Tecit code...");

    try {
        if (!hasLoadedFamilyOptions()) {
            await loadFamilies();
            setSummaryStateKeys(
                "configurator.runtime.step2",
                "configurator.runtime.decodingTecitCode",
                "configurator.runtime.decodingTecitCodeSubtitle",
                {},
                {
                    step: "Step 2",
                    title: "Decoding Tecit code",
                    subtitle: "Checking code structure and loading matching manufacturing options.",
                }
            );
            setStatusKey("configurator.runtime.decodingTecitCode", "loading", {}, "Decoding Tecit code...");
        }

        const data = await apiFetch("/?endpoint=decode-reference&ref=" + encodeURIComponent(reference));

        if (Number(data?.length) !== Number(data?.expected_length)) {
            setStatusKey("configurator.runtime.tecitCodeInvalid", "error", {}, "This Tecit code cannot be applied. Check family and length.");
            return;
        }

        if (data?.warnings?.includes("unknown_family") || !data?.segments?.family) {
            setStatusKey("configurator.runtime.tecitCodeFamilyMissing", "error", {}, "This Tecit code uses a family that is not available in the configurator.");
            return;
        }

        const result = await applyDecodedReferenceToForm(data);

        if (!result.familyMatched) {
            setStatusKey("configurator.runtime.tecitCodeFamilyMissing", "error", {}, "This Tecit code uses a family that is not available in the configurator.");
            return;
        }

        if (result.unresolved.length > 0) {
            setStatusKey(
                "configurator.runtime.tecitCodeFieldsMissing",
                "error",
                { fields: result.unresolved.join(", ") },
                "Tecit code loaded, but these fields could not be matched: " + result.unresolved.join(", ")
            );
            return;
        }

        if (result.reference !== data.reference) {
            setStatusKey(
                "configurator.runtime.tecitCodeMismatch",
                "error",
                { reference: result.reference },
                "Tecit code loaded, but rebuilt reference differs: " + result.reference
            );
            return;
        }

        if (Array.isArray(data.warnings) && data.warnings.includes("product_not_found")) {
            setStatusKey("configurator.runtime.tecitCodeAppliedWithWarning", "success", {}, "Tecit code loaded. Description may be unavailable.");
            return;
        }

        setStatusKey("configurator.runtime.tecitCodeApplied", "success", {}, "Tecit code loaded. Manufacturing fields were filled.");
    } catch (error) {
        setStatusKey("configurator.runtime.tecitCodeApplyFailed", "error", {}, "Unable to decode Tecit code right now.");
        console.error(error);
    } finally {
        setTecitCodeControlsDisabled(false);
    }
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
        emptyOption.dataset.i18n = "configurator.runtime.noOptionsAvailable";
        emptyOption.textContent = t("configurator.runtime.noOptionsAvailable", {}, "No options available");
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

        setSummaryStateKeys(
            "configurator.runtime.ready",
            "configurator.runtime.referenceReady",
            familyName ? "configurator.runtime.referenceReadyWithFamily" : "configurator.runtime.referenceReadyGeneric",
            { familyName },
            {
                step: "Ready",
                title: "Reference ready",
                subtitle: familyName
                    ? familyName + " is ready for datasheet generation."
                    : "The current configuration is ready for datasheet generation.",
            }
        );

        syncCopyButtons();
    } catch (error) {
        if (requestToken !== descriptionRequestToken) {
            return;
        }

        outputField.value = "";
        setStatusKey("configurator.runtime.descriptionLoadFailed", "error", {}, "The reference was built, but the description could not be loaded.");
        console.error(error);
    }
}

async function generateDatasheet() {
    const reference = document.getElementById("output-reference").value;
    const description = document.getElementById("output-description").value;

    if (!isDatasheetServiceAvailable()) {
        setStatusKey(
            "configurator.runtime.datasheetServiceUnavailable",
            "error",
            {},
            "Reference data loaded, but datasheet generation is currently unavailable."
        );
        return;
    }

    if (!reference) {
        setStatusKey("configurator.runtime.completeConfiguration", "error", {}, "Complete the configuration before generating the datasheet.");
        return;
    }

    const body = {
        referencia: reference,
        descricao: description,
        idioma: get("select-language"),
        empresa: get("select-company"),
        lente: getSelectedOptionHint("select-lens"),
        acabamento: getSelectedOptionHint("select-finish"),
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
    setStatusKey("configurator.runtime.generatingDatasheet", "loading", {}, "Generating datasheet...");

    try {
        const response = await apiPost("/?endpoint=datasheet", body);

        setGenerateControlsDisabled(false);
        syncGenerateButton();

        if (!response.ok) {
            const contentType = response.headers.get("content-type") || "";
            let message = t("configurator.runtime.unknownError", {}, "Unknown error");

            if (isApiServiceFailureStatus(response.status)) {
                markApiDegraded("datasheet");
            }

            const rawError = await response.text();
            const cleanError = extractResponseMessage(rawError);

            if (contentType.includes("application/json") && rawError.trim() !== "") {
                try {
                    const error = JSON.parse(rawError);
                    const errorParts = [
                        error.error || "",
                        error.stage ? "stage: " + error.stage : "",
                        error.detail || "",
                    ].filter(Boolean);

                    message = errorParts.join(" - ") || cleanError || message;
                } catch (_parseError) {
                    message = cleanError || message;
                }
            } else if (cleanError !== "") {
                message = cleanError;
            } else {
                message = "Request failed with status " + response.status;
            }

            setStatusKey(
                "configurator.runtime.datasheetFailedWithMessage",
                "error",
                { message },
                "Datasheet generation failed: " + message
            );
            return;
        }

        const successContentType = response.headers.get("content-type") || "";

        if (successContentType.includes("application/json") || successContentType.includes("text/html")) {
            const rawMessage = await response.text();
            const cleanMessage = extractResponseMessage(rawMessage);

            throw new Error(cleanMessage || "Datasheet endpoint returned a non-PDF response.");
        }

        const blob = await response.blob();

        if (blob.size === 0) {
            throw new Error("Datasheet endpoint returned an empty PDF response.");
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = reference + ".pdf";
        link.click();

        URL.revokeObjectURL(url);
        setStatusKey("configurator.runtime.datasheetReady", "success", {}, "Datasheet ready. The PDF download has started.");
    } catch (error) {
        setGenerateControlsDisabled(false);
        syncGenerateButton();
        const message = error && error.message ? error.message : "";

        if (message !== "") {
            setStatusKey(
                "configurator.runtime.datasheetFailedWithMessage",
                "error",
                { message },
                "Datasheet generation failed: " + message
            );
            console.error(error);
            return;
        }

        setStatusKey("configurator.runtime.datasheetFailed", "error", {}, "Datasheet generation failed.");
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
    const labelKey = targetId ? (COPY_LABEL_KEYS[targetId] || "shared.actions.value") : "shared.actions.value";

    if (!field || !field.value) {
        setStatusKey("configurator.runtime.nothingToCopy", "error", {}, "Nothing to copy yet.");
        return;
    }

    try {
        await navigator.clipboard.writeText(field.value);
        setStatusKey(
            "configurator.runtime.copied",
            "success",
            {},
            "Copied " + t(labelKey, {}, "value") + ".",
            { label: labelKey }
        );
    } catch (error) {
        setStatusKey("configurator.runtime.clipboardFailed", "error", {}, "Clipboard copy failed. Please copy manually.");
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
    setSummaryStateKeys(
        "configurator.runtime.step1",
        "configurator.runtime.startWithFamily",
        "configurator.runtime.selectFamilyToUnlock",
        {},
        {
            step: "Step 1",
            title: "Start with a family",
            subtitle: "Select a family to unlock the valid option set.",
        }
    );
    setStatusKey("configurator.runtime.chooseFamilyToBegin", "neutral", {}, "Choose a family to begin.");
}

function clearOutputValues() {
    document.getElementById("output-reference").value = "";
    document.getElementById("output-description").value = "";
}

function syncGenerateButton() {
    const button = document.getElementById("btn-generate");
    const hasReference = document.getElementById("output-reference").value.length > 0;
    const isDisabled = !hasReference || !isDatasheetServiceAvailable();

    button.disabled = isDisabled;

    NAV_GENERATE_IDS.forEach((id) => {
        const navButton = document.getElementById(id);

        if (!navButton) {
            return;
        }

        navButton.disabled = isDisabled;
        navButton.setAttribute("aria-disabled", String(isDisabled));
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
    summaryState = { step, title, subtitle };
    applySummaryState();
}

function setHidden(id, shouldHide) {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    element.classList.toggle("hidden", shouldHide);
}

function setStatus(message, tone = "neutral") {
    statusState = {
        tone,
        text: message,
    };
    applyStatusState();
}

function applyStatusText(message, tone = "neutral") {
    const element = document.getElementById("status-message");
    const toneClass = STATUS_TONE_CLASS[tone] || STATUS_TONE_CLASS.neutral;

    if (!element) {
        return;
    }

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

function getSelectedOptionHint(id) {
    const element = document.getElementById(id);

    if (!element || element.tagName !== "SELECT") {
        return "";
    }

    const option = element.options[element.selectedIndex] || null;

    if (!option) {
        return "";
    }

    return option.title || option.text || option.value || "";
}

function extractResponseMessage(rawValue) {
    const raw = typeof rawValue === "string" ? rawValue : "";

    return raw.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
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
    const tecitInput = document.getElementById("input-tecit-code");

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

    if (tecitInput) {
        tecitInput.value = "";
    }

    selectDropdowns.forEach((dropdown) => {
        dropdown.syncFromSelect();
    });
    syncConfiguredLanguageSelect?.(getCurrentAppLanguage(), false);

    resetConfiguratorState();
    focusFamilyField();
}

function setApiBadge(tone, text) {
    apiBadgeState = {
        tone,
        text,
    };
    applyApiBadgeState();
}
