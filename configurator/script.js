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
const SHOWCASE_DEFAULT_FILTERS = {
    datasheet_ready_only: true,
    max_variants: 250,
    max_pages: 60,
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
const SELECT_REQUEST_DEFAULTS = Object.freeze({
    "select-language": "pt",
    "select-company": "0",
    "select-purpose": "0",
    "select-connector-cable": "0",
    "select-cable-type": "branco",
    "select-end-cap": "0",
    "select-gasket": "5",
    "select-ip": "0",
    "select-fixing": "0",
    "select-power-supply": "0",
    "select-connection-cable": "0",
    "select-connection-connector": "0",
});
const CUSTOM_TEXT_OVERRIDE_FIELDS = [
    { id: "custom-document-title", key: "document_title" },
    { id: "custom-header-copy", key: "header_copy" },
    { id: "custom-footer-note", key: "footer_note" },
];
const CUSTOM_ASSET_OVERRIDE_FIELDS = [
    {
        id: "custom-header-image-asset",
        key: "header_image",
        uploadTriggerId: "custom-header-image-upload-trigger",
        uploadInputId: "custom-header-image-upload",
        uploadStatusId: "custom-header-image-upload-status",
        uploadFolderId: "nexled/media/custom-datasheet/packshots",
        uploadRole: "packshot",
        summaryId: "custom-header-image-asset-summary",
        browserButtonId: "custom-header-image-browser-button",
        targetLabelKey: "configurator.custom.browserTargetHeader",
        targetFallback: "Header image",
    },
    {
        id: "custom-drawing-image-asset",
        key: "drawing_image",
        uploadTriggerId: "custom-drawing-image-upload-trigger",
        uploadInputId: "custom-drawing-image-upload",
        uploadStatusId: "custom-drawing-image-upload-status",
        uploadFolderId: "nexled/media/custom-datasheet/drawings",
        uploadRole: "drawing",
        summaryId: "custom-drawing-image-asset-summary",
        browserButtonId: "custom-drawing-image-browser-button",
        targetLabelKey: "configurator.custom.browserTargetDrawing",
        targetFallback: "Drawing image",
    },
    {
        id: "custom-finish-image-asset",
        key: "finish_image",
        uploadTriggerId: "custom-finish-image-upload-trigger",
        uploadInputId: "custom-finish-image-upload",
        uploadStatusId: "custom-finish-image-upload-status",
        uploadFolderId: "nexled/media/custom-datasheet/finishes",
        uploadRole: "finish",
        summaryId: "custom-finish-image-asset-summary",
        browserButtonId: "custom-finish-image-browser-button",
        targetLabelKey: "configurator.custom.browserTargetFinish",
        targetFallback: "Finish image",
    },
];
const CUSTOM_SECTION_VISIBILITY_FIELDS = [
    { id: "custom-section-fixing", key: "fixing" },
    { id: "custom-section-power-supply", key: "power_supply" },
    { id: "custom-section-connection-cable", key: "connection_cable" },
];
const CUSTOM_EDIT_MODE_BASIC_ID = "custom-edit-mode-basic";
const CUSTOM_EDIT_MODE_ADVANCED_ID = "custom-edit-mode-advanced";
const CUSTOM_ADVANCED_TOGGLE_ID = "custom-advanced-copy-toggle";
const CUSTOM_IMAGES_TOGGLE_ID = "custom-images-enabled";
const CUSTOM_TEXT_TOGGLE_ID = "custom-text-enabled";
const CUSTOM_SECTIONS_TOGGLE_ID = "custom-sections-enabled";
const CUSTOM_OPTIONAL_BLOCK_DEFINITIONS = [
    { toggleId: CUSTOM_IMAGES_TOGGLE_ID, fieldsId: "custom-images-fields" },
    { toggleId: CUSTOM_TEXT_TOGGLE_ID, fieldsId: "custom-text-fields" },
    { toggleId: CUSTOM_SECTIONS_TOGGLE_ID, fieldsId: "custom-sections-fields" },
];
const CUSTOM_ADVANCED_COPY_SECTION_DEFINITIONS = [
    { section: "header", field: "intro", labelKey: "configurator.custom.copySectionHeader", fallback: "Header", maxLength: 1200 },
    { section: "characteristics", field: "intro", labelKey: "configurator.custom.copySectionCharacteristics", fallback: "Characteristics", maxLength: 800 },
    { section: "luminotechnical", field: "intro", labelKey: "configurator.custom.copySectionLuminotechnical", fallback: "Luminotechnical", maxLength: 800 },
    { section: "drawing", field: "intro", labelKey: "configurator.custom.copySectionDrawing", fallback: "Technical drawing", maxLength: 800 },
    { section: "color_graph", field: "intro", labelKey: "configurator.custom.copySectionColorGraph", fallback: "Color graph", maxLength: 800 },
    { section: "lens_diagram", field: "intro", labelKey: "configurator.custom.copySectionLensDiagram", fallback: "Lens diagram", maxLength: 800 },
    { section: "finish", field: "intro", labelKey: "configurator.custom.copySectionFinish", fallback: "Finish", maxLength: 800 },
    { section: "fixing", field: "intro", labelKey: "configurator.custom.copySectionFixing", fallback: "Fixing", maxLength: 800 },
    { section: "power_supply", field: "intro", labelKey: "configurator.custom.copySectionPowerSupply", fallback: "Power supply", maxLength: 1200 },
    { section: "connection_cable", field: "intro", labelKey: "configurator.custom.copySectionConnectionCable", fallback: "Connection cable", maxLength: 1200 },
    { section: "footer", field: "note", labelKey: "configurator.custom.copySectionFooter", fallback: "Footer", maxLength: 160 },
];
const CUSTOM_FIELD_OVERRIDE_GROUPS = [
    {
        id: "identity",
        titleKey: "configurator.custom.fieldGroupIdentity",
        fallback: "Displayed Product Data",
        fields: [
            { key: "display_reference", labelKey: "configurator.quickActions.reference", fallback: "Reference", maxLength: 64, containerId: "field-display-reference" },
            { key: "display_description", labelKey: "configurator.quickActions.description", fallback: "Description", maxLength: 160, containerId: "field-display-description" },
            { key: "display_size", labelKey: "configurator.fields.size", fallback: "Size", maxLength: 80, containerId: "field-size" },
            { key: "display_color", labelKey: "configurator.fields.color", fallback: "Color", maxLength: 120, containerId: "field-color" },
            { key: "display_cri", labelKey: "configurator.fields.cri", fallback: "CRI", maxLength: 80, containerId: "field-cri" },
            { key: "display_series", labelKey: "configurator.fields.series", fallback: "Series", maxLength: 120, containerId: "field-series" },
            { key: "display_lens_name", labelKey: "configurator.fields.lens", fallback: "Lens", maxLength: 120, containerId: "field-lens" },
            { key: "display_finish_name", labelKey: "configurator.fields.finish", fallback: "Finish", maxLength: 120, containerId: "field-finish" },
            { key: "display_cap", labelKey: "configurator.fields.cap", fallback: "Cap", maxLength: 120, containerId: "field-cap" },
            { key: "display_option_code", labelKey: "configurator.fields.option", fallback: "Option", maxLength: 120, containerId: "field-option" },
        ],
    },
    {
        id: "config",
        titleKey: "configurator.custom.fieldGroupConfig",
        fallback: "Configuration Labels",
        fields: [
            { key: "display_connector_cable", labelKey: "configurator.fields.cableConnector", fallback: "Cable Connector", maxLength: 120, containerId: "field-connector-cable" },
            { key: "display_cable_type", labelKey: "configurator.fields.cableType", fallback: "Cable Type", maxLength: 120, containerId: "field-cable-type" },
            { key: "display_extra_length", labelKey: "configurator.fields.extraLength", fallback: "Extra Length (mm)", maxLength: 80, containerId: "field-extra-length" },
            { key: "display_end_cap", labelKey: "configurator.fields.endCap", fallback: "End Cap", maxLength: 120, containerId: "field-end-cap" },
            { key: "display_gasket", labelKey: "configurator.fields.gasket", fallback: "Gasket", maxLength: 120, containerId: "field-gasket" },
            { key: "display_ip", labelKey: "configurator.fields.ip", fallback: "IP Rating", maxLength: 120, containerId: "field-ip" },
            { key: "display_fixing", labelKey: "configurator.fields.fixing", fallback: "Fixing", maxLength: 120, containerId: "field-fixing" },
            { key: "display_power_supply", labelKey: "configurator.fields.powerSupply", fallback: "Power Supply", maxLength: 160, containerId: "field-power-supply" },
            { key: "display_connection_cable", labelKey: "configurator.fields.connectionCable", fallback: "Connection Cable", maxLength: 160, containerId: "field-connection-cable" },
            { key: "display_connection_connector", labelKey: "configurator.fields.connectionConnector", fallback: "Connection Connector", maxLength: 120, containerId: "field-connection-connector" },
            { key: "display_connection_cable_length", labelKey: "configurator.fields.connectionCableLength", fallback: "Connection Cable Length (m)", maxLength: 80, containerId: "field-connection-cable-length" },
            { key: "display_purpose", labelKey: "configurator.fields.purpose", fallback: "Purpose", maxLength: 120, containerId: "field-purpose" },
            { key: "display_company", labelKey: "configurator.fields.company", fallback: "Company Logo", maxLength: 120, containerId: "field-company" },
            { key: "display_language", labelKey: "configurator.fields.language", fallback: "Language", maxLength: 80, containerId: "field-language" },
        ],
    },
    {
        id: "technical",
        titleKey: "configurator.custom.fieldGroupTechnical",
        fallback: "Technical Values",
        fields: [
            { key: "display_flux", labelKey: "configurator.custom.fieldDisplayFlux", fallback: "Flux (Lm)", maxLength: 80 },
            { key: "display_efficacy", labelKey: "configurator.custom.fieldDisplayEfficacy", fallback: "Efficacy (Lm/W)", maxLength: 80 },
            { key: "display_cct", labelKey: "configurator.custom.fieldDisplayCct", fallback: "Color Temperature (K)", maxLength: 80 },
            { key: "display_color_label", labelKey: "configurator.custom.fieldDisplayColorLabel", fallback: "Displayed Color Label", maxLength: 120 },
            { key: "display_cri_label", labelKey: "configurator.custom.fieldDisplayCriLabel", fallback: "Displayed CRI Value", maxLength: 80 },
            { key: "drawing_dimension_A", labelKey: "configurator.custom.fieldDrawingA", fallback: "Drawing Dimension A", maxLength: 32 },
            { key: "drawing_dimension_B", labelKey: "configurator.custom.fieldDrawingB", fallback: "Drawing Dimension B", maxLength: 32 },
            { key: "drawing_dimension_C", labelKey: "configurator.custom.fieldDrawingC", fallback: "Drawing Dimension C", maxLength: 32 },
            { key: "drawing_dimension_D", labelKey: "configurator.custom.fieldDrawingD", fallback: "Drawing Dimension D", maxLength: 32 },
            { key: "drawing_dimension_E", labelKey: "configurator.custom.fieldDrawingE", fallback: "Drawing Dimension E", maxLength: 32 },
            { key: "drawing_dimension_F", labelKey: "configurator.custom.fieldDrawingF", fallback: "Drawing Dimension F", maxLength: 32 },
            { key: "drawing_dimension_G", labelKey: "configurator.custom.fieldDrawingG", fallback: "Drawing Dimension G", maxLength: 32 },
            { key: "drawing_dimension_H", labelKey: "configurator.custom.fieldDrawingH", fallback: "Drawing Dimension H", maxLength: 32 },
            { key: "drawing_dimension_I", labelKey: "configurator.custom.fieldDrawingI", fallback: "Drawing Dimension I", maxLength: 32 },
            { key: "drawing_dimension_J", labelKey: "configurator.custom.fieldDrawingJ", fallback: "Drawing Dimension J", maxLength: 32 },
            { key: "fixing_name", labelKey: "configurator.custom.fieldFixingName", fallback: "Fixing Name", maxLength: 120 },
            { key: "power_supply_description", labelKey: "configurator.custom.fieldPowerSupplyDescription", fallback: "Power Supply Description", maxLength: 1200, multiline: true },
            { key: "connection_cable_description", labelKey: "configurator.custom.fieldConnectionCableDescription", fallback: "Connection Cable Description", maxLength: 1200, multiline: true },
        ],
    },
];
const CUSTOM_FIELD_OVERRIDE_DEFINITIONS = CUSTOM_FIELD_OVERRIDE_GROUPS.flatMap((group) =>
    group.fields.map((field) => ({ ...field, group: group.id }))
);
const CUSTOM_CONTROL_IDS = [
    ...CUSTOM_TEXT_OVERRIDE_FIELDS.map((field) => field.id),
    ...CUSTOM_ASSET_OVERRIDE_FIELDS.map((field) => field.id),
    ...CUSTOM_SECTION_VISIBILITY_FIELDS.map((field) => field.id),
    CUSTOM_ADVANCED_TOGGLE_ID,
    CUSTOM_IMAGES_TOGGLE_ID,
    CUSTOM_TEXT_TOGGLE_ID,
    CUSTOM_SECTIONS_TOGGLE_ID,
];
const CUSTOM_ASSET_UPLOAD_TONES = {
    neutral: "text-grey-primary",
    success: "text-green-primary",
    error: "text-red-600",
};
const CUSTOM_IMAGE_BROWSER_DEBOUNCE_MS = 180;

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
const STATUS_TOAST_TITLE_MAX_LENGTH = 64;
const STATUS_TOAST_AUTOHIDE_DELAY = 4000;
const STATUS_TOAST_HIDE_DELAY = 320;
const STATUS_TOAST_VARIANT = {
    neutral: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: true, titleKey: "shared.toast.infoTitle", titleFallback: "Info" },
    loading: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: false, titleKey: "shared.toast.loadingTitle", titleFallback: "Loading" },
    success: { className: "toast-success", iconClass: "ri-checkbox-circle-line", role: "status", autoHide: true, titleKey: "shared.toast.successTitle", titleFallback: "Success" },
    warning: { className: "toast-warning", iconClass: "ri-alert-line", role: "status", autoHide: false, titleKey: "shared.toast.warningTitle", titleFallback: "Warning" },
    error: { className: "toast-danger", iconClass: "ri-close-circle-line", role: "alert", autoHide: true, titleKey: "shared.toast.errorTitle", titleFallback: "Error" },
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
let customEditableCopySnapshot = {};
let customEditableCopyReference = "";
let customFieldOverrideSnapshot = {};
let customFieldOverrideReference = "";
let customImageBrowserFolderRequestToken = 0;
let customImageBrowserAssetRequestToken = 0;
let customImageBrowserSearchTimer = null;
let customImageBrowserLastFocus = null;
let customImageBrowserState = {
    activeFieldId: CUSTOM_ASSET_OVERRIDE_FIELDS[0]?.id || "",
    currentFolderId: "",
    currentFolder: null,
    folders: [],
    assets: [],
    selectedAsset: null,
    selectedAssetId: null,
    searchQuery: "",
    searchScope: "folder",
};
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
    bindCustomImageBrowserControls();
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
        fieldOverrideCount: 0,
        advancedCopySectionCount: 0,
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

    renderShowcaseSectionsSelector(
        document.getElementById("showcase-sections-grid"),
        {
            labelKey: "configurator.showcase.sectionsTitle",
            labelFallback: "Showcase Sections",
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

    const previousAllExpandToggle = container.querySelector("[data-showcase-all-expand]");
    const previousSelections = new Set(
        Array.from(container.querySelectorAll("[data-showcase-expand]:checked"))
            .map((input) => input.value)
    );

    const label = t(config.labelKey, {}, config.labelFallback);
    const allOptionsLabel = t(
        "configurator.showcase.expandAllOptions",
        {},
        "All options"
    );
    const hasPreviousSelections = previousSelections.size > 0;
    const allOptionsChecked = previousAllExpandToggle instanceof HTMLInputElement
        ? previousAllExpandToggle.checked
        : true;
    const itemsMarkup = items.map((item) => {
        const checked = allOptionsChecked || previousSelections.has(item.id) || (!hasPreviousSelections && item.defaultChecked);
        const inputId = "showcase-" + item.type + "-" + item.id;
        const itemLabel = t(item.labelKey, {}, item.fallback);

        return `
            <label class="checkbox-wrapper checkbox-md">
                <span class="relative inline-flex items-center justify-center">
                    <input
                        type="checkbox"
                        id="${escapeHtml(inputId)}"
                        value="${escapeHtml(item.id)}"
                        data-showcase-expand="true"
                        ${checked ? "checked" : ""}
                        class="peer"
                    >
                    <i class="ri-check-line absolute inset-0 flex items-center justify-center leading-none text-white text-icon-md opacity-0 peer-checked:opacity-100 pointer-events-none" aria-hidden="true"></i>
                </span>
                <span class="text-body-sm text-black">${escapeHtml(itemLabel)}</span>
            </label>
        `;
    }).join("");

    container.innerHTML = `
        <div class="flex flex-col gap-16" role="group" aria-label="${escapeHtml(label)}">
            <div>
                <label class="toggle toggle-md self-start">
                    <input
                        type="checkbox"
                        data-showcase-all-expand="true"
                        ${allOptionsChecked ? "checked" : ""}
                        class="toggle-input"
                        role="switch"
                    >
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label">${escapeHtml(allOptionsLabel)}</span>
                </label>
            </div>
            <div data-showcase-expand-options="true" class="pt-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-24 gap-y-16 items-start">
                    ${itemsMarkup}
                </div>
            </div>
        </div>
    `;

    syncShowcaseExpandVisibility(container);
}

function renderShowcaseSectionsSelector(container, config, items) {
    if (!container) {
        return;
    }

    const previousAllSectionsToggle = container.querySelector("[data-showcase-all-sections]");
    const previousSelections = new Set(
        Array.from(container.querySelectorAll("[data-showcase-section]:checked"))
            .map((input) => input.value)
    );

    const label = t(config.labelKey, {}, config.labelFallback);
    const allSectionsLabel = t(
        "configurator.showcase.allSectionsToggle",
        {},
        "All sections appear?"
    );
    const allSectionsChecked = previousAllSectionsToggle instanceof HTMLInputElement
        ? previousAllSectionsToggle.checked
        : true;
    const itemsMarkup = items.map((item) => {
        const checked = previousSelections.has(item.id) || (!previousSelections.size && item.defaultChecked);
        const inputId = "showcase-" + item.type + "-" + item.id;
        const itemLabel = t(item.labelKey, {}, item.fallback);

        return `
            <label class="checkbox-wrapper checkbox-md">
                <span class="relative inline-flex items-center justify-center">
                    <input
                        type="checkbox"
                        id="${escapeHtml(inputId)}"
                        value="${escapeHtml(item.id)}"
                        data-showcase-section="true"
                        ${checked ? "checked" : ""}
                        class="peer"
                    >
                    <i class="ri-check-line absolute inset-0 flex items-center justify-center leading-none text-white text-icon-md opacity-0 peer-checked:opacity-100 pointer-events-none" aria-hidden="true"></i>
                </span>
                <span class="text-body-sm text-black">${escapeHtml(itemLabel)}</span>
            </label>
        `;
    }).join("");

    container.innerHTML = `
        <div class="flex flex-col gap-16" role="group" aria-label="${escapeHtml(label)}">
            <div>
                <label class="toggle toggle-md self-start">
                    <input
                        type="checkbox"
                        data-showcase-all-sections="true"
                        ${allSectionsChecked ? "checked" : ""}
                        class="toggle-input"
                        role="switch"
                    >
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label">${escapeHtml(allSectionsLabel)}</span>
                </label>
            </div>
            <div data-showcase-sections-options="true" class="pt-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-24 gap-y-16 items-start">
                    ${itemsMarkup}
                </div>
            </div>
        </div>
    `;

    syncShowcaseSectionsVisibility(container);
}

function syncShowcaseSectionsVisibility(container) {
    if (!container) {
        return;
    }

    const allSectionsToggle = container.querySelector("[data-showcase-all-sections]");
    const optionsGrid = container.querySelector("[data-showcase-sections-options]");

    if (!(allSectionsToggle instanceof HTMLInputElement) || !(optionsGrid instanceof HTMLElement)) {
        return;
    }

    const showOptions = !allSectionsToggle.checked;
    optionsGrid.classList.toggle("hidden", !showOptions);
    optionsGrid.toggleAttribute("hidden", !showOptions);
    optionsGrid.setAttribute("aria-hidden", String(!showOptions));
}

function syncShowcaseExpandVisibility(container) {
    if (!container) {
        return;
    }

    const allExpandToggle = container.querySelector("[data-showcase-all-expand]");
    const optionsGrid = container.querySelector("[data-showcase-expand-options]");

    if (!(allExpandToggle instanceof HTMLInputElement) || !(optionsGrid instanceof HTMLElement)) {
        return;
    }

    const showOptions = !allExpandToggle.checked;
    optionsGrid.classList.toggle("hidden", !showOptions);
    optionsGrid.toggleAttribute("hidden", !showOptions);
    optionsGrid.setAttribute("aria-hidden", String(!showOptions));
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
    const designVariantInputs = document.querySelectorAll('input[name="datasheet-design-variant"]');
    const designVariantToggle = document.getElementById("datasheet-design-variant-toggle");
    const designVariantClassic = document.getElementById("datasheet-design-variant-classic");
    const designVariantModern = document.getElementById("datasheet-design-variant-modern");
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

    designVariantInputs.forEach((input) => {
        input.addEventListener("change", (event) => {
            if (!(event.target instanceof HTMLInputElement) || !event.target.checked) {
                return;
            }

            syncDatasheetDesignVariantToggle();
            syncGenerateButton();
        });
    });

    if (
        designVariantToggle instanceof HTMLInputElement
        && designVariantClassic instanceof HTMLInputElement
        && designVariantModern instanceof HTMLInputElement
    ) {
        designVariantToggle.addEventListener("change", () => {
            const nextInput = designVariantToggle.checked
                ? designVariantModern
                : designVariantClassic;

            if (!nextInput.checked) {
                nextInput.checked = true;
                nextInput.dispatchEvent(new Event("change", { bubbles: true }));
                return;
            }

            syncDatasheetDesignVariantToggle();
            syncGenerateButton();
        });
    }

    syncDatasheetDesignVariantToggle();

    expandGrid?.addEventListener("change", (event) => {
        if (!(event.target instanceof HTMLInputElement) || event.target.type !== "checkbox") {
            return;
        }

        if (event.target.matches("[data-showcase-all-expand]")) {
            syncShowcaseExpandVisibility(expandGrid);
        }

        syncShowcaseScopeFieldStates();
        scheduleShowcasePreview();
    });

    sectionsGrid?.addEventListener("change", (event) => {
        if (!(event.target instanceof HTMLInputElement) || event.target.type !== "checkbox") {
            return;
        }

        if (event.target.matches("[data-showcase-all-sections]")) {
            syncShowcaseSectionsVisibility(sectionsGrid);
        }

        scheduleShowcasePreview();
    });
}

function syncDatasheetDesignVariantToggle() {
    const toggle = document.getElementById("datasheet-design-variant-toggle");
    const classicInput = document.getElementById("datasheet-design-variant-classic");
    const modernInput = document.getElementById("datasheet-design-variant-modern");
    const classicLabel = document.getElementById("datasheet-design-variant-classic-label");
    const modernLabel = document.getElementById("datasheet-design-variant-modern-label");

    if (
        !(toggle instanceof HTMLInputElement)
        || !(classicInput instanceof HTMLInputElement)
        || !(modernInput instanceof HTMLInputElement)
    ) {
        return;
    }

    const isModern = modernInput.checked;
    toggle.checked = isModern;

    if (classicLabel instanceof HTMLElement) {
        classicLabel.classList.toggle("text-black", !isModern);
        classicLabel.classList.toggle("font-medium", !isModern);
        classicLabel.classList.toggle("text-grey-primary", isModern);
    }

    if (modernLabel instanceof HTMLElement) {
        modernLabel.classList.toggle("text-black", isModern);
        modernLabel.classList.toggle("font-medium", isModern);
        modernLabel.classList.toggle("text-grey-primary", !isModern);
    }
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
            if (id === CUSTOM_ADVANCED_TOGGLE_ID) {
                renderCustomAdvancedCopyEditors();
            }

            if (
                id === CUSTOM_IMAGES_TOGGLE_ID
                || id === CUSTOM_TEXT_TOGGLE_ID
                || id === CUSTOM_SECTIONS_TOGGLE_ID
            ) {
                syncCustomOptionalBlockVisibility();
            }

            scheduleCustomPreview();
        });
        element.dataset.customBound = "true";
    });

    const resetButton = document.getElementById("custom-reset-button");

    if (resetButton && resetButton.dataset.customBound !== "true") {
        resetButton.addEventListener("click", resetCustomOverrides);
        resetButton.dataset.customBound = "true";
    }

    [CUSTOM_EDIT_MODE_BASIC_ID, CUSTOM_EDIT_MODE_ADVANCED_ID].forEach((id) => {
        const element = document.getElementById(id);

        if (!(element instanceof HTMLInputElement) || element.dataset.customBound === "true") {
            return;
        }

        element.addEventListener("change", () => {
            if (!element.checked) {
                return;
            }

            setCustomAdvancedCopyEnabled(id === CUSTOM_EDIT_MODE_ADVANCED_ID);
        });
        element.dataset.customBound = "true";
    });

    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const trigger = document.getElementById(field.uploadTriggerId);
        const fileInput = document.getElementById(field.uploadInputId);

        if (fileInput instanceof HTMLInputElement && fileInput.dataset.customUploadBound !== "true") {
            fileInput.addEventListener("change", async (event) => {
                const input = event.currentTarget instanceof HTMLInputElement ? event.currentTarget : null;
                const file = input?.files?.[0];

                if (!file) {
                    return;
                }

                await uploadCustomAssetOverride(field, file);
            });
            fileInput.dataset.customUploadBound = "true";
        }
    });

    resetCustomAssetUploadStatuses();
    syncCustomEditingModeControls();
    renderCustomFieldOverrideEditors({}, false);
    syncCustomFieldOverrideVisibility();
    syncCustomOptionalBlockVisibility();
    syncCustomAssetFieldSummaries();
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
        generateLabel.textContent = t("configurator.quickActions.generatePdf", {}, "Generate PDF");
    }

    applyBuilderModeState();
    updateShowcaseFamilyHint();
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
    setHidden("custom-editing-mode-panel", !isCustom);
    setHidden("custom-controls", !isCustom);
    setHidden("datasheet-design-variant-panel", isShowcase || isCustom);
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

        if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
            return;
        }

        input.disabled = isDisabled;
        input.setAttribute("aria-disabled", String(isDisabled));

        if (input instanceof HTMLSelectElement) {
            const dropdown = selectDropdowns.get(input.id) || null;
            const trigger = dropdown?.root?.querySelector(".dropdown-trigger");

            dropdown?.syncFromSelect();

            if (trigger instanceof HTMLButtonElement) {
                trigger.style.opacity = isDisabled ? "1" : "";
            }
        }
    });
}

