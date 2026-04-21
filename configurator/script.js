/**
 * NexLed Configurator
 *
 * Loads the valid option set from the API, builds the product reference,
 * and exports the datasheet request from the live UI state.
 */

const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";
const PDF_LOADING_SUCCESS_HOLD_MS = 2800;
const SHOWCASE_PREVIEW_DEBOUNCE_MS = 260;
const CUSTOM_PREVIEW_DEBOUNCE_MS = 260;
const LOCAL_API_HOSTNAMES = new Set(["localhost", "127.0.0.1", "[::1]"]);
const SHOWCASE_IMPLEMENTED_FAMILY_CODES = new Set(["29", "30"]);
const SHOWCASE_DEFAULT_FILTERS = {
    datasheet_ready_only: true,
    max_variants: 80,
    max_pages: 30,
};
const SHOWCASE_EXPANDABLE_SEGMENTS = [
    "size",
    "color",
    "cri",
    "series",
    "lens",
    "finish",
    "cap",
    "option",
];
const SHOWCASE_SCOPE_FIELD_IDS = ["field-size", "field-color", "field-cri", "field-series", "field-lens"];
const SHOWCASE_DATASHEET_SECTION_IDS = [
    "datasheet-section-dimensions",
    "datasheet-section-light",
    "datasheet-section-optics",
    "datasheet-section-cable",
    "datasheet-section-mounting",
    "datasheet-section-power",
];
const SHOWCASE_SECTION_DEFINITIONS = [
    { id: "overview", labelKey: "configurator.showcase.sectionOverview", fallback: "Overview", defaultChecked: true },
    { id: "luminotechnical", labelKey: "configurator.showcase.sectionLuminotechnical", fallback: "Luminotechnical", defaultChecked: true },
    { id: "spectra", labelKey: "configurator.showcase.sectionSpectra", fallback: "Color spectra", defaultChecked: true },
    { id: "technical_drawings", labelKey: "configurator.showcase.sectionTechnicalDrawings", fallback: "Technical drawings", defaultChecked: true },
    { id: "lens_diagrams", labelKey: "configurator.showcase.sectionLensDiagrams", fallback: "Lens diagrams", defaultChecked: true },
    { id: "finish_gallery", labelKey: "configurator.showcase.sectionFinishGallery", fallback: "Finish gallery", defaultChecked: true },
    { id: "option_codes", labelKey: "configurator.showcase.sectionOptionCodes", fallback: "Option codes", defaultChecked: true },
];
const REFERENCE_PLACEHOLDER_SELECT_IDS = new Set([
    "select-size",
    "select-color",
    "select-cri",
    "select-series",
    "select-lens",
    "select-finish",
    "select-cap",
    "select-option",
]);
const CUSTOM_TEXT_OVERRIDE_FIELDS = [
    { id: "custom-document-title", key: "document_title" },
    { id: "custom-header-copy", key: "header_copy" },
    { id: "custom-footer-note", key: "footer_note" },
];
const CUSTOM_ASSET_OVERRIDE_FIELDS = [
    { id: "custom-header-image-asset", key: "header_image" },
    { id: "custom-drawing-image-asset", key: "drawing_image" },
    { id: "custom-finish-image-asset", key: "finish_image" },
];
const CUSTOM_SECTION_VISIBILITY_FIELDS = [
    { id: "custom-section-fixing", key: "fixing" },
    { id: "custom-section-power-supply", key: "power_supply" },
    { id: "custom-section-connection-cable", key: "connection_cable" },
];
const CUSTOM_CONTROL_IDS = [
    ...CUSTOM_TEXT_OVERRIDE_FIELDS.map((field) => field.id),
    ...CUSTOM_ASSET_OVERRIDE_FIELDS.map((field) => field.id),
    ...CUSTOM_SECTION_VISIBILITY_FIELDS.map((field) => field.id),
];

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

const TECIT_LOGIC_FAMILIES = [
    { code: "01", name: "T8 AC" },
    { code: "02", name: "T8 VC" },
    { code: "03", name: "T8 CC" },
    { code: "04", name: "T5 CC" },
    { code: "05", name: "T5 VC" },
    { code: "06", name: "T5 AC" },
    { code: "07", name: "PLL" },
    { code: "08", name: "PLC" },
    { code: "09", name: "S14" },
    { code: "10", name: "Barra CC" },
    { code: "11", name: "Barra 24V" },
    { code: "12", name: "Spot" },
    { code: "13", name: "AR111" },
    { code: "14", name: "AR111 CC" },
    { code: "15", name: "AR111 COB" },
    { code: "16", name: "PAR 30" },
    { code: "17", name: "PAR 30 CC" },
    { code: "18", name: "PAR 30 COB" },
    { code: "19", name: "PAR 38" },
    { code: "20", name: "PAR 38 CC" },
    { code: "21", name: "PAR 38 COB" },
    { code: "22", name: "Decoracao" },
    { code: "23", name: "Projetores" },
    { code: "24", name: "Campanulas" },
    { code: "25", name: "Luminarias" },
    { code: "26", name: "Retrofit redondo" },
    { code: "27", name: "Retrofit quadrado" },
    { code: "28", name: "Retrofit spot" },
    { code: "29", name: "Downlight redondo" },
    { code: "30", name: "Downlight quadrado" },
    { code: "31", name: "Barra RGB 24V VC" },
    { code: "32", name: "Barra 24V T" },
    { code: "33", name: "DL Quadrado COB" },
    { code: "34", name: "DL Redondo COB" },
    { code: "35", name: "Armadura emb" },
    { code: "36", name: "Armadura ext" },
    { code: "37", name: "Painel" },
    { code: "38", name: "Painel Embutir" },
    { code: "39", name: "Retrofit armadura" },
    { code: "40", name: "Barra 24V CCT" },
    { code: "41", name: "Projetor CCT" },
    { code: "42", name: "BT CCT" },
    { code: "43", name: "Decoracao2" },
    { code: "45", name: "BT45 24V" },
    { code: "46", name: "Projetor 2" },
    { code: "47", name: "Retrofit campanula" },
    { code: "48", name: "Dynamic" },
    { code: "49", name: "ShelfLED" },
    { code: "50", name: "Armadura IP" },
    { code: "51", name: "Village" },
    { code: "52", name: "DualTop embutir" },
    { code: "53", name: "DualTop saliente" },
    { code: "54", name: "Canopy" },
    { code: "55", name: "Barra 12V" },
    { code: "56", name: "BT 12V" },
    { code: "57", name: "Projetor 3" },
    { code: "58", name: "B 24V HOT" },
    { code: "59", name: "NEON 24V" },
    { code: "60", name: "B 24V I45" },
];

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

