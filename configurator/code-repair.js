const CODE_REPAIR_API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const CODE_REPAIR_DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";
const CODE_REPAIR_I18N_EVENT = "nexled:i18n-applied";
const CODE_REPAIR_API_BADGE_BASE_CLASS = "badge badge-md shrink-0";
const CODE_REPAIR_API_BADGE_VARIANTS = {
    neutral: "badge-neutral",
    loading: "badge-info",
    success: "badge-success",
    warning: "badge-warning",
    error: "badge-danger",
};
const CODE_REPAIR_TOAST_BASE_CLASS = "toast toast-sm";
const CODE_REPAIR_TOAST_TITLE_MAX_LENGTH = 64;
const CODE_REPAIR_TOAST_AUTOHIDE_DELAY = 4000;
const CODE_REPAIR_TOAST_HIDE_DELAY = 320;
const CODE_REPAIR_TOAST_VARIANTS = {
    neutral: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: true, titleKey: "shared.toast.infoTitle", titleFallback: "Info" },
    loading: { className: "toast-info", iconClass: "ri-information-line", role: "status", autoHide: false, titleKey: "shared.toast.loadingTitle", titleFallback: "Loading" },
    success: { className: "toast-success", iconClass: "ri-checkbox-circle-line", role: "status", autoHide: true, titleKey: "shared.toast.successTitle", titleFallback: "Success" },
    warning: { className: "toast-warning", iconClass: "ri-alert-line", role: "status", autoHide: false, titleKey: "shared.toast.warningTitle", titleFallback: "Warning" },
    error: { className: "toast-danger", iconClass: "ri-close-circle-line", role: "alert", autoHide: true, titleKey: "shared.toast.errorTitle", titleFallback: "Error" },
};
const CODE_REPAIR_SOURCE_ORDER = [
    "luminos",
    "runtime",
    "header",
    "technical_drawing",
    "color_graph",
    "lens_diagram",
    "finish_image",
];
const CODE_REPAIR_SOURCE_META = {
    luminos: {
        kind: "record",
        labelKey: "codeRepair.sourceLuminos",
        fallback: "Luminos identity",
    },
    runtime: {
        kind: "record",
        labelKey: "codeRepair.sourceRuntime",
        fallback: "Datasheet runtime",
    },
    header: {
        kind: "asset",
        labelKey: "codeRepair.sourceHeader",
        fallback: "Header image",
        role: "packshot",
        linkMode: "linked",
    },
    technical_drawing: {
        kind: "asset",
        labelKey: "codeRepair.sourceDrawing",
        fallback: "Technical drawing",
        role: "drawing",
        linkMode: "linked",
    },
    color_graph: {
        kind: "asset",
        labelKey: "codeRepair.sourceColorGraph",
        fallback: "Color graph",
        role: "temperature",
        linkMode: "shared",
    },
    finish_image: {
        kind: "asset",
        labelKey: "codeRepair.sourceFinish",
        fallback: "Finish image",
        role: "finish",
        linkMode: "linked",
    },
};
const CODE_REPAIR_LENS_CARD_META = {
    diagram: {
        cardId: "lens_diagram.diagram",
        sourceKey: "lens_diagram",
        kind: "asset",
        labelKey: "codeRepair.sourceLensDiagram",
        fallback: "Lens diagram",
        role: "diagram",
        linkMode: "linked",
    },
    illuminance: {
        cardId: "lens_diagram.illuminance",
        sourceKey: "lens_diagram",
        kind: "asset",
        labelKey: "codeRepair.sourceLensIlluminance",
        fallback: "Illuminance diagram",
        role: "diagram-inv",
        linkMode: "linked",
    },
};
const CODE_REPAIR_UPLOAD_FOLDERS = {
    packshot: "nexled/datasheet/packshots/generic",
    finish: "nexled/datasheet/finishes/generic",
    drawing: "nexled/datasheet/drawings",
    diagram: "nexled/datasheet/diagrams",
    "diagram-inv": "nexled/datasheet/diagrams/inverted",
    temperature: "nexled/datasheet/temperatures",
    mounting: "nexled/datasheet/mounting",
    connector: "nexled/datasheet/connectors",
    "energy-label": "nexled/datasheet/energy-labels",
    icon: "nexled/datasheet/icons",
    logo: "nexled/datasheet/logos",
    "power-supply": "nexled/datasheet/power-supplies",
};
const CODE_REPAIR_SECTION_LABEL_META = {
    context: ["codeRepair.contextTitle", "Repair Context"],
    segments: ["codeRepair.segmentsTitle", "Decoded Segments"],
    characteristics: ["codeRepair.characteristicsTitle", "Technical characteristics"],
    dimensions: ["codeRepair.dimensionsTitle", "Dimensions"],
    database: ["codeRepair.databaseTitle", "Database Checks"],
    sources: ["codeRepair.sourcesTitle", "Source Map"],
};
const CODE_REPAIR_SECTION_VISIBILITY_DEFAULTS = {
    context: false,
    segments: false,
    characteristics: false,
    dimensions: false,
    database: false,
    sources: false,
};
const codeRepairState = {
    reference: "",
    data: null,
    loading: false,
    mutating: false,
    loadedLanguage: "",
    runtimeTone: "neutral",
    runtimeMessage: "",
    sectionVisibility: { ...CODE_REPAIR_SECTION_VISIBILITY_DEFAULTS },
};
let codeRepairHasSuccessfulApiContact = false;
let codeRepairApiBadgeState = {
    tone: "error",
    key: "shared.badge.apiUnavailable",
    fallback: "API unavailable",
};
let codeRepairElements = null;
let codeRepairToastTimers = {
    dismiss: null,
    hide: null,
};

document.addEventListener("DOMContentLoaded", () => {
    initializeCodeRepairPage();
});

window.addEventListener(CODE_REPAIR_I18N_EVENT, () => {
    if (!codeRepairElements) {
        return;
    }

    updateCodeRepairTitle();
    if (
        codeRepairState.reference !== ""
        && codeRepairState.loadedLanguage !== ""
        && codeRepairState.loadedLanguage !== getCodeRepairLanguage()
        && !codeRepairState.loading
        && !codeRepairState.mutating
    ) {
        void loadCodeRepairContext(codeRepairState.reference, {
            preserveDataOnError: true,
        });
        return;
    }

    renderCodeRepairPage();
});

function initializeCodeRepairPage() {
    codeRepairElements = getCodeRepairElements();

    if (!codeRepairElements) {
        return;
    }

    bindCodeRepairEvents();
    bindCodeRepairToastCloseButton();
    updateCodeRepairTitle();
    renderCodeRepairApiBadge();
    setCodeRepairRuntimeStatus(
        t(
            "codeRepair.runtimeAwaitingReference",
            {},
            "Enter a full Tecit reference to inspect blockers and sources."
        ),
        "neutral",
        { toast: false }
    );
    renderCodeRepairPage();
    void checkCodeRepairApiHealth();

    const params = new URLSearchParams(window.location.search);
    const reference = normalizeCodeRepairReference(params.get("reference") || "");

    if (reference !== "") {
        codeRepairElements.referenceInput.value = reference;
        syncCodeRepairActionState();
        void loadCodeRepairContext(reference);
    }
}

function getCodeRepairElements() {
    const referenceForm = document.getElementById("repair-reference-form");
    const referenceInput = document.getElementById("repair-reference-input");
    const loadButton = document.getElementById("repair-load-button");
    const revalidateButton = document.getElementById("repair-revalidate-button");
    const runtimeMessage = document.getElementById("repair-runtime-message");
    const emptyStateCard = document.getElementById("repair-empty-state-card");
    const detailsCard = document.getElementById("repair-details-card");
    const summaryGrid = document.getElementById("repair-summary-grid");
    const databaseGrid = document.getElementById("repair-database-grid");
    const blockersSection = document.getElementById("repair-blockers-section");
    const blockersList = document.getElementById("repair-blockers-list");
    const sourceGrid = document.getElementById("repair-source-grid");
    const contextList = document.getElementById("repair-context-list");
    const segmentsList = document.getElementById("repair-segments-list");
    const characteristicsList = document.getElementById("repair-characteristics-list");
    const dimensionsList = document.getElementById("repair-dimensions-list");
    const openConfiguratorLink = document.getElementById("repair-open-configurator-link");
    const loadingOverlay = document.getElementById("repair-loading-overlay");
    const loadingCopy = document.getElementById("repair-loading-copy");
    const sectionToggles = Array.from(document.querySelectorAll("[data-repair-section-toggle]"));
    const sectionBodies = Array.from(document.querySelectorAll("[data-repair-section-body]"));

    if (
        !referenceForm
        || !referenceInput
        || !loadButton
        || !revalidateButton
        || !runtimeMessage
        || !emptyStateCard
        || !detailsCard
        || !summaryGrid
        || !databaseGrid
        || !blockersSection
        || !blockersList
        || !sourceGrid
        || !contextList
        || !segmentsList
        || !characteristicsList
        || !dimensionsList
        || !loadingOverlay
        || !loadingCopy
    ) {
        return null;
    }

    return {
        referenceForm,
        referenceInput,
        loadButton,
        revalidateButton,
        runtimeMessage,
        emptyStateCard,
        detailsCard,
        summaryGrid,
        databaseGrid,
        blockersSection,
        blockersList,
        sourceGrid,
        contextList,
        segmentsList,
        characteristicsList,
        dimensionsList,
        openConfiguratorLink,
        loadingOverlay,
        loadingCopy,
        sectionToggles,
        sectionBodies,
    };
}