function getCurrentFamilyMetadata() {
    const familyCode = get("select-family");
    return availableConfiguratorFamilies.find((family) => String(family?.codigo || "") === familyCode) || null;
}

function isCurrentFamilyShowcaseAvailable() {
    const family = getCurrentFamilyMetadata();
    return Boolean(family?.showcase_runtime_implemented);
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
            "Select a family first to load showcase availability."
        );
        return;
    }

    if (!isCurrentFamilyShowcaseAvailable()) {
        hint.textContent = t(
            "configurator.runtime.showcaseUnsupportedFamily",
            {},
            "Showcase mode is not implemented yet for the selected family."
        );
        return;
    }

    hint.textContent = t(
        "configurator.runtime.showcaseFamilyHint",
        {},
        "Current selections stay locked unless you expand them. Showcase mode is live for the selected family."
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

function isCustomAdvancedCopyEnabled() {
    const advancedRadio = document.getElementById(CUSTOM_EDIT_MODE_ADVANCED_ID);

    if (advancedRadio instanceof HTMLInputElement) {
        return advancedRadio.checked;
    }

    const toggle = document.getElementById(CUSTOM_ADVANCED_TOGGLE_ID);
    return toggle instanceof HTMLInputElement && toggle.checked;
}

function syncCustomEditingModeControls() {
    const toggle = document.getElementById(CUSTOM_ADVANCED_TOGGLE_ID);
    const basicRadio = document.getElementById(CUSTOM_EDIT_MODE_BASIC_ID);
    const advancedRadio = document.getElementById(CUSTOM_EDIT_MODE_ADVANCED_ID);
    const advancedEnabled = toggle instanceof HTMLInputElement && toggle.checked;

    if (basicRadio instanceof HTMLInputElement) {
        basicRadio.checked = !advancedEnabled;
    }

    if (advancedRadio instanceof HTMLInputElement) {
        advancedRadio.checked = advancedEnabled;
    }
}

function setCustomAdvancedCopyEnabled(enabled, shouldPreview = true) {
    const toggle = document.getElementById(CUSTOM_ADVANCED_TOGGLE_ID);

    if (toggle instanceof HTMLInputElement) {
        toggle.checked = Boolean(enabled);
    }

    syncCustomEditingModeControls();
    renderCustomFieldOverrideEditors();
    renderCustomAdvancedCopyEditors();

    if (shouldPreview) {
        scheduleCustomPreview();
    }
}

function isCustomBlockEnabled(toggleId) {
    const toggle = document.getElementById(toggleId);
    return toggle instanceof HTMLInputElement && toggle.checked;
}

function syncCustomOptionalBlockVisibility() {
    CUSTOM_OPTIONAL_BLOCK_DEFINITIONS.forEach((definition) => {
        setHidden(definition.fieldsId, !isCustomBlockEnabled(definition.toggleId));
    });
}

function getCustomImageBrowserElements() {
    const overlay = document.getElementById("customImageBrowserModal");
    const title = document.getElementById("customImageBrowserTitle");
    const modalHint = document.getElementById("custom-image-browser-modal-hint");
    const search = document.getElementById("custom-image-browser-search");
    const breadcrumb = document.getElementById("custom-image-browser-breadcrumb");
    const grid = document.getElementById("custom-image-browser-grid");
    const empty = document.getElementById("custom-image-browser-empty");
    const emptyLabel = document.getElementById("custom-image-browser-empty-label");
    const activeLabel = document.getElementById("custom-image-browser-active-label");
    const preview = document.getElementById("custom-image-browser-preview");
    const previewEmpty = document.getElementById("custom-image-browser-preview-empty");
    const previewImage = document.getElementById("custom-image-browser-preview-image");
    const manual = document.getElementById("custom-image-browser-manual-id");
    const clearButton = document.getElementById("custom-image-browser-clear");
    const applyButton = document.getElementById("custom-image-browser-apply");
    const status = document.getElementById("custom-image-browser-status");

    if (
        !(overlay instanceof HTMLElement)
        || !(title instanceof HTMLElement)
        || !(modalHint instanceof HTMLElement)
        || !(search instanceof HTMLInputElement)
        || !(breadcrumb instanceof HTMLElement)
        || !(grid instanceof HTMLElement)
        || !(empty instanceof HTMLElement)
        || !(emptyLabel instanceof HTMLElement)
        || !(activeLabel instanceof HTMLElement)
        || !(preview instanceof HTMLElement)
        || !(previewEmpty instanceof HTMLElement)
        || !(previewImage instanceof HTMLImageElement)
        || !(manual instanceof HTMLInputElement)
        || !(clearButton instanceof HTMLButtonElement)
        || !(applyButton instanceof HTMLButtonElement)
        || !(status instanceof HTMLElement)
    ) {
        return null;
    }

    return {
        overlay,
        title,
        modalHint,
        search,
        breadcrumb,
        grid,
        empty,
        emptyLabel,
        activeLabel,
        preview,
        previewEmpty,
        previewImage,
        manual,
        clearButton,
        applyButton,
        status,
    };
}

function getCustomAssetFieldById(fieldId) {
    return CUSTOM_ASSET_OVERRIDE_FIELDS.find((field) => field.id === fieldId) || CUSTOM_ASSET_OVERRIDE_FIELDS[0] || null;
}

function getCustomAssetFieldLabel(field) {
    if (!field) {
        return "";
    }

    return t(field.targetLabelKey, {}, field.targetFallback);
}

function getCustomAssetFieldValue(fieldId) {
    const input = document.getElementById(fieldId);
    return input instanceof HTMLInputElement ? String(input.value || "").trim() : "";
}

function syncCustomAssetFieldSummaries() {
    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const summary = document.getElementById(field.summaryId);

        if (!(summary instanceof HTMLElement)) {
            return;
        }

        const value = getCustomAssetFieldValue(field.id);
        const empty = value === "";
        summary.textContent = empty
            ? t("configurator.custom.browserNoAssetSelected", {}, "No asset selected")
            : "#" + value;
        summary.classList.toggle("text-grey-primary", empty);
        summary.classList.toggle("text-black", !empty);
    });
}

