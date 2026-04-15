const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";
const STATUS_TOAST_BASE_CLASS = "toast toast-sm";
const STATUS_TOAST_AUTOHIDE_DELAY = 4000;
const STATUS_TOAST_HIDE_DELAY = 320;
const STATUS_TOAST_VARIANT = {
    neutral: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: true },
    loading: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: false },
    success: { className: "toast-success", iconClass: "ri-checkbox-circle-line", role: "status", autoHide: true },
    warning: { className: "toast-warning", iconClass: "ri-alert-line", role: "status", autoHide: false },
    error: { className: "toast-danger", iconClass: "ri-close-circle-line", role: "alert", autoHide: true },
};
const EXPLORER_TABLE_HEAD_FALLBACK_COLUMNS = [
    "minmax(0, 1.35fr)",
    "minmax(0, 0.95fr)",
    "minmax(0, 1.1fr)",
    "minmax(0, 0.9fr)",
    "minmax(0, 1fr)",
    "minmax(0, 0.9fr)",
    "minmax(0, 0.9fr)",
    "minmax(0, 1.8fr)",
].join(" ");
const API_BADGE_BASE_CLASS = "badge badge-md shrink-0";
const API_BADGE_VARIANT_CLASS = {
    neutral: "badge-neutral",
    loading: "badge-info",
    success: "badge-success",
    warning: "badge-warning",
    error: "badge-danger",
};
const I18N_EVENT = "nexled:i18n-applied";
const SEGMENT_META = [
    { key: "family", labelKey: "codeExplorer.segmentFamily", fallback: "Family" },
    { key: "size", labelKey: "codeExplorer.segmentSize", fallback: "Size" },
    { key: "color", labelKey: "codeExplorer.segmentColor", fallback: "Color" },
    { key: "cri", labelKey: "codeExplorer.segmentCri", fallback: "CRI" },
    { key: "series", labelKey: "codeExplorer.segmentSeries", fallback: "Series" },
    { key: "lens", labelKey: "codeExplorer.segmentLens", fallback: "Lens" },
    { key: "finish", labelKey: "codeExplorer.segmentFinish", fallback: "Finish" },
    { key: "cap", labelKey: "codeExplorer.segmentCap", fallback: "Cap" },
    { key: "option", labelKey: "codeExplorer.segmentOption", fallback: "Option" },
];
const EXPLORER_SEGMENT_FILTER_META = [
    { key: "size", payloadKey: "tamanho", selectId: "explorer-filter-size", labelKey: "codeExplorer.filterSizeLabel", anyKey: "codeExplorer.filterAnySize", fallbackLabel: "Size", fallbackAny: "Any size", length: 4 },
    { key: "color", payloadKey: "cor", selectId: "explorer-filter-color", labelKey: "codeExplorer.filterColorLabel", anyKey: "codeExplorer.filterAnyColor", fallbackLabel: "Color", fallbackAny: "Any color", length: 2 },
    { key: "cri", payloadKey: "cri", selectId: "explorer-filter-cri", labelKey: "codeExplorer.filterCriLabel", anyKey: "codeExplorer.filterAnyCri", fallbackLabel: "CRI", fallbackAny: "Any CRI", length: 1 },
    { key: "series", payloadKey: "serie", selectId: "explorer-filter-series", labelKey: "codeExplorer.filterSeriesLabel", anyKey: "codeExplorer.filterAnySeries", fallbackLabel: "Series", fallbackAny: "Any series", length: 1 },
];
const FAILURE_REASON_KEYS = {
    invalid_luminos_combination: "codeExplorer.failure.invalid_luminos_combination",
    missing_header_data: "codeExplorer.failure.missing_header_data",
    missing_color_graph: "codeExplorer.failure.missing_color_graph",
    missing_lens_diagram: "codeExplorer.failure.missing_lens_diagram",
    missing_technical_drawing: "codeExplorer.failure.missing_technical_drawing",
    missing_finish_image: "codeExplorer.failure.missing_finish_image",
    missing_fixing_data: "codeExplorer.failure.missing_fixing_data",
    missing_power_supply_data: "codeExplorer.failure.missing_power_supply_data",
    missing_connection_cable_data: "codeExplorer.failure.missing_connection_cable_data",
    unsupported_datasheet_runtime: "codeExplorer.failure.unsupported_datasheet_runtime",
};
const EXPLORER_ERROR_REASON_KEYS = {
    invalid_filters_required: "codeExplorer.error.invalid_filters_required",
    family_matrix_too_large: "codeExplorer.error.family_matrix_too_large",
};
const EXPLORER_SEARCH_MODE_CODE = "code";
const EXPLORER_SEARCH_MODE_NAME = "name";

let apiBasePromise = null;
let hasSuccessfulApiContact = false;
let apiBadgeState = {
    tone: "error",
    key: "shared.badge.apiUnavailable",
    fallback: "API unavailable",
};
let pageStatusState = {
    tone: "neutral",
    key: "codeExplorer.runtime.chooseFamily",
    fallback: "Select one family to start building valid code rows.",
    vars: {},
};
let statusToastTimers = {
    dismiss: null,
    hide: null,
};
let familyCombobox = null;
let explorerState = {
    families: [],
    familyOptionsByFamily: {},
    data: null,
    selectedReference: "",
    controls: {
        searchMode: EXPLORER_SEARCH_MODE_CODE,
        family: "",
        search: "",
        status: "all",
        page: 1,
        pageSize: 100,
        includeInvalid: false,
        segmentFilters: {
            size: "",
            color: "",
            cri: "",
            series: "",
        },
    },
};

document.addEventListener("DOMContentLoaded", () => {
    initializeCodeExplorer();
});

function initializeCodeExplorer() {
    familyCombobox = setupExplorerFamilyCombobox();
    bindControls();
    bindStaticEvents();
    renderApiBadge();
    applyPageStatusState();
    renderExplorerSegmentFilterOptions(null);
    applyExplorerSearchMode(explorerState.controls.searchMode);
    renderSummary(null);
    renderTable();
    renderDetail();
    renderPagination();
    loadFamilies();
    checkApiHealth();
}