function bindCodeRepairEvents() {
    codeRepairElements.referenceForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const reference = normalizeCodeRepairReference(codeRepairElements.referenceInput.value);

        if (reference === "") {
            setCodeRepairRuntimeStatus(
                t(
                    "codeRepair.runtimeAwaitingReference",
                    {},
                    "Enter a full Tecit reference to inspect blockers and sources."
                ),
                "warning"
            );
            syncCodeRepairActionState();
            return;
        }

        void loadCodeRepairContext(reference);
    });

    codeRepairElements.referenceInput.addEventListener("input", () => {
        syncCodeRepairActionState();
    });

    codeRepairElements.revalidateButton.addEventListener("click", () => {
        const reference = codeRepairState.reference || normalizeCodeRepairReference(codeRepairElements.referenceInput.value);

        if (reference === "") {
            return;
        }

        const confirmed = window.confirm(
            t(
                "codeRepair.confirmRevalidate",
                { reference },
                'Revalidate this reference? This will run the repair checks again.'
            )
        );

        if (!confirmed) {
            return;
        }

        void loadCodeRepairContext(reference, {
            preserveDataOnError: true,
        });
    });

    codeRepairElements.detailsCard.addEventListener("click", handleCodeRepairDetailsCardClick);
    codeRepairElements.sourceGrid.addEventListener("click", handleCodeRepairGridClick);
    codeRepairElements.sourceGrid.addEventListener("change", handleCodeRepairGridChange);
}

function handleCodeRepairDetailsCardClick(event) {
    const toggleButton = event.target.closest("[data-repair-section-toggle]");

    if (!toggleButton) {
        return;
    }

    const sectionKey = String(toggleButton.dataset.repairSectionToggle || "");

    if (sectionKey === "") {
        return;
    }

    const isVisible = codeRepairState.sectionVisibility[sectionKey] !== false;
    codeRepairState.sectionVisibility[sectionKey] = !isVisible;
    syncCodeRepairSectionVisibility();
}

function syncCodeRepairActionState() {
    const typedReference = normalizeCodeRepairReference(codeRepairElements.referenceInput.value);
    const activeReference = codeRepairState.reference || typedReference;
    const actionDisabled = codeRepairState.loading || codeRepairState.mutating;
    const canLoad = typedReference !== "" && !actionDisabled;
    const canRevalidate = activeReference !== "" && !actionDisabled;

    codeRepairElements.loadButton.disabled = !canLoad;
    codeRepairElements.revalidateButton.disabled = !canRevalidate;

    if (codeRepairElements.openConfiguratorLink) {
        if (activeReference !== "") {
            codeRepairElements.openConfiguratorLink.href = "configurator.html?reference=" + encodeURIComponent(activeReference);
            codeRepairElements.openConfiguratorLink.setAttribute("aria-disabled", "false");
        } else {
            codeRepairElements.openConfiguratorLink.href = "#";
            codeRepairElements.openConfiguratorLink.setAttribute("aria-disabled", "true");
        }
    }
}

async function loadCodeRepairContext(reference, options = {}) {
    const normalizedReference = normalizeCodeRepairReference(reference);
    const preserveDataOnError = options.preserveDataOnError === true;

    if (normalizedReference === "") {
        setCodeRepairRuntimeStatus(
            t(
                "codeRepair.runtimeAwaitingReference",
                {},
                "Enter a full Tecit reference to inspect blockers and sources."
            ),
            "warning"
        );
        return;
    }

    codeRepairState.loading = true;
    codeRepairElements.referenceInput.value = normalizedReference;
    syncCodeRepairActionState();
    setCodeRepairLoadingOverlay(
        true,
        t("codeRepair.runtimeLoading", {}, "Loading repair context...")
    );
    setCodeRepairRuntimeStatus(
        t("codeRepair.runtimeLoading", {}, "Loading repair context..."),
        "loading"
    );

    try {
        const params = new URLSearchParams({
            endpoint: "code-repair",
            reference: normalizedReference,
            lang: getCodeRepairLanguage(),
        });
        const payload = await codeRepairApiRequest("/?" + params.toString());

        codeRepairHasSuccessfulApiContact = true;
        codeRepairState.reference = String(payload?.reference || normalizedReference);
        codeRepairState.data = payload;
        codeRepairState.loadedLanguage = getCodeRepairLanguage();
        codeRepairElements.referenceInput.value = codeRepairState.reference;
        history.replaceState({}, "", "code-repair.html?reference=" + encodeURIComponent(codeRepairState.reference));
        setCodeRepairApiBadge("success", "shared.badge.apiReady", "API ready");
        setCodeRepairRuntimeStatus(getCodeRepairLoadedMessage(payload), getCodeRepairLoadedTone(payload));
    } catch (error) {
        console.error(error);

        if (!preserveDataOnError) {
            codeRepairState.data = null;
            codeRepairState.reference = normalizedReference;
        }

        setCodeRepairApiBadge(
            codeRepairHasSuccessfulApiContact ? "warning" : "error",
            codeRepairHasSuccessfulApiContact ? "shared.badge.apiDegraded" : "shared.badge.apiUnavailable",
            codeRepairHasSuccessfulApiContact ? "API degraded" : "API unavailable"
        );
        setCodeRepairRuntimeStatus(
            getCodeRepairErrorMessage(
                error,
                t("codeRepair.runtimeLoadFailed", {}, "Unable to load repair context right now.")
            ),
            "error"
        );
    } finally {
        codeRepairState.loading = false;
        setCodeRepairLoadingOverlay(false, "");
        renderCodeRepairPage();
    }
}

async function checkCodeRepairApiHealth() {
    setCodeRepairApiBadge("loading", "shared.badge.apiConnecting", "Connecting to API");

    try {
        const payload = await codeRepairApiRequest("/?endpoint=health");
        const damReady = payload?.services?.dam !== false;

        if (payload?.ok && damReady) {
            setCodeRepairApiBadge("success", "shared.badge.apiReady", "API ready");
        } else {
            setCodeRepairApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        }
    } catch (error) {
        setCodeRepairApiBadge(
            codeRepairHasSuccessfulApiContact ? "warning" : "error",
            codeRepairHasSuccessfulApiContact ? "shared.badge.apiDegraded" : "shared.badge.apiUnavailable",
            codeRepairHasSuccessfulApiContact ? "API degraded" : "API unavailable"
        );
    }
}

function renderCodeRepairPage() {
    const hasData = Boolean(codeRepairState.data);

    codeRepairElements.emptyStateCard.classList.toggle("hidden", hasData);
    codeRepairElements.detailsCard.classList.toggle("hidden", !hasData);
    syncCodeRepairActionState();
    renderCodeRepairSummary();
    renderCodeRepairDatabaseChecks();
    renderCodeRepairBlockers();
    renderCodeRepairSources();
    renderCodeRepairContext();
    renderCodeRepairSegments();
    renderCodeRepairCharacteristics();
    renderCodeRepairDimensions();
    renderCodeRepairApiBadge();
    updateCodeRepairTitle();
    syncCodeRepairSectionVisibility();
}

function renderCodeRepairSummary() {
    const payload = codeRepairState.data;
    const referenceValue = payload?.summary?.reference
        || payload?.reference
        || codeRepairState.reference
        || normalizeCodeRepairReference(codeRepairElements.referenceInput.value)
        || "...";
    const familyValue = payload?.family?.code
        ? `${payload.family.code} - ${payload.family.name || payload.family.code}`
        : (payload?.family?.name || "...");

    if (!payload) {
        codeRepairElements.summaryGrid.innerHTML = buildCodeRepairSummaryHeroMarkup({
            reference: referenceValue,
            family: familyValue,
            configuratorMarkup: buildCodeRepairSummaryStatusBadge(
                t("codeRepair.summaryConfigurator", {}, "Configurator"),
                "neutral",
                t("codeRepair.statusUnavailable", {}, "Unavailable")
            ),
            datasheetMarkup: buildCodeRepairSummaryStatusBadge(
                t("codeRepair.summaryDatasheet", {}, "Datasheet"),
                "neutral",
                t("codeRepair.statusUnavailable", {}, "Unavailable")
            ),
            gridSpanClass: "sm:col-span-2 xl:col-span-2",
        }) + buildCodeRepairBlockerStatusHeroMarkup(null);
        return;
    }

    const summary = payload.summary || {};
    const configuratorMarkup = buildCodeRepairSummaryStatusBadge(
        t("codeRepair.summaryConfigurator", {}, "Configurator"),
        summary.configurator_valid === true ? "success" : "warning",
        summary.configurator_valid === true
            ? t("codeRepair.statusValid", {}, "Valid")
            : t("codeRepair.statusInvalid", {}, "Invalid")
    );
    const datasheetMarkup = buildCodeRepairSummaryStatusBadge(
        t("codeRepair.summaryDatasheet", {}, "Datasheet"),
        summary.datasheet_ready === true ? "success" : "warning",
        summary.datasheet_ready === true
            ? t("codeRepair.statusReady", {}, "Ready")
            : t("codeRepair.statusBlocked", {}, "Blocked")
    );

    codeRepairElements.summaryGrid.innerHTML = buildCodeRepairSummaryHeroMarkup({
        reference: referenceValue,
        family: familyValue,
        configuratorMarkup,
        datasheetMarkup,
        gridSpanClass: "sm:col-span-2 xl:col-span-2",
    }) + buildCodeRepairBlockerStatusHeroMarkup(payload);
}

function buildCodeRepairSummaryHeroMarkup({
    reference = "...",
    family = "...",
    configuratorMarkup = "",
    datasheetMarkup = "",
    gridSpanClass = "sm:col-span-2 xl:col-span-4",
} = {}) {
    const safeConfiguratorMarkup = configuratorMarkup || buildCodeRepairNeutralBadge(t("codeRepair.statusUnavailable", {}, "Unavailable"));
    const safeDatasheetMarkup = datasheetMarkup || buildCodeRepairNeutralBadge(t("codeRepair.statusUnavailable", {}, "Unavailable"));

    return `
        <article class="panel border-0 bg-transparent p-20 ${gridSpanClass}">
            <div class="flex flex-col gap-20">
                <div class="flex flex-col gap-8">
                    <p class="text-h2 text-black break-all">${escapeHtml(reference)}</p>
                </div>
                <div class="flex flex-col gap-20 min-w-0">
                    <p class="card-title break-words">${escapeHtml(family)}</p>
                    <div class="flex flex-wrap items-center gap-12">
                        <div>${safeConfiguratorMarkup}</div>
                        <div>${safeDatasheetMarkup}</div>
                    </div>
                </div>
            </div>
        </article>
    `;
}