function bindCustomImageBrowserControls() {
    const elements = getCustomImageBrowserElements();

    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const button = document.getElementById(field.browserButtonId);

        if (!(button instanceof HTMLButtonElement) || button.dataset.customBrowserBound === "true") {
            return;
        }

        button.addEventListener("click", () => {
            void openCustomImageBrowserForField(field.id, button);
        });
        button.dataset.customBrowserBound = "true";
    });

    if (!elements) {
        return;
    }

    if (elements.search.dataset.customBrowserBound !== "true") {
        elements.search.addEventListener("input", () => {
            window.clearTimeout(customImageBrowserSearchTimer);
            customImageBrowserState.searchQuery = String(elements.search.value || "").trim();
            customImageBrowserSearchTimer = window.setTimeout(() => {
                if (customImageBrowserState.currentFolderId) {
                    void loadCustomImageBrowserFolder(customImageBrowserState.currentFolderId);
                }
            }, CUSTOM_IMAGE_BROWSER_DEBOUNCE_MS);
        });
        elements.search.dataset.customBrowserBound = "true";
    }

    if (elements.manual.dataset.customBrowserBound !== "true") {
        elements.manual.addEventListener("input", () => {
            const manualValue = String(elements.manual.value || "").trim();

            if (!/^\d+$/.test(manualValue) || String(customImageBrowserState.selectedAsset?.id || "") !== manualValue) {
                customImageBrowserState.selectedAsset = null;
                customImageBrowserState.selectedAssetId = null;
            }

            renderCustomImageBrowserPreview();
        });
        elements.manual.dataset.customBrowserBound = "true";
    }

    if (elements.clearButton.dataset.customBrowserBound !== "true") {
        elements.clearButton.addEventListener("click", () => {
            elements.manual.value = "";
            customImageBrowserState.selectedAsset = null;
            customImageBrowserState.selectedAssetId = null;
            renderCustomImageBrowserPreview();
            setCustomImageBrowserStatus(
                "configurator.custom.browserStatusIdle",
                {},
                "Browse the custom DAM folders or type an asset ID."
            );
        });
        elements.clearButton.dataset.customBrowserBound = "true";
    }

    if (elements.applyButton.dataset.customBrowserBound !== "true") {
        elements.applyButton.addEventListener("click", () => {
            applyCustomImageBrowserSelection();
        });
        elements.applyButton.dataset.customBrowserBound = "true";
    }

    if (elements.overlay.dataset.customBrowserBound !== "true") {
        elements.overlay.addEventListener("click", (event) => {
            if (event.target === elements.overlay) {
                setCustomImageBrowserModalOpen(false);
            }
        });

        const closeButtons = Array.from(elements.overlay.querySelectorAll("[data-close-custom-image-browser]"));
        closeButtons.forEach((button) => {
            button.addEventListener("click", () => {
                setCustomImageBrowserModalOpen(false);
            });
        });

        document.addEventListener("keydown", (event) => {
            if (!isCustomImageBrowserModalOpen()) {
                return;
            }

            if (event.key === "Escape") {
                event.preventDefault();
                setCustomImageBrowserModalOpen(false);
            }
        });

        elements.overlay.dataset.customBrowserBound = "true";
    }

    syncCustomAssetFieldSummaries();
}

