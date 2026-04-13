const DAM_API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DAM_I18N_EVENT = "nexled:i18n-applied";
const DAM_DEFAULT_FOLDER_ID = "nexled/00_brand";

const damState = {
    tree: [],
    currentFolderId: "",
    currentFolder: null,
    folders: [],
    assets: [],
    selectedAssetId: null,
    selectedAsset: null,
    searchQuery: "",
};

let treeRequestToken = 0;
let listRequestToken = 0;
let searchTimer = 0;
let damElements = null;

document.addEventListener("DOMContentLoaded", () => {
    damElements = getDamElements();

    if (!damElements) {
        return;
    }

    bindDamEvents();
    loadDamTree();
});

window.addEventListener(DAM_I18N_EVENT, () => {
    if (!damElements) {
        return;
    }

    renderFolderTree();
    renderDamList();
    updateFolderSummary();
    renderSelectedAsset();
    syncFolderActionButtons();
});

function getDamElements() {
    const fileGrid = document.getElementById("fileGrid");
    const emptyState = document.getElementById("emptyState");
    const searchInput = document.getElementById("searchInput");
    const folderTree = document.querySelector("[data-dam-folder-tree]");
    const refreshTreeButton = document.querySelector("[data-dam-refresh-tree]");
    const treeStatus = document.querySelector("[data-dam-tree-status]");
    const listStatus = document.querySelector("[data-dam-list-status]");
    const currentFolder = document.querySelector("[data-dam-current-folder]");
    const currentPath = document.querySelector("[data-dam-current-path]");
    const selectedFolder = document.querySelector("[data-dam-selected-folder]");
    const createFolderInput = document.getElementById("dam-create-folder-name");
    const createFolderButton = document.querySelector("[data-dam-create-folder]");
    const folderActionStatus = document.querySelector("[data-dam-folder-action-status]");
    const uploadTrigger = document.querySelector("[data-dam-upload-trigger]");
    const uploadInput = document.querySelector("[data-dam-upload-input]");
    const uploadStatus = document.querySelector("[data-dam-upload-status]");
    const assetModal = document.querySelector("[data-dam-asset-modal]");
    const assetModalPanel = document.querySelector("[data-dam-asset-modal-panel]");
    const closeAssetModalButton = document.querySelector("[data-dam-close-asset-modal]");
    const assetPreview = document.querySelector("[data-dam-asset-preview]");
    const emptyAsset = document.querySelector("[data-dam-empty-asset]");
    const assetName = document.querySelector("[data-dam-asset-name]");
    const assetType = document.querySelector("[data-dam-asset-type]");
    const assetSize = document.querySelector("[data-dam-asset-size]");
    const assetFormat = document.querySelector("[data-dam-asset-format]");
    const assetFolder = document.querySelector("[data-dam-asset-folder]");
    const openAssetButton = document.querySelector("[data-dam-open-asset]");
    const copyAssetUrlButton = document.querySelector("[data-dam-copy-asset-url]");
    const assetStatus = document.querySelector("[data-dam-asset-status]");

    if (!fileGrid || !emptyState || !searchInput || !folderTree || !refreshTreeButton || !treeStatus || !listStatus || !currentFolder || !currentPath || !selectedFolder || !createFolderInput || !createFolderButton || !folderActionStatus || !uploadTrigger || !uploadInput || !uploadStatus || !assetModal || !assetModalPanel || !closeAssetModalButton || !assetPreview || !emptyAsset || !assetName || !assetType || !assetSize || !assetFormat || !assetFolder || !openAssetButton || !copyAssetUrlButton || !assetStatus) {
        return null;
    }

    return {
        fileGrid,
        emptyState,
        searchInput,
        folderTree,
        refreshTreeButton,
        treeStatus,
        listStatus,
        currentFolder,
        currentPath,
        selectedFolder,
        createFolderInput,
        createFolderButton,
        folderActionStatus,
        uploadTrigger,
        uploadInput,
        uploadStatus,
        assetModal,
        assetModalPanel,
        closeAssetModalButton,
        assetPreview,
        emptyAsset,
        assetName,
        assetType,
        assetSize,
        assetFormat,
        assetFolder,
        openAssetButton,
        copyAssetUrlButton,
        assetStatus,
    };
}