function buildCodeRepairSummaryStatusBadge(label, tone, status) {
    const toneClass = {
        success: "badge-success",
        warning: "badge-warning",
        neutral: "badge-neutral",
    }[tone] || "badge-neutral";
    return `<span class="badge ${toneClass} badge-sm">${escapeHtml(label)} ${escapeHtml(status)}</span>`;
}

function buildCodeRepairBlockerStatusHeroMarkup(payload) {
    const state = getCodeRepairBlockerStatusHeroState(payload);

    return `
        <article class="panel border-0 bg-transparent p-20 flex items-center justify-center sm:col-span-2 xl:col-span-2">
            ${buildCodeRepairEmptyStateMarkup({
                title: state.title,
                body: state.body,
                size: "md",
                iconClass: state.iconClass,
            })}
        </article>
    `;
}

function getCodeRepairBlockerStatusHeroState(payload) {
    if (!payload) {
        return {
            title: t("codeRepair.blockHeroAwaitingTitle", {}, "Code state unavailable"),
            body: t("codeRepair.blockHeroAwaitingBody", {}, "Load a reference to inspect the current code state and blockers."),
            iconClass: "ri-information-line",
        };
    }

    const summary = payload.summary || {};
    const blockerText = getCodeRepairTopBlockerText(payload);

    if (summary.configurator_valid !== true) {
        return {
            title: t("codeRepair.blockHeroConfiguratorBlockedTitle", {}, "Configurator blocked"),
            body: t("codeRepair.blockHeroBlockedBody", { blocker: blockerText }, "Current blocker: {blocker}."),
            iconClass: "ri-alert-line",
        };
    }

    if (summary.datasheet_ready !== true) {
        return {
            title: t("codeRepair.blockHeroDatasheetBlockedTitle", {}, "Datasheet blocked"),
            body: t("codeRepair.blockHeroBlockedBody", { blocker: blockerText }, "Current blocker: {blocker}."),
            iconClass: "ri-alert-line",
        };
    }

    return {
        title: t("codeRepair.blockNoneTitle", {}, "No blockers found"),
        body: t("codeRepair.blockNoneBody", {}, "This reference is valid and ready for the datasheet in the current runtime."),
        iconClass: "ri-checkbox-circle-line",
    };
}

function renderCodeRepairDatabaseChecks() {
    const payload = codeRepairState.data;

    if (!payload) {
        renderCodeRepairEmptyState(codeRepairElements.databaseGrid, {
            title: t("codeRepair.databaseTitle", {}, "Database Checks"),
            body: t("codeRepair.databaseEmpty", {}, "Load a reference to inspect what the databases returned for this code."),
            size: "md",
            extraClasses: "col-span-full",
        });
        return;
    }

    const checks = Array.isArray(payload?.database_checks) ? payload.database_checks : [];

    if (checks.length === 0) {
        renderCodeRepairEmptyState(codeRepairElements.databaseGrid, {
            title: t("codeRepair.databaseTitle", {}, "Database Checks"),
            body: t("codeRepair.databaseEmpty", {}, "Load a reference to inspect what the databases returned for this code."),
            size: "md",
            extraClasses: "col-span-full",
        });
        return;
    }

    codeRepairElements.databaseGrid.innerHTML = checks.map((check) => {
        return buildCodeRepairDatabaseCheckCardMarkup(check);
    }).join("");
}

function buildCodeRepairDatabaseCheckCardMarkup(check) {
    const label = getCodeRepairDatabaseCheckLabel(String(check?.key || ""));
    const sourceLabel = getCodeRepairDatabaseSourceLabel(String(check?.source || ""));
    const status = String(check?.status || "");
    const statusLabel = getCodeRepairStatusLabel(status);
    const blocking = check?.blocking === true;
    const impactLabel = blocking
        ? t("codeRepair.databaseBlocksDatasheet", {}, "Blocks datasheet")
        : t("codeRepair.databaseDiagnosticOnly", {}, "Diagnostic only");
    const displayValue = formatCodeRepairDatabaseCheckValue(check);

    return `
        <article class="card overflow-hidden">
            <div class="card-body p-24 flex flex-col gap-16">
                <div class="flex flex-wrap items-start justify-between gap-12">
                    <div class="flex flex-col gap-6">
                        <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.databaseTitle", {}, "Database Checks"))}</span>
                        <h3 class="card-title">${escapeHtml(label)}</h3>
                    </div>
                    ${buildCodeRepairStatusPill(statusLabel, status)}
                </div>
                <div class="flex flex-wrap gap-8">
                    ${buildCodeRepairNeutralBadge(sourceLabel)}
                    <span class="badge ${blocking ? "badge-warning" : "badge-neutral"} badge-sm">${escapeHtml(impactLabel)}</span>
                </div>
                <dl class="list list-spec list-md panel border-0 bg-transparent">
                    <div class="list-item">
                        <dt class="list-key">${escapeHtml(t("codeRepair.databaseSource", {}, "Source"))}</dt>
                        <dd class="list-value break-all">${escapeHtml(sourceLabel)}</dd>
                    </div>
                    <div class="list-item">
                        <dt class="list-key">${escapeHtml(t("codeRepair.databaseValue", {}, "Value"))}</dt>
                        <dd class="list-value break-all">${escapeHtml(displayValue)}</dd>
                    </div>
                </dl>
            </div>
        </article>
    `;
}

function buildCodeRepairEmptyStateMarkup({
    title = "",
    body = "",
    size = "sm",
    iconClass = "",
    extraClasses = "",
} = {}) {
    const outerClasses = ["empty-state", `empty-state-${size}`, "border-0", "bg-transparent", extraClasses]
        .filter(Boolean)
        .join(" ");
    const iconMarkup = iconClass
        ? `
            <div class="empty-state-icon icon-box icon-box-empty-state">
                <i class="${iconClass} text-icon-lg" aria-hidden="true"></i>
            </div>
        `
        : "";
    const titleMarkup = title
        ? `<p class="empty-state-title">${escapeHtml(title)}</p>`
        : "";
    const bodyMarkup = body
        ? `<p class="empty-state-text">${escapeHtml(body)}</p>`
        : "";

    return `
        <div class="${outerClasses}">
            ${iconMarkup}
            <div class="empty-state-copy">
                ${titleMarkup}
                ${bodyMarkup}
            </div>
        </div>
    `;
}

function renderCodeRepairEmptyState(target, options) {
    target.innerHTML = buildCodeRepairEmptyStateMarkup(options);
}

function syncCodeRepairSectionVisibility() {
    codeRepairElements.sectionBodies.forEach((body) => {
        const sectionKey = String(body.dataset.repairSectionBody || "");
        const isVisible = codeRepairState.sectionVisibility[sectionKey] !== false;

        body.hidden = !isVisible;
        body.classList.toggle("hidden", !isVisible);
    });

    codeRepairElements.sectionToggles.forEach((button) => {
        const sectionKey = String(button.dataset.repairSectionToggle || "");
        const isVisible = codeRepairState.sectionVisibility[sectionKey] !== false;
        const sectionLabel = getCodeRepairSectionLabel(sectionKey);
        const ariaLabel = isVisible
            ? t("codeRepair.hideSectionAria", { section: sectionLabel }, "Hide {section}")
            : t("codeRepair.showSectionAria", { section: sectionLabel }, "Show {section}");
        const icon = button.querySelector("[data-repair-section-toggle-icon]");

        button.setAttribute("aria-expanded", isVisible ? "true" : "false");
        button.setAttribute("aria-label", ariaLabel);
        button.setAttribute("title", ariaLabel);

        if (icon) {
            icon.className = (isVisible ? "ri-eye-line" : "ri-eye-off-line") + " text-icon-lg";
        }
    });
}

function getCodeRepairSectionLabel(sectionKey) {
    const [labelKey, fallback] = CODE_REPAIR_SECTION_LABEL_META[sectionKey] || [null, sectionKey];

    if (!labelKey) {
        return sectionKey;
    }

    return t(labelKey, {}, fallback);
}

function buildCodeRepairRowsMarkup(rows) {
    return rows.map(([label, value]) => {
        return buildCodeRepairContextRow(label, value);
    }).join("");
}

function buildCodeRepairMarkupRow(label, valueMarkup) {
    return `
        <div class="list-item">
            <dt class="list-key">${escapeHtml(label)}</dt>
            <dd class="list-value break-all">${valueMarkup}</dd>
        </div>
    `;
}

function renderCodeRepairRows(target, rows, emptyMessage) {
    if (!Array.isArray(rows) || rows.length === 0) {
        renderCodeRepairEmptyState(target, {
            body: emptyMessage,
            size: "sm",
        });
        return;
    }

    target.innerHTML = buildCodeRepairRowsMarkup(rows);
}