const STATUS_TOAST_BASE_CLASS = "toast toast-sm";
const STATUS_TOAST_AUTOHIDE_DELAY = 4000;
const STATUS_TOAST_HIDE_DELAY = 320;
const STATUS_TOAST_VARIANT = {
    neutral: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: true },
    loading: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: false },
    success: { className: "toast-success", iconClass: "ri-checkbox-circle-line", role: "status", autoHide: true },
    error: { className: "toast-danger", iconClass: "ri-close-circle-line", role: "alert", autoHide: true },
};
const API_BADGE_BASE_CLASS = "badge badge-md shrink-0";
const API_BADGE_VARIANT_CLASS = {
    neutral: "badge-neutral",
    loading: "badge-info",
    success: "badge-success",
    warning: "badge-warning",
    error: "badge-danger",
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
let liveReferenceDraftDirty = false;
let availableConfiguratorFamilies = [];
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
let statusToastTimers = {
    dismiss: null,
    hide: null,
};
let configuratorDeepLinkState = {
    applied: false,
    loading: false,
};
let outputModeState = "datasheet";
let showcasePreviewState = createEmptyShowcasePreviewState();
let showcasePreviewTimer = null;
let showcasePreviewRequestToken = 0;
let customPreviewState = createEmptyCustomPreviewState();
let customPreviewTimer = null;
let customPreviewRequestToken = 0;
const showcaseScopeFieldHomes = new Map();
let showcaseDropdownDocumentBound = false;

document.addEventListener("DOMContentLoaded", () => {
    try {
        initializeConfigurator();
    } catch (error) {
        handleConfiguratorInitError(error);
    }
});

function initializeConfigurator() {
    familyCombobox = setupFamilyCombobox();
    primeSelectPlaceholders();
    setupSelectDropdowns();
    bindSystemTooltips();
    bindDocumentLanguageControls();
    bindLiveReferenceLoader();
    bindStatusToast();
    renderShowcaseControls();
    bindShowcaseControls();
    bindCustomControls();
    document.getElementById("select-family").addEventListener("change", handleFamilyChange);
    document.getElementById("btn-generate").addEventListener("click", handleGenerateAction);

    bindShellActions();
    bindReferenceListeners();
    bindCopyButtons();
    resetConfiguratorState();
    applyOutputModeState();
    renderTecitCodeLogicFamilies();
    loadFamilies();
}

function createEmptyShowcasePreviewState() {
    return {
        pending: false,
        ok: false,
        family: "",
        reference: "",
        signature: "",
        variantCount: 0,
        estimatedPages: 0,
        messageVariables: {},
        messageKey: "configurator.runtime.showcasePreviewWaiting",
        messageFallback: "Choose scope filters and showcase sections to preview variant count and estimated pages.",
    };
}

function createEmptyCustomPreviewState() {
    return {
        pending: false,
        ok: false,
        runtimeImplemented: false,
        family: "",
        reference: "",
        signature: "",
        textOverrideCount: 0,
        assetOverrideCount: 0,
        hiddenSectionCount: 0,
        messageVariables: {},
        messageKey: "configurator.runtime.customPreviewWaiting",
        messageFallback: "Add approved overrides to validate the custom datasheet payload.",
    };
}

function renderShowcaseControls() {
    renderShowcaseMultiDropdown(
        document.getElementById("showcase-expand-grid"),
        {
            group: "expand",
            labelKey: "configurator.showcase.expandTitle",
            labelFallback: "Expand Valid Options",
            emptyLabelKey: "configurator.showcase.dropdownExpandEmpty",
            emptyLabelFallback: "Select options",
        },
        SHOWCASE_EXPANDABLE_SEGMENTS.map((segment) => ({
            type: "expand",
            id: segment,
            labelKey: DECODED_SEGMENT_FIELDS[segment]?.labelKey || "shared.actions.value",
            fallback: DECODED_SEGMENT_FIELDS[segment]?.fallback || segment,
            defaultChecked: false,
        }))
    );

    renderShowcaseMultiDropdown(
        document.getElementById("showcase-sections-grid"),
        {
            group: "section",
            labelKey: "configurator.showcase.sectionsTitle",
            labelFallback: "Showcase Sections",
            emptyLabelKey: "configurator.showcase.dropdownSectionsEmpty",
            emptyLabelFallback: "Select sections",
        },
        SHOWCASE_SECTION_DEFINITIONS.map((section) => ({
            type: "section",
            id: section.id,
            labelKey: section.labelKey,
            fallback: section.fallback,
            defaultChecked: section.defaultChecked,
        }))
    );
}

function renderShowcaseMultiDropdown(container, config, items) {
    if (!container) {
        return;
    }

    const previousSelections = new Set(
        Array.from(container.querySelectorAll('input[type="checkbox"]:checked'))
            .map((input) => input.value)
    );

    const label = t(config.labelKey, {}, config.labelFallback);
    const emptyLabel = t(config.emptyLabelKey, {}, config.emptyLabelFallback);
    const itemsMarkup = items.map((item) => {
        const checked = previousSelections.has(item.id) || (!previousSelections.size && item.defaultChecked);
        const inputId = "showcase-" + item.type + "-" + item.id;
        const inputDataAttribute = item.type === "expand"
            ? 'data-showcase-expand="true"'
            : 'data-showcase-section="true"';

        return `
            <li class="dropdown-item" role="option" aria-selected="${checked ? "true" : "false"}" data-value="${escapeHtml(item.id)}">
                <label class="checkbox-wrapper checkbox-md">
                    <span class="relative inline-flex items-center justify-center">
                        <input
                            type="checkbox"
                            id="${escapeHtml(inputId)}"
                            value="${escapeHtml(item.id)}"
                            ${inputDataAttribute}
                            ${checked ? "checked" : ""}
                            class="peer"
                        >
                        <i class="ri-check-line absolute inset-0 flex items-center justify-center leading-none text-white text-icon-md opacity-0 peer-checked:opacity-100 pointer-events-none" aria-hidden="true"></i>
                    </span>
                    <span class="text-body-sm">${escapeHtml(t(item.labelKey, {}, item.fallback))}</span>
                </label>
            </li>
        `;
    }).join("");

    container.innerHTML = `
        <div
            class="dropdown dropdown-multi dropdown-md w-full"
            data-showcase-multi-dropdown="true"
            data-showcase-multi-group="${escapeHtml(config.group)}"
            data-empty-label="${escapeHtml(emptyLabel)}"
            data-selected-suffix="${escapeHtml(t("configurator.showcase.dropdownSelectedSuffix", {}, "selected"))}"
        >
            <button type="button" class="dropdown-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="dropdown-value">${escapeHtml(emptyLabel)}</span>
                <i class="ri-arrow-down-s-line dropdown-arrow" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu custom-scrollbar" role="listbox" aria-multiselectable="true" aria-label="${escapeHtml(label)}">
                ${itemsMarkup}
            </ul>
        </div>
    `;

    initShowcaseMultiDropdown(container.querySelector('[data-showcase-multi-dropdown="true"]'));
}

function initShowcaseMultiDropdown(dropdown) {
    if (!dropdown || dropdown.dataset.bound === "true") {
        return;
    }

    bindShowcaseDropdownDocumentEvents();

    const trigger = dropdown.querySelector(".dropdown-trigger");
    const items = Array.from(dropdown.querySelectorAll(".dropdown-item"));
    const valueDisplay = dropdown.querySelector(".dropdown-value");

    if (!(trigger instanceof HTMLButtonElement) || !valueDisplay) {
        return;
    }

    items.forEach((item, index) => {
        item.setAttribute("tabindex", "-1");
        item.dataset.index = String(index);

        item.addEventListener("focus", () => {
            item.scrollIntoView({ block: "nearest" });
        });

        item.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown") {
                event.preventDefault();
                items[(index + 1) % items.length]?.focus();
            }

            if (event.key === "ArrowUp") {
                event.preventDefault();
                items[(index - 1 + items.length) % items.length]?.focus();
            }

            if (event.key === "Home") {
                event.preventDefault();
                items[0]?.focus();
            }

            if (event.key === "End") {
                event.preventDefault();
                items[items.length - 1]?.focus();
            }

            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                toggleShowcaseDropdownItem(item);
            }

            if (event.key === "Escape") {
                closeShowcaseMultiDropdown(dropdown);
                trigger.focus();
            }
        });

        const checkbox = item.querySelector('input[type="checkbox"]');
        if (!(checkbox instanceof HTMLInputElement)) {
            return;
        }

        checkbox.tabIndex = -1;
        item.setAttribute("aria-selected", String(checkbox.checked));

        checkbox.addEventListener("click", (event) => {
            event.stopPropagation();
        });

        checkbox.addEventListener("change", () => {
            syncShowcaseDropdownItem(item, checkbox.checked);
            updateShowcaseMultiDropdownValue(dropdown);
        });

        item.addEventListener("click", (event) => {
            if (event.target instanceof Element && event.target.closest(".checkbox-wrapper")) {
                return;
            }

            event.preventDefault();
            toggleShowcaseDropdownItem(item);
        });
    });

    trigger.addEventListener("click", (event) => {
        event.preventDefault();

        if (dropdown.classList.contains("is-open")) {
            closeShowcaseMultiDropdown(dropdown);
            return;
        }

        openShowcaseMultiDropdown(dropdown);
    });

    trigger.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
            event.preventDefault();

            if (!dropdown.classList.contains("is-open")) {
                openShowcaseMultiDropdown(dropdown);
            }

            items[0]?.focus();
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();

            if (!dropdown.classList.contains("is-open")) {
                openShowcaseMultiDropdown(dropdown);
            }

            items[items.length - 1]?.focus();
        }

        if (event.key === "Escape") {
            closeShowcaseMultiDropdown(dropdown);
        }
    });

    dropdown.dataset.bound = "true";
    updateShowcaseMultiDropdownValue(dropdown);
}

function bindShowcaseDropdownDocumentEvents() {
    if (showcaseDropdownDocumentBound) {
        return;
    }

    document.addEventListener("click", (event) => {
        if (event.target instanceof Element && event.target.closest('[data-showcase-multi-dropdown="true"]')) {
            return;
        }

        closeAllShowcaseMultiDropdowns();
    });

    showcaseDropdownDocumentBound = true;
}

function toggleShowcaseDropdownItem(item) {
    const checkbox = item?.querySelector('input[type="checkbox"]');

    if (!(checkbox instanceof HTMLInputElement)) {
        return;
    }

    checkbox.checked = !checkbox.checked;
    checkbox.dispatchEvent(new Event("change", { bubbles: true }));
}

function syncShowcaseDropdownItem(item, checked) {
    if (!item) {
        return;
    }

    item.setAttribute("aria-selected", String(checked));
}

function updateShowcaseMultiDropdownValue(dropdown) {
    if (!dropdown) {
        return;
    }

    const valueDisplay = dropdown.querySelector(".dropdown-value");
    if (!valueDisplay) {
        return;
    }

    const selectedItems = Array.from(dropdown.querySelectorAll('.dropdown-item[aria-selected="true"]'));
    const emptyLabel = dropdown.dataset.emptyLabel || t("configurator.showcase.dropdownExpandEmpty", {}, "Select options");
    const selectedSuffix = dropdown.dataset.selectedSuffix || t("configurator.showcase.dropdownSelectedSuffix", {}, "selected");

    if (selectedItems.length === 0) {
        valueDisplay.textContent = emptyLabel;
        dropdown.classList.remove("has-value");
        return;
    }

    if (selectedItems.length === 1) {
        const label = selectedItems[0]?.querySelector(".text-body-sm")?.textContent?.trim()
            || selectedItems[0]?.textContent?.replace(/\s+/g, " ").trim()
            || emptyLabel;
        valueDisplay.textContent = label;
        dropdown.classList.add("has-value");
        return;
    }

    valueDisplay.textContent = `${selectedItems.length} ${selectedSuffix}`;
    dropdown.classList.add("has-value");
}

function openShowcaseMultiDropdown(dropdown) {
    closeAllShowcaseMultiDropdowns(dropdown);
    dropdown.classList.add("is-open");
    dropdown.querySelector(".dropdown-trigger")?.setAttribute("aria-expanded", "true");
}

function closeShowcaseMultiDropdown(dropdown) {
    if (!dropdown) {
        return;
    }

    dropdown.classList.remove("is-open");
    dropdown.querySelector(".dropdown-trigger")?.setAttribute("aria-expanded", "false");
}