function getEmptyExplorerSegmentFilters() {
    return {
        size: "",
        color: "",
        cri: "",
        series: "",
    };
}

function normalizeExplorerSegmentCode(value, length) {
    const normalized = String(value ?? "").trim().replace(/\s+/g, "");

    if (normalized === "") {
        return "";
    }

    if (/^\d+$/.test(normalized) && normalized.length < length) {
        return normalized.padStart(length, "0");
    }

    return normalized.toUpperCase();
}

function normalizeExplorerFamilyOptions(payload) {
    return EXPLORER_SEGMENT_FILTER_META.reduce((accumulator, meta) => {
        const rawOptions = Array.isArray(payload?.[meta.payloadKey]) ? payload[meta.payloadKey] : [];

        accumulator[meta.key] = rawOptions
            .map((rawOption) => {
                if (meta.key === "size") {
                    const code = normalizeExplorerSegmentCode(rawOption, meta.length);

                    return {
                        code,
                        label: String(rawOption ?? code).trim() || code,
                    };
                }

                const label = String(rawOption?.[0] ?? rawOption?.[2] ?? rawOption?.[1] ?? "").trim();
                const code = normalizeExplorerSegmentCode(rawOption?.[1] ?? rawOption?.[0] ?? "", meta.length);

                return {
                    code,
                    label: label || code,
                };
            })
            .filter((option) => option.code !== "");

        return accumulator;
    }, {});
}

async function loadExplorerFamilyOptions(family, resetSelection = false) {
    if (!family) {
        explorerState.controls.segmentFilters = getEmptyExplorerSegmentFilters();
        renderExplorerSegmentFilterOptions(null);
        return null;
    }

    if (!explorerState.familyOptionsByFamily[family]) {
        const payload = await apiFetch("/?endpoint=options&family=" + encodeURIComponent(family));
        explorerState.familyOptionsByFamily[family] = normalizeExplorerFamilyOptions(payload);
    }

    if (resetSelection) {
        explorerState.controls.segmentFilters = getEmptyExplorerSegmentFilters();
    }

    const selectedFamily = document.getElementById("explorer-family")?.value || explorerState.controls.family;

    if (selectedFamily === family) {
        renderExplorerSegmentFilterOptions(explorerState.familyOptionsByFamily[family]);
    }

    return explorerState.familyOptionsByFamily[family];
}

function renderExplorerSegmentFilterOptions(optionMap) {
    const optionsBySegment = optionMap || {};

    EXPLORER_SEGMENT_FILTER_META.forEach((meta) => {
        const select = document.getElementById(meta.selectId);

        if (!select) {
            return;
        }

        const options = Array.isArray(optionsBySegment[meta.key]) ? optionsBySegment[meta.key] : [];
        const selectedValue = explorerState.controls.segmentFilters?.[meta.key] || "";
        const hasSelectedValue = options.some((option) => option.code === selectedValue);
        const nextValue = hasSelectedValue ? selectedValue : "";

        select.innerHTML = [
            `<option value="">${escapeHtml(t(meta.anyKey, {}, meta.fallbackAny))}</option>`,
            ...options.map((option) => `<option value="${escapeHtml(option.code)}">${escapeHtml(option.code)} - ${escapeHtml(option.label)}</option>`),
        ].join("");
        select.disabled = options.length === 0;
        select.value = nextValue;
        explorerState.controls.segmentFilters[meta.key] = nextValue;
    });

    updateExplorerInvalidGuidance();
}

function readExplorerSegmentFiltersFromForm() {
    return EXPLORER_SEGMENT_FILTER_META.reduce((accumulator, meta) => {
        const select = document.getElementById(meta.selectId);
        accumulator[meta.key] = normalizeExplorerSegmentCode(select?.value || "", meta.length);
        return accumulator;
    }, getEmptyExplorerSegmentFilters());
}

function hasActiveExplorerSegmentFilters(segmentFilters = explorerState.controls.segmentFilters) {
    return EXPLORER_SEGMENT_FILTER_META.some((meta) => (segmentFilters?.[meta.key] || "") !== "");
}

function isTargetedExplorerReferenceSearch(search = explorerState.controls.search, family = explorerState.controls.family) {
    const normalizedSearch = String(search ?? "").replace(/\s+/g, "").toUpperCase();
    const normalizedFamily = String(family ?? "").trim().toUpperCase();

    if (!normalizedFamily || normalizedSearch === "") {
        return false;
    }

    return normalizedSearch.length >= 10
        && normalizedSearch.length <= 17
        && normalizedSearch.startsWith(normalizedFamily);
}

function updateExplorerInvalidGuidance() {
    const guidance = document.getElementById("explorer-invalid-guidance");

    if (!guidance) {
        return;
    }

    if (explorerState.controls.searchMode !== EXPLORER_SEARCH_MODE_NAME) {
        guidance.classList.add("hidden");
        return;
    }

    const includeInvalid = document.getElementById("explorer-include-invalid")?.checked === true;
    const search = document.getElementById("explorer-search")?.value || explorerState.controls.search;
    const family = document.getElementById("explorer-family")?.value || explorerState.controls.family;
    const segmentFilters = readExplorerSegmentFiltersFromForm();
    const shouldShow = includeInvalid && !hasActiveExplorerSegmentFilters(segmentFilters) && !isTargetedExplorerReferenceSearch(search, family);

    guidance.classList.toggle("hidden", !shouldShow);
}

function getExplorerErrorMessage(error) {
    const reason = error?.payload?.reason;
    const mappedKey = reason ? EXPLORER_ERROR_REASON_KEYS[reason] : "";

    if (mappedKey) {
        return t(mappedKey, {}, error?.payload?.message || error?.message || mappedKey);
    }

    return error?.payload?.message || error?.message || t("codeExplorer.runtime.unknownError", {}, "Unknown error");
}