function renderCodeRepairBlockers() {
    const payload = codeRepairState.data;

    if (!payload) {
        codeRepairElements.blockersSection.hidden = true;
        renderCodeRepairEmptyState(codeRepairElements.blockersList, {
            title: t("codeRepair.blockTitle", {}, "Active blockers"),
            body: t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates."),
            size: "md",
        });
        return;
    }

    const blockers = Array.isArray(payload?.validation?.blockers) ? payload.validation.blockers : [];

    if (blockers.length === 0) {
        codeRepairElements.blockersSection.hidden = true;
        codeRepairElements.blockersList.innerHTML = "";
        return;
    }

    codeRepairElements.blockersSection.hidden = false;
    codeRepairElements.blockersList.innerHTML = blockers.map((blocker) => {
        const statusLabel = getCodeRepairStatusLabel(blocker.current_status || "missing");
        const sourceLabel = getCodeRepairSourceLabel(blocker.source_key || "");

        return `
            <article class="panel p-20 flex flex-col gap-12">
                <div class="flex flex-wrap items-start justify-between gap-12">
                    <div class="flex flex-col gap-6">
                        <h3 class="text-title-sm">${escapeHtml(blocker.title || blocker.code || "")}</h3>
                        <p class="text-body-sm text-grey-primary">${escapeHtml(blocker.summary || "")}</p>
                    </div>
                    ${buildCodeRepairStatusPill(statusLabel, blocker.current_status || "missing")}
                </div>
                <div class="flex flex-wrap gap-8">
                    ${buildCodeRepairNeutralBadge(sourceLabel)}
                    ${buildCodeRepairNeutralBadge(blocker.repair_mode === "asset"
                        ? t("codeRepair.sourceDamCandidates", {}, "DAM candidates")
                        : t("codeRepair.sourceStatus", {}, "Status")
                    )}
                </div>
            </article>
        `;
    }).join("");
}

function renderCodeRepairSources() {
    const payload = codeRepairState.data;

    if (!payload) {
        renderCodeRepairEmptyState(codeRepairElements.sourceGrid, {
            title: t("codeRepair.sourcesTitle", {}, "Source Map"),
            body: t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates."),
            size: "md",
            extraClasses: "col-span-full",
        });
        return;
    }

    const cards = buildCodeRepairCards(payload);

    codeRepairElements.sourceGrid.innerHTML = cards.map((card) => {
        return card.kind === "record"
            ? buildCodeRepairRecordCardMarkup(card)
            : buildCodeRepairAssetCardMarkup(card);
    }).join("");
}

function buildCodeRepairCards(payload) {
    const sourceMap = payload?.source_map || {};
    const blockersBySource = new Map();

    (payload?.validation?.blockers || []).forEach((blocker) => {
        const sourceKey = String(blocker?.source_key || "").trim();
        if (sourceKey !== "" && !blockersBySource.has(sourceKey)) {
            blockersBySource.set(sourceKey, blocker);
        }
    });

    const cards = [];

    CODE_REPAIR_SOURCE_ORDER.forEach((sourceKey) => {
        if (sourceKey === "lens_diagram") {
            const lensSource = sourceMap.lens_diagram || null;
            if (!lensSource) {
                return;
            }

            if (lensSource.status === "not_required" || !lensSource.lookup) {
                cards.push(buildCodeRepairLensCard(payload, lensSource, "diagram", blockersBySource.get("lens_diagram") || null));
                return;
            }

            cards.push(buildCodeRepairLensCard(payload, lensSource, "diagram", blockersBySource.get("lens_diagram") || null));
            cards.push(buildCodeRepairLensCard(payload, lensSource, "illuminance", blockersBySource.get("lens_diagram") || null));
            return;
        }

        const meta = CODE_REPAIR_SOURCE_META[sourceKey];
        const source = sourceMap[sourceKey];

        if (!meta || !source) {
            return;
        }

        if (meta.kind === "record") {
            cards.push(buildCodeRepairRecordCard(payload, sourceKey, source, blockersBySource.get(sourceKey) || null));
            return;
        }

        cards.push(buildCodeRepairAssetCard(payload, sourceKey, source, blockersBySource.get(sourceKey) || null));
    });

    return cards;
}

function buildCodeRepairRecordCard(payload, sourceKey, source, blocker) {
    const summary = payload?.summary || {};

    if (sourceKey === "luminos") {
        return {
            cardId: sourceKey,
            sourceKey,
            kind: "record",
            label: getCodeRepairSourceLabel(sourceKey),
            status: source.status || "missing",
            blocker,
            rows: [
                [t("codeRepair.summaryIdentity", {}, "Identity"), summary.identity || ""],
                ["Product ID", summary.product_id || ""],
                ["LED ID", summary.led_id || ""],
            ],
        };
    }

    return {
        cardId: sourceKey,
        sourceKey,
        kind: "record",
        label: getCodeRepairSourceLabel(sourceKey),
        status: source.status || (source.supported === true ? "present" : "unsupported"),
        blocker,
        rows: [
            [t("codeRepair.sourceRequired", {}, "Required"), source.required === false ? "No" : "Yes"],
            ["Supported", source.supported === true ? t("codeRepair.statusPresent", {}, "Present") : t("codeRepair.statusUnsupported", {}, "Unsupported")],
        ],
    };
}

function buildCodeRepairAssetCard(payload, sourceKey, source, blocker) {
    const meta = CODE_REPAIR_SOURCE_META[sourceKey];

    return {
        cardId: sourceKey,
        sourceKey,
        kind: "asset",
        label: getCodeRepairSourceLabel(sourceKey),
        status: String(source?.status || "missing"),
        blocker,
        role: String(source?.lookup?.dam_role || meta?.role || ""),
        linkMode: meta?.linkMode || "linked",
        required: source?.required !== false,
        active: source?.active || {},
        lookup: source?.lookup || {},
        extraRows: getCodeRepairAssetExtraRows(payload, sourceKey, source),
    };
}

function buildCodeRepairLensCard(payload, lensSource, channelKey, blocker) {
    const meta = CODE_REPAIR_LENS_CARD_META[channelKey];
    const lookup = lensSource?.lookup?.[channelKey] || {};
    const active = lensSource?.status === "not_required"
        ? (lensSource?.active || {})
        : (lensSource?.active?.[channelKey] || {});

    return {
        cardId: meta.cardId,
        sourceKey: meta.sourceKey,
        kind: "asset",
        label: t(meta.labelKey, {}, meta.fallback),
        status: String(lensSource?.status || "missing"),
        blocker,
        role: String(lookup?.dam_role || meta.role || ""),
        linkMode: meta.linkMode,
        required: lensSource?.required !== false,
        active,
        lookup,
        extraRows: [],
    };
}

function getCodeRepairAssetExtraRows(payload, sourceKey, source) {
    if (sourceKey === "header") {
        return [
            ["Image", source?.checks?.image_present === true ? t("codeRepair.statusPresent", {}, "Present") : t("codeRepair.statusMissing", {}, "Missing")],
            ["Description", source?.checks?.description_present === true ? t("codeRepair.statusPresent", {}, "Present") : t("codeRepair.statusMissing", {}, "Missing")],
        ];
    }

    if (sourceKey === "color_graph") {
        return [
            ["Label", String(source?.label || payload?.summary?.color_graph_label || "")],
        ];
    }

    if (sourceKey === "finish_image") {
        return [
            ["Finish", String(source?.finish_name || payload?.summary?.finish_name || "")],
        ];
    }

    return [];
}

function buildCodeRepairRecordCardMarkup(card) {
    const statusLabel = getCodeRepairStatusLabel(card.status);
    const rowsMarkup = Array.isArray(card.rows) && card.rows.length > 0
        ? `
            <dl class="list list-spec list-md panel border-0 bg-transparent">
                ${buildCodeRepairRowsMarkup(card.rows)}
            </dl>
        `
        : "";

    return `
        <article class="card overflow-hidden">
            <div class="card-body p-24 flex flex-col gap-16">
                <div class="flex flex-wrap items-start justify-between gap-12">
                    <div class="flex flex-col gap-6">
                        <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.sourceStatus", {}, "Status"))}</span>
                        <h3 class="card-title">${escapeHtml(card.label)}</h3>
                    </div>
                    ${buildCodeRepairStatusPill(statusLabel, card.status)}
                </div>
                ${rowsMarkup}
            </div>
        </article>
    `;
}

