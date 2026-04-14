const API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";
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
};

let apiBasePromise = null;
let hasSuccessfulApiContact = false;
let apiBadgeState = {
    tone: "error",
    key: "shared.badge.apiUnavailable",
    fallback: "API unavailable",
};
let pageStatusState = {
    key: "codeExplorer.runtime.chooseFamily",
    fallback: "Select one family to start building valid code rows.",
    vars: {},
};
let explorerState = {
    families: [],
    data: null,
    selectedReference: "",
    controls: {
        family: "",
        search: "",
        status: "all",
        page: 1,
        pageSize: 100,
    },
};

document.addEventListener("DOMContentLoaded", () => {
    initializeCodeExplorer();
});

function initializeCodeExplorer() {
    bindControls();
    bindStaticEvents();
    renderApiBadge();
    renderPageStatus();
    renderSummary(null);
    renderTable();
    renderDetail();
    loadFamilies();
    checkApiHealth();
}

function bindControls() {
    document.getElementById("explorer-filters").addEventListener("submit", (event) => {
        event.preventDefault();
        explorerState.controls.search = document.getElementById("explorer-search").value.trim();
        explorerState.controls.page = 1;
        loadExplorerData();
    });

    document.getElementById("explorer-family").addEventListener("change", (event) => {
        explorerState.controls.family = event.target.value;
        explorerState.controls.search = "";
        explorerState.controls.status = "all";
        explorerState.controls.page = 1;

        document.getElementById("explorer-search").value = "";
        document.getElementById("explorer-status").value = "all";

        if (!explorerState.controls.family) {
            explorerState.data = null;
            explorerState.selectedReference = "";
            setPageStatus("codeExplorer.runtime.chooseFamily", "Select one family to start building valid code rows.");
            renderSummary(null);
            renderTable();
            renderDetail();
            return;
        }

        loadExplorerData();
    });

    document.getElementById("explorer-status").addEventListener("change", (event) => {
        explorerState.controls.status = event.target.value;
        explorerState.controls.page = 1;

        if (explorerState.controls.family) {
            loadExplorerData();
        }
    });

    document.getElementById("explorer-page-size").addEventListener("change", (event) => {
        explorerState.controls.pageSize = Number.parseInt(event.target.value, 10) || 100;
        explorerState.controls.page = 1;

        if (explorerState.controls.family) {
            loadExplorerData();
        }
    });

    document.getElementById("explorer-prev").addEventListener("click", () => {
        if (!explorerState.data || explorerState.data.pagination.page <= 1) {
            return;
        }

        explorerState.controls.page = explorerState.data.pagination.page - 1;
        loadExplorerData();
    });

    document.getElementById("explorer-next").addEventListener("click", () => {
        if (!explorerState.data || explorerState.data.pagination.page >= explorerState.data.pagination.total_pages) {
            return;
        }

        explorerState.controls.page = explorerState.data.pagination.page + 1;
        loadExplorerData();
    });

    document.getElementById("explorer-rows").addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-reference]");

        if (!trigger) {
            return;
        }

        explorerState.selectedReference = trigger.dataset.reference || "";
        renderTable();
        renderDetail();
    });
}

function bindStaticEvents() {
    window.addEventListener(I18N_EVENT, () => {
        renderApiBadge();
        renderPageStatus();
        renderResultsMeta();
        renderSummary(explorerState.data);
        renderTable();
        renderDetail();
        renderPagination();
    });
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
    setPageStatus("codeExplorer.runtime.loadingFamilies", "Loading families...");

    try {
        const families = await apiFetch("/?endpoint=families");
        explorerState.families = Array.isArray(families) ? families : [];
        populateFamilies();
        setPageStatus("codeExplorer.runtime.chooseFamily", "Select one family to start building valid code rows.");
    } catch (error) {
        setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "Unable to load explorer data right now: {message}", {
            message: error?.message || t("codeExplorer.runtime.unknownError", {}, "Unknown error"),
        });
        renderResultsMeta();
    }
}