function getExplorerSearchModeFromForm() {
    return document.querySelector('input[name="explorer-search-mode"]:checked')?.value || EXPLORER_SEARCH_MODE_CODE;
}

function renderExplorerSearchInputCopy(searchMode = explorerState.controls.searchMode) {
    const label = document.getElementById("explorer-search-label");
    const input = document.getElementById("explorer-search");
    const isCodeMode = searchMode === EXPLORER_SEARCH_MODE_CODE;

    if (label) {
        label.textContent = isCodeMode
            ? t("codeExplorer.searchCodeLabel", {}, "Search By Code")
            : t("codeExplorer.searchNameLabel", {}, "Search By Name");
    }

    if (input) {
        input.placeholder = isCodeMode
            ? t("codeExplorer.searchCodePlaceholder", {}, "Enter a code")
            : t("codeExplorer.searchNamePlaceholder", {}, "Enter a name");
    }
}

function applyExplorerSearchMode(searchMode = explorerState.controls.searchMode) {
    const isCodeMode = searchMode === EXPLORER_SEARCH_MODE_CODE;

    explorerState.controls.searchMode = searchMode;
    document.querySelectorAll("[data-name-search-field]").forEach((element) => {
        element.classList.toggle("hidden", isCodeMode);
    });

    renderExplorerSearchInputCopy(searchMode);
    syncDraftInvalidControls();
    syncAppliedInvalidSummaryCard();
    updateExplorerInvalidGuidance();

    if (!isCodeMode && explorerState.controls.family) {
        void loadExplorerFamilyOptions(explorerState.controls.family, false);
    }
}

function setupExplorerFamilyCombobox() {
    const combobox = document.getElementById("explorer-family-combobox");
    const input = document.getElementById("explorer-family-input");
    const clearButton = document.getElementById("explorer-family-clear");
    const panel = document.getElementById("explorer-family-panel");
    const list = document.getElementById("explorer-family-list");
    const emptyState = document.getElementById("explorer-family-empty");
    const valueField = document.getElementById("explorer-family");

    if (!combobox || !input || !clearButton || !panel || !list || !emptyState || !valueField) {
        return null;
    }

    let activeOption = null;

    input.setAttribute("aria-controls", list.id);

    const getOptions = () => Array.from(list.querySelectorAll("[data-combobox-option]"));
    const getVisibleOptions = () => getOptions().filter((option) => !option.hidden);
    const getSelectedOption = () => getOptions().find((option) => option.dataset.value === valueField.value) || null;
    const getSelectedLabel = () => getSelectedOption()?.dataset.label || "";

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

    const updateFilter = (query) => {
        const normalizedQuery = query.trim().toLowerCase();

        getOptions().forEach((option) => {
            const matches = normalizedQuery === "" || (option.dataset.label || "").toLowerCase().includes(normalizedQuery);
            option.hidden = !matches;
        });

        const visibleOptions = getVisibleOptions();
        emptyState.hidden = visibleOptions.length !== 0;
        setActiveOption(visibleOptions[0] || null);
    };

    const syncSelectedState = () => {
        const currentValue = valueField.value;

        getOptions().forEach((option) => {
            option.setAttribute("aria-selected", option.dataset.value === currentValue ? "true" : "false");
        });

        combobox.classList.toggle("has-value", Boolean(currentValue));
        updateClearState();
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
            option.id = "explorer-family-option-" + (index + 1);
            option.dataset.comboboxOption = "true";
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

        if (valueField.value && !items.some((item) => item.value === valueField.value)) {
            valueField.value = "";
            input.value = "";
        } else if (valueField.value) {
            input.value = getSelectedLabel();
        }

        syncSelectedState();
        updateFilter(input.value.trim());
    };

    input.addEventListener("focus", openPanel);
    input.addEventListener("click", openPanel);
    input.addEventListener("input", () => {
        if (valueField.value && input.value.trim() !== getSelectedLabel()) {
            clearSelection(true, false);
        }

        if (input.value.trim() !== "") {
            openPanel();
        } else {
            updateFilter("");
        }
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
            const nextIndex = currentIndex === -1 ? 0 : Math.min(currentIndex + 1, visibleOptions.length - 1);
            setActiveOption(visibleOptions[nextIndex]);
            return;
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            openPanel();

            if (visibleOptions.length === 0) {
                return;
            }

            const currentIndex = visibleOptions.indexOf(activeOption);
            const nextIndex = currentIndex === -1 ? visibleOptions.length - 1 : Math.max(currentIndex - 1, 0);
            setActiveOption(visibleOptions[nextIndex]);
            return;
        }

        if (event.key === "Enter") {
            if (!combobox.classList.contains("is-open") || !activeOption) {
                return;
            }

            event.preventDefault();
            setSelection(activeOption.dataset.value || "", activeOption.dataset.label || "");
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closePanel(true);
            input.focus();
            return;
        }

        if (event.key === "Tab") {
            closePanel(true);
        }
    });

    clearButton.addEventListener("click", (event) => {
        event.preventDefault();
        clearSelection(true, true);
        openPanel();
        input.focus();
    });

    document.addEventListener("click", (event) => {
        if (combobox.contains(event.target)) {
            return;
        }

        closePanel(true);
    });

    return {
        renderOptions,
        setSelectionByValue(nextValue, shouldTrigger = false) {
            if (!nextValue) {
                clearSelection(shouldTrigger, true);
                return;
            }

            const option = getOptions().find((currentOption) => currentOption.dataset.value === nextValue);

            if (!option) {
                return;
            }

            setSelection(option.dataset.value || "", option.dataset.label || "", shouldTrigger);
        },
    };
}