function buildCodeRepairAssetCardMarkup(card) {
    const bestAsset = getCodeRepairBestDamAsset(card);
    const activePath = String(card?.active?.path || "");
    const previewUrl = getCodeRepairPreviewUrl(card?.active);
    const hasActivePreview = previewUrl !== "";
    const activeSourceType = getCodeRepairSourceTypeLabel(card?.active?.source_type || "");
    const target = getCodeRepairLinkTarget(card);
    const localLookup = card?.lookup?.local || {};
    const damLookup = card?.lookup?.dam || {};
    const localChecks = Array.isArray(localLookup?.checks) ? localLookup.checks : [];
    const topAssets = Array.isArray(damLookup?.top_assets) ? damLookup.top_assets : [];
    const isBusy = codeRepairState.loading || codeRepairState.mutating;
    const canUpload = card.required !== false;
    const canLink = bestAsset && card.linkMode === "linked" && target.requiresLink;
    const candidateStems = Array.isArray(card.lookup?.candidates) ? card.lookup.candidates : [];
    const activeNotice = bestAsset && (card.status === "missing" || card.status === "placeholder")
        ? `
            <div class="panel p-12 bg-amber-50 border-amber-200">
                <p class="text-body-xs text-amber-800">${escapeHtml(t("codeRepair.sourceDamNote", {}, "DAM candidate exists, but the current active source still resolves elsewhere."))}</p>
            </div>
        `
        : "";

    const actions = [
        canLink ? `
            <button
                type="button"
                class="btn btn-secondary btn-sm"
                data-repair-use-best="${escapeHtml(card.cardId)}"
                ${isBusy ? "disabled" : ""}
            >
                <i class="ri-links-line text-icon-sm" aria-hidden="true"></i>
                <span>${escapeHtml(t("codeRepair.useBest", {}, "Link best DAM candidate"))}</span>
            </button>
        ` : "",
        canUpload ? `
            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-repair-upload-trigger="${escapeHtml(card.cardId)}"
                ${isBusy ? "disabled" : ""}
            >
                <i class="ri-upload-2-line text-icon-sm" aria-hidden="true"></i>
                <span>${escapeHtml(card.linkMode === "linked"
                    ? t("codeRepair.uploadAndLink", {}, "Upload and link")
                    : t("codeRepair.upload", {}, "Upload asset")
                )}</span>
            </button>
            <input type="file" class="hidden" data-repair-upload-input="${escapeHtml(card.cardId)}" ${isBusy ? "disabled" : ""}>
        ` : "",
        activePath !== "" ? `
            <button
                type="button"
                class="btn btn-secondary btn-sm"
                data-repair-copy-active="${escapeHtml(activePath)}"
                ${isBusy ? "disabled" : ""}
            >
                <i class="ri-file-copy-line text-icon-sm" aria-hidden="true"></i>
                <span>${escapeHtml(t("codeRepair.copyPath", {}, "Copy path"))}</span>
            </button>
        ` : "",
    ].filter(Boolean).join("");

    const extraRows = Array.isArray(card.extraRows) && card.extraRows.length > 0
        ? card.extraRows.map(([label, value]) => {
            return `
                <div class="list-item">
                    <dt class="list-key">${escapeHtml(label)}</dt>
                    <dd class="list-value break-all">${escapeHtml(value || t("codeRepair.statusUnavailable", {}, "Unavailable"))}</dd>
                </div>
            `;
        }).join("")
        : "";

    const primaryRows = [
        [t("codeRepair.activeSourceType", {}, "Active source type"), activeSourceType],
        [t("codeRepair.activePath", {}, "Active path"), activePath || t("codeRepair.statusUnavailable", {}, "Unavailable")],
    ];
    const diagnosticsRows = [
        card.role
            ? buildCodeRepairMarkupRow(
                t("codeRepair.sourceRole", {}, "DAM role"),
                escapeHtml(card.role || t("codeRepair.statusUnavailable", {}, "Unavailable"))
            )
            : "",
        candidateStems.length > 0
            ? buildCodeRepairMarkupRow(
                t("codeRepair.sourceCandidates", {}, "Filename candidates"),
                buildCodeRepairCandidateStemMarkup(candidateStems)
            )
            : "",
        target.requiresLink
            ? buildCodeRepairMarkupRow(
                t("codeRepair.target", {}, "Link target"),
                escapeHtml(target.label)
            )
            : "",
    ].filter(Boolean).join("");
    const detailsMarkup = diagnosticsRows !== "" || localChecks.length > 0 || topAssets.length > 0
        ? `
            <details class="panel p-16 bg-grey-quaternary/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-12">
                    <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.moreDiagnostics", {}, "More diagnostics"))}</span>
                    <i class="ri-arrow-down-s-line text-icon-sm text-grey-primary" aria-hidden="true"></i>
                </summary>
                <div class="flex flex-col gap-16 pt-16">
                    ${diagnosticsRows !== ""
                        ? `<dl class="list list-spec list-md panel border-0 bg-transparent">${diagnosticsRows}</dl>`
                        : ""
                    }
                    ${localChecks.length > 0
                        ? `
                            <div class="flex flex-col gap-10">
                                <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.sourceLocalChecks", {}, "Local checks"))}</span>
                                ${buildCodeRepairLocalChecksMarkup(localChecks)}
                            </div>
                        `
                        : ""
                    }
                    ${topAssets.length > 0
                        ? `
                            <div class="flex flex-col gap-10">
                                <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.sourceDamCandidates", {}, "DAM candidates"))}</span>
                                ${buildCodeRepairDamCandidatesMarkup(card, topAssets)}
                            </div>
                        `
                        : ""
                    }
                </div>
            </details>
        `
        : "";

    return `
        <article class="card overflow-hidden">
            <div class="card-body p-24 flex flex-col gap-20">
                <div class="flex flex-wrap items-start justify-between gap-12">
                    <div class="flex flex-col gap-6">
                        <span class="text-label-md text-grey-primary">${escapeHtml(t("codeRepair.sourceStatus", {}, "Status"))}</span>
                        <h3 class="card-title">${escapeHtml(card.label)}</h3>
                    </div>
                    ${buildCodeRepairStatusPill(getCodeRepairStatusLabel(card.status), card.status)}
                </div>

                <div class="grid gap-16 lg:grid-cols-[minmax(0,160px)_minmax(0,1fr)] items-start">
                    <div class="panel p-12 bg-grey-quaternary/30 min-h-160 flex items-center justify-center overflow-hidden">
                        ${hasActivePreview
                            ? `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(card.label)}" class="w-full h-full object-contain rounded-12">`
                            : `<div class="flex flex-col items-center gap-10 text-center text-grey-primary">
                                    <i class="ri-image-2-line text-icon-xl" aria-hidden="true"></i>
                                    <span class="text-body-xs">${escapeHtml(t("codeRepair.statusUnavailable", {}, "Unavailable"))}</span>
                               </div>`
                        }
                    </div>

                    <div class="flex flex-col gap-12 min-w-0">
                        <dl class="list list-spec list-md panel border-0 bg-transparent">
                            ${buildCodeRepairRowsMarkup(primaryRows)}
                            ${extraRows}
                        </dl>
                        ${activeNotice}
                        ${actions ? `<div class="flex flex-wrap gap-12">${actions}</div>` : ""}
                    </div>
                </div>
                ${detailsMarkup}
            </div>
        </article>
    `;
}

function buildCodeRepairLocalChecksMarkup(checks) {
    if (!Array.isArray(checks) || checks.length === 0) {
        return `<p class="text-body-sm text-grey-primary">${escapeHtml(t("codeRepair.sourceNoLocalChecks", {}, "No local checks available."))}</p>`;
    }

    const trimmedChecks = checks.slice(0, 5);
    const remaining = Math.max(checks.length - trimmedChecks.length, 0);

    return `
        <div class="flex flex-col gap-8">
            ${trimmedChecks.map((check) => {
                const foundPath = String(check?.found_path || "");
                const toneClass = foundPath !== "" ? "text-green-700" : "text-grey-primary";
                const iconClass = foundPath !== "" ? "ri-checkbox-circle-line" : "ri-close-circle-line";

                return `
                    <div class="panel p-12 bg-grey-quaternary/20 flex items-start gap-10">
                        <i class="${iconClass} text-icon-sm ${toneClass}" aria-hidden="true"></i>
                        <div class="flex flex-col gap-4 min-w-0">
                            <span class="text-body-sm break-all"><code>${escapeHtml(check?.candidate || "")}</code></span>
                            <span class="text-body-xs ${toneClass} break-all">${escapeHtml(foundPath || check?.base_path || "")}</span>
                        </div>
                    </div>
                `;
            }).join("")}
            ${remaining > 0 ? `<span class="text-body-xs text-grey-primary">+${remaining}</span>` : ""}
        </div>
    `;
}

function buildCodeRepairDamCandidatesMarkup(card, assets) {
    if (!Array.isArray(assets) || assets.length === 0) {
        return `<p class="text-body-sm text-grey-primary">${escapeHtml(t("codeRepair.sourceNoDamCandidates", {}, "No DAM candidates scored for this source yet."))}</p>`;
    }

    const isBusy = codeRepairState.loading || codeRepairState.mutating;
    const target = getCodeRepairLinkTarget(card);
    const trimmedAssets = assets.slice(0, 4);
    const remaining = Math.max(assets.length - trimmedAssets.length, 0);

    return `
        <div class="flex flex-col gap-10">
            ${trimmedAssets.map((asset) => {
                const previewUrl = getCodeRepairPreviewUrl(asset);
                const linkLabel = buildCodeRepairLinkTargetLabel(asset?.link_family_code, asset?.link_product_code);
                const canLink = card.linkMode === "linked" && target.requiresLink;

                return `
                    <div class="panel p-12 bg-grey-quaternary/20 flex flex-col gap-10">
                        <div class="flex items-start gap-12">
                            <div class="w-56 h-56 rounded-12 overflow-hidden bg-white border border-grey-quaternary shrink-0 flex items-center justify-center">
                                ${previewUrl !== ""
                                    ? `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(asset?.filename || asset?.display_name || "")}" class="w-full h-full object-contain">`
                                    : `<i class="ri-image-line text-icon-md text-grey-primary" aria-hidden="true"></i>`
                                }
                            </div>
                            <div class="flex flex-col gap-6 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-8">
                                    <span class="text-body-sm font-medium break-all">${escapeHtml(asset?.filename || asset?.display_name || "")}</span>
                                    ${buildCodeRepairNeutralBadge(t("codeRepair.score", {}, "Score") + ": " + String(asset?.score ?? 0))}
                                </div>
                                <span class="text-body-xs text-grey-primary break-all">${escapeHtml(asset?.folder_id || "")}</span>
                                ${linkLabel !== ""
                                    ? `<span class="text-body-xs text-grey-primary">${escapeHtml(linkLabel)}</span>`
                                    : ""
                                }
                                ${Array.isArray(asset?.match_reasons) && asset.match_reasons.length > 0
                                    ? `<span class="text-body-xs text-grey-primary">${escapeHtml(formatCodeRepairMatchReasons(asset.match_reasons))}</span>`
                                    : ""
                                }
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-10">
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                data-repair-open-url="${escapeHtml(asset?.secure_url || "")}"
                                ${!asset?.secure_url || isBusy ? "disabled" : ""}
                            >
                                <i class="ri-external-link-line text-icon-sm" aria-hidden="true"></i>
                                <span>${escapeHtml(t("codeRepair.preview", {}, "Preview"))}</span>
                            </button>
                            ${canLink ? `
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-repair-link-asset="${escapeHtml(card.cardId)}"
                                    data-asset-id="${escapeHtml(String(asset?.id || ""))}"
                                    ${isBusy ? "disabled" : ""}
                                >
                                    <i class="ri-links-line text-icon-sm" aria-hidden="true"></i>
                                    <span>${escapeHtml(t("codeRepair.useAsset", {}, "Link this asset"))}</span>
                                </button>
                            ` : ""}
                            ${asset?.link_id ? `
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-repair-unlink="${escapeHtml(String(asset.link_id))}"
                                    ${isBusy ? "disabled" : ""}
                                >
                                    <i class="ri-link-unlink-m text-icon-sm" aria-hidden="true"></i>
                                    <span>${escapeHtml(t("codeRepair.unlink", {}, "Unlink"))}</span>
                                </button>
                            ` : ""}
                        </div>
                    </div>
                `;
            }).join("")}
            ${remaining > 0 ? `<span class="text-body-xs text-grey-primary">+${remaining}</span>` : ""}
        </div>
    `;
}