function closeAllShowcaseMultiDropdowns(exceptDropdown = null) {
    document.querySelectorAll('[data-showcase-multi-dropdown="true"]').forEach((dropdown) => {
        if (dropdown !== exceptDropdown) {
            closeShowcaseMultiDropdown(dropdown);
        }
    });
}

function bindShowcaseControls() {
    const modeInputs = document.querySelectorAll('input[name="output-mode"]');
    const expandGrid = document.getElementById("showcase-expand-grid");
    const sectionsGrid = document.getElementById("showcase-sections-grid");

    modeInputs.forEach((input) => {
        input.addEventListener("change", (event) => {
            if (!(event.target instanceof HTMLInputElement) || !event.target.checked) {
                return;
            }

            setOutputMode(event.target.value);
        });
    });

    expandGrid?.addEventListener("change", (event) => {
        if (!(event.target instanceof HTMLInputElement) || event.target.type !== "checkbox") {
            return;
        }

        syncShowcaseScopeFieldStates();
        scheduleShowcasePreview();
    });

    sectionsGrid?.addEventListener("change", (event) => {
        if (!(event.target instanceof HTMLInputElement) || event.target.type !== "checkbox") {
            return;
        }

        scheduleShowcasePreview();
    });
}

function bindCustomControls() {
    CUSTOM_CONTROL_IDS.forEach((id) => {
        const element = document.getElementById(id);

        if (!element || element.dataset.customBound === "true") {
            return;
        }

        const eventName = element instanceof HTMLInputElement && element.type === "checkbox"
            ? "change"
            : "input";

        element.addEventListener(eventName, () => {
            scheduleCustomPreview();
        });
        element.dataset.customBound = "true";
    });

    const resetButton = document.getElementById("custom-reset-button");

    if (resetButton && resetButton.dataset.customBound !== "true") {
        resetButton.addEventListener("click", resetCustomOverrides);
        resetButton.dataset.customBound = "true";
    }
}

function setOutputMode(mode) {
    const normalizedMode = mode === "showcase" || mode === "custom" ? mode : "datasheet";

    if (outputModeState === normalizedMode) {
        return;
    }

    outputModeState = normalizedMode;
    applyOutputModeState();

    if (outputModeState === "showcase") {
        scheduleShowcasePreview();
        return;
    }

    if (outputModeState === "custom") {
        scheduleCustomPreview();
        return;
    }

    resetShowcasePreviewState();
    resetCustomPreviewState();
    syncGenerateButton();
}

function applyOutputModeState() {
    const isShowcase = outputModeState === "showcase";
    const isCustom = outputModeState === "custom";
    const datasheetInput = document.getElementById("output-mode-datasheet");
    const showcaseInput = document.getElementById("output-mode-showcase");
    const customInput = document.getElementById("output-mode-custom");
    const hint = document.getElementById("output-mode-hint");
    const generateLabel = document.getElementById("btn-generate-label");

    if (datasheetInput instanceof HTMLInputElement) {
        datasheetInput.checked = !isShowcase;
    }

    if (showcaseInput instanceof HTMLInputElement) {
        showcaseInput.checked = isShowcase;
    }

    if (customInput instanceof HTMLInputElement) {
        customInput.checked = isCustom;
    }

    if (hint) {
        hint.textContent = isShowcase
            ? t("configurator.quickActions.modeShowcaseHint", {}, "Generate a grouped showcase PDF from baseline filters and expanded valid options.")
            : isCustom
                ? t("configurator.quickActions.modeCustomHint", {}, "Generate a custom datasheet from one exact product plus approved overrides.")
                : t("configurator.quickActions.modeDatasheetHint", {}, "Generate one technical datasheet from the current live reference.");
    }

    if (generateLabel) {
        generateLabel.textContent = isShowcase
            ? t("configurator.quickActions.generateShowcasePdf", {}, "Generate Showcase PDF")
            : isCustom
                ? t("configurator.quickActions.generateCustomPdf", {}, "Generate Custom PDF")
                : t("configurator.quickActions.generatePdf", {}, "Generate PDF");
    }

    applyBuilderModeState();
    updateShowcaseFamilyHint();
    updateCustomFamilyHint();
    syncShowcaseScopeFieldStates();
    renderShowcasePreviewState();
    renderCustomPreviewState();
    syncGenerateButton();
}

function isShowcaseMode() {
    return outputModeState === "showcase";
}

function isCustomMode() {
    return outputModeState === "custom";
}

function applyBuilderModeState() {
    const isShowcase = isShowcaseMode();
    const isCustom = isCustomMode();
    const metadataGrid = document.getElementById("metadata-fields-grid");
    const metadataTitle = document.getElementById("metadata-section-title");
    const metadataText = document.getElementById("metadata-section-text");

    setHidden("showcase-scope", !isShowcase);
    setHidden("showcase-controls", !isShowcase);
    setHidden("custom-controls", !isCustom);
    setHidden("field-purpose", isShowcase);

    SHOWCASE_DATASHEET_SECTION_IDS.forEach((id) => {
        setHidden(id, isShowcase);
    });

    if (metadataGrid) {
        metadataGrid.classList.toggle("xl:grid-cols-3", !isShowcase);
        metadataGrid.classList.toggle("xl:grid-cols-2", isShowcase);
    }

    if (metadataTitle) {
        metadataTitle.textContent = isShowcase
            ? t("configurator.sections.exportTitle", {}, "Export Settings")
            : isCustom
                ? t("configurator.sections.customMetadataTitle", {}, "Custom Datasheet Settings")
                : t("configurator.sections.metadataTitle", {}, "Datasheet Metadata");
    }

    if (metadataText) {
        metadataText.textContent = isShowcase
            ? t("configurator.sections.exportText", {}, "Choose the branded export and output language used in the showcase PDF.")
            : isCustom
                ? t("configurator.sections.customMetadataText", {}, "Choose the base export settings used in the custom datasheet.")
                : t("configurator.sections.metadataText", {}, "Choose the application context, branded export, and output language used in the PDF.");
    }

    moveShowcaseScopeFields(isShowcase);
}

function moveShowcaseScopeFields(shouldUseShowcaseScope) {
    const scopeGrid = document.getElementById("showcase-scope-grid");

    cacheShowcaseScopeFieldHomes();

    if (!scopeGrid) {
        return;
    }

    SHOWCASE_SCOPE_FIELD_IDS.forEach((fieldId) => {
        const field = document.getElementById(fieldId);
        const home = showcaseScopeFieldHomes.get(fieldId);

        if (!field || !home?.parent) {
            return;
        }

        if (shouldUseShowcaseScope) {
            if (field.parentElement !== scopeGrid) {
                scopeGrid.appendChild(field);
            }

            return;
        }

        if (field.parentElement === home.parent) {
            return;
        }

        const referenceChild = home.parent.children[home.index] || null;
        home.parent.insertBefore(field, referenceChild);
    });
}

function cacheShowcaseScopeFieldHomes() {
    SHOWCASE_SCOPE_FIELD_IDS.forEach((fieldId) => {
        const field = document.getElementById(fieldId);

        if (!field || showcaseScopeFieldHomes.has(fieldId) || !field.parentElement) {
            return;
        }

        showcaseScopeFieldHomes.set(fieldId, {
            parent: field.parentElement,
            index: Array.from(field.parentElement.children).indexOf(field),
        });
    });
}

function syncShowcaseScopeFieldStates() {
    const expandedSegments = new Set(getSelectedShowcaseExpandedSegments());
    const showcaseActive = isShowcaseMode();

    SHOWCASE_SCOPE_FIELD_IDS.forEach((fieldId) => {
        const field = document.getElementById(fieldId);
        const segment = fieldId.replace("field-", "");
        const inputId = DECODED_SEGMENT_FIELDS[segment]?.id;
        const input = inputId ? document.getElementById(inputId) : null;
        const isDisabled = showcaseActive && expandedSegments.has(segment);

        if (field) {
            field.classList.toggle("opacity-60", isDisabled);
        }

        if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
            return;
        }

        input.disabled = isDisabled;
        input.setAttribute("aria-disabled", String(isDisabled));
    });
}

function getCurrentFamilyMetadata() {
    const familyCode = get("select-family");
    return availableConfiguratorFamilies.find((family) => String(family?.codigo || "") === familyCode) || null;
}

function isCurrentFamilyShowcaseAvailable() {
    const family = getCurrentFamilyMetadata();
    const familyCode = String(family?.codigo || get("select-family") || "").trim();

    if (typeof family?.showcase_runtime_implemented === "boolean") {
        return family.showcase_runtime_implemented;
    }

    return SHOWCASE_IMPLEMENTED_FAMILY_CODES.has(familyCode);
}

function updateShowcaseFamilyHint() {
    const hint = document.getElementById("showcase-family-note");

    if (!hint) {
        return;
    }

    if (!get("select-family")) {
        hint.textContent = t(
            "configurator.runtime.showcaseSelectFamilyFirst",
            {},
            "Select a family first. Showcase mode is currently mapped for families 29 and 30."
        );
        return;
    }

    if (!isCurrentFamilyShowcaseAvailable()) {
        hint.textContent = t(
            "configurator.runtime.showcaseUnsupportedFamily",
            {},
            "Showcase mode is currently available only for mapped families. Use 29 or 30 for this first renderer."
        );
        return;
    }

    hint.textContent = t(
        "configurator.runtime.showcaseFamilyHint",
        {},
        "Current selections stay locked unless you expand them. Showcase mode is live for families 29 and 30."
    );
}