function bindDamEvents() {
    damElements.assetModal.inert = true;

    damElements.refreshTreeButton.addEventListener("click", () => {
        loadDamTree(true);
    });

    damElements.searchInput.addEventListener("input", (event) => {
        window.clearTimeout(searchTimer);
        damState.searchQuery = String(event.target.value || "").trim();
        searchTimer = window.setTimeout(() => {
            if (damState.currentFolderId) {
                loadDamFolder(damState.currentFolderId);
            }
        }, 180);
    });

    damElements.createFolderButton.addEventListener("click", handleCreateFolder);
    damElements.createFolderInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            handleCreateFolder();
        }
    });

    damElements.uploadTrigger.addEventListener("click", () => {
        if (damElements.uploadTrigger.disabled) {
            return;
        }

        damElements.uploadInput.click();
    });

    damElements.uploadInput.addEventListener("change", handleUploadAsset);
    damElements.closeAssetModalButton.addEventListener("click", () => {
        closeAssetDetailsModal(true);
    });

    damElements.assetModal.addEventListener("click", (event) => {
        if (event.target === damElements.assetModal) {
            closeAssetDetailsModal(true);
        }
    });

    damElements.openAssetButton.addEventListener("click", () => {
        if (damState.selectedAsset?.secure_url) {
            window.open(damState.selectedAsset.secure_url, "_blank", "noopener");
        }
    });

    damElements.copyAssetUrlButton.addEventListener("click", async () => {
        if (!damState.selectedAsset?.secure_url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(damState.selectedAsset.secure_url);
            setAssetStatus(t("dam.copyAssetUrlDone", "Asset URL copied."));
        } catch (error) {
            console.error(error);
            setAssetStatus(t("dam.copyAssetUrlFailed", "Unable to copy asset URL."));
        }
    });

    document.addEventListener("keydown", (event) => {
        if (!isAssetModalOpen()) {
            return;
        }

        if (event.key === "Escape") {
            closeAssetDetailsModal(true);
            return;
        }

        if (event.key === "Tab") {
            trapAssetModalFocus(event);
        }
    });
}

async function loadDamTree(preserveSelection = true) {
    const requestToken = ++treeRequestToken;
    setTreeStatus(t("dam.loadingFolders", "Loading folders..."));

    try {
        const response = await fetchDamGet("tree", {
            depth: "3",
        });

        if (requestToken !== treeRequestToken) {
            return;
        }

        damState.tree = Array.isArray(response?.data?.folders) ? response.data.folders : [];
        renderFolderTree();

        const nextFolderId = resolveInitialFolderId(preserveSelection);

        if (nextFolderId) {
            await loadDamFolder(nextFolderId);
            return;
        }

        damState.currentFolderId = "";
        damState.currentFolder = null;
        damState.folders = [];
        damState.assets = [];
        clearSelectedAsset();
        updateFolderSummary();
        renderDamList();
        syncFolderActionButtons();
        setTreeStatus(t("dam.loadFailed", "Unable to load DAM data."));
    } catch (error) {
        console.error(error);

        if (requestToken !== treeRequestToken) {
            return;
        }

        damState.tree = [];
        damState.currentFolderId = "";
        damState.currentFolder = null;
        damState.folders = [];
        damState.assets = [];
        clearSelectedAsset();
        renderFolderTree();
        renderDamList();
        updateFolderSummary();
        syncFolderActionButtons();
        setTreeStatus(t("dam.loadFailed", "Unable to load DAM data."));
        setListStatus(t("dam.loadFailed", "Unable to load DAM data."));
    }
}