function bindControls() {
    document.getElementById("explorer-filters").addEventListener("submit", (event) => {
        event.preventDefault();
        applyFiltersFromForm();
    });

    document.getElementById("explorer-search").addEventListener("input", () => {
        updateExplorerInvalidGuidance();
    });

    document.querySelectorAll('input[name="explorer-search-mode"]').forEach((radio) => {
        radio.addEventListener("change", () => {
            if (!radio.checked) {
                return;
            }

            applyExplorerSearchMode(radio.value);
        });
    });

    document.getElementById("explorer-include-invalid").addEventListener("change", () => {
        syncDraftInvalidControls();
        updateExplorerInvalidGuidance();
    });

    document.getElementById("explorer-family").addEventListener("change", async () => {
        const selectedFamily = document.getElementById("explorer-family").value;
        explorerState.controls.family = selectedFamily;
        explorerState.controls.segmentFilters = getEmptyExplorerSegmentFilters();
        renderExplorerSegmentFilterOptions(null);

        if (!selectedFamily) {
            return;
        }

        try {
            await loadExplorerFamilyOptions(selectedFamily, true);
        } catch (error) {
            setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "error", "Unable to load explorer data right now: {message}", {
                message: getExplorerErrorMessage(error),
            });
        }
    });

    EXPLORER_SEGMENT_FILTER_META.forEach((meta) => {
        const select = document.getElementById(meta.selectId);

        if (!select) {
            return;
        }

        select.addEventListener("change", () => {
            explorerState.controls.segmentFilters = readExplorerSegmentFiltersFromForm();
            updateExplorerInvalidGuidance();
        });
    });

    document.getElementById("explorer-pagination").addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-pagination-prev], [data-pagination-next], [data-page]");

        if (!trigger) {
            return;
        }

        if (trigger.matches("[data-pagination-prev]")) {
            const currentPage = getExplorerCurrentPage();

            if (!explorerState.data || currentPage <= 1) {
                return;
            }

            explorerState.controls.page = currentPage - 1;
            loadExplorerData();
            return;
        }

        if (trigger.matches("[data-pagination-next]")) {
            const currentPage = getExplorerCurrentPage();
            const totalPages = explorerState.data?.pagination?.total_pages || 1;

            if (!explorerState.data || currentPage >= totalPages) {
                return;
            }

            explorerState.controls.page = currentPage + 1;
            loadExplorerData();
            return;
        }

        const nextPage = Number.parseInt(trigger.dataset.page || "", 10);

        if (!Number.isFinite(nextPage) || nextPage < 1 || explorerState.controls.page === nextPage) {
            return;
        }

        explorerState.controls.page = nextPage;
        loadExplorerData();
    });

    syncDraftInvalidControls();
    updateExplorerInvalidGuidance();

    document.getElementById("explorer-rows").addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-reference]");

        if (!trigger) {
            return;
        }

        explorerState.selectedReference = trigger.dataset.reference || "";
        renderTable();
        renderDetail();
        openCodeDetailModal(trigger);
    });
}

async function applyFiltersFromForm() {
    const search = document.getElementById("explorer-search").value.trim();
    const searchMode = getExplorerSearchModeFromForm();
    const previousFamily = explorerState.controls.family;
    const includeInvalid = searchMode === EXPLORER_SEARCH_MODE_NAME
        ? document.getElementById("explorer-include-invalid").checked
        : false;
    let family = searchMode === EXPLORER_SEARCH_MODE_NAME
        ? document.getElementById("explorer-family").value
        : "";

    if (searchMode === EXPLORER_SEARCH_MODE_CODE) {
        family = detectFamilyCodeFromReferenceSearch(search);
    }

    explorerState.controls.searchMode = searchMode;
    explorerState.controls.search = search;
    explorerState.controls.family = family;
    explorerState.controls.status = searchMode === EXPLORER_SEARCH_MODE_NAME
        ? document.getElementById("explorer-status").value
        : "all";
    explorerState.controls.includeInvalid = includeInvalid;
    explorerState.controls.pageSize = searchMode === EXPLORER_SEARCH_MODE_NAME
        ? (Number.parseInt(document.getElementById("explorer-page-size").value, 10) || 100)
        : 100;
    explorerState.controls.segmentFilters = searchMode === EXPLORER_SEARCH_MODE_NAME
        ? readExplorerSegmentFiltersFromForm()
        : getEmptyExplorerSegmentFilters();
    explorerState.controls.page = 1;

    if (searchMode === EXPLORER_SEARCH_MODE_NAME) {
        syncDraftInvalidControls();
    }

    const familyChanged = family !== previousFamily;

    if (searchMode === EXPLORER_SEARCH_MODE_NAME && family) {
        if (familyChanged) {
            explorerState.controls.segmentFilters = getEmptyExplorerSegmentFilters();
        }

        try {
            await loadExplorerFamilyOptions(family, familyChanged);
            explorerState.controls.segmentFilters = readExplorerSegmentFiltersFromForm();
        } catch (error) {
            explorerState.data = null;
            explorerState.selectedReference = "";
            renderSummary(null);
            renderTable();
            renderDetail();
            renderResultsMeta();
            renderPagination();
            setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "error", "Unable to load explorer data right now: {message}", {
                message: getExplorerErrorMessage(error),
            });
            return;
        }
    }

    syncAppliedInvalidSummaryCard();
    updateExplorerInvalidGuidance();

    if (!explorerState.controls.family) {
        explorerState.data = null;
        explorerState.selectedReference = "";
        setPageStatus(
            searchMode === EXPLORER_SEARCH_MODE_CODE ? "codeExplorer.runtime.searchCodeNeedsFamily" : "codeExplorer.runtime.chooseFamily",
            "neutral",
            searchMode === EXPLORER_SEARCH_MODE_CODE
                ? "Enter full code with family prefix to load explorer rows."
                : "Select one family to start building valid code rows."
        );
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        return;
    }

    if (searchMode === EXPLORER_SEARCH_MODE_NAME
        && explorerState.controls.includeInvalid
        && !hasActiveExplorerSegmentFilters(explorerState.controls.segmentFilters)
        && !isTargetedExplorerReferenceSearch(search, explorerState.controls.family)) {
        explorerState.data = null;
        explorerState.selectedReference = "";
        setPageStatus("codeExplorer.runtime.invalidNeedsDrillDown", "warning", "Include invalid requires at least one drill-down filter or a targeted full code.");
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        return;
    }

    loadExplorerData();
}