function isCurrentFamilyCustomDatasheetAvailable() {
    const family = getCurrentFamilyMetadata();
    return Boolean(family?.custom_datasheet_supported);
}

function isCurrentFamilyCustomDatasheetRuntimeImplemented() {
    const family = getCurrentFamilyMetadata();
    return Boolean(family?.custom_datasheet_runtime_implemented);
}

function updateCustomFamilyHint() {
    const hint = document.getElementById("custom-family-note");

    if (!hint) {
        return;
    }

    if (!get("select-family")) {
        hint.textContent = t(
            "configurator.runtime.customSelectFamilyFirst",
            {},
            "Select a family first. Custom datasheet works only on families that already support the official datasheet runtime."
        );
        return;
    }

    if (!isCurrentFamilyCustomDatasheetAvailable()) {
        hint.textContent = t(
            "configurator.runtime.customUnsupportedFamily",
            {},
            "Custom datasheet is currently blocked for families without official datasheet runtime support."
        );
        return;
    }

    if (!isCurrentFamilyCustomDatasheetRuntimeImplemented()) {
        hint.textContent = t(
            "configurator.runtime.customRuntimePending",
            {},
            "Custom datasheet preview is scaffolded. PDF render is still pending implementation."
        );
        return;
    }

    hint.textContent = t(
        "configurator.runtime.customFamilyHint",
        {},
        "Custom mode uses the exact product datasheet and applies approved text and image overrides."
    );
}

function resetShowcasePreviewState() {
    if (showcasePreviewTimer) {
        clearTimeout(showcasePreviewTimer);
        showcasePreviewTimer = null;
    }

    showcasePreviewRequestToken += 1;
    showcasePreviewState = createEmptyShowcasePreviewState();
    renderShowcasePreviewState();
}

function resetCustomPreviewState() {
    if (customPreviewTimer) {
        clearTimeout(customPreviewTimer);
        customPreviewTimer = null;
    }

    customPreviewRequestToken += 1;
    customPreviewState = createEmptyCustomPreviewState();
    renderCustomPreviewState();
}

function resetCustomOverrides(shouldPreview = true) {
    CUSTOM_TEXT_OVERRIDE_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);

        if (element) {
            element.value = "";
        }
    });

    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);

        if (element) {
            element.value = "";
        }
    });

    CUSTOM_SECTION_VISIBILITY_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);

        if (element instanceof HTMLInputElement) {
            element.checked = true;
        }
    });

    resetCustomPreviewState();

    if (shouldPreview) {
        scheduleCustomPreview();
    }
}

function renderShowcasePreviewState() {
    const variants = document.getElementById("showcase-preview-variants");
    const pages = document.getElementById("showcase-preview-pages");
    const message = document.getElementById("showcase-preview-message");

    if (!variants || !pages || !message) {
        return;
    }

    variants.textContent = showcasePreviewState.ok ? String(showcasePreviewState.variantCount) : "--";
    pages.textContent = showcasePreviewState.ok ? String(showcasePreviewState.estimatedPages) : "--";
    message.textContent = showcasePreviewState.pending
        ? t("configurator.runtime.showcasePreviewLoading", {}, "Previewing showcase...")
        : t(
            showcasePreviewState.messageKey,
            showcasePreviewState.messageVariables || {},
            showcasePreviewState.messageFallback
        );
    message.classList.toggle("text-red-600", !showcasePreviewState.pending && !showcasePreviewState.ok && showcasePreviewState.family !== "");
    message.classList.toggle("text-grey-primary", showcasePreviewState.pending || showcasePreviewState.ok || showcasePreviewState.family === "");
}

function renderCustomPreviewState() {
    const textCount = document.getElementById("custom-preview-text-count");
    const assetCount = document.getElementById("custom-preview-asset-count");
    const hiddenCount = document.getElementById("custom-preview-hidden-count");
    const message = document.getElementById("custom-preview-message");

    if (!textCount || !assetCount || !hiddenCount || !message) {
        return;
    }

    textCount.textContent = String(customPreviewState.textOverrideCount || 0);
    assetCount.textContent = String(customPreviewState.assetOverrideCount || 0);
    hiddenCount.textContent = String(customPreviewState.hiddenSectionCount || 0);
    message.textContent = customPreviewState.pending
        ? t("configurator.runtime.customPreviewLoading", {}, "Validating custom datasheet...")
        : t(
            customPreviewState.messageKey,
            customPreviewState.messageVariables || {},
            customPreviewState.messageFallback
        );
    message.classList.toggle("text-red-600", !customPreviewState.pending && !customPreviewState.ok && customPreviewState.family !== "");
    message.classList.toggle("text-grey-primary", customPreviewState.pending || customPreviewState.ok || customPreviewState.family === "");
}

function getSelectedShowcaseExpandedSegments() {
    return Array.from(document.querySelectorAll("[data-showcase-expand]:checked"))
        .map((input) => String(input.value || "").trim())
        .filter(Boolean);
}

function getSelectedShowcaseSections() {
    return Array.from(document.querySelectorAll("[data-showcase-section]:checked"))
        .map((input) => String(input.value || "").trim())
        .filter(Boolean);
}

function canRequestShowcasePreview() {
    if (!isShowcaseMode()) {
        return false;
    }

    if (!get("select-family")) {
        return false;
    }

    if (!isCurrentFamilyShowcaseAvailable()) {
        return false;
    }

    return getSelectedShowcaseSections().length > 0;
}

function buildShowcaseRequestBody() {
    const baseReference = sanitizeTecitCode(document.getElementById("output-reference")?.value || "");
    const expanded = getSelectedShowcaseExpandedSegments();
    const expandedSet = new Set(expanded);
    const locked = {};

    Object.entries(DECODED_SEGMENT_FIELDS).forEach(([segment, meta]) => {
        if (expandedSet.has(segment)) {
            return;
        }

        const value = String(get(meta.id) || "").trim();

        if (value === "") {
            return;
        }

        locked[segment] = pad(value, meta.length);
    });

    const sections = getSelectedShowcaseSections();
    enforceDownlightShowcaseFinishSection(sections);

    const body = {
        family: get("select-family"),
        lang: get("select-language"),
        company: get("select-company"),
        locked,
        expanded,
        sections,
        filters: { ...SHOWCASE_DEFAULT_FILTERS },
    };

    if (baseReference.length === 17 && !liveReferenceDraftDirty) {
        body.base_reference = baseReference;
    }

    return body;
}

function enforceDownlightShowcaseFinishSection(sections) {
    if (!Array.isArray(sections)) {
        return;
    }

    if (!SHOWCASE_IMPLEMENTED_FAMILY_CODES.has(String(get("select-family") || ""))) {
        return;
    }

    if (!sections.includes("option_codes")) {
        return;
    }

    if (!sections.includes("finish_gallery")) {
        sections.push("finish_gallery");
    }
}

function buildShowcaseRequestSignature(body = buildShowcaseRequestBody()) {
    return JSON.stringify({
        family: String(body.family || ""),
        base_reference: String(body.base_reference || ""),
        locked: body.locked || {},
        expanded: body.expanded || [],
        sections: body.sections || [],
        lang: String(body.lang || ""),
        company: String(body.company || ""),
        filters: body.filters || {},
    });
}

function buildDatasheetRequestBody() {
    return {
        referencia: document.getElementById("output-reference").value,
        descricao: document.getElementById("output-description").value,
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
}

function buildCustomRequestBody() {
    const textOverrides = {};
    const assetOverrides = {};
    const sectionVisibility = {};

    CUSTOM_TEXT_OVERRIDE_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);
        const value = String(element?.value || "").trim();

        if (value !== "") {
            textOverrides[field.key] = value;
        }
    });

    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);
        const value = String(element?.value || "").trim();

        if (value !== "") {
            assetOverrides[field.key] = {
                source: "dam",
                asset_id: value,
            };
        }
    });

    CUSTOM_SECTION_VISIBILITY_FIELDS.forEach((field) => {
        const element = document.getElementById(field.id);

        if (!(element instanceof HTMLInputElement)) {
            return;
        }

        sectionVisibility[field.key] = element.checked;
    });

    return {
        base_request: buildDatasheetRequestBody(),
        custom: {
            mode: "custom",
            text_overrides: textOverrides,
            asset_overrides: assetOverrides,
            section_visibility: sectionVisibility,
            footer: {
                marker: "CustPDF",
            },
        },
    };
}

function buildCustomRequestSignature(body = buildCustomRequestBody()) {
    return JSON.stringify(body);
}

function canRequestCustomPreview() {
    if (!isCustomMode()) {
        return false;
    }

    if (!get("select-family")) {
        return false;
    }

    if (!isCurrentFamilyCustomDatasheetAvailable()) {
        return false;
    }

    const reference = sanitizeTecitCode(document.getElementById("output-reference")?.value || "");
    return reference.length === 17 && hasResolvedReferenceSegments() && !liveReferenceDraftDirty;
}