async function loadDamFolder(folderId) {
    const requestToken = ++listRequestToken;
    closeAssetDetailsModal(false);
    damState.currentFolderId = folderId;
    damState.selectedAssetId = null;
    damState.selectedAsset = null;
    updateFolderSummary();
    renderFolderTree();
    renderSelectedAsset();
    syncFolderActionButtons();
    setListStatus(t("dam.loadingAssets", "Loading assets..."));

    try {
        const response = await fetchDamGet("list", {
            folder_id: folderId,
            q: damState.searchQuery,
        });

        if (requestToken !== listRequestToken) {
            return;
        }

        damState.currentFolder = response?.data?.folder || null;
        damState.folders = Array.isArray(response?.data?.folders) ? response.data.folders : [];
        damState.assets = Array.isArray(response?.data?.assets) ? response.data.assets : [];
        updateFolderSummary();
        renderFolderTree();
        renderDamList();
        syncFolderActionButtons();
        setListStatus(buildListSummaryText());
    } catch (error) {
        console.error(error);

        if (requestToken !== listRequestToken) {
            return;
        }

        damState.currentFolder = {
            id: folderId,
            name: folderId.split("/").pop() || folderId,
            path: folderId,
            scope: "",
            can_upload: false,
            can_create_children: false,
        };
        damState.folders = [];
        damState.assets = [];
        updateFolderSummary();
        renderFolderTree();
        renderDamList();
        syncFolderActionButtons();
        setListStatus(t("dam.loadFailed", "Unable to load DAM data."));
    }
}

async function handleCreateFolder() {
    if (!damState.currentFolder?.can_create_children) {
        setFolderActionStatus(t("dam.folderActionBlocked", "Selected folder cannot create child folders."));
        return;
    }

    const name = String(damElements.createFolderInput.value || "").trim();

    if (!name) {
        setFolderActionStatus(t("dam.folderCreateFailed", "Unable to create folder."));
        return;
    }

    setFolderActionStatus(t("dam.loadingFolders", "Loading folders..."));

    try {
        const response = await fetchDamJson("create-folder", {
            parent_id: damState.currentFolder.id,
            name,
        });
        const folder = response?.data?.folder || null;

        damElements.createFolderInput.value = "";
        setFolderActionStatus(t("dam.folderCreated", "Folder created."));
        await loadDamTree(false);

        if (folder?.id) {
            await loadDamFolder(folder.id);
        }
    } catch (error) {
        console.error(error);
        setFolderActionStatus(getDamErrorMessage(error, t("dam.folderCreateFailed", "Unable to create folder.")));
    }
}

async function handleUploadAsset(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    if (!damState.currentFolder?.can_upload) {
        setUploadStatus(t("dam.uploadActionBlocked", "Selected folder does not allow uploads."));
        event.target.value = "";
        return;
    }

    setUploadStatus(t("dam.uploadReady", "{name} selected for upload.", { name: file.name }));

    try {
        setUploadStatus(t("dam.uploadStarted", "Uploading asset..."));
        const formData = new FormData();
        formData.append("file", file);
        formData.append("folder_id", damState.currentFolder.id);

        const response = await fetchDamUpload("upload", formData);
        const asset = response?.data?.asset || null;

        setUploadStatus(t("dam.uploadDone", "Asset uploaded."));
        await loadDamFolder(damState.currentFolder.id);

        if (asset?.id) {
            selectAssetById(asset.id);
        }
    } catch (error) {
        console.error(error);
        setUploadStatus(getDamErrorMessage(error, t("dam.uploadFailed", "Unable to upload asset.")));
    } finally {
        event.target.value = "";
    }
}

async function fetchDamGet(action, params = {}) {
    const url = new URL(resolveDamApiBase() + "/");
    url.searchParams.set("endpoint", "dam");
    url.searchParams.set("action", action);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== "") {
            url.searchParams.set(key, String(value));
        }
    });

    return performDamRequest(url.toString(), {
        method: "GET",
        headers: {
            "X-API-Key": DAM_API_KEY,
        },
    });
}

async function fetchDamJson(action, body) {
    const url = new URL(resolveDamApiBase() + "/");
    url.searchParams.set("endpoint", "dam");
    url.searchParams.set("action", action);

    return performDamRequest(url.toString(), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-API-Key": DAM_API_KEY,
        },
        body: JSON.stringify(body),
    });
}