function renderCodeRepairContext() {
    const payload = codeRepairState.data;

    if (!payload) {
        renderCodeRepairEmptyState(codeRepairElements.contextList, {
            body: t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates."),
            size: "sm",
        });
        return;
    }

    const summary = payload.summary || {};
    const editable = payload.editable_fields || {};
    const rows = [
        [t("codeRepair.summaryIdentity", {}, "Identity"), summary.identity || ""],
        [t("codeRepair.summaryProductType", {}, "Product type"), summary.product_type || ""],
        ["Product ID", summary.product_id || ""],
        ["LED ID", summary.led_id || ""],
        ["Finish", editable.finish_name || summary.finish_name || ""],
        ["Header text", editable.header_description_text || summary.header_description || ""],
    ];

    renderCodeRepairRows(
        codeRepairElements.contextList,
        rows,
        t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates.")
    );
}

function getCodeRepairDatabaseCheckLabel(key) {
    const map = {
        luminos_identity: ["codeRepair.dbCheckLuminosIdentity", "Luminos identity record"],
        product_id: ["codeRepair.dbCheckProductId", "Product ID"],
        led_id: ["codeRepair.dbCheckLedId", "LED ID"],
        luminos_description: ["codeRepair.dbCheckLuminosDescription", "Luminos description"],
        header_description: ["codeRepair.dbCheckHeaderDescription", "Header description text"],
        characteristics: ["codeRepair.dbCheckCharacteristics", "Technical characteristics"],
        dimensions: ["codeRepair.dbCheckDimensions", "Dimensions"],
        ip_rating: ["codeRepair.dbCheckIpRating", "IP rating"],
        color_graph_label: ["codeRepair.dbCheckColorGraphLabel", "Color graph label"],
    };
    const entry = map[key] || ["", key];
    return t(entry[0], {}, entry[1]);
}

function getCodeRepairDatabaseSourceLabel(source) {
    const map = {
        luminos: ["codeRepair.dbSourceLuminos", "Luminos"],
        product_database: ["codeRepair.dbSourceProductDatabase", "Product database"],
        led_database: ["codeRepair.dbSourceLedDatabase", "LED database"],
    };
    const entry = map[source] || ["", source];
    return t(entry[0], {}, entry[1]);
}

function formatCodeRepairDatabaseCheckValue(check) {
    const count = Number.parseInt(String(check?.count ?? ""), 10);
    const value = String(check?.value || "").trim();

    if (Number.isFinite(count) && count >= 0) {
        if (count === 1) {
            return t("codeRepair.databaseRowCountSingle", { count }, "1 row");
        }

        return t("codeRepair.databaseRowCountPlural", { count }, `${count} rows`);
    }

    if (value !== "") {
        return value;
    }

    return getCodeRepairStatusLabel(String(check?.status || ""));
}

function buildCodeRepairContextRow(label, value) {
    return `
        <div class="list-item">
            <dt class="list-key">${escapeHtml(label)}</dt>
            <dd class="list-value break-all">${escapeHtml(value || t("codeRepair.statusUnavailable", {}, "Unavailable"))}</dd>
        </div>
    `;
}

function renderCodeRepairSegments() {
    const payload = codeRepairState.data;
    const segments = payload?.segments || {};
    const labels = payload?.segment_labels || {};
    const orderedSegments = [
        ["family", t("codeExplorer.segmentFamily", {}, "Family")],
        ["size", t("codeExplorer.segmentSize", {}, "Size")],
        ["color", t("codeExplorer.segmentColor", {}, "Color")],
        ["cri", t("codeExplorer.segmentCri", {}, "CRI")],
        ["series", t("codeExplorer.segmentSeries", {}, "Series")],
        ["lens", t("codeExplorer.segmentLens", {}, "Lens")],
        ["finish", t("codeExplorer.segmentFinish", {}, "Finish")],
        ["cap", t("codeExplorer.segmentCap", {}, "Cap")],
        ["option", t("codeExplorer.segmentOption", {}, "Option")],
    ];

    if (!payload) {
        renderCodeRepairEmptyState(codeRepairElements.segmentsList, {
            body: t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates."),
            size: "sm",
        });
        return;
    }

    const rows = orderedSegments.map(([key, label]) => {
        const rawValue = String(segments?.[key] || "");
        const value = labels?.[key] && labels[key] !== rawValue
            ? `${rawValue} - ${labels[key]}`
            : rawValue;

        return [label, value];
    });

    renderCodeRepairRows(
        codeRepairElements.segmentsList,
        rows,
        t("codeRepair.sourcesEmpty", {}, "Load a reference to inspect the current active sources, local checks, and DAM candidates.")
    );
}

function renderCodeRepairCharacteristics() {
    renderCodeRepairDefinitionList(
        codeRepairElements.characteristicsList,
        codeRepairState.data?.characteristics || [],
        t("codeRepair.characteristicsEmpty", {}, "No technical characteristics returned.")
    );
}

function renderCodeRepairDimensions() {
    renderCodeRepairDefinitionList(
        codeRepairElements.dimensionsList,
        codeRepairState.data?.dimensions || [],
        t("codeRepair.dimensionsEmpty", {}, "No drawing dimensions returned.")
    );
}

function renderCodeRepairDefinitionList(target, items, emptyMessage) {
    const rows = Array.isArray(items)
        ? items.map((item) => [String(item?.label || ""), String(item?.value || "")])
        : [];

    renderCodeRepairRows(target, rows, emptyMessage);
}

function handleCodeRepairGridClick(event) {
    const trigger = event.target.closest("[data-repair-use-best], [data-repair-link-asset], [data-repair-unlink], [data-repair-upload-trigger], [data-repair-open-url], [data-repair-copy-active]");

    if (!trigger || codeRepairState.loading || codeRepairState.mutating) {
        return;
    }

    if (trigger.dataset.repairUseBest) {
        const card = getCodeRepairCardById(trigger.dataset.repairUseBest);
        const bestAsset = getCodeRepairBestDamAsset(card);

        if (!card || !bestAsset?.id) {
            setCodeRepairRuntimeStatus(
                t("codeRepair.sourceNoDamCandidates", {}, "No DAM candidates scored for this source yet."),
                "warning"
            );
            return;
        }

        void runCodeRepairLinkMutation(card, bestAsset.id);
        return;
    }

    if (trigger.dataset.repairLinkAsset) {
        const card = getCodeRepairCardById(trigger.dataset.repairLinkAsset);
        const assetId = Number.parseInt(String(trigger.dataset.assetId || ""), 10);

        if (!card || !Number.isFinite(assetId) || assetId <= 0) {
            return;
        }

        void runCodeRepairLinkMutation(card, assetId);
        return;
    }

    if (trigger.dataset.repairUnlink) {
        const linkId = Number.parseInt(String(trigger.dataset.repairUnlink || ""), 10);

        if (!Number.isFinite(linkId) || linkId <= 0) {
            return;
        }

        const confirmed = window.confirm(
            t(
                "codeRepair.confirmUnlink",
                {},
                "Remove this DAM link? This can affect other references that rely on the same family or product target."
            )
        );

        if (!confirmed) {
            return;
        }

        void runCodeRepairMutation(async () => {
            await codeRepairApiRequest(`/?endpoint=dam&action=unlink&id=${encodeURIComponent(String(linkId))}`, {
                method: "DELETE",
            });
        });
        return;
    }

    if (trigger.dataset.repairUploadTrigger) {
        const input = Array.from(codeRepairElements.sourceGrid.querySelectorAll("[data-repair-upload-input]"))
            .find((field) => String(field.dataset.repairUploadInput || "") === String(trigger.dataset.repairUploadTrigger || ""));
        input?.click();
        return;
    }

    if (trigger.dataset.repairOpenUrl) {
        window.open(trigger.dataset.repairOpenUrl, "_blank", "noopener");
        return;
    }

    if (trigger.dataset.repairCopyActive) {
        void copyCodeRepairText(trigger.dataset.repairCopyActive);
    }
}

function handleCodeRepairGridChange(event) {
    const input = event.target.closest("[data-repair-upload-input]");

    if (!input || !input.files || input.files.length === 0) {
        return;
    }

    const file = input.files[0];
    const card = getCodeRepairCardById(String(input.dataset.repairUploadInput || ""));
    input.value = "";

    if (!card || !file) {
        return;
    }

    void runCodeRepairUploadMutation(card, file);
}

async function runCodeRepairLinkMutation(card, assetId) {
    const target = getCodeRepairLinkTarget(card);

    if (!target.requiresLink) {
        setCodeRepairRuntimeStatus(
            t("codeRepair.linkNotNeeded", {}, "This source reads shared DAM assets and does not need a product link."),
            "warning"
        );
        return;
    }

    if (!target.familyCode && !target.productCode) {
        setCodeRepairRuntimeStatus(
            t("codeRepair.linkFailed", {}, "Unable to link asset."),
            "error"
        );
        return;
    }

    await runCodeRepairMutation(async () => {
        await codeRepairApiRequest("/?endpoint=dam&action=link", {
            method: "POST",
            json: {
                asset_id: assetId,
                role: card.role,
                family_code: target.familyCode || undefined,
                product_code: target.productCode || undefined,
                sort_order: 0,
            },
        });
    });
}