function populateFamilies() {
    const select = document.getElementById("explorer-family");
    const placeholderLabel = t("codeExplorer.familyPlaceholder", {}, "Select a family");
    const options = [
        `<option value="">${escapeHtml(placeholderLabel)}</option>`,
    ];

    explorerState.families.forEach((family) => {
        const code = String(family.codigo || "");
        const name = String(family.nome || code);
        options.push(`<option value="${escapeHtml(code)}">${escapeHtml(code + " - " + name)}</option>`);
    });

    select.innerHTML = options.join("");
    select.value = explorerState.controls.family;
}

async function loadExplorerData() {
    if (!explorerState.controls.family) {
        return;
    }

    toggleLoading(true);
    setPageStatus("codeExplorer.runtime.loadingRows", "Building family code matrix...");

    const params = new URLSearchParams({
        endpoint: "code-explorer",
        family: explorerState.controls.family,
        page: String(explorerState.controls.page),
        page_size: String(explorerState.controls.pageSize),
        search: explorerState.controls.search,
        status: explorerState.controls.status,
    });

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
            setPageStatus("codeExplorer.runtime.loadedRows", "Explorer rows loaded.");
        } else {
            setPageStatus("codeExplorer.runtime.noRows", "No rows match current filters.");
        }
    } catch (error) {
        explorerState.data = null;
        explorerState.selectedReference = "";
        renderSummary(null);
        renderTable();
        renderDetail();
        renderResultsMeta();
        renderPagination();
        setPageStatus("codeExplorer.runtime.loadFailedWithMessage", "Unable to load explorer data right now: {message}", {
            message: error?.message || t("codeExplorer.runtime.unknownError", {}, "Unknown error"),
        });

        if (error.status >= 500 || error.status === 401 || error.status === 403) {
            setApiBadge("warning", "shared.badge.apiDegraded", "API degraded");
        }
    } finally {
        toggleLoading(false);
    }
}

function toggleLoading(isLoading) {
    document.getElementById("explorer-loading").classList.toggle("hidden", !isLoading);
}

function renderSummary(data) {
    const summary = data?.summary || {
        total_codes: 0,
        configurator_valid: 0,
        datasheet_ready: 0,
        datasheet_blocked: 0,
    };

    document.getElementById("summary-total").textContent = formatNumber(summary.total_codes);
    document.getElementById("summary-configurator").textContent = formatNumber(summary.configurator_valid);
    document.getElementById("summary-ready").textContent = formatNumber(summary.datasheet_ready);
    document.getElementById("summary-blocked").textContent = formatNumber(summary.datasheet_blocked);
}

function renderTable() {
    const body = document.getElementById("explorer-rows");
    const empty = document.getElementById("explorer-empty");
    const rows = explorerState.data?.rows || [];

    if (rows.length === 0) {
        body.innerHTML = "";
        empty.classList.remove("hidden");
        return;
    }

    empty.classList.add("hidden");
    body.innerHTML = rows.map((row) => {
        const selected = row.reference === explorerState.selectedReference;
        const rowClass = selected ? "bg-green-quaternary/40" : "";

        return `
            <tr class="border-b border-grey-quaternary/40 align-top ${rowClass}">
                <td class="py-12 pr-16">
                    <button type="button" class="link link-sm text-left" data-reference="${escapeHtml(row.reference)}">
                        <span class="font-mono">${escapeHtml(row.reference)}</span>
                    </button>
                </td>
                <td class="py-12 pr-16 font-mono text-grey-primary">${escapeHtml(row.identity || "")}</td>
                <td class="py-12 pr-16">${escapeHtml(row.description || "—")}</td>
                <td class="py-12 pr-16">${escapeHtml(row.product_type || "—")}</td>
                <td class="py-12 pr-16 break-all">${escapeHtml(row.product_id || "—")}</td>
                <td class="py-12 pr-16">${buildStatusBadge(row.configurator_valid, t("codeExplorer.statusConfiguratorValidShort", {}, "Valid"), t("codeExplorer.statusBlockedShort", {}, "Blocked"))}</td>
                <td class="py-12 pr-16">${buildStatusBadge(row.datasheet_ready, t("codeExplorer.statusReadyShort", {}, "Ready"), t("codeExplorer.statusBlockedShort", {}, "Blocked"))}</td>
                <td class="py-12">${escapeHtml(getFailureReasonText(row.failure_reason))}</td>
            </tr>
        `;
    }).join("");
}