async function fetchDamUpload(action, formData) {
    const url = new URL(resolveDamApiBase() + "/");
    url.searchParams.set("endpoint", "dam");
    url.searchParams.set("action", action);

    return performDamRequest(url.toString(), {
        method: "POST",
        headers: {
            "X-API-Key": DAM_API_KEY,
        },
        body: formData,
    });
}

async function performDamRequest(url, options) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));

    if (!response.ok || payload.ok === false) {
        throw new Error(payload?.error?.message || payload?.error || "DAM request failed.");
    }

    return payload;
}

function resolveDamApiBase() {
    if (window.location.protocol === "file:") {
        return "http://localhost/api_nexled/api";
    }

    return new URL("../api", window.location.href).toString().replace(/\/+$/, "");
}

function resolveInitialFolderId(preserveSelection) {
    const flatFolders = flattenFolderTree(damState.tree);

    if (preserveSelection && flatFolders.some((folder) => folder.id === damState.currentFolderId)) {
        return damState.currentFolderId;
    }

    const preferred = flatFolders.find((folder) => folder.id === DAM_DEFAULT_FOLDER_ID);
    return preferred?.id || flatFolders[0]?.id || "";
}

function flattenFolderTree(folders, depth = 0, items = []) {
    folders.forEach((folder) => {
        items.push({
            ...folder,
            depth,
        });

        if (Array.isArray(folder.children) && folder.children.length > 0) {
            flattenFolderTree(folder.children, depth + 1, items);
        }
    });

    return items;
}

function renderFolderTree() {
    if (!damElements) {
        return;
    }

    const flatFolders = flattenFolderTree(damState.tree);
    damElements.folderTree.innerHTML = "";

    if (flatFolders.length === 0) {
        return;
    }

    const fragment = document.createDocumentFragment();

    flatFolders.forEach((folder) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = [
            "btn",
            folder.id === damState.currentFolderId ? "btn-primary" : "btn-secondary",
            "btn-sm",
            "w-full",
            "justify-between",
            getTreeIndentClass(folder.depth),
        ].filter(Boolean).join(" ");
        button.setAttribute("aria-label", t("dam.openFolder", "Open folder") + ": " + folder.name);
        button.addEventListener("click", () => {
            loadDamFolder(folder.id);
        });

        const label = document.createElement("span");
        label.className = "flex min-w-0 items-center gap-8";

        const icon = document.createElement("i");
        icon.className = (folder.id === damState.currentFolderId ? "ri-folder-open-line" : "ri-folder-3-line") + " text-icon-sm";
        icon.setAttribute("aria-hidden", "true");

        const text = document.createElement("span");
        text.className = "truncate";
        text.textContent = folder.name;

        label.appendChild(icon);
        label.appendChild(text);

        const count = document.createElement("span");
        count.className = "text-body-xs";
        count.textContent = String(folder.asset_count || 0);

        button.appendChild(label);
        button.appendChild(count);
        fragment.appendChild(button);
    });

    damElements.folderTree.appendChild(fragment);
    setTreeStatus(buildTreeSummaryText(flatFolders.length));
}

function renderDamList() {
    if (!damElements) {
        return;
    }

    damElements.fileGrid.innerHTML = "";
    const itemCount = damState.folders.length + damState.assets.length;

    if (itemCount === 0) {
        damElements.emptyState.classList.remove("hidden");
        return;
    }

    damElements.emptyState.classList.add("hidden");

    const fragment = document.createDocumentFragment();
    damState.folders.forEach((folder) => {
        fragment.appendChild(createFolderCard(folder));
    });
    damState.assets.forEach((asset) => {
        fragment.appendChild(createAssetCard(asset));
    });

    damElements.fileGrid.appendChild(fragment);
}

function createFolderCard(folder) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "group relative flex w-full flex-col items-center gap-12 rounded-8 p-16";
    button.title = folder.path;
    button.setAttribute("aria-label", t("dam.openFolder", "Open folder") + ": " + folder.name);
    button.addEventListener("click", () => {
        loadDamFolder(folder.id);
    });

    const icon = document.createElement("i");
    icon.className = "ri-folder-3-line text-icon-xxl text-grey-primary";
    icon.setAttribute("aria-hidden", "true");

    const name = document.createElement("span");
    name.className = "text-body-sm text-center leading-tight text-grey-primary break-all";
    name.textContent = folder.name;

    const meta = document.createElement("span");
    meta.className = "text-body-xs text-center text-grey-secondary";
    meta.textContent = buildFolderMeta(folder);

    button.appendChild(icon);
    button.appendChild(name);
    button.appendChild(meta);
    return button;
}