function detectFamilyCodeFromReferenceSearch(search) {
    const normalizedSearch = String(search ?? "").replace(/\s+/g, "").toUpperCase();

    if (normalizedSearch.length < 2) {
        return "";
    }

    const familyCode = normalizedSearch.slice(0, 2);
    return /^\d{2}$/.test(familyCode) ? familyCode : "";
}

function bindStaticEvents() {
    const closeButton = document.getElementById("status-message-close");

    if (closeButton) {
        closeButton.addEventListener("click", () => {
            hideStatusToast(true);
        });
    }

    window.addEventListener(I18N_EVENT, () => {
        renderApiBadge();
        applyPageStatusState();
        applyExplorerSearchMode(explorerState.controls.searchMode);
        renderExplorerSegmentFilterOptions(explorerState.familyOptionsByFamily[explorerState.controls.family] || null);
        renderResultsMeta();
        renderSummary(explorerState.data);
        renderTable();
        renderDetail();
        renderPagination();
    });

    window.addEventListener("resize", syncExplorerTableHeadColumns);
}

function syncDraftInvalidControls() {
    const includeInvalid = document.getElementById("explorer-include-invalid")?.checked === true;
    const invalidStatusOption = document.querySelector("[data-invalid-status-option]");
    const statusSelect = document.getElementById("explorer-status");

    if (invalidStatusOption) {
        invalidStatusOption.disabled = !includeInvalid;
        invalidStatusOption.hidden = !includeInvalid;
    }

    if (!includeInvalid && statusSelect?.value === "configurator_invalid") {
        statusSelect.value = "all";
    }
}

function syncAppliedInvalidSummaryCard() {
    const invalidSummaryCard = document.getElementById("summary-invalid-card");

    if (invalidSummaryCard) {
        invalidSummaryCard.classList.toggle(
            "hidden",
            explorerState.controls.searchMode !== EXPLORER_SEARCH_MODE_NAME || explorerState.controls.includeInvalid !== true
        );
    }
}

async function getApiBase() {
    if (!apiBasePromise) {
        apiBasePromise = Promise.resolve(DEFAULT_API_BASE.replace(/\/+$/, ""));
    }

    return apiBasePromise;
}

async function apiFetch(queryString) {
    const base = await getApiBase();
    const response = await fetch(base + queryString, {
        headers: {
            "X-API-Key": API_KEY,
        },
    });

    const rawText = await response.text();
    let payload = null;

    if (rawText !== "") {
        try {
            payload = JSON.parse(rawText);
        } catch (error) {
            payload = null;
        }
    }

    if (!response.ok) {
        const message = payload?.error || rawText || ("Request failed with status " + response.status);
        const failure = new Error(message);
        failure.status = response.status;
        failure.payload = payload;
        throw failure;
    }

    noteSuccessfulApiContact();
    return payload;
}

function noteSuccessfulApiContact() {
    hasSuccessfulApiContact = true;
}

async function checkApiHealth() {
    setApiBadge("loading", "shared.badge.apiConnecting", "Connecting to API");

    try {
        const health = await apiFetch("/?endpoint=health");

        if (health?.ok) {
            setApiBadge("success", "shared.badge.apiReady", "API ready");
        } else {
            setApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        }
    } catch (error) {
        if (hasSuccessfulApiContact) {
            setApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        } else {
            setApiBadge("error", "shared.badge.apiUnavailable", "API unavailable");
        }
    }
}

async function loadFamilies() {
    setPageStatus("codeExplorer.runtime.loadingFamilies", "loading", "Loading families...");

    try {
        const families = await apiFetch("/?endpoint=families");
        explorerState.families = Array.isArray(families) ? families : [];
        populateFamilies();
        setPageStatus(
            explorerState.controls.searchMode === EXPLORER_SEARCH_MODE_CODE
                ? "codeExplorer.runtime.searchCodeNeedsFamily"
                : "codeExplorer.runtime.chooseFamily",
            "neutral",
            explorerState.controls.searchMode === EXPLORER_SEARCH_MODE_CODE
                ? "Enter a full code with family prefix first."
                : "Select one family to start building valid code rows."
        );
    } catch (error) {
        setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "error", "Unable to load explorer data right now: {message}", {
            message: getExplorerErrorMessage(error),
        });
        renderResultsMeta();
    }
}

function populateFamilies() {
    const valueField = document.getElementById("explorer-family");
    const options = explorerState.families.map((family) => {
        const code = String(family.codigo || "");
        const name = String(family.nome || code);

        return {
            value: code,
            label: code + " - " + name,
        };
    });

    if (valueField) {
        valueField.value = explorerState.controls.family;
    }

    familyCombobox?.renderOptions(options);
    document.getElementById("explorer-include-invalid").checked = explorerState.controls.includeInvalid;
    document.getElementById("explorer-status").value = explorerState.controls.status;
    document.getElementById("explorer-page-size").value = String(explorerState.controls.pageSize);
    syncDraftInvalidControls();
    updateExplorerInvalidGuidance();
}

