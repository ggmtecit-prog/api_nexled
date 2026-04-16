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
const EXPLORER_VIEW_MODE_SEARCH = "search";
const EXPLORER_VIEW_MODE_FILTERS = "filters";
const EXPLORER_SEARCH_TYPE_CODE = "code";
const EXPLORER_SEARCH_TYPE_DESCRIPTION = "description";

let apiBasePromise = null;
let hasSuccessfulApiContact = false;
let explorerSelectDropdowns = new Map();
let activeExplorerSelectDropdown = null;
let explorerSelectDropdownEventsBound = false;
let apiBadgeState = {
    tone: "error",
    key: "shared.badge.apiUnavailable",
    fallback: "API unavailable",
};
let pageStatusState = {
    tone: "neutral",
    key: "codeExplorer.runtime.awaitingSearch",
    fallback: "Enter a Tecit code or product description.",
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
    coverageExpandedIdentity: "",
    coverageDetailsByIdentity: {},
    detailPdfSpecsByKey: {},
    controls: {
        viewMode: EXPLORER_VIEW_MODE_SEARCH,
        searchType: EXPLORER_SEARCH_TYPE_CODE,
        family: "",
        search: "",
        status: "all",
        page: 1,
        pageSize: 5,
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
    setupExplorerSelectDropdowns();
    bindControls();
    bindStaticEvents();
    renderApiBadge();
    applyPageStatusState();
    renderExplorerSegmentFilterOptions(null);
    applyExplorerViewMode(explorerState.controls.viewMode);
    renderCoverage(null);
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
        refreshExplorerSelectDropdown(meta.selectId);
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
    guidance.classList.add("hidden");
}

function getExplorerErrorMessage(error) {
    const reason = error?.payload?.reason;
    const mappedKey = reason ? EXPLORER_ERROR_REASON_KEYS[reason] : "";

    if (mappedKey) {
        return t(mappedKey, {}, error?.payload?.message || error?.message || mappedKey);
    }

    return error?.payload?.message || error?.message || t("codeExplorer.runtime.unknownError", {}, "Unknown error");
}

function getExplorerViewModeFromForm() {
    return document.querySelector('input[name="explorer-view-mode"]:checked')?.value || EXPLORER_VIEW_MODE_SEARCH;
}

function detectExplorerSearchType(search = explorerState.controls.search) {
    const normalizedSearch = String(search ?? "").replace(/\s+/g, "").toUpperCase();

    return /^\d{2,17}$/.test(normalizedSearch)
        ? EXPLORER_SEARCH_TYPE_CODE
        : EXPLORER_SEARCH_TYPE_DESCRIPTION;
}

function normalizeExplorerDescriptionText(value) {
    return String(value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, " ")
        .trim();
}

function getExplorerDescriptionSubject(search = explorerState.controls.search) {
    return normalizeExplorerDescriptionText(search).replace(/^lled\b\s*/, "").trim();
}

function getExplorerDescriptionCandidateFamilies(
    search = explorerState.controls.search,
    families = explorerState.families
) {
    if (!Array.isArray(families) || families.length === 0) {
        return [];
    }

    const subject = getExplorerDescriptionSubject(search);

    if (subject === "") {
        return families;
    }

    const searchTokens = subject.split(" ").filter(Boolean);
    const matches = [];
    let bestPrefixMatch = 0;

    families.forEach((family) => {
        const familyCode = String(family?.codigo || "");
        const familyName = normalizeExplorerDescriptionText(family?.nome || "");
        const familyTokens = familyName.split(" ").filter(Boolean);

        if (familyCode === "" || familyTokens.length === 0) {
            return;
        }

        let prefixMatch = 0;

        while (
            prefixMatch < familyTokens.length
            && prefixMatch < searchTokens.length
            && familyTokens[prefixMatch] === searchTokens[prefixMatch]
        ) {
            prefixMatch += 1;
        }

        const exactPrefix = subject === familyName || subject.startsWith(familyName + " ");

        if (!exactPrefix && prefixMatch === 0) {
            return;
        }

        bestPrefixMatch = Math.max(bestPrefixMatch, exactPrefix ? familyTokens.length : prefixMatch);
        matches.push({
            family,
            exactPrefix,
            prefixMatch,
            familyTokenCount: familyTokens.length,
        });
    });

    const exactPrefixMatches = matches.filter((entry) => entry.exactPrefix);

    if (exactPrefixMatches.length > 0) {
        const longestExactPrefix = exactPrefixMatches.reduce((maximum, entry) => {
            return Math.max(maximum, entry.familyTokenCount);
        }, 0);

        return exactPrefixMatches
            .filter((entry) => entry.familyTokenCount === longestExactPrefix)
            .map((entry) => entry.family);
    }

    if (bestPrefixMatch >= 2) {
        return matches
            .filter((entry) => entry.prefixMatch === bestPrefixMatch)
            .map((entry) => entry.family);
    }

    if (bestPrefixMatch === 1) {
        return matches
            .filter((entry) => entry.prefixMatch === 1)
            .map((entry) => entry.family);
    }

    const firstToken = searchTokens[0] || "";
    const productTypeAliases = {
        barra: ["barra"],
        downlight: ["downlight"],
        dynamic: ["dynamic"],
        projetor: ["dynamic"],
        shelfled: ["shelf"],
        shelf: ["shelf"],
        spot: ["spot"],
        painel: ["panel"],
        panel: ["panel"],
        decoracao: ["decor"],
        decor: ["decor"],
        canopy: ["canopy"],
        tubular: ["tubular"],
        t8: ["tubular"],
        t5: ["tubular"],
        pll: ["tubular"],
        plc: ["tubular"],
        s14: ["tubular"],
    };
    const targetTypes = productTypeAliases[firstToken] || [];

    if (targetTypes.length > 0) {
        const typedFamilies = families.filter((family) => targetTypes.includes(String(family?.product_type || "").toLowerCase()));

        if (typedFamilies.length > 0) {
            return typedFamilies;
        }
    }

    return families;
}

function isExplorerFilterMode(viewMode = explorerState.controls.viewMode) {
    return viewMode === EXPLORER_VIEW_MODE_FILTERS;
}

function hasExplorerChunkItems(data = explorerState.data) {
    return Array.isArray(data?.chunk?.items) && data.chunk.items.length > 0;
}

function isLegacyFilterResponse(data = explorerState.data, viewMode = explorerState.controls.viewMode) {
    return isExplorerFilterMode(viewMode)
        && !Array.isArray(data?.chunk?.items)
        && Array.isArray(data?.rows)
        && data.rows.length > 0;
}

function shouldShowExplorerCodeResultsSection(
    viewMode = explorerState.controls.viewMode,
    data = explorerState.data
) {
    return !isExplorerFilterMode(viewMode) || isLegacyFilterResponse(data, viewMode);
}

function isGlobalExplorerDescriptionSearch(
    viewMode = explorerState.controls.viewMode,
    searchType = explorerState.controls.searchType,
    family = explorerState.controls.family
) {
    return !isExplorerFilterMode(viewMode)
        && searchType === EXPLORER_SEARCH_TYPE_DESCRIPTION
        && String(family || "").trim() === "";
}

function shouldShowExplorerFamilyField(
    viewMode = explorerState.controls.viewMode
) {
    return isExplorerFilterMode(viewMode);
}

function renderExplorerSearchInputCopy(
    viewMode = explorerState.controls.viewMode
) {
    const label = document.getElementById("explorer-search-label");
    const input = document.getElementById("explorer-search");
    const pageSizeLabel = document.getElementById("explorer-page-size-label");

    if (label) {
        label.textContent = t("codeExplorer.searchInputLabel", {}, "Search");
    }

    if (input) {
        input.placeholder = t("codeExplorer.searchInputPlaceholder", {}, "Enter a code or description");
    }

    if (pageSizeLabel) {
        pageSizeLabel.textContent = isExplorerFilterMode(viewMode)
            ? t("codeExplorer.pageSizeChunkLabel", {}, "Base combos per page")
            : t("codeExplorer.pageSizeLabel", {}, "Rows per page");
    }
}

function applyExplorerViewMode(viewMode = explorerState.controls.viewMode) {
    const searchType = detectExplorerSearchType(document.getElementById("explorer-search")?.value || explorerState.controls.search);
    const showFilters = isExplorerFilterMode(viewMode);
    const textSearchField = document.getElementById("explorer-text-search-field");
    const familyField = document.getElementById("explorer-family-field");
    const chunkControlsRow = document.getElementById("explorer-chunk-controls-row");
    const includeInvalidField = document.getElementById("explorer-include-invalid-field");
    const drilldownField = document.getElementById("explorer-drilldown-field");
    const codeResultsSection = document.getElementById("explorer-code-results-section");

    explorerState.controls.viewMode = viewMode;
    explorerState.controls.searchType = searchType;

    if (textSearchField) {
        textSearchField.classList.toggle("hidden", showFilters);
    }

    if (familyField) {
        familyField.classList.toggle("hidden", !shouldShowExplorerFamilyField(viewMode));
    }

    if (chunkControlsRow) {
        chunkControlsRow.classList.toggle("hidden", !showFilters);
    }

    if (includeInvalidField) {
        includeInvalidField.classList.toggle("hidden", !showFilters);
    }

    if (drilldownField) {
        drilldownField.classList.toggle("hidden", !showFilters);
    }

    renderExplorerSearchInputCopy(viewMode);
    syncDraftInvalidControls();
    syncAppliedInvalidSummaryCard();
    updateExplorerInvalidGuidance();
    renderCoverage(explorerState.data);

    if (codeResultsSection) {
        codeResultsSection.classList.toggle(
            "hidden",
            !shouldShowExplorerCodeResultsSection(viewMode, explorerState.data)
        );
    }

    if (showFilters && explorerState.controls.family) {
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

function setupExplorerSelectDropdowns() {
    const selects = Array.from(document.querySelectorAll("select[id]"));

    selects.forEach((select) => {
        if (explorerSelectDropdowns.has(select.id)) {
            return;
        }

        const dropdown = createExplorerSelectDropdown(select);

        if (dropdown) {
            explorerSelectDropdowns.set(select.id, dropdown);
        }
    });

    bindExplorerSelectDropdownDocumentEvents();
}

function createExplorerSelectDropdown(select) {
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

    root.className = "dropdown dropdown-sm w-full";
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

    function updateValueDisplay(text, hasValue = true) {
        valueDisplay.textContent = text;
        root.classList.toggle("has-value", hasValue);
    }

    function closeDropdown({ restoreFocus = false } = {}) {
        root.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");

        if (activeExplorerSelectDropdown === api) {
            activeExplorerSelectDropdown = null;
        }

        if (restoreFocus) {
            trigger.focus();
        }
    }

    function openDropdown() {
        if (trigger.disabled) {
            return;
        }

        if (activeExplorerSelectDropdown && activeExplorerSelectDropdown !== api) {
            activeExplorerSelectDropdown.close();
        }

        root.classList.add("is-open");
        trigger.setAttribute("aria-expanded", "true");
        activeExplorerSelectDropdown = api;
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
            if (option.hidden) {
                return;
            }

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
            focusItem(itemElements.find((item) => item.getAttribute("aria-selected") === "true") || enabledItems[0]);
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            openDropdown();
            focusItem(itemElements.find((item) => item.getAttribute("aria-selected") === "true") || enabledItems[enabledItems.length - 1]);
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

function bindExplorerSelectDropdownDocumentEvents() {
    if (explorerSelectDropdownEventsBound) {
        return;
    }

    explorerSelectDropdownEventsBound = true;

    document.addEventListener("click", (event) => {
        if (!activeExplorerSelectDropdown) {
            return;
        }

        if (activeExplorerSelectDropdown.root.contains(event.target)) {
            return;
        }

        activeExplorerSelectDropdown.close();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape" || !activeExplorerSelectDropdown) {
            return;
        }

        activeExplorerSelectDropdown.close();
    });
}

function refreshExplorerSelectDropdown(id) {
    explorerSelectDropdowns.get(id)?.refreshOptions();
}

function syncExplorerSelectDropdown(id) {
    explorerSelectDropdowns.get(id)?.syncFromSelect();
}

function refreshExplorerSelectDropdowns() {
    explorerSelectDropdowns.forEach((dropdown) => {
        dropdown.refreshOptions();
    });
}

function bindControls() {
    document.getElementById("explorer-filters").addEventListener("submit", (event) => {
        event.preventDefault();
        applyFiltersFromForm();
    });

    document.getElementById("explorer-search").addEventListener("input", () => {
        updateExplorerInvalidGuidance();
    });

    document.querySelectorAll('input[name="explorer-view-mode"]').forEach((radio) => {
        radio.addEventListener("change", () => {
            if (!radio.checked) {
                return;
            }

            explorerState.data = null;
            explorerState.selectedReference = "";
            resetCoverageInteractions();
            applyExplorerViewMode(radio.value);
            renderSummary(null);
            renderTable();
            renderDetail();
            renderResultsMeta();
            renderPagination();
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
        explorerState.data = null;
        resetCoverageInteractions();
        renderExplorerSegmentFilterOptions(null);
        renderCoverage(null);
        renderPagination();

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

    document.getElementById("coverage-identity-list").addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-identity-toggle]");

        if (!trigger) {
            return;
        }

        void toggleCoverageIdentity(trigger.dataset.identity || "");
    });
}

async function applyFiltersFromForm() {
    const rawSearch = document.getElementById("explorer-search").value.trim();
    const viewMode = getExplorerViewModeFromForm();
    const isFilterMode = isExplorerFilterMode(viewMode);
    const searchType = isFilterMode
        ? EXPLORER_SEARCH_TYPE_CODE
        : detectExplorerSearchType(rawSearch);
    const search = isFilterMode ? "" : rawSearch;
    const previousFamily = explorerState.controls.family;
    const includeInvalid = isFilterMode
        ? document.getElementById("explorer-include-invalid").checked
        : false;
    let family = shouldShowExplorerFamilyField(viewMode)
        ? document.getElementById("explorer-family").value
        : "";

    if (!isFilterMode && searchType === EXPLORER_SEARCH_TYPE_CODE) {
        family = detectFamilyCodeFromReferenceSearch(search);
    }

    explorerState.controls.viewMode = viewMode;
    explorerState.controls.searchType = searchType;
    explorerState.controls.search = search;
    explorerState.controls.family = family;
    explorerState.controls.status = isFilterMode
        ? document.getElementById("explorer-status").value
        : "all";
    explorerState.controls.includeInvalid = includeInvalid;
    explorerState.controls.pageSize = isFilterMode
        ? (Number.parseInt(document.getElementById("explorer-page-size").value, 10) || 5)
        : 5;
    explorerState.controls.segmentFilters = isFilterMode
        ? readExplorerSegmentFiltersFromForm()
        : getEmptyExplorerSegmentFilters();
    explorerState.controls.page = 1;

    if (isFilterMode) {
        syncDraftInvalidControls();
    }

    const familyChanged = family !== previousFamily;

    if (shouldShowExplorerFamilyField(viewMode) && family) {
        if (familyChanged) {
            explorerState.controls.segmentFilters = getEmptyExplorerSegmentFilters();
        }

        try {
            await loadExplorerFamilyOptions(family, familyChanged);
            explorerState.controls.segmentFilters = readExplorerSegmentFiltersFromForm();
        } catch (error) {
            resetCoverageInteractions();
            explorerState.data = null;
            explorerState.selectedReference = "";
            renderCoverage(null);
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

    if (!isFilterMode && search === "") {
        resetCoverageInteractions();
        explorerState.data = null;
        explorerState.selectedReference = "";
        setPageStatus("codeExplorer.runtime.awaitingSearch", "neutral", "Enter a Tecit code or product description.");
        renderCoverage(null);
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        return;
    }

    if (!explorerState.controls.family && !isGlobalExplorerDescriptionSearch(viewMode, searchType, family)) {
        resetCoverageInteractions();
        explorerState.data = null;
        explorerState.selectedReference = "";
        setPageStatus(
            !shouldShowExplorerFamilyField(viewMode)
                ? "codeExplorer.runtime.searchCodeNeedsFamily"
                : "codeExplorer.runtime.chooseFamily",
            "neutral",
            !shouldShowExplorerFamilyField(viewMode)
                ? "Enter full code with family prefix, or use product description text."
                : isFilterMode
                    ? "Select one family to start building valid code rows."
                    : "Select one family to start building valid code rows."
        );
        renderCoverage(null);
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        return;
    }

    resetCoverageInteractions();
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

function resetCoverageInteractions() {
    explorerState.coverageExpandedIdentity = "";
    explorerState.coverageDetailsByIdentity = {};
}

function syncCoverageStateWithData(data) {
    const identities = Array.isArray(data?.chunk?.items) ? data.chunk.items : [];
    const availableIdentities = new Set(identities.map((identity) => identity.identity));

    if (!availableIdentities.has(explorerState.coverageExpandedIdentity)) {
        explorerState.coverageExpandedIdentity = "";
    }

    Object.keys(explorerState.coverageDetailsByIdentity).forEach((identityCode) => {
        if (!availableIdentities.has(identityCode)) {
            delete explorerState.coverageDetailsByIdentity[identityCode];
        }
    });
}

function renderCoverage(data) {
    const section = document.getElementById("explorer-coverage-section");
    const list = document.getElementById("coverage-identity-list");
    const currentViewMode = getExplorerViewModeFromForm();
    const shouldShow = isExplorerFilterMode(currentViewMode);
    const legacyFilterResponse = isLegacyFilterResponse(data, currentViewMode);
    const chunk = data?.chunk || null;
    const identities = Array.isArray(chunk?.items) ? chunk.items : [];
    const summary = data?.coverage?.summary || {
        total_identities: 0,
        current_chunk_identities: 0,
        fully_ready_identities: 0,
        partially_ready_identities: 0,
        blocked_identities: 0,
        invalid_identities: 0,
        datasheet_ready_ratio: 0,
    };

    if (!section || !list) {
        return;
    }

    section.classList.toggle("hidden", !shouldShow);

    if (!shouldShow) {
        list.innerHTML = "";
        renderCoverageSummary(summary);
        return;
    }

    renderCoverageSummary(summary);

    if (legacyFilterResponse) {
        list.innerHTML = "";
        return;
    }

    if (identities.length === 0) {
        list.innerHTML = "";
        return;
    }

    list.innerHTML = identities.map((identity) => buildCoverageIdentityCard(identity)).join("");
}

function renderCoverageSummary(summary) {
    document.getElementById("coverage-total-identities").textContent = formatNumber(summary.total_identities);
    document.getElementById("coverage-ready-identities").textContent = formatNumber(summary.fully_ready_identities);
    document.getElementById("coverage-partial-identities").textContent = formatNumber(summary.partially_ready_identities);
    document.getElementById("coverage-blocked-identities").textContent = formatNumber(summary.blocked_identities);
    document.getElementById("coverage-invalid-identities").textContent = formatNumber(summary.invalid_identities);
    document.getElementById("coverage-ready-rate").textContent = formatPercent(summary.datasheet_ready_ratio);
}

function buildCoverageIdentityCard(identity) {
    const identityCode = identity.identity || "";
    const counts = identity.counts || {};
    const isExpanded = explorerState.coverageExpandedIdentity === identityCode;
    const description = identity.description
        ? `<p class="text-body-sm text-black">${escapeHtml(identity.description)}</p>`
        : "";
    const productMeta = identity.product_type || identity.product_id
        ? `<p class="text-body-xs text-grey-primary">${escapeHtml([identity.product_type, identity.product_id].filter(Boolean).join(" / "))}</p>`
        : "";

    return `
        <div class="panel panel-sm flex flex-col gap-12">
            <button
                type="button"
                class="flex w-full flex-col gap-12 text-left"
                data-identity-toggle
                data-identity="${escapeHtml(identityCode)}"
                aria-expanded="${isExpanded ? "true" : "false"}"
            >
                <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-wrap items-center gap-8">
                            <code class="text-body-sm font-mono text-black break-all">${escapeHtml(identityCode)}</code>
                            ${buildStatusBadge(
                                identity.configurator_valid === true,
                                t("codeExplorer.statusConfiguratorValidShort", {}, "Valid"),
                                t("codeExplorer.statusConfiguratorInvalidShort", {}, "Invalid")
                            )}
                            ${identity.configurator_valid === true ? getCoverageIdentityStatusBadge(identity.status) : ""}
                        </div>
                        <p class="text-body-sm text-grey-primary">${escapeHtml(getCoverageIdentitySegmentSummary(identity))}</p>
                        ${description}
                        ${productMeta}
                    </div>
                    <div class="flex flex-wrap gap-8 lg:justify-end">
                        <span class="badge badge-success badge-sm">${escapeHtml(`${formatNumber(counts.datasheet_ready)} ${t("codeExplorer.coverageCountReady", {}, "ready")}`)}</span>
                        <span class="badge badge-warning badge-sm">${escapeHtml(`${formatNumber(counts.datasheet_blocked)} ${t("codeExplorer.coverageCountBlocked", {}, "blocked")}`)}</span>
                        ${counts.configurator_invalid > 0 ? `<span class="badge badge-danger badge-sm">${escapeHtml(`${formatNumber(counts.configurator_invalid)} ${t("codeExplorer.coverageCountInvalid", {}, "invalid")}`)}</span>` : ""}
                        <span class="badge badge-neutral badge-sm">${escapeHtml(`${formatPercent(identity.ready_ratio)} ${t("codeExplorer.coverageCountRate", {}, "ready")}`)}</span>
                    </div>
                </div>
            </button>
            ${isExpanded ? buildCoverageIdentityDetail(identity) : ""}
        </div>
    `;
}

function getCoverageIdentityStatusBadge(status) {
    const label = getCoverageIdentityStatusText(status);

    if (status === "fully_ready") {
        return `<span class="badge badge-success badge-sm">${escapeHtml(label)}</span>`;
    }

    if (status === "partially_ready") {
        return `<span class="badge badge-info badge-sm">${escapeHtml(label)}</span>`;
    }

    if (status === "blocked") {
        return `<span class="badge badge-warning badge-sm">${escapeHtml(label)}</span>`;
    }

    return `<span class="badge badge-danger badge-sm">${escapeHtml(label)}</span>`;
}

function getCoverageIdentityStatusText(status) {
    if (status === "fully_ready") {
        return t("codeExplorer.coverageStatusFullyReady", {}, "Fully ready");
    }

    if (status === "partially_ready") {
        return t("codeExplorer.coverageStatusPartial", {}, "Partial");
    }

    if (status === "blocked") {
        return t("codeExplorer.coverageStatusBlocked", {}, "Blocked");
    }

    return t("codeExplorer.coverageStatusInvalid", {}, "Invalid");
}

function getCoverageIdentitySegmentSummary(identity) {
    return [
        getCoverageIdentitySegmentDisplay(identity, "size"),
        getCoverageIdentitySegmentDisplay(identity, "color"),
        getCoverageIdentitySegmentDisplay(identity, "cri"),
        getCoverageIdentitySegmentDisplay(identity, "series"),
    ].filter(Boolean).join(" / ");
}

function getCoverageIdentitySegmentDisplay(identity, key) {
    const value = identity.segments?.[key] ?? "";
    const label = identity.segment_labels?.[key];

    if (!label || label === value) {
        return String(value);
    }

    return `${value} - ${label}`;
}

function buildCoverageIdentityDetail(identity) {
    const identityCode = identity.identity || "";
    const detailState = explorerState.coverageDetailsByIdentity[identityCode] || null;

    if (!detailState || detailState.status === "loading") {
        return `
            <div class="rounded-card border border-grey-quaternary/60 bg-grey-tertiary/40 px-16 py-14">
                <p class="text-body-sm text-grey-primary">${escapeHtml(t("codeExplorer.coverageDetailLoading", {}, "Loading codes for this identity..."))}</p>
            </div>
        `;
    }

    if (detailState.status === "error") {
        return `
            <div class="rounded-card border border-red-200 bg-red-50 px-16 py-14">
                <p class="text-body-sm text-red-700">${escapeHtml(detailState.errorMessage || t("codeExplorer.coverageDetailError", {}, "Unable to load identity codes right now."))}</p>
            </div>
        `;
    }

    if ((detailState.rows?.length || 0) === 0) {
        return `
            <div class="rounded-card border border-grey-quaternary/60 bg-grey-tertiary/40 px-16 py-14">
                <p class="text-body-sm text-grey-primary">${escapeHtml(t("codeExplorer.coverageDetailEmpty", {}, "No codes found under this identity."))}</p>
            </div>
        `;
    }

    const totalDetailCodes = detailState.totalRows || detailState.rows.length;
    const truncated = detailState.totalRows > detailState.rows.length || detailState.totalPages > 1;
    const truncatedCopy = truncated
        ? `<p class="text-body-xs text-grey-primary">${escapeHtml(t("codeExplorer.coverageDetailTruncated", {
            shown: detailState.rows.length,
            total: detailState.totalRows,
        }, `Showing ${detailState.rows.length} of ${detailState.totalRows} codes.`))}</p>`
        : "";

    return `
        <div class="flex flex-col gap-12 border-t border-grey-quaternary/60 pt-12">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-8">
                    <span class="input-label">${escapeHtml(t("codeExplorer.coverageDetailTitle", {}, "Full codes in this identity"))}</span>
                    <span class="badge badge-neutral badge-sm">${escapeHtml(formatNumber(totalDetailCodes))}</span>
                </div>
                ${truncatedCopy}
            </div>
            <div class="flex max-h-96 flex-col gap-8 overflow-y-auto pr-4">
                ${detailState.rows.map((row) => buildCoverageCodeRow(row)).join("")}
            </div>
        </div>
    `;
}

function buildCoverageCodeRow(row) {
    const configuratorBadge = buildStatusBadge(
        row.configurator_valid === true,
        t("codeExplorer.statusConfiguratorValidShort", {}, "Valid"),
        t("codeExplorer.statusConfiguratorInvalidShort", {}, "Invalid")
    );
    const datasheetBadge = row.configurator_valid
        ? buildStatusBadge(
            row.datasheet_ready,
            t("codeExplorer.statusReadyShort", {}, "Ready"),
            t("codeExplorer.statusBlockedShort", {}, "Blocked")
        )
        : buildNeutralBadge(t("codeExplorer.statusNotApplicableShort", {}, "N/A"));
    const failureText = row.datasheet_ready
        ? t("codeExplorer.failure.none", {}, "No blocking reason.")
        : getFailureReasonText(row.failure_reason);

    return `
        <div class="rounded-card border border-grey-quaternary/60 bg-white px-12 py-12">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex flex-col gap-6">
                    <code class="text-body-sm font-mono text-black break-all">${escapeHtml(row.reference || "")}</code>
                    <p class="text-body-xs text-grey-primary">${escapeHtml(formatCoverageSuffix(row))}</p>
                    <p class="text-body-xs text-grey-primary">${escapeHtml(failureText)}</p>
                </div>
                <div class="flex flex-wrap gap-8">
                    ${configuratorBadge}
                    ${datasheetBadge}
                </div>
            </div>
        </div>
    `;
}

function formatCoverageSuffix(row) {
    return [
        getSegmentDisplay(row, "lens"),
        getSegmentDisplay(row, "finish"),
        getSegmentDisplay(row, "cap"),
        getSegmentDisplay(row, "option"),
    ].filter(Boolean).join(" / ");
}

async function toggleCoverageIdentity(identityCode) {
    if (!identityCode) {
        return;
    }

    if (explorerState.coverageExpandedIdentity === identityCode) {
        explorerState.coverageExpandedIdentity = "";
        renderCoverage(explorerState.data);
        return;
    }

    explorerState.coverageExpandedIdentity = identityCode;
    renderCoverage(explorerState.data);

    if (explorerState.coverageDetailsByIdentity[identityCode]) {
        return;
    }

    await loadCoverageIdentityCodes(identityCode);
}

async function loadCoverageIdentityCodes(identityCode) {
    const family = explorerState.controls.family;

    if (!family || !identityCode) {
        return;
    }

    explorerState.coverageDetailsByIdentity[identityCode] = {
        status: "loading",
        rows: [],
        totalRows: 0,
        totalPages: 0,
        errorMessage: "",
    };
    renderCoverage(explorerState.data);

    const params = new URLSearchParams({
        endpoint: "code-explorer",
        family,
        mode: "search",
        page: "1",
        page_size: "250",
        search: identityCode,
        search_type: "code",
        status: explorerState.controls.status || "all",
        include_invalid: explorerState.controls.includeInvalid ? "1" : "0",
    });

    try {
        const data = await apiFetch("/?" + params.toString());

        if (family !== explorerState.controls.family) {
            return;
        }

        explorerState.coverageDetailsByIdentity[identityCode] = {
            status: "ready",
            rows: Array.isArray(data?.rows) ? data.rows : [],
            totalRows: Number.parseInt(String(data?.pagination?.total_rows || 0), 10) || 0,
            totalPages: Number.parseInt(String(data?.pagination?.total_pages || 0), 10) || 0,
            errorMessage: "",
        };
    } catch (error) {
        if (family !== explorerState.controls.family) {
            return;
        }

        explorerState.coverageDetailsByIdentity[identityCode] = {
            status: "error",
            rows: [],
            totalRows: 0,
            totalPages: 0,
            errorMessage: getExplorerErrorMessage(error),
        };
    }

    renderCoverage(explorerState.data);
}

function bindStaticEvents() {
    const closeButton = document.getElementById("status-message-close");
    const loadPdfSpecsButton = document.getElementById("detail-load-pdf-specs");

    if (closeButton) {
        closeButton.addEventListener("click", () => {
            hideStatusToast(true);
        });
    }

    if (loadPdfSpecsButton) {
        loadPdfSpecsButton.addEventListener("click", () => {
            void loadDetailPdfSpecs();
        });
    }

    window.addEventListener(I18N_EVENT, () => {
        renderApiBadge();
        applyPageStatusState();
        applyExplorerViewMode(explorerState.controls.viewMode);
        renderExplorerSegmentFilterOptions(explorerState.familyOptionsByFamily[explorerState.controls.family] || null);
        refreshExplorerSelectDropdowns();
        renderResultsMeta();
        renderCoverage(explorerState.data);
        renderSummary(explorerState.data);
        renderTable();
        renderDetail();
        renderPagination();
    });

    window.addEventListener("resize", () => {
        syncExplorerTableHeadColumns();
        syncExplorerEmptyStateHeight();
    });
}

function syncExplorerEmptyStateHeight() {
    const resultsCardBody = document.getElementById("explorer-results-card-body");
    const filtersCard = document.getElementById("explorer-filters-card");
    const emptyState = document.getElementById("explorer-empty-state");
    const resultsSection = document.getElementById("explorer-code-results-section");

    if (!resultsCardBody) {
        return;
    }

    if (
        !filtersCard ||
        !emptyState ||
        emptyState.classList.contains("hidden") ||
        resultsSection?.classList.contains("hidden")
    ) {
        resultsCardBody.style.minHeight = "";
        return;
    }

    const filtersHeight = Math.ceil(filtersCard.getBoundingClientRect().height);
    resultsCardBody.style.minHeight = filtersHeight > 0 ? `${filtersHeight}px` : "";
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

    refreshExplorerSelectDropdown("explorer-status");
}

function syncAppliedInvalidSummaryCard() {
    const invalidSummaryCard = document.getElementById("summary-invalid-card");

    if (invalidSummaryCard) {
        invalidSummaryCard.classList.toggle(
            "hidden",
            !isExplorerFilterMode(explorerState.controls.viewMode) || explorerState.controls.includeInvalid !== true
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
            explorerState.controls.viewMode === EXPLORER_VIEW_MODE_SEARCH
                ? "codeExplorer.runtime.awaitingSearch"
                : "codeExplorer.runtime.chooseFamily",
            "neutral",
            explorerState.controls.viewMode === EXPLORER_VIEW_MODE_SEARCH
                ? "Enter a Tecit code or product description."
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
    syncExplorerSelectDropdown("explorer-status");
    syncExplorerSelectDropdown("explorer-page-size");
    syncDraftInvalidControls();
    updateExplorerInvalidGuidance();
}

async function loadExplorerData() {
    if (isGlobalExplorerDescriptionSearch()) {
        const candidateFamilies = getExplorerDescriptionCandidateFamilies();
        const narrowedFamilyCount = Array.isArray(candidateFamilies) ? candidateFamilies.length : 0;
        const totalFamilyCount = Array.isArray(explorerState.families) ? explorerState.families.length : 0;

        toggleLoading(true);
        setPageStatus(
            "codeExplorer.runtime.loadingDescriptionSearch",
            "loading",
            narrowedFamilyCount > 0 && narrowedFamilyCount < totalFamilyCount
                ? `Searching descriptions in ${narrowedFamilyCount} likely families...`
                : "Searching descriptions across all families..."
        );

        try {
            const data = await loadGlobalDescriptionSearchData(candidateFamilies);
            explorerState.data = data;
            explorerState.controls.page = data.pagination?.page || explorerState.controls.page;
            syncCoverageStateWithData(data);

            const firstRow = Array.isArray(data.rows) && data.rows.length > 0 ? data.rows[0] : null;
            const selectedExists = data.rows?.some((row) => row.reference === explorerState.selectedReference);
            explorerState.selectedReference = selectedExists ? explorerState.selectedReference : (firstRow?.reference || "");

            renderCoverage(data);
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
            resetCoverageInteractions();
            explorerState.data = null;
            explorerState.selectedReference = "";
            renderCoverage(null);
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
        return;
    }

    if (!explorerState.controls.family) {
        return;
    }

    toggleLoading(true);
    setPageStatus("codeExplorer.runtime.loadingRows", "loading", "Loading family codes...");

    const params = new URLSearchParams({
        endpoint: "code-explorer",
        family: explorerState.controls.family,
        mode: explorerState.controls.viewMode,
        page: String(explorerState.controls.page),
        page_size: String(explorerState.controls.pageSize),
        search: explorerState.controls.search,
        search_type: explorerState.controls.searchType,
        status: explorerState.controls.status,
        include_invalid: explorerState.controls.includeInvalid ? "1" : "0",
    });

    if (isExplorerFilterMode(explorerState.controls.viewMode)) {
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
        syncCoverageStateWithData(data);

        const firstRow = Array.isArray(data.rows) && data.rows.length > 0 ? data.rows[0] : null;
        const selectedExists = data.rows?.some((row) => row.reference === explorerState.selectedReference);
        explorerState.selectedReference = selectedExists ? explorerState.selectedReference : (firstRow?.reference || "");

        renderCoverage(data);
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
        resetCoverageInteractions();
        explorerState.data = null;
        explorerState.selectedReference = "";
        renderCoverage(null);
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

async function fetchGlobalDescriptionRowsForFamily(familyCode) {
    const pageSize = 250;
    const baseParams = {
        endpoint: "code-explorer",
        family: familyCode,
        mode: EXPLORER_VIEW_MODE_SEARCH,
        page_size: String(pageSize),
        search: explorerState.controls.search,
        search_type: EXPLORER_SEARCH_TYPE_DESCRIPTION,
        status: explorerState.controls.status,
        include_invalid: "0",
    };
    const rows = [];
    let currentPage = 1;
    let firstPayload = null;
    let totalPages = 1;

    while (currentPage <= totalPages) {
        const params = new URLSearchParams({
            ...baseParams,
            page: String(currentPage),
        });
        const payload = await apiFetch("/?" + params.toString());

        if (firstPayload === null) {
            firstPayload = payload;
            totalPages = Number.parseInt(String(payload?.pagination?.total_pages || 1), 10) || 1;
        }

        if (Array.isArray(payload?.rows) && payload.rows.length > 0) {
            rows.push(...payload.rows);
        }

        currentPage += 1;
    }

    return {
        family: firstPayload?.family || { code: familyCode, name: familyCode },
        summary: firstPayload?.summary || {
            total_codes: 0,
            configurator_valid: 0,
            configurator_invalid: 0,
            datasheet_ready: 0,
            datasheet_blocked: 0,
        },
        rows,
    };
}

async function loadGlobalDescriptionSearchData(candidateFamilies = explorerState.families) {
    const summary = {
        total_codes: 0,
        configurator_valid: 0,
        configurator_invalid: 0,
        datasheet_ready: 0,
        datasheet_blocked: 0,
    };
    const allRows = [];
    const familiesToSearch = Array.isArray(candidateFamilies) && candidateFamilies.length > 0
        ? candidateFamilies
        : explorerState.families;

    for (const family of familiesToSearch) {
        const familyCode = String(family.codigo || "");

        if (familyCode === "") {
            continue;
        }

        let familyData = null;

        try {
            familyData = await fetchGlobalDescriptionRowsForFamily(familyCode);
        } catch (error) {
            continue;
        }

        summary.total_codes += Number.parseInt(String(familyData.summary?.total_codes || 0), 10) || 0;
        summary.configurator_valid += Number.parseInt(String(familyData.summary?.configurator_valid || 0), 10) || 0;
        summary.configurator_invalid += Number.parseInt(String(familyData.summary?.configurator_invalid || 0), 10) || 0;
        summary.datasheet_ready += Number.parseInt(String(familyData.summary?.datasheet_ready || 0), 10) || 0;
        summary.datasheet_blocked += Number.parseInt(String(familyData.summary?.datasheet_blocked || 0), 10) || 0;

        if (familyData.rows.length > 0) {
            allRows.push(...familyData.rows);
        }
    }

    allRows.sort((left, right) => String(left.reference || "").localeCompare(String(right.reference || "")));

    const totalRows = allRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / explorerState.controls.pageSize));
    const safePage = Math.min(Math.max(explorerState.controls.page, 1), totalPages);
    const offset = (safePage - 1) * explorerState.controls.pageSize;
    const pageRows = allRows.slice(offset, offset + explorerState.controls.pageSize);

    return {
        family: null,
        summary,
        filters: {
            search: explorerState.controls.search,
            search_type: EXPLORER_SEARCH_TYPE_DESCRIPTION,
            status: explorerState.controls.status,
            include_invalid: false,
            segment_filters: getEmptyExplorerSegmentFilters(),
        },
        pagination: {
            page: safePage,
            page_size: explorerState.controls.pageSize,
            total_pages: totalPages,
            total_rows: totalRows,
        },
        rows: pageRows,
    };
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
    const codeResultsSection = document.getElementById("explorer-code-results-section");
    const body = document.getElementById("explorer-rows");
    const tableRoot = document.getElementById("explorer-data-table");
    const emptyState = document.getElementById("explorer-empty-state");
    const resultsTitle = document.getElementById("explorer-results-title");
    const summaryBadges = document.getElementById("explorer-summary-badges");
    const rows = explorerState.data?.rows || [];
    const valueUnavailable = t("codeExplorer.valueUnavailable", {}, "Not available");
    const shouldShowResultsSection = shouldShowExplorerCodeResultsSection(
        getExplorerViewModeFromForm(),
        explorerState.data
    );

    if (codeResultsSection) {
        codeResultsSection.classList.toggle("hidden", !shouldShowResultsSection);
    }

    if (!body || !tableRoot || !shouldShowResultsSection) {
        resultsTitle?.classList.remove("hidden");
        summaryBadges?.classList.add("hidden");
        syncExplorerEmptyStateHeight();
        return;
    }

    if (rows.length === 0) {
        body.innerHTML = "";
        tableRoot.classList.add("hidden");
        emptyState?.classList.remove("hidden");
        resultsTitle?.classList.add("hidden");
        summaryBadges?.classList.add("hidden");
        syncExplorerEmptyStateHeight();
        requestAnimationFrame(syncExplorerTableHeadColumns);
        return;
    }

    tableRoot.classList.remove("hidden");
    emptyState?.classList.add("hidden");
    resultsTitle?.classList.remove("hidden");
    summaryBadges?.classList.remove("hidden");
    syncExplorerEmptyStateHeight();
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
                    <button type="button" class="link link-sm text-left" data-reference="${escapeHtml(row.reference)}">
                        <span class="link-label font-mono break-all">${escapeHtml(row.reference)}</span>
                    </button>
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(row.identity || "")}">
                    <span class="text-body-sm font-mono">${escapeHtml(row.identity || "")}</span>
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(description)}">
                    <span class="text-body-sm">${escapeHtml(description)}</span>
                </td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(productType)}">
                    <span class="text-body-sm">${escapeHtml(productType)}</span>
                </td>
                <td class="data-table-cell break-all" data-sort-value="${escapeHtml(productId)}">
                    <span class="text-body-sm break-all">${escapeHtml(productId)}</span>
                </td>
                <td class="data-table-cell" data-sort-value="${row.configurator_valid ? "valid" : "invalid"}">${buildStatusBadge(row.configurator_valid, t("codeExplorer.statusConfiguratorValidShort", {}, "Valid"), t("codeExplorer.statusConfiguratorInvalidShort", {}, "Invalid"))}</td>
                <td class="data-table-cell" data-sort-value="${row.configurator_valid ? (row.datasheet_ready ? "ready" : "blocked") : "na"}">${datasheetStatus}</td>
                <td class="data-table-cell" data-sort-value="${escapeHtml(failureReason)}">
                    <span class="text-body-sm">${escapeHtml(failureReason)}</span>
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
    renderDetailPdfSpecs(row);
}

function getCurrentExplorerLanguage() {
    return window.NexLedI18n?.getLanguage?.() || document.documentElement.lang || "en";
}

function getDetailPdfSpecsCacheKey(reference, lang = getCurrentExplorerLanguage()) {
    return `${lang}:${reference}`;
}

function getDetailPdfSpecsState(reference, lang = getCurrentExplorerLanguage()) {
    if (!reference) {
        return {
            status: "idle",
            data: null,
            errorMessage: "",
        };
    }

    return explorerState.detailPdfSpecsByKey[getDetailPdfSpecsCacheKey(reference, lang)] || {
        status: "idle",
        data: null,
        errorMessage: "",
    };
}

function buildDetailPdfSpecsRows(rows) {
    return rows.map((row) => {
        return `
            <div class="flex items-start justify-between gap-12 rounded-card border border-grey-quaternary/60 bg-white px-12 py-10">
                <span class="text-body-sm text-grey-primary">${escapeHtml(row.label || "")}</span>
                <span class="text-body-sm text-black text-right">${escapeHtml(row.value || t("codeExplorer.valueUnavailable", {}, "Not available"))}</span>
            </div>
        `;
    }).join("");
}

function buildDetailPdfSpecsSection(title, rows, emptyMessage = "") {
    if (!Array.isArray(rows) || rows.length === 0) {
        if (!emptyMessage) {
            return "";
        }

        return `
            <div class="flex flex-col gap-8">
                <span class="input-label">${escapeHtml(title)}</span>
                <p class="text-body-sm text-grey-primary">${escapeHtml(emptyMessage)}</p>
            </div>
        `;
    }

    return `
        <div class="flex flex-col gap-8">
            <span class="input-label">${escapeHtml(title)}</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                ${buildDetailPdfSpecsRows(rows)}
            </div>
        </div>
    `;
}

function buildDetailPdfAssetStatusRow(label, available) {
    let statusMarkup = buildNeutralBadge(t("codeExplorer.detailsPdfAssetNotApplicable", {}, "Not applicable"));

    if (available === true) {
        statusMarkup = buildStatusBadge(true, t("codeExplorer.detailsPdfAssetAvailable", {}, "Available"), t("codeExplorer.detailsPdfAssetMissing", {}, "Missing"));
    } else if (available === false) {
        statusMarkup = buildStatusBadge(false, t("codeExplorer.detailsPdfAssetAvailable", {}, "Available"), t("codeExplorer.detailsPdfAssetMissing", {}, "Missing"));
    }

    return `
        <div class="flex items-start justify-between gap-12 rounded-card border border-grey-quaternary/60 bg-white px-12 py-10">
            <span class="text-body-sm text-grey-primary">${escapeHtml(label)}</span>
            <span>${statusMarkup}</span>
        </div>
    `;
}

function renderDetailPdfSpecs(row = getSelectedRow()) {
    const button = document.getElementById("detail-load-pdf-specs");
    const content = document.getElementById("detail-pdf-specs-content");

    if (!button || !content) {
        return;
    }

    if (!row) {
        button.disabled = true;
        content.innerHTML = "";
        return;
    }

    if (!row.configurator_valid) {
        button.disabled = true;
        content.innerHTML = `
            <p class="text-body-sm text-grey-primary">${escapeHtml(t("codeExplorer.detailsPdfSpecsUnavailable", {}, "PDF specs are available only for configurator-valid codes."))}</p>
        `;
        return;
    }

    const specsState = getDetailPdfSpecsState(row.reference);
    button.disabled = specsState.status === "loading";

    if (specsState.status === "idle") {
        content.innerHTML = `
            <p class="text-body-sm text-grey-primary">${escapeHtml(t("codeExplorer.detailsPdfSpecsIdle", {}, "Click the button to load the same specs used by the datasheet."))}</p>
        `;
        return;
    }

    if (specsState.status === "loading") {
        content.innerHTML = `
            <div class="flex items-center gap-8 text-grey-primary">
                <i class="ri-loader-4-line animate-spin text-icon-sm" aria-hidden="true"></i>
                <span class="text-body-sm">${escapeHtml(t("codeExplorer.detailsPdfSpecsLoading", {}, "Loading PDF specs..."))}</span>
            </div>
        `;
        return;
    }

    if (specsState.status === "error") {
        content.innerHTML = `
            <p class="text-body-sm text-red-700">${escapeHtml(specsState.errorMessage || t("codeExplorer.detailsPdfSpecsError", {}, "Unable to load PDF specs right now."))}</p>
        `;
        return;
    }

    const payload = specsState.data || {};
    const summaryRows = [
        {
            label: t("codeExplorer.detailsPdfHeaderDescriptionLabel", {}, "Header description"),
            value: payload.summary?.header_description || payload.summary?.description || t("codeExplorer.valueUnavailable", {}, "Not available"),
        },
        {
            label: t("codeExplorer.detailsPdfFinishNameLabel", {}, "Finish"),
            value: payload.summary?.finish_name || t("codeExplorer.valueUnavailable", {}, "Not available"),
        },
        {
            label: t("codeExplorer.detailsPdfIpRatingLabel", {}, "IP rating"),
            value: payload.summary?.ip_rating || t("codeExplorer.valueUnavailable", {}, "Not available"),
        },
        {
            label: t("codeExplorer.detailsPdfLedIdLabel", {}, "LED ID"),
            value: payload.summary?.led_id || t("codeExplorer.valueUnavailable", {}, "Not available"),
        },
        {
            label: t("codeExplorer.detailsPdfColorGraphLabel", {}, "Color graph"),
            value: payload.summary?.color_graph_label || t("codeExplorer.valueUnavailable", {}, "Not available"),
        },
    ].filter((item) => item.value && item.value !== t("codeExplorer.valueUnavailable", {}, "Not available"));

    const assetRows = [
        buildDetailPdfAssetStatusRow(t("codeExplorer.detailsPdfAssetHeaderImage", {}, "Header image"), payload.assets?.header_image),
        buildDetailPdfAssetStatusRow(t("codeExplorer.detailsPdfAssetTechnicalDrawing", {}, "Technical drawing"), payload.assets?.technical_drawing),
        buildDetailPdfAssetStatusRow(t("codeExplorer.detailsPdfAssetColorGraph", {}, "Color graph"), payload.assets?.color_graph),
        buildDetailPdfAssetStatusRow(t("codeExplorer.detailsPdfAssetLensDiagram", {}, "Lens diagram"), payload.assets?.lens_diagram),
        buildDetailPdfAssetStatusRow(t("codeExplorer.detailsPdfAssetFinishImage", {}, "Finish image"), payload.assets?.finish_image),
    ].join("");

    content.innerHTML = [
        buildDetailPdfSpecsSection(
            t("codeExplorer.detailsPdfOverviewTitle", {}, "PDF Summary"),
            summaryRows,
            t("codeExplorer.detailsPdfOverviewEmpty", {}, "No PDF summary data found.")
        ),
        buildDetailPdfSpecsSection(
            t("codeExplorer.detailsPdfCharacteristicsTitle", {}, "Technical characteristics"),
            payload.characteristics || [],
            t("codeExplorer.detailsPdfCharacteristicsEmpty", {}, "No technical characteristics found.")
        ),
        buildDetailPdfSpecsSection(
            t("codeExplorer.detailsPdfDimensionsTitle", {}, "Dimensions"),
            payload.dimensions || [],
            t("codeExplorer.detailsPdfDimensionsEmpty", {}, "No drawing dimensions found.")
        ),
        `
            <div class="flex flex-col gap-8">
                <span class="input-label">${escapeHtml(t("codeExplorer.detailsPdfAssetsTitle", {}, "PDF assets"))}</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    ${assetRows}
                </div>
            </div>
        `,
    ].filter(Boolean).join("");
}

async function loadDetailPdfSpecs(force = false) {
    const row = getSelectedRow();

    if (!row || !row.configurator_valid) {
        renderDetailPdfSpecs(row);
        return;
    }

    const lang = getCurrentExplorerLanguage();
    const cacheKey = getDetailPdfSpecsCacheKey(row.reference, lang);
    const currentState = explorerState.detailPdfSpecsByKey[cacheKey];

    if (!force && (currentState?.status === "loading" || currentState?.status === "loaded")) {
        renderDetailPdfSpecs(row);
        return;
    }

    explorerState.detailPdfSpecsByKey[cacheKey] = {
        status: "loading",
        data: null,
        errorMessage: "",
    };
    renderDetailPdfSpecs(row);

    try {
        const params = new URLSearchParams({
            endpoint: "code-explorer",
            action: "pdf_specs",
            family: row.segments?.family || String(row.reference || "").slice(0, 2),
            reference: row.reference || "",
            lang,
        });
        const payload = await apiFetch("/?" + params.toString());

        explorerState.detailPdfSpecsByKey[cacheKey] = {
            status: "loaded",
            data: payload,
            errorMessage: "",
        };
    } catch (error) {
        explorerState.detailPdfSpecsByKey[cacheKey] = {
            status: "error",
            data: null,
            errorMessage: getExplorerErrorMessage(error),
        };

        if (error.status >= 500 || error.status === 401 || error.status === 403) {
            setApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        }
    }

    renderDetailPdfSpecs(getSelectedRow());
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
}

function renderPagination() {
    const wrapper = document.getElementById("explorer-pagination-wrap");
    const pagination = document.getElementById("explorer-pagination");
    const list = pagination?.querySelector(".pagination-list");
    const prev = pagination?.querySelector("[data-pagination-prev]");
    const next = pagination?.querySelector("[data-pagination-next]");
    const hasRows = isExplorerFilterMode(explorerState.controls.viewMode)
        ? hasExplorerChunkItems(explorerState.data) || (isLegacyFilterResponse(explorerState.data) && (explorerState.data?.rows?.length || 0) > 0)
        : (explorerState.data?.rows?.length || 0) > 0;
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

function formatPercent(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return "0%";
    }

    const normalizedValue = Math.round(numericValue * 10) / 10;
    const formatter = new Intl.NumberFormat(window.NexLedI18n?.getLanguage?.() === "pt" ? "pt-PT" : "en-US", {
        minimumFractionDigits: Number.isInteger(normalizedValue) ? 0 : 1,
        maximumFractionDigits: 1,
    });

    return `${formatter.format(normalizedValue)}%`;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