function createAssetCard(asset) {
    const wrapper = document.createElement("div");
    wrapper.className = "group relative flex cursor-pointer flex-col items-center gap-12 rounded-8 p-16";
    wrapper.title = buildAssetTitle(asset);
    wrapper.addEventListener("click", () => {
        selectAssetById(asset.id);
    });

    const icon = document.createElement("i");
    icon.className = getAssetIconClass(asset) + " text-icon-xxl text-grey-primary";
    icon.setAttribute("aria-hidden", "true");

    const name = document.createElement("span");
    name.className = "text-body-sm text-center leading-tight text-grey-primary break-all";
    name.textContent = asset.display_name || asset.filename;

    const overlay = document.createElement("div");
    overlay.className = "pointer-events-none absolute inset-0 flex items-center justify-center gap-12 rounded-8 bg-white/90 opacity-0 transition-opacity group-hover:opacity-100 group-hover:pointer-events-auto";

    overlay.appendChild(createAssetActionButton("ri-download-line", t("dam.assetAction.download", "Download"), () => {
        window.open(asset.secure_url, "_blank", "noopener");
    }));
    overlay.appendChild(createAssetActionButton("ri-external-link-line", t("dam.openInNewTab", "Open in New Tab"), () => {
        window.open(asset.secure_url, "_blank", "noopener");
    }));
    overlay.appendChild(createAssetActionButton("ri-information-line", t("dam.seeDetails", "See Details"), (triggerButton) => {
        openAssetDetailsModal(asset.id, triggerButton);
    }));

    wrapper.appendChild(icon);
    wrapper.appendChild(name);
    wrapper.appendChild(overlay);
    return wrapper;
}

function createAssetActionButton(iconClass, label, handler, disabled = false) {
    const wrapper = document.createElement("span");
    wrapper.className = "tooltip-wrapper group/tip";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-ghost btn-icon btn-xs";
    button.setAttribute("aria-label", label);
    button.addEventListener("click", (event) => {
        event.stopPropagation();

        if (disabled || typeof handler !== "function") {
            return;
        }

        handler(button);
    });

    if (disabled) {
        button.disabled = true;
        button.title = t("shared.actions.comingSoon", "Coming Soon");
    }

    const icon = document.createElement("i");
    icon.className = iconClass + " text-icon-lg";
    icon.setAttribute("aria-hidden", "true");
    button.appendChild(icon);

    const tooltip = document.createElement("span");
    tooltip.className = "pointer-events-none absolute left-1/2 top-full mt-8 -translate-x-1/2 whitespace-nowrap rounded-xs bg-black px-8 py-4 text-body-xs text-white opacity-0 transition-all shadow-btn-default group-hover/tip:opacity-100";
    tooltip.textContent = label;

    wrapper.appendChild(button);
    wrapper.appendChild(tooltip);
    return wrapper;
}

function selectAssetById(assetId) {
    damState.selectedAssetId = assetId;
    damState.selectedAsset = damState.assets.find((asset) => asset.id === assetId) || null;
    renderSelectedAsset();
}

function clearSelectedAsset() {
    damState.selectedAssetId = null;
    damState.selectedAsset = null;
    renderSelectedAsset();
}

function openAssetDetailsModal(assetId, triggerElement = null) {
    if (!damElements) {
        return;
    }

    selectAssetById(assetId);
    damElements.assetModal._lastTrigger = triggerElement || document.activeElement || null;
    damElements.assetModal.inert = false;
    damElements.assetModal.classList.add("is-open");
    damElements.assetModal.setAttribute("aria-hidden", "false");
    syncModalBodyLock();

    window.requestAnimationFrame(() => {
        const initialFocus = damElements.closeAssetModalButton || getFocusableElements(damElements.assetModalPanel)[0] || damElements.assetModalPanel;
        initialFocus?.focus({ preventScroll: true });
    });
}