function scheduleShowcasePreview() {
    if (!isShowcaseMode()) {
        return;
    }

    if (showcasePreviewTimer) {
        clearTimeout(showcasePreviewTimer);
        showcasePreviewTimer = null;
    }

    if (!get("select-family")) {
        resetShowcasePreviewState();
        syncGenerateButton();
        return;
    }

    if (!isCurrentFamilyShowcaseAvailable()) {
        showcasePreviewState = {
            ...createEmptyShowcasePreviewState(),
            family: get("select-family"),
            messageKey: "configurator.runtime.showcaseUnsupportedFamily",
            messageFallback: "Showcase mode is currently available only for mapped families. Use 29 or 30 for this first renderer.",
        };
        renderShowcasePreviewState();
        syncGenerateButton();
        return;
    }

    if (getSelectedShowcaseSections().length === 0) {
        showcasePreviewState = {
            ...createEmptyShowcasePreviewState(),
            family: get("select-family"),
            messageKey: "configurator.runtime.showcaseSectionRequired",
            messageFallback: "Select at least one showcase section.",
        };
        renderShowcasePreviewState();
        syncGenerateButton();
        return;
    }

    if (!canRequestShowcasePreview()) {
        showcasePreviewState = {
            ...createEmptyShowcasePreviewState(),
            family: get("select-family"),
            messageKey: "configurator.runtime.showcasePreviewNeedsScope",
            messageFallback: "Choose a family and keep the scope filters you want before previewing showcase PDF.",
        };
        renderShowcasePreviewState();
        syncGenerateButton();
        return;
    }

    showcasePreviewState = {
        ...showcasePreviewState,
        pending: true,
        ok: false,
        family: get("select-family"),
        reference: sanitizeTecitCode(document.getElementById("output-reference")?.value || ""),
        signature: buildShowcaseRequestSignature(),
    };
    renderShowcasePreviewState();
    syncGenerateButton();

    showcasePreviewTimer = setTimeout(() => {
        showcasePreviewTimer = null;
        void runShowcasePreview();
    }, SHOWCASE_PREVIEW_DEBOUNCE_MS);
}

async function runShowcasePreview() {
    const requestBody = buildShowcaseRequestBody();
    const requestReference = requestBody.base_reference || "";
    const requestSignature = buildShowcaseRequestSignature(requestBody);
    const requestToken = ++showcasePreviewRequestToken;

    showcasePreviewState = {
        ...showcasePreviewState,
        pending: true,
        ok: false,
        family: requestBody.family,
        reference: requestReference,
        signature: requestSignature,
    };
    renderShowcasePreviewState();
    syncGenerateButton();

    try {
        const response = await apiPost("/?endpoint=showcase-preview", requestBody);

        if (!response.ok) {
            if (requestToken !== showcasePreviewRequestToken) {
                return;
            }

            if (isApiServiceFailureStatus(response.status)) {
                markApiDegraded();
            }

            const errorData = await readApiJsonError(response, "Showcase preview failed.");

            showcasePreviewState = {
                ...createEmptyShowcasePreviewState(),
                family: requestBody.family,
                reference: requestReference,
                messageVariables: { message: errorData.message },
                messageKey: "configurator.runtime.showcasePreviewFailedWithMessage",
                messageFallback: "Showcase preview failed: " + errorData.message,
            };
            renderShowcasePreviewState();
            syncGenerateButton();
            return;
        }

        const payload = await response.json();
        const previewData = payload?.data || {};

        if (
            requestToken !== showcasePreviewRequestToken
            || !isShowcaseMode()
            || requestSignature !== buildShowcaseRequestSignature()
        ) {
            return;
        }

        showcasePreviewState = {
            pending: false,
            ok: true,
            family: requestBody.family,
            reference: requestReference,
            signature: requestSignature,
            variantCount: Number(previewData.variant_count || 0),
            estimatedPages: Number(previewData.estimated_pages || 0),
            messageKey: "configurator.runtime.showcasePreviewReady",
            messageFallback: "Showcase preview ready. Review counts, then generate PDF.",
        };
        renderShowcasePreviewState();
        syncGenerateButton();
    } catch (error) {
        if (requestToken !== showcasePreviewRequestToken) {
            return;
        }

        const message = error && error.message ? error.message : "Showcase preview failed.";

        showcasePreviewState = {
            ...createEmptyShowcasePreviewState(),
            family: requestBody.family,
            reference: requestReference,
            messageVariables: { message },
            messageKey: "configurator.runtime.showcasePreviewFailedWithMessage",
            messageFallback: "Showcase preview failed: " + message,
        };
        renderShowcasePreviewState();
        syncGenerateButton();
        console.error(error);
    }
}

function scheduleCustomPreview() {
    if (!isCustomMode()) {
        return;
    }

    if (customPreviewTimer) {
        clearTimeout(customPreviewTimer);
        customPreviewTimer = null;
    }

    if (!get("select-family")) {
        resetCustomPreviewState();
        syncGenerateButton();
        return;
    }

    if (!isCurrentFamilyCustomDatasheetAvailable()) {
        customPreviewState = {
            ...createEmptyCustomPreviewState(),
            family: get("select-family"),
            messageKey: "configurator.runtime.customUnsupportedFamily",
            messageFallback: "Custom datasheet is currently blocked for families without official datasheet runtime support.",
        };
        renderCustomPreviewState();
        syncGenerateButton();
        return;
    }

    if (!canRequestCustomPreview()) {
        customPreviewState = {
            ...createEmptyCustomPreviewState(),
            family: get("select-family"),
            messageKey: "configurator.runtime.customPreviewNeedsBase",
            messageFallback: "Build one exact datasheet configuration first, then add custom overrides.",
        };
        renderCustomPreviewState();
        syncGenerateButton();
        return;
    }

    customPreviewState = {
        ...customPreviewState,
        pending: true,
        ok: false,
        family: get("select-family"),
        reference: sanitizeTecitCode(document.getElementById("output-reference")?.value || ""),
        signature: buildCustomRequestSignature(),
    };
    renderCustomPreviewState();
    syncGenerateButton();

    customPreviewTimer = setTimeout(() => {
        customPreviewTimer = null;
        void runCustomPreview();
    }, CUSTOM_PREVIEW_DEBOUNCE_MS);
}

async function runCustomPreview() {
    const requestBody = buildCustomRequestBody();
    const requestReference = requestBody.base_request?.referencia || "";
    const requestSignature = buildCustomRequestSignature(requestBody);
    const requestToken = ++customPreviewRequestToken;

    customPreviewState = {
        ...customPreviewState,
        pending: true,
        ok: false,
        family: get("select-family"),
        reference: requestReference,
        signature: requestSignature,
    };
    renderCustomPreviewState();
    syncGenerateButton();

    try {
        const response = await apiPost("/?endpoint=custom-datasheet-preview", requestBody);

        if (!response.ok) {
            if (requestToken !== customPreviewRequestToken) {
                return;
            }

            if (isApiServiceFailureStatus(response.status)) {
                markApiDegraded();
            }

            const errorData = await readApiJsonError(response, "Custom datasheet preview failed.");
            customPreviewState = {
                ...createEmptyCustomPreviewState(),
                family: get("select-family"),
                reference: requestReference,
                messageVariables: { message: errorData.message },
                messageKey: "configurator.runtime.customPreviewFailedWithMessage",
                messageFallback: "Custom datasheet preview failed: " + errorData.message,
            };
            renderCustomPreviewState();
            syncGenerateButton();
            return;
        }

        const payload = await response.json();
        const previewData = payload?.data || {};

        if (
            requestToken !== customPreviewRequestToken
            || !isCustomMode()
            || requestSignature !== buildCustomRequestSignature()
        ) {
            return;
        }

        const appliedFields = previewData.applied_fields || {};
        const runtimeImplemented = Boolean(previewData.custom_datasheet?.runtime_implemented);

        customPreviewState = {
            pending: false,
            ok: true,
            runtimeImplemented,
            family: get("select-family"),
            reference: requestReference,
            signature: requestSignature,
            textOverrideCount: Number((appliedFields.text || []).length || 0),
            assetOverrideCount: Number((appliedFields.assets || []).length || 0),
            hiddenSectionCount: Number((appliedFields.hidden_sections || []).length || 0),
            messageKey: runtimeImplemented
                ? "configurator.runtime.customPreviewReady"
                : "configurator.runtime.customRuntimePending",
            messageFallback: runtimeImplemented
                ? "Custom datasheet preview ready. Generate the PDF when needed."
                : "Custom datasheet preview is scaffolded. PDF render is still pending implementation.",
        };
        renderCustomPreviewState();
        syncGenerateButton();
    } catch (error) {
        if (requestToken !== customPreviewRequestToken) {
            return;
        }

        const message = error && error.message ? error.message : "Custom datasheet preview failed.";

        customPreviewState = {
            ...createEmptyCustomPreviewState(),
            family: get("select-family"),
            reference: requestReference,
            messageVariables: { message },
            messageKey: "configurator.runtime.customPreviewFailedWithMessage",
            messageFallback: "Custom datasheet preview failed: " + message,
        };
        renderCustomPreviewState();
        syncGenerateButton();
        console.error(error);
    }
}

async function readApiJsonError(response, fallbackMessage) {
    const raw = await response.text();
    const contentType = response.headers.get("content-type") || "";
    let message = extractResponseMessage(raw) || fallbackMessage || ("Request failed with status " + response.status);

    if (contentType.includes("application/json") && raw.trim() !== "") {
        try {
            const payload = JSON.parse(raw);
            const detail = typeof payload?.detail === "string" && payload.detail.trim() !== "" ? payload.detail.trim() : "";
            message = typeof payload?.error === "string" && payload.error.trim() !== ""
                ? payload.error.trim()
                : message;

            if (detail !== "") {
                message += " - " + detail;
            }
        } catch (_parseError) {
            // Keep cleaned text fallback.
        }
    }

    return { message };
}