function renderDetail() {
    const detail = document.getElementById("explorer-detail");
    const empty = document.getElementById("explorer-detail-empty");
    const row = getSelectedRow();

    if (!row) {
        detail.classList.add("hidden");
        empty.classList.remove("hidden");
        return;
    }

    empty.classList.add("hidden");
    detail.classList.remove("hidden");

    document.getElementById("detail-reference").textContent = row.reference || "";
    document.getElementById("detail-identity").textContent = row.identity || "";
    document.getElementById("detail-description").textContent = row.description || "—";
    document.getElementById("detail-type").textContent = row.product_type || "—";
    document.getElementById("detail-product-id").textContent = row.product_id || "—";

    document.getElementById("detail-segments").innerHTML = SEGMENT_META.map((segment) => {
        return `
            <div class="flex items-start justify-between gap-12 rounded-card border border-grey-quaternary/60 bg-white px-12 py-10">
                <span class="text-body-sm text-grey-primary">${escapeHtml(t(segment.labelKey, {}, segment.fallback))}</span>
                <code class="text-body-sm font-mono text-black text-right">${escapeHtml(getSegmentDisplay(row, segment.key))}</code>
            </div>
        `;
    }).join("");

    document.getElementById("detail-statuses").innerHTML = [
        buildStatusBadge(row.configurator_valid, t("codeExplorer.statusConfiguratorValid", {}, "Configurator valid"), t("codeExplorer.statusBlockedShort", {}, "Blocked")),
        buildStatusBadge(row.datasheet_ready, t("codeExplorer.statusDatasheetReady", {}, "Datasheet ready"), t("codeExplorer.statusDatasheetBlocked", {}, "Datasheet blocked")),
    ].join("");

    document.getElementById("detail-failure").textContent = row.failure_reason
        ? getFailureReasonText(row.failure_reason)
        : t("codeExplorer.failure.none", {}, "No blocking reason.");
}

function renderResultsMeta() {
    const meta = document.getElementById("explorer-results-meta");

    if (!explorerState.controls.family) {
        meta.textContent = t("codeExplorer.runtime.awaitingFamily", {}, "Waiting for family selection.");
        return;
    }

    if (!explorerState.data) {
        meta.textContent = t("codeExplorer.runtime.loadFailed", {}, "Unable to load explorer data right now.");
        return;
    }

    const familyLabel = explorerState.data.family
        ? `${explorerState.data.family.code} - ${explorerState.data.family.name}`
        : explorerState.controls.family;

    meta.textContent = t("codeExplorer.runtime.resultsMeta", {
        family: familyLabel,
        total: explorerState.data.pagination.total_rows,
    }, `${familyLabel} · ${explorerState.data.pagination.total_rows} filtered rows`);
}

function renderPagination() {
    const meta = document.getElementById("explorer-pagination-meta");
    const indicator = document.getElementById("explorer-page-indicator");
    const prev = document.getElementById("explorer-prev");
    const next = document.getElementById("explorer-next");

    if (!explorerState.data) {
        meta.textContent = "0-0 / 0";
        indicator.textContent = "1 / 1";
        prev.disabled = true;
        next.disabled = true;
        return;
    }

    const { page, page_size: pageSize, total_pages: totalPages, total_rows: totalRows } = explorerState.data.pagination;
    const rows = explorerState.data.rows || [];
    const start = totalRows === 0 ? 0 : ((page - 1) * pageSize) + 1;
    const end = totalRows === 0 ? 0 : (start + rows.length - 1);

    meta.textContent = t("codeExplorer.paginationRange", {
        start,
        end,
        total: totalRows,
    }, `${start}-${end} / ${totalRows}`);
    indicator.textContent = `${page} / ${totalPages}`;
    prev.disabled = page <= 1;
    next.disabled = page >= totalPages;
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

    return `${value} · ${label}`;
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

function setPageStatus(key, fallback, vars = {}) {
    pageStatusState = { key, fallback, vars };
    renderPageStatus();
}

function renderPageStatus() {
    document.getElementById("explorer-status-message").textContent = t(pageStatusState.key, pageStatusState.vars, pageStatusState.fallback);
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