async function loadExplorerData() {
    if (!explorerState.controls.family) {
        return;
    }

    toggleLoading(true);
    setPageStatus("codeExplorer.runtime.loadingRows", "loading", "Loading family codes...");

    const params = new URLSearchParams({
        endpoint: "code-explorer",
        family: explorerState.controls.family,
        page: String(explorerState.controls.page),
        page_size: String(explorerState.controls.pageSize),
        search: explorerState.controls.search,
        status: explorerState.controls.status,
        include_invalid: explorerState.controls.includeInvalid ? "1" : "0",
    });

    if (explorerState.controls.searchMode === EXPLORER_SEARCH_MODE_NAME) {
        EXPLORER_SEGMENT_FILTER_META.forEach((meta) => {
            const value = explorerState.controls.segmentFilters?.[meta.key] || "";

            if (value !== "") {
                params.set(meta.key, value);
            }
        });
    }

    try {
        const data = await apiFetch("/?" + params.toString());
        explorerState.data = data;
        explorerState.controls.page = data.pagination?.page || explorerState.controls.page;

        const firstRow = Array.isArray(data.rows) && data.rows.length > 0 ? data.rows[0] : null;
        const selectedExists = data.rows?.some((row) => row.reference === explorerState.selectedReference);
        explorerState.selectedReference = selectedExists ? explorerState.selectedReference : (firstRow?.reference || "");

        renderSummary(data);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();

        if (data.pagination.total_rows > 0) {
            setPageStatus("codeExplorer.runtime.loadedRows", "success", "Code Explorer results loaded.");
        } else {
            setPageStatus("codeExplorer.runtime.noRows", "neutral", "No rows match current filters.");
        }
    } catch (error) {
        explorerState.data = null;
        explorerState.selectedReference = "";
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "error", "Unable to load explorer data right now: {message}", {
            message: getExplorerErrorMessage(error),
        });

        if (error.status >= 500 || error.status === 401 || error.status === 403) {
            setApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        }
    } finally {
        toggleLoading(false);
    }
}

function toggleLoading(isLoading) {
    const loading = document.getElementById("explorer-loading");

    if (!loading) {
        return;
    }

    loading.classList.toggle("hidden", !isLoading);
}

function renderSummary(data) {
    const summary = data?.summary || {
        total_codes: 0,
        configurator_valid: 0,
        configurator_invalid: 0,
        datasheet_ready: 0,
        datasheet_blocked: 0,
    };

    document.getElementById("summary-total").textContent = formatNumber(summary.total_codes);
    document.getElementById("summary-configurator").textContent = formatNumber(summary.configurator_valid);
    document.getElementById("summary-invalid").textContent = formatNumber(summary.configurator_invalid);
    document.getElementById("summary-ready").textContent = formatNumber(summary.datasheet_ready);
    document.getElementById("summary-blocked").textContent = formatNumber(summary.datasheet_blocked);
    syncAppliedInvalidSummaryCard();
}

function renderTable() {
    const body = document.getElementById("explorer-rows");
    const tableRoot = document.getElementById("explorer-data-table");
    const emptyState = document.getElementById("explorer-empty-state");
    const rows = explorerState.data?.rows || [];
    const valueUnavailable = t("codeExplorer.valueUnavailable", {}, "Not available");

    if (!body || !tableRoot) {
        return;
    }

    if (rows.length === 0) {
        body.innerHTML = "";
        tableRoot.classList.add("hidden");
        emptyState?.classList.remove("hidden");
        requestAnimationFrame(syncExplorerTableHeadColumns);
        return;
    }

    tableRoot.classList.remove("hidden");
    emptyState?.classList.add("hidden");
    body.innerHTML = rows.map((row) => {
        const datasheetStatus = row.configurator_valid
            ? buildStatusBadge(row.datasheet_ready, t("codeExplorer.statusReadyShort", {}, "Ready"), t("codeExplorer.statusBlockedShort", {}, "Blocked"))
            : buildNeutralBadge(t("codeExplorer.statusNotApplicableShort", {}, "N/A"));
        const description = row.description || valueUnavailable;
        const productType = row.product_type || valueUnavailable;
        const productId = row.product_id || valueUnavailable;
        const failureReason = getFailureReasonText(row.failure_reason);

        return `
            <tr class="data-table-row">
                <td class="data-table-cell" data-sort-value="${escapeHtml(row.reference)}">
                    <button type="button" class="link link-sm text-left break-all" data-reference="${escapeHtml(row.reference)}">
                        <span class="font-mono">${escapeHtml(row.reference)}</span>
                    </button>
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(row.identity || "")}">
                    <span class="font-mono">${escapeHtml(row.identity || "")}</span>
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(description)}">
                    ${escapeHtml(description)}
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(productType)}">
                    ${escapeHtml(productType)}
                </td>
                <td class="data-table-cell break-all" data-sort-value="${escapeHtml(productId)}">
                    <span class="break-all">${escapeHtml(productId)}</span>
                </td>
                <td class="data-table-cell" data-sort-value="${row.configurator_valid ? "valid" : "invalid"}">${buildStatusBadge(row.configurator_valid, t("codeExplorer.statusConfiguratorValidShort", {}, "Valid"), t("codeExplorer.statusConfiguratorInvalidShort", {}, "Invalid"))}</td>
                <td class="data-table-cell" data-sort-value="${row.configurator_valid ? (row.datasheet_ready ? "ready" : "blocked") : "na"}">${datasheetStatus}</td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(failureReason)}">
                    ${escapeHtml(failureReason)}
                </td>
            </tr>
        `;
    }).join("");

    requestAnimationFrame(syncExplorerTableHeadColumns);
}