function isCustomImageBrowserModalOpen() {
    const overlay = document.getElementById("customImageBrowserModal");
    return overlay instanceof HTMLElement && overlay.classList.contains("is-open");
}

function setCustomImageBrowserModalOpen(isOpen) {
    const elements = getCustomImageBrowserElements();

    if (!elements) {
        return;
    }

    elements.overlay.classList.toggle("is-open", isOpen);
    elements.overlay.setAttribute("aria-hidden", String(!isOpen));
    elements.overlay.inert = !isOpen;
    document.body.classList.toggle(
        "modal-open",
        Array.from(document.querySelectorAll(".modal-overlay")).some((item) => item.classList.contains("is-open"))
    );

    if (isOpen) {
        window.requestAnimationFrame(() => {
            elements.search.focus({ preventScroll: true });
        });
        return;
    }

    if (customImageBrowserLastFocus instanceof HTMLElement && typeof customImageBrowserLastFocus.focus === "function") {
        customImageBrowserLastFocus.focus({ preventScroll: true });
    }
}

async function openCustomImageBrowserForField(fieldId, trigger = null) {
    const field = getCustomAssetFieldById(fieldId);
    const elements = getCustomImageBrowserElements();

    if (!field || !elements) {
        return;
    }

    if (trigger instanceof HTMLElement && !elements.overlay.contains(trigger)) {
        customImageBrowserLastFocus = trigger;
    }
    customImageBrowserState.activeFieldId = field.id;
    customImageBrowserState.searchQuery = "";
    customImageBrowserState.searchScope = "folder";
    customImageBrowserState.selectedAsset = null;
    customImageBrowserState.selectedAssetId = null;
    elements.search.value = "";
    elements.manual.value = getCustomAssetFieldValue(field.id);

    syncCustomImageBrowserHeader(field);
    renderCustomImageBrowserBreadcrumb();
    renderCustomImageBrowserPreview();
    setCustomImageBrowserStatus(
        "configurator.custom.browserLoadingFolder",
        { target: getCustomAssetFieldLabel(field) },
        "Loading DAM folder for " + getCustomAssetFieldLabel(field) + "..."
    );
    setCustomImageBrowserModalOpen(true);

    await loadCustomImageBrowserFolder(field.uploadFolderId);
    await hydrateCustomImageBrowserAsset(elements.manual.value);
}

function syncCustomImageBrowserHeader(field) {
    const elements = getCustomImageBrowserElements();

    if (!elements || !field) {
        return;
    }

    const fieldLabel = getCustomAssetFieldLabel(field);
    elements.title.textContent = fieldLabel;
    elements.modalHint.textContent = t(
        "configurator.custom.browserModalHintSingle",
        { target: fieldLabel },
        "Browse isolated DAM folders, search assets, or type an asset ID manually for " + fieldLabel + "."
    );
}

async function loadCustomImageBrowserFolder(folderId) {
    const requestToken = ++customImageBrowserFolderRequestToken;
    const normalizedFolderId = String(folderId || "").trim();
    const isGlobalSearch = customImageBrowserState.searchQuery !== "";

    if (normalizedFolderId === "") {
        return;
    }

    customImageBrowserState.currentFolderId = normalizedFolderId;
    renderCustomImageBrowserBreadcrumb();
    renderCustomImageBrowserGrid();

    try {
        const payload = await apiFetch(
            "/?endpoint=dam&action=list&folder_id=" + encodeURIComponent(normalizedFolderId)
            + (customImageBrowserState.searchQuery ? "&q=" + encodeURIComponent(customImageBrowserState.searchQuery) : "")
            + (isGlobalSearch ? "&global=1" : "")
        );

        if (requestToken !== customImageBrowserFolderRequestToken) {
            return;
        }

        customImageBrowserState.currentFolder = payload?.data?.folder || null;
        customImageBrowserState.folders = Array.isArray(payload?.data?.folders) ? payload.data.folders : [];
        customImageBrowserState.assets = Array.isArray(payload?.data?.assets) ? payload.data.assets : [];
        customImageBrowserState.searchScope = String(payload?.data?.search_scope || (isGlobalSearch ? "global" : "folder"));
        renderCustomImageBrowserBreadcrumb();
        renderCustomImageBrowserGrid();
        setCustomImageBrowserStatus(
            customImageBrowserState.searchScope === "global"
                ? "configurator.custom.browserStatusGlobalResults"
                : "configurator.custom.browserStatusIdle",
            { query: customImageBrowserState.searchQuery },
            customImageBrowserState.searchScope === "global"
                ? "Showing DAM search results for \"" + customImageBrowserState.searchQuery + "\"."
                : "Browse the custom DAM folders or type an asset ID."
        );
    } catch (error) {
        if (requestToken !== customImageBrowserFolderRequestToken) {
            return;
        }

        customImageBrowserState.currentFolder = {
            id: normalizedFolderId,
            name: normalizedFolderId.split("/").pop() || normalizedFolderId,
            path: normalizedFolderId,
        };
        customImageBrowserState.folders = [];
        customImageBrowserState.assets = [];
        customImageBrowserState.searchScope = isGlobalSearch ? "global" : "folder";
        renderCustomImageBrowserBreadcrumb();
        renderCustomImageBrowserGrid();
        setCustomImageBrowserStatus(
            "configurator.custom.browserFolderLoadFailed",
            { message: error?.message || "" },
            "Unable to load DAM folder right now."
        );
    }
}

function renderCustomImageBrowserBreadcrumb() {
    const elements = getCustomImageBrowserElements();

    if (!elements) {
        return;
    }

    const currentPath = String(
        customImageBrowserState.currentFolder?.path
        || customImageBrowserState.currentFolderId
        || getCustomAssetFieldById(customImageBrowserState.activeFieldId)?.uploadFolderId
        || ""
    );

    elements.breadcrumb.innerHTML = "";

    if (customImageBrowserState.searchScope === "global" && customImageBrowserState.searchQuery !== "") {
        const item = document.createElement("li");
        item.className = "breadcrumb-item";

        const current = document.createElement("span");
        current.className = "breadcrumb-current";
        current.textContent = t(
            "configurator.custom.browserSearchAllBreadcrumb",
            { query: customImageBrowserState.searchQuery },
            "All DAM search: " + customImageBrowserState.searchQuery
        );

        item.appendChild(current);
        elements.breadcrumb.appendChild(item);
        return;
    }

    if (currentPath === "") {
        return;
    }

    const parts = currentPath.split("/").filter(Boolean);
    let folderPath = "";

    parts.forEach((part, index) => {
        folderPath = folderPath ? folderPath + "/" + part : part;

        const item = document.createElement("li");
        item.className = "breadcrumb-item";

        if (index === parts.length - 1) {
            const current = document.createElement("span");
            current.className = "breadcrumb-current";
            current.textContent = part;
            item.appendChild(current);
        } else {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "breadcrumb-link";
            button.textContent = part;
            button.addEventListener("click", () => {
                void loadCustomImageBrowserFolder(folderPath);
            });
            item.appendChild(button);
        }

        elements.breadcrumb.appendChild(item);
    });
}

function renderCustomImageBrowserGrid() {
    const elements = getCustomImageBrowserElements();

    if (!elements) {
        return;
    }

    elements.grid.innerHTML = "";
    const itemsCount = customImageBrowserState.folders.length + customImageBrowserState.assets.length;

    elements.emptyLabel.textContent = customImageBrowserState.searchScope === "global"
        ? t("configurator.custom.browserEmptyGlobal", {}, "No DAM folders or assets match this search.")
        : t("configurator.custom.browserEmpty", {}, "No folders or assets found here.");
    elements.empty.classList.toggle("hidden", itemsCount !== 0);

    if (itemsCount === 0) {
        return;
    }

    const fragment = document.createDocumentFragment();

    customImageBrowserState.folders.forEach((folder) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "group relative flex w-full flex-col items-center gap-12 rounded-8 p-16";
        button.title = folder.path || folder.name || "";
        button.setAttribute("aria-label", "Open folder: " + (folder.name || folder.path || ""));
        button.addEventListener("click", () => {
            if (customImageBrowserState.searchScope === "global" && customImageBrowserState.searchQuery !== "") {
                const elementsRef = getCustomImageBrowserElements();

                customImageBrowserState.searchQuery = "";
                customImageBrowserState.searchScope = "folder";

                if (elementsRef) {
                    elementsRef.search.value = "";
                }
            }

            void loadCustomImageBrowserFolder(folder.id);
        });

        const icon = document.createElement("i");
        icon.className = "ri-folder-3-line text-icon-xxl text-grey-primary";
        icon.setAttribute("aria-hidden", "true");

        const name = document.createElement("span");
        name.className = "text-body-sm text-center leading-tight text-grey-primary break-all";
        name.textContent = folder.name || folder.path || "";

        if (customImageBrowserState.searchScope === "global" && folder.path) {
            const path = document.createElement("span");
            path.className = "text-body-xs text-center leading-tight text-grey-primary break-all";
            path.textContent = folder.path;
            button.append(icon, name, path);
            fragment.appendChild(button);
            return;
        }

        button.append(icon, name);
        fragment.appendChild(button);
    });

    customImageBrowserState.assets.forEach((asset) => {
        const wrapper = document.createElement("button");
        wrapper.type = "button";
        wrapper.className = "group relative flex w-full flex-col items-center gap-12 rounded-8 p-16";
        wrapper.title = asset.display_name || asset.filename || "";
        wrapper.setAttribute("aria-pressed", String(String(customImageBrowserState.selectedAssetId || "") === String(asset.id || "")));
        wrapper.addEventListener("click", () => {
            const elementsRef = getCustomImageBrowserElements();

            customImageBrowserState.selectedAsset = asset;
            customImageBrowserState.selectedAssetId = asset.id || null;

            if (elementsRef) {
                elementsRef.manual.value = String(asset.id || "");
            }

            renderCustomImageBrowserGrid();
            renderCustomImageBrowserPreview();
            setCustomImageBrowserStatus(
                "configurator.custom.browserAssetSelected",
                { id: String(asset.id || "") },
                "Selected asset #" + String(asset.id || "") + "."
            );
        });

        const preview = document.createElement("div");
        preview.className = "relative flex w-full items-center justify-center overflow-hidden rounded-lg border border-grey-secondary bg-grey-tertiary";
        preview.style.aspectRatio = "1 / 1";
        preview.style.minHeight = "120px";

        const imageUrl = resolveCustomImageBrowserAssetImageUrl(asset);

        if (imageUrl) {
            const image = document.createElement("img");
            image.src = imageUrl;
            image.alt = asset.display_name || asset.filename || "Asset";
            image.className = "block h-full w-full object-cover object-center";
            image.loading = "lazy";
            preview.appendChild(image);
        } else {
            const fallback = document.createElement("i");
            fallback.className = "ri-image-line text-icon-xxl text-grey-primary";
            fallback.setAttribute("aria-hidden", "true");
            preview.appendChild(fallback);
        }

        const name = document.createElement("span");
        name.className = "text-body-sm text-center leading-tight text-grey-primary break-all";
        name.textContent = asset.display_name || asset.filename || ("#" + String(asset.id || ""));

        const folderPath = String(asset.folder_path || asset.asset_folder || "").trim();
        const path = document.createElement("span");
        path.className = "text-body-xs text-center leading-tight text-grey-primary break-all";
        path.textContent = folderPath;

        if (String(customImageBrowserState.selectedAssetId || "") === String(asset.id || "")) {
            wrapper.classList.add("ring-2", "ring-green-primary");
        }

        wrapper.append(preview, name);

        if (customImageBrowserState.searchScope === "global" && folderPath !== "") {
            wrapper.append(path);
        }

        fragment.appendChild(wrapper);
    });

    elements.grid.appendChild(fragment);
}