function closeAssetDetailsModal(restoreFocus) {
    if (!damElements) {
        return;
    }

    if (!damElements.assetModal.classList.contains("is-open")) {
        return;
    }

    const lastTrigger = damElements.assetModal._lastTrigger || null;
    damElements.assetModal.classList.remove("is-open");
    damElements.assetModal.setAttribute("aria-hidden", "true");
    damElements.assetModal.inert = true;
    syncModalBodyLock();

    if (restoreFocus && lastTrigger && typeof lastTrigger.focus === "function") {
        lastTrigger.focus({ preventScroll: true });
    }
}

function isAssetModalOpen() {
    return Boolean(damElements?.assetModal?.classList.contains("is-open"));
}

function syncModalBodyLock() {
    document.body.classList.toggle("modal-open", Boolean(document.querySelector(".modal-overlay.is-open")));
}

function trapAssetModalFocus(event) {
    if (!damElements) {
        return;
    }

    const focusable = getFocusableElements(damElements.assetModalPanel);

    if (focusable.length === 0) {
        event.preventDefault();
        damElements.assetModalPanel.focus({ preventScroll: true });
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus({ preventScroll: true });
        return;
    }

    if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus({ preventScroll: true });
    }
}

function getFocusableElements(root) {
    if (!root) {
        return [];
    }

    return Array.from(root.querySelectorAll("a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex='-1'])"))
        .filter((element) => !element.hasAttribute("inert") && !element.closest("[inert]") && !element.hidden && element.getAttribute("aria-hidden") !== "true");
}

function renderSelectedAsset() {
    if (!damElements) {
        return;
    }

    const asset = damState.selectedAsset;

    damElements.assetPreview.innerHTML = "";

    if (!asset) {
        damElements.assetPreview.appendChild(damElements.emptyAsset);
        damElements.assetName.textContent = "-";
        damElements.assetType.textContent = "-";
        damElements.assetSize.textContent = "-";
        damElements.assetFormat.textContent = "-";
        damElements.assetFolder.textContent = "-";
        damElements.openAssetButton.disabled = true;
        damElements.copyAssetUrlButton.disabled = true;
        setAssetStatus("");
        return;
    }

    if (asset.resource_type === "image" && asset.secure_url) {
        const image = document.createElement("img");
        image.src = asset.secure_url;
        image.alt = asset.display_name || asset.filename || "Asset preview";
        image.className = "max-h-160 w-full rounded-8 object-contain";
        damElements.assetPreview.appendChild(image);
    } else {
        const fallback = document.createElement("div");
        fallback.className = "flex flex-col items-center gap-12 text-center";

        const icon = document.createElement("i");
        icon.className = getAssetIconClass(asset) + " text-icon-xxl text-grey-primary";
        icon.setAttribute("aria-hidden", "true");

        const text = document.createElement("span");
        text.className = "text-body-sm text-grey-primary";
        text.textContent = asset.display_name || asset.filename || "-";

        fallback.appendChild(icon);
        fallback.appendChild(text);
        damElements.assetPreview.appendChild(fallback);
    }

    damElements.assetName.textContent = asset.display_name || asset.filename || "-";
    damElements.assetType.textContent = asset.resource_type || "-";
    damElements.assetSize.textContent = formatBytes(asset.bytes) || "-";
    damElements.assetFormat.textContent = String(asset.format || "-").toUpperCase();
    damElements.assetFolder.textContent = asset.asset_folder || "-";
    damElements.openAssetButton.disabled = !asset.secure_url;
    damElements.copyAssetUrlButton.disabled = !asset.secure_url;
    setAssetStatus("");
}

function updateFolderSummary() {
    if (!damElements) {
        return;
    }

    const folderName = damState.currentFolder?.name || damState.currentFolderId || "nexled";
    const folderPath = damState.currentFolder?.path || damState.currentFolderId || "nexled";

    damElements.currentFolder.textContent = folderName;
    damElements.currentPath.textContent = folderPath;
    damElements.selectedFolder.textContent = folderPath;
}