async function runCodeRepairUploadMutation(card, file) {
    const folderId = getCodeRepairUploadFolder(card);

    if (!folderId) {
        setCodeRepairRuntimeStatus(
            t("codeRepair.uploadNoFolder", {}, "No upload folder could be inferred for this source."),
            "error"
        );
        return;
    }

    await runCodeRepairMutation(async () => {
        const formData = new FormData();
        formData.append("folder_id", folderId);
        formData.append("kind", card.role);
        formData.append("file", file);

        const uploadResponse = await codeRepairApiRequest("/?endpoint=dam&action=upload", {
            method: "POST",
            body: formData,
        });
        const assetId = Number.parseInt(String(uploadResponse?.data?.asset?.id || ""), 10);
        const target = getCodeRepairLinkTarget(card);

        if (Number.isFinite(assetId) && assetId > 0 && target.requiresLink) {
            await codeRepairApiRequest("/?endpoint=dam&action=link", {
                method: "POST",
                json: {
                    asset_id: assetId,
                    role: card.role,
                    family_code: target.familyCode || undefined,
                    product_code: target.productCode || undefined,
                    sort_order: 0,
                },
            });
        }
    });
}

async function runCodeRepairMutation(task) {
    if (!codeRepairState.reference) {
        return;
    }

    codeRepairState.mutating = true;
    syncCodeRepairActionState();
    setCodeRepairLoadingOverlay(
        true,
        t("codeRepair.runtimeMutating", {}, "Applying repair action...")
    );
    setCodeRepairRuntimeStatus(
        t("codeRepair.runtimeMutating", {}, "Applying repair action..."),
        "loading"
    );

    try {
        await task();
        await loadCodeRepairContext(codeRepairState.reference, {
            preserveDataOnError: true,
        });
    } catch (error) {
        console.error(error);
        setCodeRepairRuntimeStatus(
            getCodeRepairErrorMessage(
                error,
                t("codeRepair.runtimeMutationFailed", {}, "Repair action failed.")
            ),
            "error"
        );
    } finally {
        codeRepairState.mutating = false;
        syncCodeRepairActionState();
        setCodeRepairLoadingOverlay(false, "");
    }
}

function getCodeRepairCardById(cardId) {
    const cards = buildCodeRepairCards(codeRepairState.data);
    return cards.find((card) => card.cardId === cardId) || null;
}

function getCodeRepairBestDamAsset(card) {
    if (!card?.lookup?.dam) {
        return null;
    }

    return card.lookup.dam.matched_asset
        || (Array.isArray(card.lookup.dam.top_assets) ? card.lookup.dam.top_assets[0] : null)
        || null;
}

function getCodeRepairUploadFolder(card) {
    const bestAsset = getCodeRepairBestDamAsset(card);

    if (bestAsset?.folder_id) {
        return String(bestAsset.folder_id);
    }

    return CODE_REPAIR_UPLOAD_FOLDERS[card?.role] || "";
}

function getCodeRepairLinkTarget(card) {
    if (!card || card.linkMode !== "linked") {
        return {
            requiresLink: false,
            familyCode: "",
            productCode: "",
            label: t("codeRepair.linkNotNeeded", {}, "This source reads shared DAM assets and does not need a product link."),
        };
    }

    const familyCode = String(
        getCodeRepairBestDamAsset(card)?.link_family_code
        || codeRepairState.data?.family?.code
        || codeRepairState.data?.segments?.family
        || ""
    ).trim();
    let productCode = String(getCodeRepairBestDamAsset(card)?.link_product_code || "").trim();

    if (!productCode && (card.cardId === "technical_drawing" || card.cardId.startsWith("lens_diagram."))) {
        const candidate = (Array.isArray(card?.lookup?.candidates) ? card.lookup.candidates : [])
            .map((value) => String(value || "").trim())
            .find((value) => value !== "");

        productCode = sanitizeCodeRepairProductCode(candidate || "");
    }

    return {
        requiresLink: familyCode !== "" || productCode !== "",
        familyCode,
        productCode,
        label: buildCodeRepairLinkTargetLabel(familyCode, productCode),
    };
}

function buildCodeRepairLinkTargetLabel(familyCode, productCode) {
    const normalizedFamily = String(familyCode || "").trim();
    const normalizedProduct = String(productCode || "").trim();

    if (normalizedFamily !== "" && normalizedProduct !== "") {
        return t(
            "codeRepair.targetFamilyProduct",
            {
                family: normalizedFamily,
                product: normalizedProduct,
            },
            `Family ${normalizedFamily} · Product ${normalizedProduct}`
        );
    }

    if (normalizedFamily !== "") {
        return t(
            "codeRepair.targetFamily",
            {
                family: normalizedFamily,
            },
            `Family ${normalizedFamily}`
        );
    }

    if (normalizedProduct !== "") {
        return t(
            "codeRepair.targetProduct",
            {
                product: normalizedProduct,
            },
            `Product ${normalizedProduct}`
        );
    }

    return "";
}

function sanitizeCodeRepairProductCode(value) {
    return String(value || "")
        .trim()
        .replace(/[^a-zA-Z0-9_-]/g, "")
        .slice(0, 64);
}

function normalizeCodeRepairReference(value) {
    return String(value || "")
        .trim()
        .replace(/\s+/g, "")
        .toUpperCase();
}

function setCodeRepairRuntimeStatus(message, tone = "neutral", options = {}) {
    const { toast = true } = options;

    codeRepairState.runtimeMessage = String(message || "");
    codeRepairState.runtimeTone = tone;

    if (!codeRepairElements?.runtimeMessage) {
        if (toast) {
            applyCodeRepairStatusToast(codeRepairState.runtimeMessage, tone);
        }

        return;
    }

    codeRepairElements.runtimeMessage.className = "hidden";
    codeRepairElements.runtimeMessage.textContent = "";

    if (toast) {
        applyCodeRepairStatusToast(codeRepairState.runtimeMessage, tone);
        return;
    }

    hideCodeRepairStatusToast(true);
}