function getDownloadFilename(response, fallbackFilename) {
    const disposition = response.headers.get("content-disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match?.[1] || fallbackFilename;
}

async function handleGenerateAction() {
    if (isShowcaseMode()) {
        await generateShowcasePdf();
        return;
    }

    if (isCustomMode()) {
        await generateCustomDatasheetPdf();
        return;
    }

    await generateDatasheet();
}

async function generateShowcasePdf() {
    if (!canRequestShowcasePreview()) {
        setStatusKey(
            "configurator.runtime.showcasePreviewNeedsScope",
            "error",
            {},
            "Choose a family and keep the scope filters you want before previewing showcase PDF."
        );
        return;
    }

    if (!showcasePreviewState.ok || showcasePreviewState.signature !== buildShowcaseRequestSignature()) {
        scheduleShowcasePreview();
        setStatusKey(
            "configurator.runtime.showcasePreviewRequired",
            "error",
            {},
            "Wait for showcase preview before generating PDF."
        );
        return;
    }

    const body = buildShowcaseRequestBody();
    const fallbackFilename = sanitizeTecitCode(body.base_reference || body.family || "showcase") + "-showcase.pdf";
    let holdSuccessState = false;

    setGenerateControlsDisabled(true);
    setPdfLoadingOverlayState("loading");
    setPdfLoadingOverlayOpen(true);
    setStatusKey("configurator.runtime.generatingShowcase", "loading", {}, "Generating showcase PDF...");

    try {
        const response = await apiPost("/?endpoint=showcase-pdf", body);

        setGenerateControlsDisabled(false);
        syncGenerateButton();

        if (!response.ok) {
            if (isApiServiceFailureStatus(response.status)) {
                markApiDegraded();
            }

            const errorData = await readApiJsonError(response, "Showcase PDF generation failed.");
            setStatusKey(
                "configurator.runtime.showcaseFailedWithMessage",
                "error",
                { message: errorData.message },
                "Showcase PDF generation failed: " + errorData.message
            );
            return;
        }

        const successContentType = response.headers.get("content-type") || "";

        if (successContentType.includes("application/json") || successContentType.includes("text/html")) {
            const rawMessage = await response.text();
            throw new Error(extractResponseMessage(rawMessage) || "Showcase endpoint returned a non-PDF response.");
        }

        const blob = await response.blob();

        if (blob.size === 0) {
            throw new Error("Showcase endpoint returned an empty PDF response.");
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = getDownloadFilename(response, fallbackFilename);
        link.click();

        URL.revokeObjectURL(url);
        holdSuccessState = true;
        setPdfLoadingOverlayState("success");
        setStatusKey("configurator.runtime.showcaseReady", "success", {}, "Showcase PDF ready. The download has started.");
    } catch (error) {
        setGenerateControlsDisabled(false);
        syncGenerateButton();
        const message = error && error.message ? error.message : "";

        if (message !== "") {
            setStatusKey(
                "configurator.runtime.showcaseFailedWithMessage",
                "error",
                { message },
                "Showcase PDF generation failed: " + message
            );
            console.error(error);
            return;
        }

        setStatusKey("configurator.runtime.showcaseFailed", "error", {}, "Showcase PDF generation failed.");
        console.error(error);
    } finally {
        if (holdSuccessState) {
            await waitForNextPaint();
            await new Promise((resolve) => window.setTimeout(resolve, PDF_LOADING_SUCCESS_HOLD_MS));
        }

        setPdfLoadingOverlayOpen(false);
        setPdfLoadingOverlayState("loading");
    }
}

async function generateCustomDatasheetPdf() {
    if (!canRequestCustomPreview()) {
        setStatusKey(
            "configurator.runtime.customPreviewNeedsBase",
            "error",
            {},
            "Build one exact datasheet configuration first, then add custom overrides."
        );
        return;
    }

    if (!customPreviewState.ok || customPreviewState.signature !== buildCustomRequestSignature()) {
        scheduleCustomPreview();
        setStatusKey(
            "configurator.runtime.customPreviewRequired",
            "error",
            {},
            "Wait for the custom datasheet preview before generating the PDF."
        );
        return;
    }

    if (!customPreviewState.runtimeImplemented) {
        setStatusKey(
            "configurator.runtime.customRuntimePending",
            "warning",
            {},
            "Custom datasheet preview is scaffolded. PDF render is still pending implementation."
        );
        return;
    }

    const body = buildCustomRequestBody();
    const reference = sanitizeTecitCode(body.base_request?.referencia || "");
    const fallbackFilename = (reference || "custom-datasheet") + ".pdf";
    let holdSuccessState = false;

    setGenerateControlsDisabled(true);
    setPdfLoadingOverlayState("loading");
    setPdfLoadingOverlayOpen(true);
    setStatusKey("configurator.runtime.generatingCustom", "loading", {}, "Generating custom datasheet...");

    try {
        const response = await apiPost("/?endpoint=custom-datasheet-pdf", body);

        setGenerateControlsDisabled(false);
        syncGenerateButton();

        if (!response.ok) {
            if (isApiServiceFailureStatus(response.status)) {
                markApiDegraded();
            }

            const errorData = await readApiJsonError(response, "Custom datasheet generation failed.");
            setStatusKey(
                "configurator.runtime.customFailedWithMessage",
                "error",
                { message: errorData.message },
                "Custom datasheet generation failed: " + errorData.message
            );
            return;
        }

        const successContentType = response.headers.get("content-type") || "";

        if (successContentType.includes("application/json") || successContentType.includes("text/html")) {
            const rawMessage = await response.text();
            throw new Error(extractResponseMessage(rawMessage) || "Custom datasheet endpoint returned a non-PDF response.");
        }

        const blob = await response.blob();

        if (blob.size === 0) {
            throw new Error("Custom datasheet endpoint returned an empty PDF response.");
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = getDownloadFilename(response, fallbackFilename);
        link.click();

        URL.revokeObjectURL(url);
        holdSuccessState = true;
        setPdfLoadingOverlayState("success");
        setStatusKey("configurator.runtime.customReady", "success", {}, "Custom datasheet ready. The download has started.");
    } catch (error) {
        setGenerateControlsDisabled(false);
        syncGenerateButton();
        const message = error && error.message ? error.message : "";

        if (message !== "") {
            setStatusKey(
                "configurator.runtime.customFailedWithMessage",
                "error",
                { message },
                "Custom datasheet generation failed: " + message
            );
            console.error(error);
            return;
        }

        setStatusKey("configurator.runtime.customFailed", "error", {}, "Custom datasheet generation failed.");
        console.error(error);
    } finally {
        if (holdSuccessState) {
            await waitForNextPaint();
            await new Promise((resolve) => window.setTimeout(resolve, PDF_LOADING_SUCCESS_HOLD_MS));
        }

        setPdfLoadingOverlayOpen(false);
        setPdfLoadingOverlayState("loading");
    }
}

function prependBlankSelectOption(select, shouldSelect = false) {
    if (!select || select.id === "select-family") {
        return;
    }

    const firstOption = select.options[0] || null;

    if (firstOption && firstOption.value === "") {
        firstOption.dataset.placeholder = "true";
        delete firstOption.dataset.emptyState;
        firstOption.textContent = "--";
        if (shouldSelect) {
            firstOption.selected = true;
            select.value = "";
        }
        return;
    }

    const placeholderOption = document.createElement("option");
    placeholderOption.value = "";
    placeholderOption.dataset.placeholder = "true";
    placeholderOption.textContent = "--";
    select.insertBefore(placeholderOption, select.firstChild);

    if (shouldSelect) {
        placeholderOption.selected = true;
        select.value = "";
    }
}

function primeSelectPlaceholders() {
    document.querySelectorAll("select[id]").forEach((select) => {
        if (!REFERENCE_PLACEHOLDER_SELECT_IDS.has(select.id)) {
            return;
        }

        prependBlankSelectOption(select, true);
    });
}

function bindSystemTooltips(root = document) {
    const wrappers = root.querySelectorAll(".tooltip-wrapper");

    wrappers.forEach((wrapper) => {
        if (wrapper.dataset.tooltipBound === "true") {
            return;
        }

        const tooltip = wrapper.querySelector(".tooltip");

        if (!tooltip) {
            return;
        }

        const showTooltip = () => {
            tooltip.style.opacity = "1";
            tooltip.style.transform = "translateX(-50%) scale(1) translateY(0)";
        };

        const hideTooltip = () => {
            tooltip.style.opacity = "0";
            tooltip.style.transform = "translateX(-50%) scale(var(--scale-press)) translateY(var(--press-offset))";
        };

        wrapper.addEventListener("mouseenter", showTooltip);
        wrapper.addEventListener("mouseleave", hideTooltip);
        wrapper.addEventListener("focusin", showTooltip);
        wrapper.addEventListener("focusout", (event) => {
            if (wrapper.contains(event.relatedTarget)) {
                return;
            }

            hideTooltip();
        });

        wrapper.dataset.tooltipBound = "true";
    });
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

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

async function getApiBase() {
    if (!apiBasePromise) {
        apiBasePromise = Promise.resolve(resolveApiBase());
    }

    return apiBasePromise;
}

function resolveApiBase() {
    if (typeof window !== "undefined" && window.location) {
        const hostname = String(window.location.hostname || "").toLowerCase();

        if (LOCAL_API_HOSTNAMES.has(hostname)) {
            const localApiBase = buildLocalApiBase(window.location.origin, window.location.pathname);

            if (localApiBase) {
                return localApiBase;
            }
        }
    }

    return DEFAULT_API_BASE.replace(/\/+$/, "");
}

function buildLocalApiBase(origin, pathname) {
    const cleanOrigin = String(origin || "").replace(/\/+$/, "");
    const cleanPathname = String(pathname || "");

    if (!cleanOrigin) {
        return "";
    }

    if (cleanPathname.includes("/configurator/")) {
        return (cleanOrigin + cleanPathname.split("/configurator/")[0] + "/api").replace(/\/+$/, "");
    }

    const withoutFile = cleanPathname.replace(/\/[^/]*$/, "");
    const withoutConfiguratorRoot = withoutFile.replace(/\/configurator$/, "");
    return (cleanOrigin + withoutConfiguratorRoot + "/api").replace(/\/+$/, "");
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
    setApiBadgeKey("warning", "shared.badge.apiDegraded", "API degraded");
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
        "configurator.runtime.apiFailureFile": "Product data is unavailable right now.",
        "configurator.runtime.apiFailureLocal": "Product data is unavailable right now.",
        "configurator.runtime.apiFailureRemote": "Unable to reach product data from this page.",
    };

    return fallbacks[key] || "";
}

function getFamilyPlaceholderMessageKey() {
    return "configurator.runtime.familyPlaceholderRemote";
}

function getFamilyPlaceholderFallback(key) {
    const fallbacks = {
        "configurator.runtime.familyPlaceholderFile": "Unable to load product families right now.",
        "configurator.runtime.familyPlaceholderLocal": "Unable to load product families right now.",
        "configurator.runtime.familyPlaceholderRemote": "Unable to load product families right now.",
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
        upsertOption(item) {
            if (!item || !item.value || !item.label) {
                return false;
            }

            const existing = getOptions().find((option) => option.dataset.value === item.value);

            if (existing) {
                existing.dataset.label = item.label;

                const labelNode = existing.querySelector(".combobox-option-label");

                if (labelNode) {
                    labelNode.textContent = item.label;
                }

                syncSelectedState();
                updateFilter(input.value.trim());
                return true;
            }

            const items = getOptions().map((option) => ({
                value: option.dataset.value,
                label: option.dataset.label,
            }));

            items.push({
                value: item.value,
                label: item.label,
            });

            items.sort((left, right) => left.label.localeCompare(right.label, undefined, { numeric: true, sensitivity: "base" }));
            renderOptions(items);
            return true;
        },
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
            statusState.tone,
            statusState.key
        );
        return;
    }

    applyStatusText(statusState.text || "", statusState.tone, "");
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

    const badgeClass = API_BADGE_VARIANT_CLASS[apiBadgeState.tone] || API_BADGE_VARIANT_CLASS.error;
    const text = apiBadgeState.key
        ? t(apiBadgeState.key, resolveTranslatedVariables(apiBadgeState), apiBadgeState.fallback)
        : apiBadgeState.text || "";

    document.querySelectorAll("[data-api-badge]").forEach((badge) => {
        badge.className = API_BADGE_BASE_CLASS + " " + badgeClass;
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
    renderShowcaseControls();
    renderTecitCodeLogicFamilies();
    applyStatusState();
    applySummaryState();
    applyApiBadgeState();
    applyOutputModeState();
}

window.addEventListener(CONFIGURATOR_I18N_EVENT, refreshLocalizedControls);

function bindDocumentLanguageControls() {
    const languageSelect = document.getElementById("select-language");

    if (!languageSelect) {
        return;
    }

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

        const rawError = await response.text();
        const cleanError = extractResponseMessage(rawError);
        let message = cleanError || "Request failed with status " + response.status;
        let code = "";

        if (rawError.trim() !== "") {
            try {
                const errorPayload = JSON.parse(rawError);

                if (typeof errorPayload?.error === "string" && errorPayload.error.trim() !== "") {
                    message = errorPayload.error.trim();
                }

                if (typeof errorPayload?.error_code === "string") {
                    code = errorPayload.error_code;
                }
            } catch (_parseError) {
                // Keep cleaned text fallback when body is not JSON.
            }
        }

        const requestError = new Error(message);
        requestError.status = response.status;
        requestError.code = code;
        throw requestError;
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
        availableConfiguratorFamilies = Array.isArray(data) ? data : [];
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
        void applyConfiguratorDeepLinkIfNeeded();
    } catch (error) {
        availableConfiguratorFamilies = [];
        familyCombobox?.renderOptions([]);
        familyCombobox?.setDisabled(true);
        setFamilyPlaceholderKey(getFamilyPlaceholderMessageKey(), getFamilyPlaceholderFallback(getFamilyPlaceholderMessageKey()));
        markApiUnavailable();
        setStatusKey(getApiFailureMessageKey(), "error", {}, getApiFailureFallback(getApiFailureMessageKey()));
        console.error(error);
    }
}

function renderTecitCodeLogicFamilies() {
    const list = document.getElementById("tecitCodeLogicFamiliesList");

    if (!list) {
        return;
    }

    const families = TECIT_LOGIC_FAMILIES;

    if (families.length === 0) {
        list.innerHTML = `
            <div class="list-item">
                <dt class="list-key">${escapeHtml(t("configurator.familyLabel", {}, "Family"))}</dt>
                <dd class="list-value text-grey-primary">${escapeHtml(t("configurator.tecitCodeLogicFamiliesEmpty", {}, "No families loaded yet."))}</dd>
            </div>
        `;
        return;
    }

    list.innerHTML = families.map((family) => {
        return `
            <div class="list-item">
                <dt class="list-key"><code class="text-body">${escapeHtml(family.code)}</code></dt>
                <dd class="list-value">${escapeHtml(family.name || family.code)}</dd>
            </div>
        `;
    }).join("");
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
            subtitle: "Loading the valid manufacturing options for the selected family.",
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
    updateShowcaseFamilyHint();
    updateCustomFamilyHint();
    syncShowcaseScopeFieldStates();
    scheduleShowcasePreview();
    scheduleCustomPreview();

    setSummaryStateKeys(
        "configurator.runtime.step2",
        "configurator.runtime.referenceBuilderActive",
        "configurator.runtime.referenceBuilderSubtitle",
        {},
        {
            step: "Step 2",
            title: "Reference ready to edit",
            subtitle: "Adjust the configuration. The live output on the right updates automatically.",
        }
    );
    setStatusKey("configurator.runtime.optionsLoaded", "success", {}, "Options loaded. The reference now updates automatically.");
}

function bindLiveReferenceLoader() {
    const input = document.getElementById("output-reference");
    const button = document.getElementById("btn-load-output-reference");

    if (!input || !button || input.dataset.liveReferenceBound === "true") {
        return;
    }

    input.addEventListener("input", () => {
        input.value = sanitizeTecitCode(input.value);
        liveReferenceDraftDirty = true;
        descriptionRequestToken += 1;
        document.getElementById("output-description").value = "";
        resetShowcasePreviewState();
        resetCustomPreviewState();
        syncGenerateButton();
        syncCopyButtons();
    });

    input.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") {
            return;
        }

        event.preventDefault();
        loadTecitCodeIntoForm("output-reference");
    });

    button.addEventListener("click", () => loadTecitCodeIntoForm("output-reference"));

    input.dataset.liveReferenceBound = "true";
}