function syncFolderActionButtons() {
    if (!damElements) {
        return;
    }

    const canCreateChildren = Boolean(damState.currentFolder?.can_create_children);
    const canUpload = Boolean(damState.currentFolder?.can_upload);

    damElements.createFolderButton.disabled = !canCreateChildren;
    damElements.uploadTrigger.disabled = !canUpload;

    if (!canCreateChildren) {
        damElements.createFolderButton.title = t("dam.folderActionBlocked", "Selected folder cannot create child folders.");
    } else {
        damElements.createFolderButton.removeAttribute("title");
    }

    if (!canUpload) {
        damElements.uploadTrigger.title = t("dam.uploadActionBlocked", "Selected folder does not allow uploads.");
    } else {
        damElements.uploadTrigger.removeAttribute("title");
    }
}

function setTreeStatus(message) {
    if (damElements) {
        damElements.treeStatus.textContent = message;
    }
}

function setListStatus(message) {
    if (damElements) {
        damElements.listStatus.textContent = message;
    }
}

function setFolderActionStatus(message) {
    if (damElements) {
        damElements.folderActionStatus.textContent = message;
    }
}

function setUploadStatus(message) {
    if (damElements) {
        damElements.uploadStatus.textContent = message;
    }
}

function setAssetStatus(message) {
    if (damElements) {
        damElements.assetStatus.textContent = message;
    }
}

function getDamErrorMessage(error, fallback) {
    if (error instanceof Error && error.message.trim() !== "") {
        return error.message;
    }

    return fallback;
}

function buildTreeSummaryText(folderCount) {
    return t("dam.foldersLabel", "{count} folders", { count: folderCount });
}

function buildListSummaryText() {
    const folderText = t("dam.foldersLabel", "{count} folders", { count: damState.folders.length });
    const assetText = t("dam.assetsLabel", "{count} assets", { count: damState.assets.length });
    return folderText + " . " + assetText;
}

function buildFolderMeta(folder) {
    const folderText = t("dam.foldersLabel", "{count} folders", { count: folder.folder_count || 0 });
    const assetText = t("dam.assetsLabel", "{count} assets", { count: folder.asset_count || 0 });
    return folderText + " . " + assetText;
}

function buildAssetTitle(asset) {
    const name = asset.display_name || asset.filename || "";
    const size = formatBytes(asset.bytes || 0);
    return size ? name + "\n" + size : name;
}

function getTreeIndentClass(depth) {
    if (depth <= 0) {
        return "";
    }

    if (depth === 1) {
        return "pl-20";
    }

    if (depth === 2) {
        return "pl-32";
    }

    return "pl-40";
}

function getAssetIconClass(asset) {
    const format = String(asset.format || "").toLowerCase();
    const resourceType = String(asset.resource_type || "").toLowerCase();

    if (resourceType === "video") {
        return "ri-video-line";
    }

    if (format === "pdf") {
        return "ri-file-pdf-2-line";
    }

    if (["zip", "rar", "7z"].includes(format)) {
        return "ri-file-zip-line";
    }

    if (["doc", "docx"].includes(format)) {
        return "ri-file-word-line";
    }

    if (["xml", "json", "csv", "txt"].includes(format)) {
        return "ri-file-code-line";
    }

    if (resourceType === "image") {
        return "ri-image-line";
    }

    return "ri-file-line";
}

function formatBytes(bytes) {
    const value = Number(bytes || 0);

    if (!value) {
        return "";
    }

    const units = ["B", "KB", "MB", "GB"];
    let unitIndex = 0;
    let size = value;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return (unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)) + " " + units[unitIndex];
}

function t(key, fallback, variables = {}) {
    if (window.NexLedI18n?.t) {
        return window.NexLedI18n.t(key, variables, fallback);
    }

    return interpolateFallback(fallback, variables);
}

function interpolateFallback(template, variables = {}) {
    return String(template || "").replace(/\{(\w+)\}/g, (_, token) => {
        return token in variables ? String(variables[token]) : "";
    });
}