function buildCodeRepairToastCopy(message, tone) {
    const variant = CODE_REPAIR_TOAST_VARIANTS[tone] || CODE_REPAIR_TOAST_VARIANTS.neutral;
    const normalizedMessage = typeof message === "string"
        ? message.replace(/\s+/g, " ").trim()
        : "";
    const shouldUseBodyCopy = normalizedMessage.length > CODE_REPAIR_TOAST_TITLE_MAX_LENGTH
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

function applyCodeRepairStatusToast(message, tone = "neutral") {
    const toast = document.getElementById("repair-status-message");
    const title = document.getElementById("repair-status-message-title");
    const text = document.getElementById("repair-status-message-text");
    const icon = document.getElementById("repair-status-message-icon");
    const variant = CODE_REPAIR_TOAST_VARIANTS[tone] || CODE_REPAIR_TOAST_VARIANTS.neutral;
    const shouldHide = !message;

    if (!toast || !title || !text || !icon) {
        return;
    }

    const content = buildCodeRepairToastCopy(message, tone);
    title.textContent = content.title;
    title.hidden = content.title === "";
    text.textContent = content.text;
    text.hidden = content.text === "";
    toast.className = CODE_REPAIR_TOAST_BASE_CLASS + " " + variant.className;
    toast.setAttribute("role", variant.role);
    icon.className = variant.iconClass + " text-icon-lg";

    if (shouldHide) {
        hideCodeRepairStatusToast(true);
        return;
    }

    showCodeRepairStatusToast(variant.autoHide ? CODE_REPAIR_TOAST_AUTOHIDE_DELAY : 0);
}

function showCodeRepairStatusToast(dismissDelay = 0) {
    const toast = document.getElementById("repair-status-message");

    if (!toast) {
        return;
    }

    clearCodeRepairStatusToastTimers();
    toast.hidden = false;
    toast.inert = false;
    toast.setAttribute("aria-hidden", "false");

    window.requestAnimationFrame(() => {
        toast.classList.add("is-visible");
    });

    if (dismissDelay > 0) {
        codeRepairToastTimers.dismiss = setTimeout(() => {
            hideCodeRepairStatusToast();
        }, dismissDelay);
    }
}

function hideCodeRepairStatusToast(immediate = false) {
    const toast = document.getElementById("repair-status-message");

    if (!toast) {
        return;
    }

    clearCodeRepairStatusToastTimers();
    toast.classList.remove("is-visible");
    toast.setAttribute("aria-hidden", "true");

    if (immediate) {
        toast.hidden = true;
        toast.inert = true;
        return;
    }

    codeRepairToastTimers.hide = setTimeout(() => {
        toast.hidden = true;
        toast.inert = true;
        codeRepairToastTimers.hide = null;
    }, CODE_REPAIR_TOAST_HIDE_DELAY);
}

function clearCodeRepairStatusToastTimers() {
    if (codeRepairToastTimers.dismiss) {
        clearTimeout(codeRepairToastTimers.dismiss);
        codeRepairToastTimers.dismiss = null;
    }

    if (codeRepairToastTimers.hide) {
        clearTimeout(codeRepairToastTimers.hide);
        codeRepairToastTimers.hide = null;
    }
}

function bindCodeRepairToastCloseButton() {
    const closeButton = document.getElementById("repair-status-message-close");

    if (!closeButton || closeButton.dataset.bound === "true") {
        return;
    }

    closeButton.dataset.bound = "true";
    closeButton.addEventListener("click", () => {
        hideCodeRepairStatusToast();
    });
}

function getCodeRepairLoadedMessage(payload) {
    const blockerText = getCodeRepairTopBlockerText(payload);

    if (payload?.summary?.datasheet_ready === true) {
        return t("codeRepair.runtimeLoadedReady", {}, "Repair context loaded. Datasheet is ready.");
    }

    return t(
        "codeRepair.runtimeLoadedBlocked",
        {
            blocker: blockerText,
        },
        `Repair context loaded. Top blocker: ${blockerText}.`
    );
}

function getCodeRepairLoadedTone(payload) {
    if (payload?.summary?.datasheet_ready === true) {
        return "success";
    }

    if (payload?.summary?.configurator_valid === true) {
        return "warning";
    }

    return "error";
}

function getCodeRepairTopBlockerText(payload) {
    const blockers = Array.isArray(payload?.validation?.blockers) ? payload.validation.blockers : [];
    const topBlocker = String(payload?.summary?.top_blocker || "");

    if (blockers.length > 0) {
        const explicitMatch = blockers.find((blocker) => String(blocker?.code || "") === topBlocker);
        return String((explicitMatch || blockers[0])?.title || topBlocker || t("codeRepair.statusUnavailable", {}, "Unavailable"));
    }

    return topBlocker !== ""
        ? getCodeRepairFailureReasonText(topBlocker)
        : t("codeRepair.statusReady", {}, "Ready");
}

function getCodeRepairFailureReasonText(code) {
    const reason = String(code || "").trim();

    if (!reason) {
        return t("codeRepair.statusReady", {}, "Ready");
    }

    const map = {
        invalid_luminos_combination: "codeExplorer.failure.invalid_luminos_combination",
        missing_header_data: "codeExplorer.failure.missing_header_data",
        missing_color_graph: "codeExplorer.failure.missing_color_graph",
        missing_lens_diagram: "codeExplorer.failure.missing_lens_diagram",
        missing_technical_drawing: "codeExplorer.failure.missing_technical_drawing",
        missing_finish_image: "codeExplorer.failure.missing_finish_image",
        unsupported_datasheet_runtime: "codeExplorer.failure.unsupported_datasheet_runtime",
    };

    return t(map[reason] || "", {}, reason);
}

function getCodeRepairSourceLabel(sourceKey) {
    const meta = CODE_REPAIR_SOURCE_META[sourceKey];

    if (meta) {
        return t(meta.labelKey, {}, meta.fallback);
    }

    if (sourceKey === "lens_diagram") {
        return t("codeRepair.sourceLensDiagram", {}, "Lens diagram");
    }

    return sourceKey;
}

function getCodeRepairStatusLabel(status) {
    const key = String(status || "").trim();
    const map = {
        present: ["codeRepair.statusPresent", "Present"],
        missing: ["codeRepair.statusMissing", "Missing"],
        placeholder: ["codeRepair.statusPlaceholder", "Placeholder"],
        unsupported: ["codeRepair.statusUnsupported", "Unsupported"],
        not_required: ["codeRepair.statusNotRequired", "Not required"],
        unavailable: ["codeRepair.statusUnavailable", "Unavailable"],
    };

    const entry = map[key] || ["codeRepair.statusUnavailable", "Unavailable"];
    return t(entry[0], {}, entry[1]);
}

function getCodeRepairSourceTypeLabel(sourceType) {
    const normalized = String(sourceType || "").trim();

    if (normalized === "") {
        return t("codeRepair.statusUnavailable", {}, "Unavailable");
    }

    return normalized
        .split(/[_-]+/g)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function buildCodeRepairCandidateStemMarkup(candidates) {
    if (!Array.isArray(candidates) || candidates.length === 0) {
        return escapeHtml(t("codeRepair.statusUnavailable", {}, "Unavailable"));
    }

    return candidates
        .map((candidate) => `<code>${escapeHtml(candidate)}</code>`)
        .join(", ");
}

function buildCodeRepairStatusPill(label, status) {
    const toneClass = getCodeRepairStatusToneClass(status);
    return `<span class="badge ${toneClass} badge-sm">${escapeHtml(label)}</span>`;
}

function buildCodeRepairStatusBadge(isPositive, positiveLabel, negativeLabel) {
    return `<span class="badge ${isPositive ? "badge-success" : "badge-warning"} badge-sm">${escapeHtml(isPositive ? positiveLabel : negativeLabel)}</span>`;
}

function buildCodeRepairNeutralBadge(label) {
    return `<span class="badge badge-neutral badge-sm">${escapeHtml(label)}</span>`;
}

function getCodeRepairStatusToneClass(status) {
    switch (String(status || "").trim()) {
        case "present":
            return "badge-success";
        case "not_required":
            return "badge-neutral";
        case "missing":
        case "placeholder":
        case "unsupported":
            return "badge-warning";
        default:
            return "badge-neutral";
    }
}

function formatCodeRepairMatchReasons(reasons) {
    return reasons
        .map((reason) => {
            const normalized = String(reason || "").trim();
            const map = {
                filename_exact: "Filename",
                filename_suffix: "Filename suffix",
                product_code: "Linked product",
                public_id: "Public ID",
                format: "Format",
            };

            return map[normalized] || normalized;
        })
        .join(" · ");
}

function getCodeRepairPreviewUrl(assetLike) {
    const secureUrl = String(assetLike?.secure_url || "").trim();

    if (secureUrl !== "") {
        return secureUrl;
    }

    const rawPath = String(assetLike?.path || "").trim();

    if (rawPath === "") {
        return "";
    }

    if (/^https?:\/\//i.test(rawPath)) {
        return rawPath;
    }

    return convertCodeRepairLocalPathToUrl(rawPath);
}

function convertCodeRepairLocalPathToUrl(path) {
    const normalizedPath = String(path || "").trim();

    if (normalizedPath === "") {
        return "";
    }

    const repositoryMarker = "api_nexled\\";
    const lowercasePath = normalizedPath.toLowerCase();
    const markerIndex = lowercasePath.indexOf(repositoryMarker);

    if (markerIndex === -1) {
        return "";
    }

    const projectBase = window.location.pathname.replace(/\/configurator\/[^/]+$/, "");
    const relativePath = normalizedPath
        .slice(markerIndex + repositoryMarker.length)
        .replace(/\\/g, "/");

    return window.location.origin + projectBase + "/" + encodeURI(relativePath);
}

async function copyCodeRepairText(value) {
    try {
        await navigator.clipboard.writeText(String(value || ""));
        setCodeRepairRuntimeStatus(
            t("codeRepair.copyUrlDone", {}, "Copied to clipboard."),
            "success"
        );
    } catch (error) {
        console.error(error);
        setCodeRepairRuntimeStatus(
            t("codeRepair.copyUrlFailed", {}, "Unable to copy right now."),
            "error"
        );
    }
}

function updateCodeRepairTitle() {
    const reference = codeRepairState.reference || normalizeCodeRepairReference(codeRepairElements?.referenceInput?.value || "");
    const baseTitle = t("title.codeRepair", {}, "Code Repair - NexLed");
    document.title = reference ? `${baseTitle} - ${reference}` : baseTitle;
}

function renderCodeRepairApiBadge() {
    document.querySelectorAll("[data-api-badge]").forEach((element) => {
        element.className = `${CODE_REPAIR_API_BADGE_BASE_CLASS} ${CODE_REPAIR_API_BADGE_VARIANTS[codeRepairApiBadgeState.tone] || CODE_REPAIR_API_BADGE_VARIANTS.error}`;
    });

    document.querySelectorAll("[data-api-badge-text]").forEach((element) => {
        element.textContent = t(codeRepairApiBadgeState.key, {}, codeRepairApiBadgeState.fallback);
    });
}

function setCodeRepairApiBadge(tone, key, fallback) {
    codeRepairApiBadgeState = {
        tone,
        key,
        fallback,
    };
    renderCodeRepairApiBadge();
}

function setCodeRepairLoadingOverlay(isVisible, message) {
    if (!codeRepairElements?.loadingOverlay || !codeRepairElements?.loadingCopy) {
        return;
    }

    codeRepairElements.loadingCopy.textContent = message || "";
    codeRepairElements.loadingOverlay.classList.toggle("is-open", isVisible);
    codeRepairElements.loadingOverlay.classList.toggle("is-visible", isVisible);
    codeRepairElements.loadingOverlay.setAttribute("aria-hidden", isVisible ? "false" : "true");
    codeRepairElements.loadingOverlay.inert = !isVisible;
}

function getCodeRepairErrorMessage(error, fallback) {
    const payloadMessage = error?.payload?.error?.message || error?.payload?.message || error?.message;

    if (typeof payloadMessage === "string" && payloadMessage.trim() !== "") {
        return payloadMessage.trim();
    }

    return fallback;
}

async function codeRepairApiRequest(path, options = {}) {
    const base = getCodeRepairApiBase();
    const headers = new Headers(options.headers || {});

    headers.set("X-API-Key", CODE_REPAIR_API_KEY);

    const requestOptions = {
        method: options.method || "GET",
        headers,
    };

    if (options.json !== undefined) {
        headers.set("Content-Type", "application/json");
        requestOptions.body = JSON.stringify(options.json);
    } else if (options.body !== undefined) {
        requestOptions.body = options.body;
    }

    const response = await fetch(base + path, requestOptions);
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
        const failure = new Error(
            payload?.error?.message
            || payload?.message
            || rawText
            || `Request failed with status ${response.status}`
        );
        failure.status = response.status;
        failure.payload = payload;
        throw failure;
    }

    codeRepairHasSuccessfulApiContact = true;
    return payload;
}

function getCodeRepairApiBase() {
    const protocol = String(window.location.protocol || "").toLowerCase();
    const origin = String(window.location.origin || "").trim();
    const pathname = String(window.location.pathname || "");

    if (
        (protocol === "http:" || protocol === "https:")
        && origin !== ""
        && /\/configurator\/[^/]+$/.test(pathname)
    ) {
        const projectBase = pathname.replace(/\/configurator\/[^/]+$/, "");
        return origin + (projectBase || "") + "/api";
    }

    return CODE_REPAIR_DEFAULT_API_BASE.replace(/\/+$/, "");
}

function getCodeRepairLanguage() {
    return window.NexLedI18n?.getLanguage?.() || document.documentElement.lang || "en";
}

function t(key, vars = {}, fallback = "") {
    return window.NexLedI18n?.t?.(key, vars, fallback) || fallback;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}