function sanitizeTecitCode(value) {
    return String(value || "")
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, "");
}

function getConfiguratorDeepLinkReference() {
    const params = new URLSearchParams(window.location.search);
    return sanitizeTecitCode(params.get("reference") || "");
}

function shouldAutoGenerateFromDeepLink() {
    const params = new URLSearchParams(window.location.search);
    return params.get("generate") === "1";
}

async function waitForConfiguratorDescription(reference, timeoutMs = 5000) {
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutMs) {
        const currentReference = sanitizeTecitCode(document.getElementById("output-reference")?.value || "");
        const currentDescription = String(document.getElementById("output-description")?.value || "").trim();

        if (currentReference === reference && currentDescription) {
            return true;
        }

        await new Promise((resolve) => window.setTimeout(resolve, 120));
    }

    return false;
}

async function applyConfiguratorDeepLinkIfNeeded() {
    const reference = getConfiguratorDeepLinkReference();

    if (!reference || configuratorDeepLinkState.applied || configuratorDeepLinkState.loading) {
        return;
    }

    const input = document.getElementById("output-reference");

    if (!input) {
        return;
    }

    configuratorDeepLinkState.loading = true;
    input.value = reference;

    try {
        const applied = await loadTecitCodeIntoForm("output-reference");

        configuratorDeepLinkState.applied = true;

        if (!applied || !shouldAutoGenerateFromDeepLink()) {
            return;
        }

        const hasDescription = await waitForConfiguratorDescription(reference);

        if (!hasDescription) {
            setStatusKey("configurator.runtime.descriptionLoadFailed", "error", {}, "The reference was built, but the description could not be loaded.");
            return;
        }

        await generateDatasheet();
    } finally {
        configuratorDeepLinkState.loading = false;
    }
}