function resolveCustomImageBrowserAssetImageUrl(asset) {
    const thumbnail = typeof asset?.thumbnail_url === "string" ? asset.thumbnail_url.trim() : "";
    const secure = typeof asset?.secure_url === "string" ? asset.secure_url.trim() : "";
    return thumbnail || secure || "";
}

function renderCustomImageBrowserPreview() {
    const elements = getCustomImageBrowserElements();
    const activeField = getCustomAssetFieldById(customImageBrowserState.activeFieldId);

    if (!elements || !activeField) {
        return;
    }

    elements.activeLabel.textContent = getCustomAssetFieldLabel(activeField);

    const imageUrl = resolveCustomImageBrowserAssetImageUrl(customImageBrowserState.selectedAsset);
    const showImage = imageUrl !== "";

    elements.previewEmpty.classList.toggle("hidden", showImage);
    elements.previewImage.classList.toggle("hidden", !showImage);

    if (showImage) {
        elements.previewImage.src = imageUrl;
        elements.previewImage.alt = customImageBrowserState.selectedAsset?.display_name || customImageBrowserState.selectedAsset?.filename || getCustomAssetFieldLabel(activeField);
    } else {
        elements.previewImage.removeAttribute("src");
        elements.previewImage.alt = "";
    }
}

async function hydrateCustomImageBrowserAsset(assetId) {
    const numericId = String(assetId || "").trim();

    if (!/^\d+$/.test(numericId)) {
        customImageBrowserState.selectedAsset = null;
        customImageBrowserState.selectedAssetId = null;
        renderCustomImageBrowserPreview();
        return;
    }

    const requestToken = ++customImageBrowserAssetRequestToken;

    try {
        const payload = await apiFetch("/?endpoint=dam&action=asset&id=" + encodeURIComponent(numericId));

        if (requestToken !== customImageBrowserAssetRequestToken || !isCustomImageBrowserModalOpen()) {
            return;
        }

        customImageBrowserState.selectedAsset = payload?.data?.asset || null;
        customImageBrowserState.selectedAssetId = customImageBrowserState.selectedAsset?.id || null;
        renderCustomImageBrowserGrid();
        renderCustomImageBrowserPreview();
    } catch (error) {
        if (requestToken !== customImageBrowserAssetRequestToken) {
            return;
        }

        customImageBrowserState.selectedAsset = null;
        customImageBrowserState.selectedAssetId = null;
        renderCustomImageBrowserPreview();
    }
}

function applyCustomImageBrowserSelection() {
    const elements = getCustomImageBrowserElements();
    const activeField = getCustomAssetFieldById(customImageBrowserState.activeFieldId);

    if (!elements || !activeField) {
        return;
    }

    const input = document.getElementById(activeField.id);

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.value = String(elements.manual.value || "").trim();
    syncCustomAssetFieldSummaries();
    scheduleCustomPreview();
    setStatusKey(
        "configurator.custom.browserApplyDone",
        "success",
        { target: getCustomAssetFieldLabel(activeField) },
        getCustomAssetFieldLabel(activeField) + " updated for this custom PDF."
    );
    setCustomImageBrowserModalOpen(false);
}

function setCustomImageBrowserStatus(key, variables = {}, fallback = "") {
    const elements = getCustomImageBrowserElements();

    if (!elements) {
        return;
    }

    elements.status.textContent = t(key, variables, fallback);
}

function getCustomAdvancedCopyFieldId(section, field) {
    return "custom-advanced-" + section + "-" + field;
}

function clearCustomAdvancedCopySnapshot() {
    customEditableCopySnapshot = {};
    customEditableCopyReference = "";
    renderCustomAdvancedCopyEditors({}, false);
}

function setCustomAdvancedCopySnapshot(snapshot, reference = "") {
    const nextSnapshot = snapshot && typeof snapshot === "object" ? snapshot : {};
    const preserveCurrentValues = customEditableCopyReference === String(reference || "");

    customEditableCopySnapshot = nextSnapshot;
    customEditableCopyReference = String(reference || "");
    renderCustomAdvancedCopyEditors(nextSnapshot, preserveCurrentValues);
}

function getCustomAdvancedCopyDefinition(section) {
    return CUSTOM_ADVANCED_COPY_SECTION_DEFINITIONS.find((definition) => definition.section === section) || null;
}

function syncCustomAdvancedCopyVisibility() {
    const panel = document.getElementById("custom-advanced-copy-panel");
    const message = document.getElementById("custom-advanced-copy-message");
    const enabled = isCustomAdvancedCopyEnabled();

    if (panel) {
        setHidden(panel.id, !enabled);
    }

    if (!message) {
        return;
    }

    if (!enabled) {
        message.textContent = t(
            "configurator.custom.advancedEditorsWaiting",
            {},
            "Enable advanced copy and wait for the custom preview to load editable section text."
        );
        return;
    }

    const sectionCount = Object.keys(customEditableCopySnapshot || {}).length;

    if (customPreviewState.pending) {
        message.textContent = t(
            "configurator.custom.advancedEditorsLoading",
            {},
            "Loading editable section copy..."
        );
        return;
    }

    if (!customPreviewState.ok) {
        message.textContent = t(
            "configurator.custom.advancedEditorsNeedPreview",
            {},
            "Wait for the custom preview to validate the base product before editing section copy."
        );
        return;
    }

    if (sectionCount === 0) {
        message.textContent = t(
            "configurator.custom.advancedEditorsEmpty",
            {},
            "No editable section copy is available for this product yet."
        );
        return;
    }

    message.textContent = t(
        "configurator.custom.advancedEditorsReady",
        { count: sectionCount },
        "Editable section copy loaded."
    );
}

function renderCustomAdvancedCopyEditors(snapshot = customEditableCopySnapshot, preserveCurrentValues = true) {
    const panel = document.getElementById("custom-advanced-copy-panel");
    const container = document.getElementById("custom-advanced-copy-editors");

    if (!panel || !container) {
        return;
    }

    const nextSnapshot = snapshot && typeof snapshot === "object" ? snapshot : {};
    const currentValues = {};

    if (preserveCurrentValues) {
        Array.from(container.querySelectorAll("[data-custom-advanced-field]")).forEach((element) => {
            if (element instanceof HTMLTextAreaElement) {
                currentValues[element.id] = {
                    value: element.value,
                    defaultValue: String(element.dataset.defaultValue || ""),
                };
            }
        });
    }

    container.innerHTML = "";

    CUSTOM_ADVANCED_COPY_SECTION_DEFINITIONS.forEach((definition) => {
        const sectionData = nextSnapshot[definition.section];

        if (!sectionData || typeof sectionData !== "object" || !(definition.field in sectionData)) {
            return;
        }

        const fieldId = getCustomAdvancedCopyFieldId(definition.section, definition.field);
        const defaultValue = String(sectionData[definition.field] || "");
        const wrapper = document.createElement("div");
        const label = document.createElement("label");
        const textarea = document.createElement("textarea");
        const textareaShell = document.createElement("div");
        const textareaShellIcon = document.createElement("span");
        const helper = document.createElement("p");

        wrapper.className = "flex flex-col gap-12";
        label.className = "input-label ml-12";
        label.htmlFor = fieldId;
        label.textContent = t(definition.labelKey, {}, definition.fallback);

        textarea.id = fieldId;
        textarea.className = "input input-md min-h-[132px] resize-y";
        textarea.dataset.customAdvancedField = "true";
        textarea.dataset.section = definition.section;
        textarea.dataset.field = definition.field;
        textarea.dataset.defaultValue = defaultValue;
        textarea.maxLength = Number(definition.maxLength || 800);
        if (Object.prototype.hasOwnProperty.call(currentValues, fieldId)) {
            const currentState = currentValues[fieldId];
            const currentValue = String(currentState?.value || "");
            const oldDefaultValue = String(currentState?.defaultValue || "");

            textarea.value = currentValue.trim() !== oldDefaultValue.trim()
                ? currentValue
                : defaultValue;
        } else {
            textarea.value = defaultValue;
        }

        textareaShell.className = "text-field-shell text-field-shell-meta";
        textareaShellIcon.className = "text-field-shell-meta-icon";
        textareaShellIcon.setAttribute("aria-hidden", "true");
        textareaShellIcon.innerHTML = '<i class="ri-expand-diagonal-s-2-fill text-icon-sm"></i>';

        helper.className = "text-body-xs text-grey-primary ml-12";
        helper.textContent = t(
            "configurator.custom.advancedFieldHint",
            {},
            "Leave the default text unchanged to keep the official copy for this section."
        );

        textarea.addEventListener("input", () => {
            scheduleCustomPreview();
        });

        textareaShell.append(textarea, textareaShellIcon);
        wrapper.append(label, textareaShell, helper);
        container.appendChild(wrapper);
    });

    syncCustomAdvancedCopyVisibility();
}

function collectCustomAdvancedCopyOverrides() {
    if (!isCustomAdvancedCopyEnabled()) {
        return {};
    }

    const overrides = {};

    Array.from(document.querySelectorAll("[data-custom-advanced-field]")).forEach((element) => {
        if (!(element instanceof HTMLTextAreaElement)) {
            return;
        }

        const section = String(element.dataset.section || "").trim();
        const field = String(element.dataset.field || "").trim();
        const defaultValue = String(element.dataset.defaultValue || "").trim();
        const value = String(element.value || "").trim();

        if (!section || !field || value === "" || value === defaultValue) {
            return;
        }

        if (!overrides[section]) {
            overrides[section] = {};
        }

        overrides[section][field] = value;
    });

    return overrides;
}

function getCustomFieldOverrideDefinition(key) {
    return CUSTOM_FIELD_OVERRIDE_DEFINITIONS.find((definition) => definition.key === key) || null;
}

function getCustomFieldOverrideToggleId(key) {
    return "custom-field-override-toggle-" + key;
}

function getCustomFieldOverrideInputId(key) {
    return "custom-field-override-input-" + key;
}