function renderDetail() {
    const detail = document.getElementById("explorer-detail");
    const empty = document.getElementById("explorer-detail-empty");
    const row = getSelectedRow();
    const valueUnavailable = t("codeExplorer.valueUnavailable", {}, "Not available");

    if (!row) {
        detail.classList.add("hidden");
        empty.classList.remove("hidden");
        return;
    }

    empty.classList.add("hidden");
    detail.classList.remove("hidden");

    document.getElementById("detail-reference").textContent = row.reference || "";
    document.getElementById("detail-identity").textContent = row.identity || "";
    document.getElementById("detail-description").textContent = row.description || valueUnavailable;
    document.getElementById("detail-type").textContent = row.product_type || valueUnavailable;
    document.getElementById("detail-product-id").textContent = row.product_id || valueUnavailable;

    document.getElementById("detail-segments").innerHTML = SEGMENT_META.map((segment) => {
        return `
            <div class="flex items-start justify-between gap-12 rounded-card border border-grey-quaternary/60 bg-white px-12 py-10">
                <span class="text-body-sm text-grey-primary">${escapeHtml(t(segment.labelKey, {}, segment.fallback))}</span>
                <code class="text-body-sm font-mono text-black text-right">${escapeHtml(getSegmentDisplay(row, segment.key))}</code>
            </div>
        `;
    }).join("");

    document.getElementById("detail-statuses").innerHTML = [
        buildStatusBadge(row.configurator_valid, t("codeExplorer.statusConfiguratorValid", {}, "Configurator valid"), t("codeExplorer.statusConfiguratorInvalid", {}, "Configurator invalid")),
        row.configurator_valid
            ? buildStatusBadge(row.datasheet_ready, t("codeExplorer.statusDatasheetReady", {}, "Datasheet ready"), t("codeExplorer.statusDatasheetBlocked", {}, "Datasheet blocked"))
            : buildNeutralBadge(t("codeExplorer.statusNotApplicable", {}, "Not applicable")),
    ].join("");

    document.getElementById("detail-failure").textContent = row.failure_reason
        ? getFailureReasonText(row.failure_reason)
        : t("codeExplorer.failure.none", {}, "No blocking reason.");
}

function openCodeDetailModal(trigger) {
    const overlay = document.getElementById("codeExplorerDetailModal");

    if (!overlay || !getSelectedRow()) {
        return;
    }

    document.querySelectorAll(".modal-overlay").forEach((otherOverlay) => {
        if (otherOverlay === overlay) {
            return;
        }

        otherOverlay.classList.remove("is-open");
        otherOverlay.classList.remove("is-visible");
        otherOverlay.setAttribute("aria-hidden", "true");
        otherOverlay.inert = true;
    });

    overlay.inert = false;
    overlay.classList.add("is-open");
    overlay.classList.remove("is-visible");
    overlay.setAttribute("aria-hidden", "false");
    overlay._lastTrigger = trigger || null;

    document.body.classList.toggle(
        "modal-open",
        Array.from(document.querySelectorAll(".modal-overlay")).some((item) => item.classList.contains("is-open"))
    );

    requestAnimationFrame(() => {
        const initialFocus = overlay.querySelector("[data-modal-initial-focus]")
            || overlay.querySelector(".modal");

        initialFocus?.focus({ preventScroll: true });
    });
}

function renderResultsMeta() {
    const meta = document.getElementById("explorer-results-meta");

    if (!meta) {
        return;
    }

    if (!explorerState.controls.family) {
        meta.textContent = "";
        meta.classList.add("hidden");
        return;
    }

    if (!explorerState.data) {
        meta.textContent = t("codeExplorer.runtime.loadFailed", {}, "Unable to load explorer data right now.");
        meta.classList.remove("hidden");
        return;
    }

    const familyLabel = explorerState.data.family
        ? `${explorerState.data.family.code} - ${explorerState.data.family.name}`
        : explorerState.controls.family;

    meta.textContent = t("codeExplorer.runtime.resultsMeta", {
        family: familyLabel,
        total: explorerState.data.pagination.total_rows,
    }, `${familyLabel} - ${explorerState.data.pagination.total_rows} filtered rows`);
    meta.classList.remove("hidden");
}

function renderPagination() {
    const wrapper = document.getElementById("explorer-pagination-wrap");
    const pagination = document.getElementById("explorer-pagination");
    const list = pagination?.querySelector(".pagination-list");
    const prev = pagination?.querySelector("[data-pagination-prev]");
    const next = pagination?.querySelector("[data-pagination-next]");
    const hasRows = (explorerState.data?.rows?.length || 0) > 0;
    const totalPages = Math.max(explorerState.data?.pagination?.total_pages || 1, 1);
    const shouldShowPagination = hasRows && totalPages >= 2;

    if (!list || !prev || !next) {
        return;
    }

    if (wrapper) {
        wrapper.classList.toggle("hidden", !shouldShowPagination);
    }

    if (!explorerState.data) {
        list.innerHTML = [
            '<li class="pagination-item">',
            '<button type="button" class="pagination-link" data-page="1" aria-current="page">1</button>',
            "</li>",
        ].join("");
        syncExplorerPaginationControl(prev, true);
        syncExplorerPaginationControl(next, true);
        window.requestAnimationFrame(() => {
            list.scrollTo({ left: 0, behavior: "auto" });
        });
        return;
    }

    const page = getExplorerCurrentPage();

    list.innerHTML = Array.from({ length: Math.max(totalPages, 1) }, (_, index) => {
        const pageNumber = index + 1;
        const current = pageNumber === page ? ' aria-current="page"' : "";

        return [
            '<li class="pagination-item">',
            `<button type="button" class="pagination-link" data-page="${pageNumber}"${current}>${pageNumber}</button>`,
            "</li>",
        ].join("");
    }).join("");
    syncExplorerPaginationControl(prev, page <= 1);
    syncExplorerPaginationControl(next, page >= totalPages);
    revealExplorerActivePage(list);
}

function syncExplorerTableHeadColumns() {
    const tableRoot = document.getElementById("explorer-data-table");
    const table = tableRoot?.querySelector(".data-table-table");
    const body = table?.tBodies?.[0];

    if (!tableRoot || !table || !body) {
        return;
    }

    const firstRow = body.rows[0];

    if (!firstRow) {
        tableRoot.style.setProperty("--data-table-head-columns", EXPLORER_TABLE_HEAD_FALLBACK_COLUMNS);
        return;
    }

    const widths = Array.from(firstRow.cells)
        .map((cell) => Math.ceil(cell.getBoundingClientRect().width))
        .filter((width) => width > 0);

    if (widths.length !== firstRow.cells.length) {
        tableRoot.style.setProperty("--data-table-head-columns", EXPLORER_TABLE_HEAD_FALLBACK_COLUMNS);
        return;
    }

    tableRoot.style.setProperty("--data-table-head-columns", widths.map((width) => `${width}px`).join(" "));
}