function setTecitCodeControlsDisabled(isDisabled) {
    const input = document.getElementById("output-reference");
    const button = document.getElementById("btn-load-output-reference");

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

function getSelectedOptionElement(id) {
    const element = document.getElementById(id);

    if (!element || element.tagName !== "SELECT") {
        return null;
    }

    return element.options[element.selectedIndex] || null;
}

function isBlankSelectOption(option) {
    if (!option) {
        return true;
    }

    return String(option.value || "") === "" || option.dataset.placeholder === "true";
}

function hasResolvedSelectValue(id) {
    return !isBlankSelectOption(getSelectedOptionElement(id));
}

function hasResolvedReferenceSegments() {
    if (!get("select-family")) {
        return false;
    }

    return Object.values(DECODED_SEGMENT_FIELDS).every((meta) => hasResolvedSelectValue(meta.id));
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
    const options = Array.from(select?.options || []).filter((option) => !isBlankSelectOption(option));

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
    let familyMatched = familyCombobox?.selectByValue(familyCode, false) || false;

    if (!familyMatched && familyCode && data?.family_name) {
        familyCombobox?.upsertOption({
            value: familyCode,
            label: data.family_name,
        });

        familyMatched = familyCombobox?.selectByValue(familyCode, false) || false;
    }

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

async function loadTecitCodeIntoForm(sourceInputId = "output-reference") {
    const input = document.getElementById(sourceInputId);
    const reference = sanitizeTecitCode(input?.value || "");
    const shouldValidateCurrentBuilderState = sourceInputId === "output-reference" && !liveReferenceDraftDirty;

    if (!input) {
        return false;
    }

    input.value = reference;

    liveReferenceDraftDirty = true;
    syncCopyButtons();

    if (shouldValidateCurrentBuilderState && !hasResolvedReferenceSegments()) {
        setStatusKey("configurator.runtime.completeConfiguration", "error", {}, "Complete the configuration before generating the datasheet.");
        input.focus();
        return false;
    }

    if (!reference) {
        setStatusKey("configurator.runtime.tecitCodeMissing", "error", {}, "Enter a Tecit code first.");
        input.focus();
        return false;
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
            setStatusKey("configurator.runtime.tecitCodeInvalid", "error", {}, "This Tecit code cannot be applied. A full live reference must contain exactly 17 characters.");
            return false;
        }

        if (data?.warnings?.includes("unknown_family") || !data?.segments?.family) {
            setStatusKey("configurator.runtime.tecitCodeFamilyMissing", "error", {}, "This Tecit code uses a family that is not available in the configurator.");
            return false;
        }

        const result = await applyDecodedReferenceToForm(data);

        if (!result.familyMatched) {
            setStatusKey("configurator.runtime.tecitCodeFamilyMissing", "error", {}, "This Tecit code uses a family that is not available in the configurator.");
            return false;
        }

        if (result.unresolved.length > 0) {
            setStatusKey(
                "configurator.runtime.tecitCodeFieldsMissing",
                "error",
                { fields: result.unresolved.join(", ") },
                "Tecit code loaded, but these fields could not be matched: " + result.unresolved.join(", ")
            );
            return false;
        }

        if (result.reference !== data.reference) {
            setStatusKey(
                "configurator.runtime.tecitCodeMismatch",
                "error",
                { reference: result.reference },
                "Tecit code loaded, but rebuilt reference differs: " + result.reference
            );
            return false;
        }

        if (data?.error_code === "invalid_luminos_combination") {
            document.getElementById("output-description").value = "";
            syncCopyButtons();
            setStatusKey(
                "configurator.runtime.tecitCodeInvalidLuminos",
                "error",
                {},
                "The family, size, color, CRI, and series combination does not exist in the Luminos view."
            );
            return false;
        }

        setStatusKey("configurator.runtime.tecitCodeApplied", "success", {}, "Tecit code loaded. Manufacturing fields were filled.");
        return true;
    } catch (error) {
        setStatusKey("configurator.runtime.tecitCodeApplyFailed", "error", {}, "Unable to decode Tecit code right now.");
        console.error(error);
        return false;
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
    prependBlankSelectOption(select, true);

    if (!Array.isArray(items) || items.length === 0) {
        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.dataset.placeholder = "true";
        emptyOption.dataset.emptyState = "true";
        emptyOption.dataset.i18n = "configurator.runtime.noOptionsAvailable";
        emptyOption.textContent = t("configurator.runtime.noOptionsAvailable", {}, "No options available");
        const placeholderOption = select.options[0] || null;

        if (placeholderOption) {
            placeholderOption.value = "";
            placeholderOption.dataset.placeholder = "true";
            placeholderOption.dataset.emptyState = "true";
            placeholderOption.dataset.i18n = emptyOption.dataset.i18n;
            placeholderOption.textContent = emptyOption.textContent;
        } else {
            select.appendChild(emptyOption);
        }
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

    liveReferenceDraftDirty = false;
    document.getElementById("output-reference").value = reference;

    syncGenerateButton();
    syncCopyButtons();
    scheduleShowcasePreview();
    scheduleCustomPreview();

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
        syncCopyButtons();

        if (error?.code === "invalid_luminos_combination") {
            setStatusKey(
                "configurator.runtime.referenceInvalidLuminos",
                "error",
                {},
                "The family, size, color, CRI, and series combination does not exist in the Luminos view."
            );
            console.error(error);
            return;
        }

        setStatusKey("configurator.runtime.descriptionLoadFailed", "error", {}, "The reference was built, but the description could not be loaded.");
        console.error(error);
    }
}

async function generateDatasheet() {
    const reference = document.getElementById("output-reference").value;

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

    if (!hasResolvedReferenceSegments()) {
        setStatusKey("configurator.runtime.completeConfiguration", "error", {}, "Complete the configuration before generating the datasheet.");
        return;
    }

    const body = buildDatasheetRequestBody();

    let holdSuccessState = false;

    setGenerateControlsDisabled(true);
    setPdfLoadingOverlayState("loading");
    setPdfLoadingOverlayOpen(true);
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
        holdSuccessState = true;
        setPdfLoadingOverlayState("success");
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
    } finally {
        if (holdSuccessState) {
            await waitForNextPaint();
            await new Promise((resolve) => window.setTimeout(resolve, PDF_LOADING_SUCCESS_HOLD_MS));
        }

        setPdfLoadingOverlayOpen(false);
        setPdfLoadingOverlayState("loading");
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
                handleGenerateAction();
            }
        });
    });
}

function bindStatusToast() {
    const closeButton = document.getElementById("status-message-close");

    if (!closeButton || closeButton.dataset.bound === "true") {
        return;
    }

    closeButton.dataset.bound = "true";
    closeButton.addEventListener("click", () => {
        hideStatusToast();
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
    resetShowcasePreviewState();
    resetCustomPreviewState();
    resetCustomOverrides(false);
    updateShowcaseFamilyHint();
    updateCustomFamilyHint();
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
    liveReferenceDraftDirty = false;
    document.getElementById("output-reference").value = "";
    document.getElementById("output-description").value = "";
}

function syncGenerateButton() {
    const button = document.getElementById("btn-generate");
    const hasReference = document.getElementById("output-reference").value.length > 0;
    const hasResolvedDatasheetConfig = hasResolvedReferenceSegments();
    const showcaseDisabled = !get("select-family")
        || !isCurrentFamilyShowcaseAvailable()
        || showcasePreviewState.pending
        || !showcasePreviewState.ok
        || getSelectedShowcaseSections().length === 0
        || showcasePreviewState.signature !== buildShowcaseRequestSignature();
    const customDisabled = !get("select-family")
        || !isCurrentFamilyCustomDatasheetAvailable()
        || customPreviewState.pending
        || !customPreviewState.ok
        || !customPreviewState.runtimeImplemented
        || customPreviewState.signature !== buildCustomRequestSignature();
    const datasheetDisabled = !hasReference || !hasResolvedDatasheetConfig || liveReferenceDraftDirty || !isDatasheetServiceAvailable();
    const isDisabled = isShowcaseMode()
        ? showcaseDisabled
        : isCustomMode()
            ? customDisabled
            : datasheetDisabled;

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

function setPdfLoadingOverlayOpen(isOpen) {
    const overlay = document.getElementById("pdf-loading-overlay");

    if (!overlay) {
        return;
    }

    overlay.classList.toggle("is-open", isOpen);
    overlay.setAttribute("aria-hidden", String(!isOpen));
    overlay.inert = !isOpen;

    document.body.classList.toggle(
        "modal-open",
        Array.from(document.querySelectorAll(".modal-overlay")).some((item) => item.classList.contains("is-open"))
    );
}

function setPdfLoadingOverlayState(state) {
    const loadingState = document.getElementById("pdf-loading-state");
    const successState = document.getElementById("pdf-success-state");
    const status = document.getElementById("pdf-loading-status");

    if (!loadingState || !successState || !status) {
        return;
    }

    const isSuccess = state === "success";

    loadingState.classList.toggle("hidden", isSuccess);
    successState.classList.toggle("hidden", !isSuccess);
    successState.setAttribute("aria-hidden", String(!isSuccess));
    status.setAttribute("aria-busy", String(!isSuccess));
}

function waitForNextPaint() {
    return new Promise((resolve) => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(resolve);
        });
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

function applyStatusText(message, tone = "neutral", key = "") {
    const toast = document.getElementById("status-message");
    const copy = document.getElementById("status-message-copy");
    const icon = document.getElementById("status-message-icon");
    const variant = STATUS_TOAST_VARIANT[tone] || STATUS_TOAST_VARIANT.neutral;
    const shouldHide = !message || (tone === "neutral" && key === "configurator.runtime.chooseFamilyToBegin");

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

function get(id) {
    const element = document.getElementById(id);

    if (!element) {
        return "";
    }

    if (element.tagName === "SELECT") {
        const option = element.options[element.selectedIndex] || null;
        return isBlankSelectOption(option) ? "" : element.value;
    }

    return element.value;
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

    const option = element.options[element.selectedIndex] || null;
    return isBlankSelectOption(option) ? "" : option.text || "";
}

function getSelectedOptionHint(id) {
    const element = document.getElementById(id);

    if (!element || element.tagName !== "SELECT") {
        return "";
    }

    const option = getSelectedOptionElement(id);

    if (isBlankSelectOption(option)) {
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