function buildLocalCustomFieldOverrideSnapshot() {
    return {
        display_reference: sanitizeTecitCode(document.getElementById("output-reference")?.value || ""),
        display_description: String(document.getElementById("output-description")?.value || "").trim(),
        display_size: getDisplayText("select-size") || get("select-size"),
        display_color: getDisplayText("select-color") || get("select-color"),
        display_cri: getDisplayText("select-cri") || get("select-cri"),
        display_series: getDisplayText("select-series") || get("select-series"),
        display_lens_name: getDisplayText("select-lens") || getSelectedOptionHint("select-lens"),
        display_finish_name: getDisplayText("select-finish") || getSelectedOptionHint("select-finish"),
        display_cap: getDisplayText("select-cap") || get("select-cap"),
        display_option_code: getDisplayText("select-option") || get("select-option"),
        display_connector_cable: getDisplayText("select-connector-cable") || getRequestSelectValue("select-connector-cable"),
        display_cable_type: getDisplayText("select-cable-type") || getRequestSelectValue("select-cable-type"),
        display_extra_length: String(get("input-extra-length") || "0").trim(),
        display_end_cap: getDisplayText("select-end-cap") || getRequestSelectValue("select-end-cap"),
        display_gasket: getDisplayText("select-gasket") || getRequestSelectValue("select-gasket"),
        display_ip: getDisplayText("select-ip") || getRequestSelectValue("select-ip"),
        display_fixing: getDisplayText("select-fixing") || getRequestSelectValue("select-fixing"),
        display_power_supply: getDisplayText("select-power-supply") || getRequestSelectValue("select-power-supply"),
        display_connection_cable: getDisplayText("select-connection-cable") || getRequestSelectValue("select-connection-cable"),
        display_connection_connector: getDisplayText("select-connection-connector") || getRequestSelectValue("select-connection-connector"),
        display_connection_cable_length: String(get("input-connection-cable-length") || "0").trim(),
        display_purpose: getDisplayText("select-purpose") || getRequestSelectValue("select-purpose"),
        display_company: getDisplayText("select-company") || getRequestSelectValue("select-company"),
        display_language: getDisplayText("select-language") || getRequestSelectValue("select-language"),
    };
}

function clearCustomFieldOverrideSnapshot() {
    customFieldOverrideSnapshot = {};
    customFieldOverrideReference = "";
    renderCustomFieldOverrideEditors({}, false);
}

function setCustomFieldOverrideSnapshot(snapshot, reference = "") {
    const nextSnapshot = snapshot && typeof snapshot === "object" ? snapshot : {};
    const preserveCurrentValues = customFieldOverrideReference === String(reference || "");

    customFieldOverrideSnapshot = nextSnapshot;
    customFieldOverrideReference = String(reference || "");
    renderCustomFieldOverrideEditors(nextSnapshot, preserveCurrentValues);
}

function syncCustomFieldOverrideVisibility() {
    const panel = document.getElementById("custom-field-overrides-panel");
    const message = document.getElementById("custom-field-overrides-message");
    const enabled = isCustomAdvancedCopyEnabled();

    if (panel) {
        setHidden(panel.id, !enabled);
    }

    if (!message) {
        return;
    }

    if (!enabled) {
        message.textContent = t(
            "configurator.custom.fieldOverridesWaiting",
            {},
            "Enable advanced editing to turn any visible field into a custom text override."
        );
        return;
    }

    if (customPreviewState.pending) {
        message.textContent = t(
            "configurator.custom.fieldOverridesLoading",
            {},
            "Loading field values from the base datasheet..."
        );
        return;
    }

    if (!customPreviewState.ok) {
        message.textContent = t(
            "configurator.custom.fieldOverridesFallback",
            {},
            "Toggle any field to override it. Exact technical defaults fill in after the custom preview finishes."
        );
        return;
    }

    message.textContent = t(
        "configurator.custom.fieldOverridesReady",
        {},
        "Toggle any field to replace the displayed PDF value without changing the real product configuration."
    );
}

function captureCurrentCustomFieldOverrideStates() {
    const states = {};

    Array.from(document.querySelectorAll("[data-custom-field-override-input]")).forEach((element) => {
        if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
            return;
        }

        const key = String(element.dataset.customFieldKey || "").trim();

        if (!key) {
            return;
        }

        const toggle = document.getElementById(getCustomFieldOverrideToggleId(key));

        states[key] = {
            checked: toggle instanceof HTMLInputElement ? toggle.checked : false,
            value: element.value,
            defaultValue: String(element.dataset.defaultValue || ""),
        };
    });

    return states;
}

function buildEffectiveCustomFieldOverrideSnapshot(snapshot = customFieldOverrideSnapshot) {
    return {
        ...(snapshot && typeof snapshot === "object" ? snapshot : {}),
        ...buildLocalCustomFieldOverrideSnapshot(),
    };
}

function buildCustomFieldOverrideControl(field, defaultValue, previousState = null, variant = "panel") {
    const toggleId = getCustomFieldOverrideToggleId(field.key);
    const inputId = getCustomFieldOverrideInputId(field.key);
    const checked = previousState ? Boolean(previousState.checked) : false;
    const inputValue = previousState && checked ? previousState.value : defaultValue;
    const compact = variant === "inline";

    const wrapper = document.createElement("div");
    const row = document.createElement("div");
    const textWrap = document.createElement("div");
    const label = document.createElement("span");
    const baseValue = document.createElement("p");
    const toggleLabel = document.createElement("label");
    const toggleInput = document.createElement("input");
    const toggleText = document.createElement("span");
    const inputWrap = document.createElement("div");
    const inputLabel = document.createElement("label");
    const input = field.multiline ? document.createElement("textarea") : document.createElement("input");

    wrapper.className = compact
        ? "mt-10 rounded-16 border border-dashed border-grey-secondary bg-grey-50/40 px-12 py-12"
        : "flex flex-col gap-12";
    wrapper.dataset.customFieldOverrideInline = compact ? "true" : "false";
    wrapper.dataset.customFieldOverrideWrapper = field.key;
    row.className = "flex items-start justify-between gap-16";
    textWrap.className = "flex flex-col gap-4 min-w-0";
    label.className = "text-label text-black";
    label.textContent = t(field.labelKey, {}, field.fallback);
    baseValue.className = "text-body-xs text-grey-primary break-words";
    baseValue.textContent = t(
        "configurator.custom.fieldBaseValue",
        { value: defaultValue !== "" ? defaultValue : "—" },
        "Base value: " + (defaultValue !== "" ? defaultValue : "—")
    );

    toggleLabel.className = "flex items-center gap-8 shrink-0 text-body-xs text-grey-primary";
    toggleInput.type = "checkbox";
    toggleInput.id = toggleId;
    toggleInput.className = "h-16 w-16 shrink-0";
    toggleInput.checked = checked;
    toggleText.textContent = t("configurator.custom.fieldOverrideToggle", {}, "Override");

    textWrap.append(label, baseValue);
    toggleLabel.append(toggleInput, toggleText);
    row.append(textWrap, toggleLabel);

    inputWrap.className = compact ? "pt-10" : "pt-8";
    inputWrap.dataset.customFieldOverrideInputWrap = field.key;

    inputLabel.className = "input-label ml-12";
    inputLabel.htmlFor = inputId;
    inputLabel.textContent = t("configurator.custom.fieldCustomValue", {}, "Custom value");

    input.id = inputId;
    input.className = field.multiline
        ? "input input-sm min-h-[132px] resize-y"
        : "input input-sm";
    input.dataset.customFieldOverrideInput = "true";
    input.dataset.customFieldKey = field.key;
    input.dataset.defaultValue = defaultValue;
    input.maxLength = Number(field.maxLength || 255);
    input.value = inputValue;

    if (input instanceof HTMLInputElement) {
        input.type = "text";
    }

    const syncInputState = () => {
        inputWrap.classList.toggle("hidden", !toggleInput.checked);
        input.disabled = !toggleInput.checked;
    };

    toggleInput.addEventListener("change", () => {
        if (toggleInput.checked && String(input.value || "").trim() === "") {
            input.value = defaultValue;
        }

        syncInputState();
        scheduleCustomPreview();
    });

    input.addEventListener("input", () => {
        scheduleCustomPreview();
    });

    inputWrap.append(inputLabel, input);
    wrapper.append(row, inputWrap);
    syncInputState();

    return wrapper;
}

function renderCustomInlineFieldOverrideEditors(snapshot = customFieldOverrideSnapshot, preserveCurrentValues = true) {
    const currentValues = preserveCurrentValues ? captureCurrentCustomFieldOverrideStates() : {};
    const effectiveSnapshot = buildEffectiveCustomFieldOverrideSnapshot(snapshot);
    const enabled = isCustomMode() && isCustomAdvancedCopyEnabled();
    const inlineTargets = new Set();
    const renderedKeys = new Set();
    const renderedContainers = new Set();
    const inlineFields = CUSTOM_FIELD_OVERRIDE_DEFINITIONS.filter((field) => Boolean(field.containerId));

    inlineFields.forEach((field) => {
        inlineTargets.add(field.containerId);
    });

    inlineTargets.forEach((containerId) => {
        const target = document.getElementById(containerId);

        if (!target) {
            return;
        }

        Array.from(target.querySelectorAll('[data-custom-field-override-inline="true"]')).forEach((node) => node.remove());
    });

    inlineFields.forEach((field) => {
        const containerId = String(field.containerId || "").trim();

        if (containerId === "" || renderedKeys.has(field.key) || renderedContainers.has(containerId)) {
            return;
        }

        const target = document.getElementById(containerId);

        if (!target) {
            return;
        }

        const defaultValue = String(effectiveSnapshot[field.key] || "");
        const control = buildCustomFieldOverrideControl(field, defaultValue, currentValues[field.key] || null, "inline");
        control.classList.toggle("hidden", !enabled);
        target.appendChild(control);
        renderedKeys.add(field.key);
        renderedContainers.add(containerId);
    });
}

function renderCustomFieldOverrideEditors(snapshot = customFieldOverrideSnapshot, preserveCurrentValues = true) {
    const panel = document.getElementById("custom-field-overrides-panel");
    const container = document.getElementById("custom-field-overrides-editors");

    if (!panel || !container) {
        return;
    }

    const currentValues = preserveCurrentValues ? captureCurrentCustomFieldOverrideStates() : {};
    const effectiveSnapshot = buildEffectiveCustomFieldOverrideSnapshot(snapshot);
    const inlineKeys = new Set(
        CUSTOM_FIELD_OVERRIDE_DEFINITIONS
            .filter((field) => Boolean(field.containerId))
            .map((field) => field.key)
    );
    const renderedPanelKeys = new Set();

    renderCustomInlineFieldOverrideEditors(snapshot, preserveCurrentValues);

    container.innerHTML = "";

    CUSTOM_FIELD_OVERRIDE_GROUPS.forEach((group) => {
        const groupFields = group.fields.filter((field) => {
            if (field.containerId || inlineKeys.has(field.key) || renderedPanelKeys.has(field.key)) {
                return false;
            }

            renderedPanelKeys.add(field.key);
            return true;
        });

        if (groupFields.length === 0) {
            return;
        }

        const groupCard = document.createElement("div");
        const groupHeader = document.createElement("div");
        const groupTitle = document.createElement("span");
        const groupBody = document.createElement("div");

        groupCard.className = "flex flex-col gap-16";
        groupHeader.className = "flex flex-col gap-4";
        groupTitle.className = "text-label text-grey-primary ml-12";
        groupTitle.textContent = t(group.titleKey, {}, group.fallback);
        groupHeader.appendChild(groupTitle);

        groupBody.className = "grid grid-cols-1 gap-16 pt-4";

        groupFields.forEach((field) => {
            const defaultValue = String(effectiveSnapshot[field.key] || "");
            const wrapper = buildCustomFieldOverrideControl(field, defaultValue, currentValues[field.key] || null, "panel");
            groupBody.appendChild(wrapper);
        });

        groupCard.append(groupHeader, groupBody);
        container.appendChild(groupCard);
    });

    syncCustomFieldOverrideVisibility();
}

function collectCustomFieldOverrides() {
    if (!isCustomAdvancedCopyEnabled()) {
        return {};
    }

    const overrides = {};

    Array.from(document.querySelectorAll("[data-custom-field-override-input]")).forEach((element) => {
        if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
            return;
        }

        const key = String(element.dataset.customFieldKey || "").trim();
        const defaultValue = String(element.dataset.defaultValue || "").trim();
        const toggle = document.getElementById(getCustomFieldOverrideToggleId(key));

        if (!(toggle instanceof HTMLInputElement) || !toggle.checked) {
            return;
        }

        const value = String(element.value || "").trim();

        if (key === "" || value === "" || value === defaultValue) {
            return;
        }

        overrides[key] = value;
    });

    return overrides;
}