function getExplorerCurrentPage() {
    const dataPage = Number.parseInt(String(explorerState.data?.pagination?.page || ""), 10);
    const controlPage = Number.parseInt(String(explorerState.controls.page || ""), 10);

    if (Number.isFinite(dataPage) && dataPage > 0) {
        return dataPage;
    }

    if (Number.isFinite(controlPage) && controlPage > 0) {
        return controlPage;
    }

    return 1;
}

function syncExplorerPaginationControl(button, disabled) {
    if (!button) {
        return;
    }

    button.disabled = disabled;
    button.setAttribute("aria-disabled", disabled ? "true" : "false");
}

function revealExplorerActivePage(list) {
    if (!list) {
        return;
    }

    const activeButton = list.querySelector('[aria-current="page"]');

    if (!activeButton) {
        return;
    }

    const maxScrollLeft = list.scrollWidth - list.clientWidth;

    if (maxScrollLeft <= 0) {
        return;
    }

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const nextScrollLeft = activeButton.offsetLeft - ((list.clientWidth - activeButton.offsetWidth) / 2);

    window.requestAnimationFrame(() => {
        list.scrollTo({
            left: Math.min(Math.max(0, nextScrollLeft), maxScrollLeft),
            behavior: reduceMotion ? "auto" : "smooth",
        });
    });
}

function getSelectedRow() {
    const rows = explorerState.data?.rows || [];
    return rows.find((row) => row.reference === explorerState.selectedReference) || null;
}

function getSegmentDisplay(row, key) {
    const value = row.segments?.[key] ?? "";
    const label = row.segment_labels?.[key];

    if (!label || label === value) {
        return String(value);
    }

    return `${value} - ${label}`;
}

function getFailureReasonText(reason) {
    if (!reason) {
        return t("codeExplorer.failure.none", {}, "No blocking reason.");
    }

    const key = FAILURE_REASON_KEYS[reason];
    return key ? t(key, {}, reason) : reason;
}

function buildStatusBadge(isPositive, positiveLabel, negativeLabel) {
    const toneClass = isPositive ? "badge-success" : "badge-warning";
    const label = isPositive ? positiveLabel : negativeLabel;
    return `<span class="badge ${toneClass} badge-sm">${escapeHtml(label)}</span>`;
}

function buildNeutralBadge(label) {
    return `<span class="badge badge-neutral badge-sm">${escapeHtml(label)}</span>`;
}

function setApiBadge(tone, key, fallback) {
    apiBadgeState = { tone, key, fallback };
    renderApiBadge();
}

function renderApiBadge() {
    document.querySelectorAll("[data-api-badge]").forEach((element) => {
        element.className = `${API_BADGE_BASE_CLASS} ${API_BADGE_VARIANT_CLASS[apiBadgeState.tone] || API_BADGE_VARIANT_CLASS.error}`;
    });

    document.querySelectorAll("[data-api-badge-text]").forEach((element) => {
        element.textContent = t(apiBadgeState.key, {}, apiBadgeState.fallback);
    });
}

function setPageStatus(key, tone = "neutral", fallback, vars = {}) {
    pageStatusState = { key, tone, fallback, vars };
    applyPageStatusState();
}

function applyPageStatusState() {
    if (!pageStatusState) {
        return;
    }

    applyStatusText(
        t(pageStatusState.key, pageStatusState.vars, pageStatusState.fallback),
        pageStatusState.tone,
        pageStatusState.key
    );
}

function applyStatusText(message, tone = "neutral", key = "") {
    const toast = document.getElementById("status-message");
    const copy = document.getElementById("status-message-copy");
    const icon = document.getElementById("status-message-icon");
    const variant = STATUS_TOAST_VARIANT[tone] || STATUS_TOAST_VARIANT.neutral;
    const shouldHide = !message || (tone === "neutral" && key === "codeExplorer.runtime.chooseFamily");

    if (!toast || !copy || !icon) {
        return;
    }

    copy.textContent = message;
    toast.className = STATUS_TOAST_BASE_CLASS + " " + variant.className;
    toast.setAttribute("role", variant.role);
    icon.className = variant.iconClass + " text-icon-lg";

    if (shouldHide) {
        hideStatusToast(true);
        return;
    }

    showStatusToast(variant.autoHide ? STATUS_TOAST_AUTOHIDE_DELAY : 0);
}

function showStatusToast(dismissDelay = 0) {
    const toast = document.getElementById("status-message");

    if (!toast) {
        return;
    }

    clearStatusToastTimers();
    toast.hidden = false;
    toast.inert = false;
    toast.setAttribute("aria-hidden", "false");

    requestAnimationFrame(() => {
        toast.classList.add("is-visible");
    });

    if (dismissDelay > 0) {
        statusToastTimers.dismiss = setTimeout(() => {
            hideStatusToast();
        }, dismissDelay);
    }
}

function hideStatusToast(immediate = false) {
    const toast = document.getElementById("status-message");

    if (!toast) {
        return;
    }

    clearStatusToastTimers();
    toast.classList.remove("is-visible");
    toast.setAttribute("aria-hidden", "true");

    if (immediate) {
        toast.hidden = true;
        toast.inert = true;
        return;
    }

    statusToastTimers.hide = setTimeout(() => {
        toast.hidden = true;
        toast.inert = true;
        statusToastTimers.hide = null;
    }, STATUS_TOAST_HIDE_DELAY);
}

function clearStatusToastTimers() {
    if (statusToastTimers.dismiss) {
        clearTimeout(statusToastTimers.dismiss);
        statusToastTimers.dismiss = null;
    }

    if (statusToastTimers.hide) {
        clearTimeout(statusToastTimers.hide);
        statusToastTimers.hide = null;
    }
}

function t(key, vars = {}, fallback = "") {
    return window.NexLedI18n?.t?.(key, vars, fallback) || fallback;
}

function formatNumber(value) {
    return new Intl.NumberFormat(window.NexLedI18n?.getLanguage?.() === "pt" ? "pt-PT" : "en-US").format(Number(value) || 0);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