function setCustomAssetUploadStatus(field, messageKey, fallback, variables = {}, tone = "neutral") {
    const status = document.getElementById(field?.uploadStatusId || "");

    if (!status) {
        return;
    }

    status.textContent = t(messageKey, variables, fallback);
    Object.values(CUSTOM_ASSET_UPLOAD_TONES).forEach((className) => {
        status.classList.remove(className);
    });
    status.classList.add(CUSTOM_ASSET_UPLOAD_TONES[tone] || CUSTOM_ASSET_UPLOAD_TONES.neutral);
}

function setCustomAssetUploadTriggerDisabled(trigger, disabled) {
    if (!(trigger instanceof HTMLElement)) {
        return;
    }

    if (trigger instanceof HTMLButtonElement) {
        trigger.disabled = disabled;
        return;
    }

    trigger.setAttribute("aria-disabled", disabled ? "true" : "false");
    trigger.tabIndex = disabled ? -1 : 0;
    trigger.classList.toggle("pointer-events-none", disabled);

    const uploader = trigger.closest("[data-uploader]");

    if (uploader instanceof HTMLElement) {
        uploader.setAttribute("aria-disabled", disabled ? "true" : "false");
    }
}

function resetCustomAssetUploaderVisual(field) {
    const fileInput = document.getElementById(field?.uploadInputId || "");
    const uploader = fileInput instanceof HTMLInputElement ? fileInput.closest("[data-uploader]") : null;
    const text = uploader instanceof HTMLElement ? uploader.querySelector("[data-uploader-text]") : null;
    const icon = uploader instanceof HTMLElement ? uploader.querySelector(".uploader-icon i") : null;

    if (!(uploader instanceof HTMLElement)) {
        return;
    }

    uploader.classList.remove("has-files", "is-error", "is-dragover");
    uploader.classList.add("is-default");

    if (text instanceof HTMLElement) {
        text.textContent = text.dataset.uploaderIdleText || text.textContent.trim();
    }

    if (icon instanceof HTMLElement) {
        icon.className = icon.dataset.uploaderIdleIcon || "ri-image-add-line";
    }
}

function resetCustomAssetUploadStatuses() {
    CUSTOM_ASSET_OVERRIDE_FIELDS.forEach((field) => {
        const fileInput = document.getElementById(field.uploadInputId);
        const trigger = document.getElementById(field.uploadTriggerId);

        if (fileInput instanceof HTMLInputElement) {
            fileInput.value = "";
        }

        setCustomAssetUploadTriggerDisabled(trigger, false);

        setCustomAssetUploadStatus(
            field,
            "configurator.custom.assetUploadHint",
            "Upload sends the image to DAM and fills the asset ID automatically."
        );
        resetCustomAssetUploaderVisual(field);
    });

    syncCustomAssetFieldSummaries();
}

async function ensureDamFolderPath(folderId) {
    const normalizedFolderId = String(folderId || "").trim().replace(/\\/g, "/").replace(/\/+/g, "/");
    const segments = normalizedFolderId.split("/").filter(Boolean);

    if (segments.length < 2 || segments[0] !== "nexled") {
        throw new Error("Custom upload folder is invalid.");
    }

    let parentId = segments[0];

    for (let index = 1; index < segments.length; index += 1) {
        const segment = segments[index];
        const currentFolderId = parentId + "/" + segment;
        const response = await apiPost("/?endpoint=dam&action=create-folder", {
            parent_id: parentId,
            name: segment,
        });

        if (response.ok) {
            parentId = currentFolderId;
            continue;
        }

        const errorData = await readApiJsonError(response, "Unable to prepare DAM folder.");
        const normalizedMessage = String(errorData.message || "").toLowerCase();

        if (response.status === 409 && normalizedMessage.includes("already exists")) {
            parentId = currentFolderId;
            continue;
        }

        throw new Error(errorData.message || "Unable to prepare DAM folder.");
    }

    return normalizedFolderId;
}

async function uploadCustomAssetOverride(field, file) {
    const targetInput = document.getElementById(field.id);
    const trigger = document.getElementById(field.uploadTriggerId);
    const fileInput = document.getElementById(field.uploadInputId);

    if (!(targetInput instanceof HTMLInputElement)) {
        throw new Error("Custom asset target field is missing.");
    }

    if (!(file instanceof File)) {
        throw new Error("No upload file selected.");
    }

    setCustomAssetUploadTriggerDisabled(trigger, true);

    setCustomAssetUploadStatus(
        field,
        "configurator.custom.assetUploadStarted",
        "Uploading image to DAM..."
    );

    try {
        const folderId = await ensureDamFolderPath(field.uploadFolderId);
        const formData = new FormData();
        formData.append("file", file);
        formData.append("folder_id", folderId);
        formData.append("kind", field.uploadRole);

        const response = await apiPostFormData("/?endpoint=dam&action=upload", formData);

        if (!response.ok) {
            const errorData = await readApiJsonError(response, "Image upload failed.");
            throw new Error(errorData.message || "Image upload failed.");
        }

        const payload = await readApiJsonPayload(response, "Image upload returned an invalid response.");
        const asset = payload?.data?.asset || null;
        const assetId = String(asset?.id || "").trim();

        if (assetId === "") {
            throw new Error("Image upload returned no DAM asset id.");
        }

        targetInput.value = assetId;
        syncCustomAssetFieldSummaries();
        setCustomAssetUploadStatus(
            field,
            "configurator.custom.assetUploadDone",
            "Upload finished. Isolated custom DAM asset #{id} is now used only as a custom override field value.",
            { id: assetId },
            "success"
        );
        scheduleCustomPreview();
        setStatusKey(
            "configurator.custom.assetUploadDone",
            "success",
            { id: assetId },
            "Upload finished. Isolated custom DAM asset #" + assetId + " is ready for this custom override."
        );
    } catch (error) {
        const message = error && error.message ? error.message : "Image upload failed.";

        setCustomAssetUploadStatus(
            field,
            "configurator.custom.assetUploadFailed",
            "Image upload failed: {message}",
            { message },
            "error"
        );
        setStatusKey(
            "configurator.custom.assetUploadFailed",
            "error",
            { message },
            "Image upload failed: " + message
        );
        console.error(error);
    } finally {
        if (fileInput instanceof HTMLInputElement) {
            fileInput.value = "";
        }

        setCustomAssetUploadTriggerDisabled(trigger, false);
        resetCustomAssetUploaderVisual(field);
    }
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

    const advancedToggle = document.getElementById(CUSTOM_ADVANCED_TOGGLE_ID);

    if (advancedToggle instanceof HTMLInputElement) {
        advancedToggle.checked = false;
    }

    [CUSTOM_IMAGES_TOGGLE_ID, CUSTOM_TEXT_TOGGLE_ID, CUSTOM_SECTIONS_TOGGLE_ID].forEach((id) => {
        const element = document.getElementById(id);

        if (element instanceof HTMLInputElement) {
            element.checked = false;
        }
    });

    syncCustomEditingModeControls();

    clearCustomFieldOverrideSnapshot();
    clearCustomAdvancedCopySnapshot();
        renderCustomFieldOverrideEditors({}, false);
        syncCustomAdvancedCopyVisibility();
        syncCustomFieldOverrideVisibility();
        syncCustomOptionalBlockVisibility();
        resetCustomAssetUploadStatuses();
        syncCustomAssetFieldSummaries();

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
    const fieldCount = document.getElementById("custom-preview-field-count");
    const advancedCount = document.getElementById("custom-preview-advanced-count");
    const hiddenCount = document.getElementById("custom-preview-hidden-count");
    const message = document.getElementById("custom-preview-message");

    if (!textCount || !assetCount || !fieldCount || !advancedCount || !hiddenCount || !message) {
        return;
    }

    textCount.textContent = String(customPreviewState.textOverrideCount || 0);
    assetCount.textContent = String(customPreviewState.assetOverrideCount || 0);
    fieldCount.textContent = String(customPreviewState.fieldOverrideCount || 0);
    advancedCount.textContent = String(customPreviewState.advancedCopySectionCount || 0);
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
    syncCustomAdvancedCopyVisibility();
    syncCustomFieldOverrideVisibility();
}

function getSelectedShowcaseExpandedSegments() {
    const allExpandToggle = document.querySelector("[data-showcase-all-expand]");

    if (allExpandToggle instanceof HTMLInputElement && allExpandToggle.checked) {
        return [...SHOWCASE_EXPANDABLE_SEGMENTS];
    }

    return Array.from(document.querySelectorAll("[data-showcase-expand]:checked"))
        .map((input) => String(input.value || "").trim())
        .filter(Boolean);
}

function getSelectedShowcaseSections() {
    const allSectionsToggle = document.querySelector("[data-showcase-all-sections]");

    if (allSectionsToggle instanceof HTMLInputElement && allSectionsToggle.checked) {
        return SHOWCASE_SECTION_DEFINITIONS.map((section) => section.id);
    }

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
        lang: getRequestSelectValue("select-language"),
        company: getRequestSelectValue("select-company"),
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

    const family = getCurrentFamilyMetadata();

    if (!family || !family.showcase_runtime_implemented || family.showcase_renderer !== "downlight") {
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

function getSelectedDatasheetDesignVariant() {
    if (isShowcaseMode() || isCustomMode()) {
        return "classic";
    }

    const selected = document.querySelector('input[name="datasheet-design-variant"]:checked');
    return selected instanceof HTMLInputElement && selected.value === "modern"
        ? "modern"
        : "classic";
}

function buildDatasheetRequestBody() {
    return {
        referencia: document.getElementById("output-reference").value,
        descricao: document.getElementById("output-description").value,
        idioma: getRequestSelectValue("select-language"),
        empresa: getRequestSelectValue("select-company"),
        lente: getSelectedOptionHint("select-lens"),
        acabamento: getSelectedOptionHint("select-finish"),
        opcao: get("select-option"),
        conectorcabo: getRequestSelectValue("select-connector-cable"),
        tipocabo: getRequestSelectValue("select-cable-type"),
        tampa: getRequestSelectValue("select-end-cap"),
        vedante: getRequestSelectValue("select-gasket"),
        acrescimo: get("input-extra-length") || "0",
        ip: getRequestSelectValue("select-ip"),
        fixacao: getRequestSelectValue("select-fixing"),
        fonte: getRequestSelectValue("select-power-supply"),
        caboligacao: getRequestSelectValue("select-connection-cable"),
        conectorligacao: getRequestSelectValue("select-connection-connector"),
        tamanhocaboligacao: get("input-connection-cable-length") || "0",
        finalidade: getRequestSelectValue("select-purpose"),
        design_variant: getSelectedDatasheetDesignVariant(),
    };
}

function buildCustomRequestBody() {
    const textOverrides = {};
    const assetOverrides = {};
    const fieldOverrides = collectCustomFieldOverrides();
    const copyOverrides = collectCustomAdvancedCopyOverrides();
    const sectionVisibility = {};
    const customImagesEnabled = isCustomBlockEnabled(CUSTOM_IMAGES_TOGGLE_ID);
    const customTextEnabled = isCustomBlockEnabled(CUSTOM_TEXT_TOGGLE_ID);
    const customSectionsEnabled = isCustomBlockEnabled(CUSTOM_SECTIONS_TOGGLE_ID);

    if (customTextEnabled) {
        CUSTOM_TEXT_OVERRIDE_FIELDS.forEach((field) => {
            const element = document.getElementById(field.id);
            const value = String(element?.value || "").trim();

            if (value !== "") {
                textOverrides[field.key] = value;
            }
        });
    }

    if (customImagesEnabled) {
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
    }

    if (customSectionsEnabled) {
        CUSTOM_SECTION_VISIBILITY_FIELDS.forEach((field) => {
            const element = document.getElementById(field.id);

            if (!(element instanceof HTMLInputElement)) {
                return;
            }

            sectionVisibility[field.key] = element.checked;
        });
    }

    return {
        base_request: buildDatasheetRequestBody(),
        custom: {
            mode: "custom",
            copy_mode: isCustomAdvancedCopyEnabled() ? "advanced" : "simple",
            text_overrides: textOverrides,
            asset_overrides: assetOverrides,
            field_overrides: fieldOverrides,
            copy_overrides: copyOverrides,
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
            messageFallback: "Showcase mode is not implemented yet for the selected family.",
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

            const failureMessage = resolveRuntimeFailureMessage("showcasePreview", errorData.message);

            showcasePreviewState = {
                ...createEmptyShowcasePreviewState(),
                family: requestBody.family,
                reference: requestReference,
                messageVariables: failureMessage.variables,
                messageKey: failureMessage.key,
                messageFallback: failureMessage.fallback,
            };
            setStatusKey(
                failureMessage.key,
                "error",
                failureMessage.variables,
                failureMessage.fallback
            );
            renderShowcasePreviewState();
            syncGenerateButton();
            return;
        }

        const payload = await readApiJsonPayload(response, "Showcase preview returned an invalid response.");
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

        const failureMessage = resolveRuntimeFailureMessage("showcasePreview", message);

        showcasePreviewState = {
            ...createEmptyShowcasePreviewState(),
            family: requestBody.family,
            reference: requestReference,
            messageVariables: failureMessage.variables,
            messageKey: failureMessage.key,
            messageFallback: failureMessage.fallback,
        };
        setStatusKey(
            failureMessage.key,
            "error",
            failureMessage.variables,
            failureMessage.fallback
        );
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
        clearCustomFieldOverrideSnapshot();
        clearCustomAdvancedCopySnapshot();
        resetCustomPreviewState();
        syncGenerateButton();
        return;
    }

    if (!isCurrentFamilyCustomDatasheetAvailable()) {
        clearCustomFieldOverrideSnapshot();
        clearCustomAdvancedCopySnapshot();
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
        clearCustomFieldOverrideSnapshot();
        clearCustomAdvancedCopySnapshot();
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
            const failureMessage = resolveRuntimeFailureMessage("customPreview", errorData.message);

            customPreviewState = {
                ...createEmptyCustomPreviewState(),
                family: get("select-family"),
                reference: requestReference,
                messageVariables: failureMessage.variables,
                messageKey: failureMessage.key,
                messageFallback: failureMessage.fallback,
            };
            setStatusKey(
                failureMessage.key,
                "error",
                failureMessage.variables,
                failureMessage.fallback
            );
            renderCustomPreviewState();
            syncGenerateButton();
            return;
        }

        const payload = await readApiJsonPayload(response, "Custom datasheet preview returned an invalid response.");
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
        const fieldSnapshot = previewData.field_snapshot || {};
        const editableCopy = previewData.editable_copy || {};

        setCustomFieldOverrideSnapshot(fieldSnapshot, requestReference);
        setCustomAdvancedCopySnapshot(editableCopy, requestReference);

        customPreviewState = {
            pending: false,
            ok: true,
            runtimeImplemented,
            family: get("select-family"),
            reference: requestReference,
            signature: requestSignature,
            textOverrideCount: Number((appliedFields.text || []).length || 0),
            assetOverrideCount: Number((appliedFields.assets || []).length || 0),
            fieldOverrideCount: Number((appliedFields.field_overrides || []).length || 0),
            advancedCopySectionCount: Number((appliedFields.advanced_copy_sections || []).length || 0),
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

        const failureMessage = resolveRuntimeFailureMessage("customPreview", message);

        customPreviewState = {
            ...createEmptyCustomPreviewState(),
            family: get("select-family"),
            reference: requestReference,
            messageVariables: failureMessage.variables,
            messageKey: failureMessage.key,
            messageFallback: failureMessage.fallback,
        };
        setStatusKey(
            failureMessage.key,
            "error",
            failureMessage.variables,
            failureMessage.fallback
        );
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
            const detail = typeof payload?.detail === "string" && payload.detail.trim() !== ""
                ? payload.detail.trim()
                : typeof payload?.error?.details?.detail === "string" && payload.error.details.detail.trim() !== ""
                    ? payload.error.details.detail.trim()
                    : "";
            const topLevelError = typeof payload?.error === "string" && payload.error.trim() !== ""
                ? payload.error.trim()
                : "";
            const nestedError = typeof payload?.error?.message === "string" && payload.error.message.trim() !== ""
                ? payload.error.message.trim()
                : "";
            message = topLevelError || nestedError || message;

            if (detail !== "") {
                message += " - " + detail;
            }
        } catch (_parseError) {
            // Keep cleaned text fallback.
        }
    }

    return { message };
}

function resolveRuntimeFailureMessage(kind, rawMessage) {
    const normalizedMessage = typeof rawMessage === "string"
        ? rawMessage.replace(/\s+/g, " ").trim()
        : "";
    const isMemoryFailure = /allowed memory size|memory exhausted|exhausted/i.test(normalizedMessage);
    const isInternalFailure = /fatal error|uncaught|stack trace| on line \d+| in [A-Z]:\\| in \/[^\s]+|<br/i.test(normalizedMessage);
    const fallbackMap = {
        showcasePreview: {
            genericKey: "configurator.runtime.showcasePreviewFailed",
            genericFallback: "Showcase preview failed. Try again.",
            tooLargeKey: "configurator.runtime.showcasePreviewTooLarge",
            tooLargeFallback: "Showcase preview too large. Narrow the scope and try again.",
            withMessageKey: "configurator.runtime.showcasePreviewFailedWithMessage",
            withMessageFallback: (message) => "Showcase preview failed: " + message,
        },
        customPreview: {
            genericKey: "configurator.runtime.customPreviewFailed",
            genericFallback: "Custom preview failed. Try again.",
            tooLargeKey: "configurator.runtime.customPreviewTooLarge",
            tooLargeFallback: "Custom preview too large. Reduce overrides and try again.",
            withMessageKey: "configurator.runtime.customPreviewFailedWithMessage",
            withMessageFallback: (message) => "Custom datasheet preview failed: " + message,
        },
        showcasePdf: {
            genericKey: "configurator.runtime.showcaseFailed",
            genericFallback: "Showcase PDF generation failed.",
            tooLargeKey: "configurator.runtime.showcaseFailedTooLarge",
            tooLargeFallback: "Showcase PDF too large. Narrow the scope and try again.",
            withMessageKey: "configurator.runtime.showcaseFailedWithMessage",
            withMessageFallback: (message) => "Showcase PDF generation failed: " + message,
        },
        customPdf: {
            genericKey: "configurator.runtime.customFailed",
            genericFallback: "Custom datasheet generation failed.",
            tooLargeKey: "configurator.runtime.customFailedTooLarge",
            tooLargeFallback: "Custom PDF too large. Reduce overrides and try again.",
            withMessageKey: "configurator.runtime.customFailedWithMessage",
            withMessageFallback: (message) => "Custom datasheet generation failed: " + message,
        },
        datasheetPdf: {
            genericKey: "configurator.runtime.datasheetFailed",
            genericFallback: "Datasheet generation failed.",
            tooLargeKey: "configurator.runtime.datasheetFailedTooLarge",
            tooLargeFallback: "PDF request too large. Adjust the selection and try again.",
            withMessageKey: "configurator.runtime.datasheetFailedWithMessage",
            withMessageFallback: (message) => "Datasheet generation failed: " + message,
        },
    };
    const config = fallbackMap[kind] || fallbackMap.datasheetPdf;

    if (isMemoryFailure) {
        return {
            key: config.tooLargeKey,
            variables: {},
            fallback: config.tooLargeFallback,
        };
    }

    if (isInternalFailure || normalizedMessage === "") {
        return {
            key: config.genericKey,
            variables: {},
            fallback: config.genericFallback,
        };
    }

    return {
        key: config.withMessageKey,
        variables: { message: normalizedMessage },
        fallback: config.withMessageFallback(normalizedMessage),
    };
}

async function readApiJsonPayload(response, fallbackMessage) {
    const raw = await response.text();
    const contentType = response.headers.get("content-type") || "";

    if (raw.trim() === "") {
        throw new Error(fallbackMessage || "Endpoint returned an empty response.");
    }

    if (!contentType.includes("application/json")) {
        throw new Error(extractResponseMessage(raw) || fallbackMessage || "Endpoint returned a non-JSON response.");
    }

    try {
        return JSON.parse(raw);
    } catch (_parseError) {
        throw new Error(extractResponseMessage(raw) || fallbackMessage || "Endpoint returned invalid JSON.");
    }
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
            const failureMessage = resolveRuntimeFailureMessage("showcasePdf", errorData.message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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
            const failureMessage = resolveRuntimeFailureMessage("showcasePdf", message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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
            const failureMessage = resolveRuntimeFailureMessage("customPdf", errorData.message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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
            const failureMessage = resolveRuntimeFailureMessage("customPdf", message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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

        const isBottomPlacement = wrapper.dataset.tooltipPlacement === "bottom";

        tooltip.style.top = isBottomPlacement ? "calc(100% + var(--space-8))" : "auto";
        tooltip.style.bottom = isBottomPlacement ? "auto" : "calc(100% + var(--space-8))";

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
        data = await readApiJsonPayload(response, "Health endpoint returned an invalid response.");
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

function buildStatusToastCopy(message, tone) {
    const variant = STATUS_TOAST_VARIANT[tone] || STATUS_TOAST_VARIANT.neutral;
    const normalizedMessage = typeof message === "string"
        ? message.replace(/\s+/g, " ").trim()
        : "";
    const shouldUseBodyCopy = normalizedMessage.length > STATUS_TOAST_TITLE_MAX_LENGTH
        || normalizedMessage.includes(":")
        || normalizedMessage.includes("\n");

    if (!shouldUseBodyCopy) {
        return {
            title: normalizedMessage,
            text: "",
        };
    }

    return {
        title: t(variant.titleKey, {}, variant.titleFallback),
        text: normalizedMessage,
    };
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
    syncCustomAssetFieldSummaries();
    if (isCustomImageBrowserModalOpen()) {
        syncCustomImageBrowserHeader(getCustomAssetFieldById(customImageBrowserState.activeFieldId));
        renderCustomImageBrowserBreadcrumb();
        renderCustomImageBrowserPreview();
        setCustomImageBrowserStatus(
            "configurator.custom.browserStatusIdle",
            {},
            "Browse the custom DAM folders or type an asset ID."
        );
    }
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

async function apiPostFormData(path, formData) {
    const apiBase = await getApiBase();

    try {
        const response = await fetch(apiBase + path, {
            method: "POST",
            headers: {
                "X-API-Key": API_KEY,
            },
            body: formData,
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
                "No Luminos match for the selected family, size, color, CRI, and series."
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
                "No Luminos match for the selected family, size, color, CRI, and series."
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

            const failureMessage = resolveRuntimeFailureMessage("datasheetPdf", message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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
            const failureMessage = resolveRuntimeFailureMessage("datasheetPdf", message);
            setStatusKey(failureMessage.key, "error", failureMessage.variables, failureMessage.fallback);
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
    const title = document.getElementById("status-message-title");
    const text = document.getElementById("status-message-text");
    const icon = document.getElementById("status-message-icon");
    const variant = STATUS_TOAST_VARIANT[tone] || STATUS_TOAST_VARIANT.neutral;
    const shouldHide = !message || (tone === "neutral" && key === "configurator.runtime.chooseFamilyToBegin");

    if (!toast || !title || !text || !icon) {
        return;
    }

    const content = buildStatusToastCopy(message, tone);
    title.textContent = content.title;
    title.hidden = content.title === "";
    text.textContent = content.text;
    text.hidden = content.text === "";
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

function getRequestSelectValue(id) {
    const value = get(id);

    if (value !== "") {
        return value;
    }

    return SELECT_REQUEST_DEFAULTS[id] ?? "";
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
