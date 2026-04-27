const DAM_API_KEY = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
const DAM_I18N_EVENT = "nexled:i18n-applied";
const DAM_ROOT_FOLDER_ID = "nexled";
const DAM_DEFAULT_FOLDER_ID = "nexled/datasheet";
const DAM_ROLE_OPTIONS = ["packshot", "finish", "drawing", "diagram", "diagram-inv", "mounting", "connector", "temperature", "energy-label", "icon", "logo", "power-supply", "product-photo", "lifestyle", "datasheet-pdf", "eprel-label", "eprel-fiche", "brand-logo", "brand-asset", "hero", "banner", "category", "support-asset", "web-asset"];
const DAM_ASSET_MODAL_LINKING_ENABLED = false;
const DAM_GRID_THUMB_TRANSFORM = "c_fit,w_240,h_240,dpr_auto,f_auto,q_auto";
const DAM_GRID_PDF_FETCH_TRANSFORM = "c_fit,w_240,h_240,f_auto,q_auto";
const DAM_TOAST_BASE_CLASS = "toast toast-sm";
const DAM_TOAST_HIDE_DELAY = 320;
const DAM_TOAST_AUTOHIDE_DELAY = 3200;
const DAM_TOAST_VARIANT = {
    success: { className: "toast-success", iconClass: "ri-checkbox-circle-line", role: "status", titleKey: "shared.toast.successTitle", titleFallback: "Success" },
    error: { className: "toast-danger", iconClass: "ri-close-circle-line", role: "alert", titleKey: "shared.toast.errorTitle", titleFallback: "Error" },
    info: { className: "toast-info", iconClass: "ri-information-line", role: "status", titleKey: "shared.toast.infoTitle", titleFallback: "Info" },
};

const damState = {
    tree: [],
    currentFolderId: "",
    currentFolder: null,
    folders: [],
    assets: [],
    selectedAssetId: null,
    selectedAsset: null,
    selectedAssetLinks: [],
    selectedAssetLinksLoading: false,
    selectedAssetLinkActionBusy: false,
    assetLinkPanelHidden: true,
    searchQuery: "",
    createFolderParentId: "",
    treeErrorMessage: "",
    folderErrorMessage: "",
};

let treeRequestToken = 0;
let listRequestToken = 0;
let assetDetailsRequestToken = 0;
let searchTimer = 0;
let damElements = null;
let damToastTimer = 0;
let damToastHideTimer = 0;

document.addEventListener("DOMContentLoaded", () => {
    damElements = getDamElements();

    if (!damElements) {
        return;
    }

    renderLinkRoleOptions();
    bindDamEvents();
    loadDamTree();
});

window.addEventListener(DAM_I18N_EVENT, () => {
    if (!damElements) {
        return;
    }

    renderRootDropdown();
    renderCreateFolderParentDropdown();
    renderLinkRoleOptions();
    renderDamList();
    updateFolderSummary();
    renderSelectedAsset();
    syncFolderActionButtons();
});

function getDamElements() {
    const fileGrid = document.getElementById("fileGrid");
    const emptyState = document.getElementById("emptyState");
    const emptyStateLabel = document.querySelector("[data-dam-empty-state-label]");
    const searchInput = document.getElementById("searchInput");
    const breadcrumb = document.querySelector("[data-dam-breadcrumb]");
    const rootDropdown = document.querySelector("[data-dam-root-dropdown]");
    const rootValue = document.querySelector("[data-dam-root-value]");
    const rootMenu = document.querySelector("[data-dam-root-menu]");
    const refreshTreeButton = document.querySelector("[data-dam-refresh-tree]");
    const openCreateFolderButton = document.querySelector("[data-dam-open-create-folder]");
    const createFolderModal = document.querySelector("[data-dam-create-folder-modal]");
    const createFolderInput = document.querySelector("[data-dam-create-folder-name]");
    const createFolderParentDropdown = document.querySelector("[data-dam-create-parent-dropdown]");
    const createFolderParentValue = document.querySelector("[data-dam-create-parent-value]");
    const createFolderParentMenu = document.querySelector("[data-dam-create-parent-menu]");
    const createFolderButton = document.querySelector("[data-dam-submit-create-folder]");
    const folderActionStatus = document.querySelector("[data-dam-folder-action-status]");
    const uploadTrigger = document.querySelector("[data-dam-upload-trigger]");
    const uploadInput = document.querySelector("[data-dam-upload-input]");
    const uploadStatus = document.querySelector("[data-dam-upload-status]");
    const assetModal = document.querySelector("[data-dam-asset-modal]");
    const assetModalPanel = document.querySelector("[data-dam-asset-modal-panel]");
    const assetModalTitle = document.querySelector("[data-dam-asset-modal-title]");
    const closeAssetModalButton = document.querySelector("[data-dam-close-asset-modal]");
    const assetPreview = document.querySelector("[data-dam-asset-preview]");
    const emptyAsset = document.querySelector("[data-dam-empty-asset]");
    const assetMetaList = document.querySelector("[data-dam-asset-meta-list]");
    const assetSize = document.querySelector("[data-dam-asset-size]");
    const assetFormat = document.querySelector("[data-dam-asset-format]");
    const assetFolder = document.querySelector("[data-dam-asset-folder]");
    const linkFamilyCodeInput = document.querySelector("[data-dam-link-family-code]");
    const linkProductCodeInput = document.querySelector("[data-dam-link-product-code]");
    const linkRoleSelect = document.querySelector("[data-dam-link-role]");
    const linkSortOrderInput = document.querySelector("[data-dam-link-sort-order]");
    const linkSubmitButton = document.querySelector("[data-dam-submit-link]");
    const linksList = document.querySelector("[data-dam-links-list]");
    const emptyLinks = document.querySelector("[data-dam-empty-links]");
    const openAssetButton = document.querySelector("[data-dam-open-asset]");
    const copyAssetUrlButton = document.querySelector("[data-dam-copy-asset-url]");
    const toggleLinkingButton = document.querySelector("[data-dam-toggle-linking]");
    const toggleLinkingIcon = document.querySelector("[data-dam-toggle-linking-icon]");
    const toggleLinkingLabel = document.querySelector("[data-dam-toggle-linking-label]");
    const linkingPanel = document.querySelector("[data-dam-linking-panel]");
    const assetStatus = document.querySelector("[data-dam-asset-status]");

    if (!fileGrid || !emptyState || !emptyStateLabel || !searchInput || !breadcrumb || !rootDropdown || !rootValue || !rootMenu || !refreshTreeButton || !openCreateFolderButton || !createFolderModal || !createFolderInput || !createFolderParentDropdown || !createFolderParentValue || !createFolderParentMenu || !createFolderButton || !folderActionStatus || !uploadTrigger || !uploadInput || !uploadStatus || !assetModal || !assetModalPanel || !assetModalTitle || !closeAssetModalButton || !assetPreview || !emptyAsset || !assetMetaList || !assetSize || !assetFormat || !assetFolder || !linkFamilyCodeInput || !linkProductCodeInput || !linkRoleSelect || !linkSortOrderInput || !linkSubmitButton || !linksList || !emptyLinks || !openAssetButton || !copyAssetUrlButton || !toggleLinkingButton || !toggleLinkingIcon || !toggleLinkingLabel || !linkingPanel || !assetStatus) {
        return null;
    }

    return {
        fileGrid,
        emptyState,
        emptyStateLabel,
        searchInput,
        breadcrumb,
        rootDropdown,
        rootValue,
        rootMenu,
        refreshTreeButton,
        openCreateFolderButton,
        createFolderModal,
        createFolderInput,
        createFolderParentDropdown,
        createFolderParentValue,
        createFolderParentMenu,
        createFolderButton,
        folderActionStatus,
        uploadTrigger,
        uploadInput,
        uploadStatus,
        assetModal,
        assetModalPanel,
        assetModalTitle,
        closeAssetModalButton,
        assetPreview,
        emptyAsset,
        assetMetaList,
        assetSize,
        assetFormat,
        assetFolder,
        linkFamilyCodeInput,
        linkProductCodeInput,
        linkRoleSelect,
        linkSortOrderInput,
        linkSubmitButton,
        linksList,
        emptyLinks,
        openAssetButton,
        copyAssetUrlButton,
        toggleLinkingButton,
        toggleLinkingIcon,
        toggleLinkingLabel,
        linkingPanel,
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

    damElements.openCreateFolderButton.addEventListener("click", () => {
        prepareCreateFolderModal();
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
            setAssetStatus("");
            showDamToast(t("dam.copyAssetUrlDone", "Asset URL copied."), "success");
        } catch (error) {
            console.error(error);
            setAssetStatus("");
            showDamToast(t("dam.copyAssetUrlFailed", "Unable to copy asset URL."), "error");
        }
    });

    if (DAM_ASSET_MODAL_LINKING_ENABLED) {
        damElements.toggleLinkingButton.addEventListener("click", () => {
            if (!damState.selectedAsset?.id) {
                return;
            }

            window.requestAnimationFrame(() => {
                const isPressed = damElements.toggleLinkingButton.getAttribute("aria-pressed") === "true";
                damState.assetLinkPanelHidden = !isPressed;
                syncAssetLinkPanelVisibility({ syncPressed: false });
            });
        });

        damElements.linkSubmitButton.addEventListener("click", handleCreateAssetLink);
        [damElements.linkFamilyCodeInput, damElements.linkProductCodeInput, damElements.linkSortOrderInput].forEach((input) => {
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    handleCreateAssetLink();
                }
            });
        });
    }

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

    try {
        const response = await fetchDamGet("tree", {
            depth: "3",
        });

        if (requestToken !== treeRequestToken) {
            return;
        }

        damState.tree = Array.isArray(response?.data?.folders) ? response.data.folders : [];
        damState.treeErrorMessage = "";
        damState.folderErrorMessage = "";
        renderRootDropdown();

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
        setFolderActionStatus("");
    } catch (error) {
        console.error(error);

        if (requestToken !== treeRequestToken) {
            return;
        }

        const message = getDamErrorMessage(error, t("dam.treeLoadFailed", "Unable to load folders."));
        damState.tree = [];
        damState.currentFolderId = "";
        damState.currentFolder = null;
        damState.folders = [];
        damState.assets = [];
        damState.treeErrorMessage = message;
        damState.folderErrorMessage = message;
        clearSelectedAsset();
        renderRootDropdown();
        renderDamList();
        updateFolderSummary();
        syncFolderActionButtons();
        setFolderActionStatus(message);
    }
}

async function loadDamFolder(folderId) {
    const requestToken = ++listRequestToken;
    closeAssetDetailsModal(false);
    damState.currentFolderId = folderId;
    damState.selectedAssetId = null;
    damState.selectedAsset = null;
    damState.selectedAssetLinks = [];
    damState.selectedAssetLinksLoading = false;
    damState.selectedAssetLinkActionBusy = false;
    updateFolderSummary();
    renderRootDropdown();
    renderSelectedAsset();
    syncFolderActionButtons();

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
        damState.folderErrorMessage = "";
        updateFolderSummary();
        renderRootDropdown();
        renderDamList();
        syncFolderActionButtons();
        setFolderActionStatus("");
    } catch (error) {
        console.error(error);

        if (requestToken !== listRequestToken) {
            return;
        }

        const message = getDamErrorMessage(error, t("dam.folderLoadFailed", "Unable to load folder contents."));
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
        damState.folderErrorMessage = message;
        updateFolderSummary();
        renderRootDropdown();
        renderDamList();
        syncFolderActionButtons();
        setFolderActionStatus(message);
    }
}

async function handleCreateFolder() {
    const creatableFolders = getCreatableFolders();

    if (creatableFolders.length === 0) {
        setFolderActionStatus(t("dam.folderActionBlocked", "No folder destinations available."));
        return;
    }

    const parentId = resolveCreateFolderParentId(creatableFolders);
    const parentFolder = creatableFolders.find((folder) => folder.id === parentId) || null;
    const name = String(damElements.createFolderInput.value || "").trim();

    if (!parentFolder) {
        setFolderActionStatus(t("dam.folderDestinationRequired", "Select folder destination."));
        return;
    }

    if (!name) {
        setFolderActionStatus(t("dam.folderNameRequired", "Enter folder name."));
        return;
    }

    setFolderActionStatus(t("dam.folderCreating", "Creating folder..."));

    try {
        const response = await fetchDamJson("create-folder", {
            parent_id: parentFolder.id,
            name,
        });
        const folder = response?.data?.folder || null;

        damElements.createFolderInput.value = "";
        setFolderActionStatus("");
        await loadDamTree(false);

        if (folder?.id) {
            await loadDamFolder(folder.id);
        }

        closeCreateFolderModal(true);
    } catch (error) {
        console.error(error);
        setFolderActionStatus(getDamErrorMessage(error, t("dam.folderCreateFailed", "Unable to create folder.")));
    }
}

function prepareCreateFolderModal() {
    const creatableFolders = getCreatableFolders();
    damState.createFolderParentId = resolveCreateFolderParentId(creatableFolders);
    damElements.createFolderInput.value = "";
    setFolderActionStatus("");
    renderCreateFolderParentDropdown();
}

function getCreatableFolders() {
    return flattenFolderTree(damState.tree).filter((folder) => folder.can_create_children);
}

function resolveCreateFolderParentId(creatableFolders = getCreatableFolders()) {
    if (creatableFolders.length === 0) {
        return "";
    }

    if (damState.createFolderParentId && creatableFolders.some((folder) => folder.id === damState.createFolderParentId)) {
        return damState.createFolderParentId;
    }

    const currentFolderId = damState.currentFolder?.id || damState.currentFolderId || "";

    if (currentFolderId && creatableFolders.some((folder) => folder.id === currentFolderId)) {
        return currentFolderId;
    }

    const activeRootId = resolveActiveRootFolder()?.id || "";
    const activeRoot = creatableFolders.find((folder) => folder.id === activeRootId);

    return activeRoot?.id || creatableFolders[0]?.id || "";
}

function renderCreateFolderParentDropdown() {
    if (!damElements) {
        return;
    }

    const creatableFolders = getCreatableFolders();
    const selectedParentId = resolveCreateFolderParentId(creatableFolders);
    const selectedParent = creatableFolders.find((folder) => folder.id === selectedParentId) || null;

    damState.createFolderParentId = selectedParentId;
    damElements.createFolderParentMenu.innerHTML = "";

    if (creatableFolders.length === 0) {
        damElements.createFolderParentValue.textContent = t("dam.folderActionBlocked", "No folder destinations available.");
        damElements.createFolderParentDropdown.classList.remove("has-value");
        return;
    }

    const fragment = document.createDocumentFragment();

    creatableFolders.forEach((folder) => {
        const item = document.createElement("li");
        item.className = "dropdown-item";
        item.setAttribute("role", "option");
        item.setAttribute("aria-selected", String(selectedParentId === folder.id));
        item.dataset.value = folder.id;
        item.tabIndex = 0;

        const label = document.createElement("span");
        label.textContent = folder.path || folder.name;

        const check = document.createElement("i");
        check.className = "ri-check-line dropdown-item-check";
        check.setAttribute("aria-hidden", "true");

        item.appendChild(label);
        item.appendChild(check);
        item.addEventListener("click", () => {
            handleCreateFolderParentSelect(folder);
        });
        item.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                handleCreateFolderParentSelect(folder);
            }
        });
        fragment.appendChild(item);
    });

    damElements.createFolderParentMenu.appendChild(fragment);
    damElements.createFolderParentValue.textContent = selectedParent?.path || t("dam.createFolderDestinationPlaceholder", "Select destination");
    damElements.createFolderParentDropdown.classList.add("has-value");
}

function handleCreateFolderParentSelect(folder) {
    if (!folder || !damElements) {
        return;
    }

    damState.createFolderParentId = folder.id;
    damElements.createFolderParentValue.textContent = folder.path || folder.name;
    syncCreateFolderParentSelection(folder.id);
    closeCreateFolderParentDropdown();
}

function syncCreateFolderParentSelection(folderId) {
    if (!damElements) {
        return;
    }

    damElements.createFolderParentMenu.querySelectorAll(".dropdown-item").forEach((item) => {
        item.setAttribute("aria-selected", String(item.dataset.value === folderId));
    });
}

function closeCreateFolderParentDropdown() {
    if (!damElements) {
        return;
    }

    damElements.createFolderParentDropdown.classList.remove("is-open");
    damElements.createFolderParentDropdown.querySelector(".dropdown-trigger")?.setAttribute("aria-expanded", "false");
}

function closeCreateFolderModal(restoreFocus) {
    if (!damElements?.createFolderModal) {
        return;
    }

    const overlay = damElements.createFolderModal;
    overlay.classList.remove("is-open", "is-visible");
    overlay.setAttribute("aria-hidden", "true");
    overlay.inert = true;
    damElements.openCreateFolderButton?.setAttribute("aria-expanded", "false");
    syncModalBodyLock();

    if (restoreFocus && overlay._lastTrigger && typeof overlay._lastTrigger.focus === "function") {
        overlay._lastTrigger.focus({ preventScroll: true });
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

async function fetchDamDelete(action, params = {}) {
    const url = new URL(resolveDamApiBase() + "/");
    url.searchParams.set("endpoint", "dam");
    url.searchParams.set("action", action);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== "") {
            url.searchParams.set(key, String(value));
        }
    });

    return performDamRequest(url.toString(), {
        method: "DELETE",
        headers: {
            "X-API-Key": DAM_API_KEY,
        },
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

function renderLinkRoleOptions() {
    if (!damElements) {
        return;
    }

    const currentValue = damElements.linkRoleSelect.value;
    damElements.linkRoleSelect.innerHTML = "";

    DAM_ROLE_OPTIONS.forEach((role) => {
        const option = document.createElement("option");
        option.value = role;
        option.textContent = formatDamRoleLabel(role);
        damElements.linkRoleSelect.appendChild(option);
    });

    if (DAM_ROLE_OPTIONS.includes(currentValue)) {
        damElements.linkRoleSelect.value = currentValue;
    }
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

function renderRootDropdown() {
    if (!damElements) {
        return;
    }

    const rootFolders = getRootFolders();
    damElements.rootMenu.innerHTML = "";

    if (rootFolders.length === 0) {
        damElements.rootValue.textContent = damState.treeErrorMessage || t("dam.loadingFolders", "Loading folders...");
        damElements.rootDropdown.classList.remove("has-value");
        return;
    }

    const activeRoot = resolveActiveRootFolder();
    const fragment = document.createDocumentFragment();

    rootFolders.forEach((folder) => {
        const item = document.createElement("li");
        item.className = "dropdown-item";
        item.setAttribute("role", "option");
        item.setAttribute("aria-selected", String(activeRoot?.id === folder.id));
        item.dataset.value = folder.id;
        item.tabIndex = 0;

        const label = document.createElement("span");
        label.textContent = formatRootFolderLabel(folder);

        const check = document.createElement("i");
        check.className = "ri-check-line dropdown-item-check";
        check.setAttribute("aria-hidden", "true");

        item.appendChild(label);
        item.appendChild(check);
        item.addEventListener("click", () => {
            handleRootDropdownSelect(folder);
        });
        item.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                handleRootDropdownSelect(folder);
            }
        });
        fragment.appendChild(item);
    });

    damElements.rootMenu.appendChild(fragment);
    damElements.rootValue.textContent = formatRootFolderLabel(activeRoot || rootFolders[0]);
    damElements.rootDropdown.classList.add("has-value");
}

function handleRootDropdownSelect(folder) {
    if (!folder || !damElements) {
        return;
    }

    syncRootDropdownSelection(folder.id);
    damElements.rootValue.textContent = formatRootFolderLabel(folder);
    closeRootDropdown();
    loadDamFolder(folder.id);
}

function renderDamList() {
    if (!damElements) {
        return;
    }

    clearDamCardHeights();

    damElements.fileGrid.innerHTML = "";
    const itemCount = damState.folders.length + damState.assets.length;
    const emptyMessage = damState.folderErrorMessage || damState.treeErrorMessage || t("dam.emptyFolder", "This folder is empty");
    damElements.emptyStateLabel.textContent = emptyMessage;

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

function clearDamCardHeights() {
    document.querySelector("[data-dam-main-card]")?.style.removeProperty("min-height");
    document.querySelector("[data-dam-side-card]")?.style.removeProperty("min-height");
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

    button.appendChild(icon);
    button.appendChild(name);
    return button;
}

function createAssetCard(asset) {
    const wrapper = document.createElement("div");
    wrapper.className = "group relative flex cursor-pointer flex-col items-center gap-12 rounded-8 p-16";
    wrapper.title = buildAssetTitle(asset);
    wrapper.addEventListener("click", () => {
        selectAssetById(asset.id);
    });

    const preview = createAssetGridPreview(asset);

    const name = document.createElement("span");
    name.className = "text-body-sm text-center leading-tight text-grey-primary break-all";
    name.textContent = asset.display_name || asset.filename;

    const overlay = document.createElement("div");
    overlay.className = "pointer-events-none absolute inset-0 flex items-center justify-center gap-12 rounded-lg bg-white/90 opacity-0 transition-opacity group-hover:opacity-100 group-hover:pointer-events-auto";

    overlay.appendChild(createAssetActionButton("ri-download-line", t("dam.assetAction.download", "Download"), () => {
        void handleAssetDownload(asset);
    }));
    overlay.appendChild(createAssetActionButton("ri-external-link-line", t("dam.openInNewTab", "Open in New Tab"), () => {
        window.open(asset.secure_url, "_blank", "noopener");
    }));
    overlay.appendChild(createAssetActionButton("ri-information-line", t("dam.seeDetails", "See Details"), (triggerButton) => {
        openAssetDetailsModal(asset.id, triggerButton);
    }));

    preview.appendChild(overlay);
    wrapper.appendChild(preview);
    wrapper.appendChild(name);
    return wrapper;
}

function createAssetGridPreview(asset) {
    const preview = document.createElement("div");
    preview.className = "relative flex w-full items-center justify-center overflow-visible rounded-lg";
    preview.style.aspectRatio = "1 / 1";
    preview.style.minHeight = "120px";

    const fallback = createAssetGridPreviewFallback(asset);
    preview.appendChild(fallback);

    const thumbnailUrl = resolveAssetThumbnailUrl(asset);
    if (!thumbnailUrl) {
        return preview;
    }

    fallback.classList.add("hidden");

    const image = document.createElement("img");
    image.src = thumbnailUrl;
    image.alt = asset.display_name || asset.filename || "Asset thumbnail";
    image.className = "block h-full w-full rounded-lg border border-grey-secondary bg-grey-secondary object-cover object-center";
    image.loading = "lazy";
    image.decoding = "async";
    image.fetchPriority = "low";
    image.width = 240;
    image.height = 240;

    image.addEventListener("load", () => {
        fallback.classList.add("hidden");
    });

    image.addEventListener("error", () => {
        image.remove();
        fallback.classList.remove("hidden");
    });

    preview.appendChild(image);
    return preview;
}

function createAssetGridPreviewFallback(asset) {
    const fallback = document.createElement("div");
    fallback.className = "flex h-full w-full items-center justify-center rounded-lg border border-grey-secondary bg-grey-secondary";

    const icon = document.createElement("i");
    icon.className = getAssetIconClass(asset) + " text-icon-xxl text-grey-primary";
    icon.setAttribute("aria-hidden", "true");

    fallback.appendChild(icon);
    return fallback;
}

function createAssetActionButton(iconClass, label, handler, disabled = false) {
    const wrapper = document.createElement("span");
    wrapper.className = "tooltip-wrapper group/tip relative z-0";

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

    const showTooltipLayer = () => {
        wrapper.style.zIndex = "var(--z-tooltip)";
        tooltip.style.zIndex = "var(--z-tooltip)";
    };

    const hideTooltipLayer = () => {
        wrapper.style.zIndex = "";
        tooltip.style.zIndex = "";
    };

    wrapper.addEventListener("mouseenter", showTooltipLayer);
    wrapper.addEventListener("mouseleave", hideTooltipLayer);
    wrapper.addEventListener("focusin", showTooltipLayer);
    wrapper.addEventListener("focusout", (event) => {
        if (wrapper.contains(event.relatedTarget)) {
            return;
        }

        hideTooltipLayer();
    });

    wrapper.appendChild(button);
    wrapper.appendChild(tooltip);
    return wrapper;
}

async function handleAssetDownload(asset) {
    const secureUrl = normalizeDamAssetUrl(asset?.secure_url);
    const downloadName = resolveAssetDownloadFilename(asset);

    if (!secureUrl) {
        return;
    }

    try {
        const response = await fetch(secureUrl, {
            method: "GET",
            mode: "cors",
            credentials: "omit",
        });

        if (!response.ok) {
            throw new Error("Asset download failed.");
        }

        const blob = await response.blob();
        triggerAssetDownload(URL.createObjectURL(blob), downloadName);
        return;
    } catch (error) {
        console.error(error);
    }

    triggerAssetDownload(buildCloudinaryDownloadUrl(secureUrl, downloadName), downloadName);
}

function triggerAssetDownload(url, filename) {
    const link = document.createElement("a");
    link.href = url;
    link.rel = "noopener";
    link.download = filename || "asset";
    link.style.display = "none";

    document.body.appendChild(link);
    link.click();
    link.remove();

    if (typeof url === "string" && url.startsWith("blob:")) {
        window.setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 1000);
    }
}

function resolveAssetDownloadFilename(asset) {
    const displayName = String(asset?.display_name || asset?.filename || "").trim();
    return displayName !== "" ? displayName : "asset";
}

function selectAssetById(assetId) {
    damState.selectedAssetId = assetId;
    damState.selectedAsset = damState.assets.find((asset) => asset.id === assetId) || null;
    damState.selectedAssetLinks = [];
    damState.selectedAssetLinksLoading = false;
    damState.selectedAssetLinkActionBusy = false;
    damState.assetLinkPanelHidden = true;
    resetAssetLinkForm();
    if (damState.selectedAsset && damElements) {
        damElements.linkRoleSelect.value = getDefaultAssetLinkRole(damState.selectedAsset);
    }
    renderSelectedAsset();
}

function clearSelectedAsset() {
    damState.selectedAssetId = null;
    damState.selectedAsset = null;
    damState.selectedAssetLinks = [];
    damState.selectedAssetLinksLoading = false;
    damState.selectedAssetLinkActionBusy = false;
    damState.assetLinkPanelHidden = true;
    resetAssetLinkForm();
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

    loadSelectedAssetDetails(assetId);
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

async function loadSelectedAssetDetails(assetId) {
    const requestToken = ++assetDetailsRequestToken;
    damState.selectedAssetLinksLoading = true;
    renderSelectedAsset();

    try {
        const response = await fetchDamGet("asset", {
            id: assetId,
        });

        if (requestToken !== assetDetailsRequestToken || damState.selectedAssetId !== assetId) {
            return;
        }

        damState.selectedAsset = response?.data?.asset || damState.selectedAsset;
        damState.selectedAssetLinks = Array.isArray(response?.data?.links) ? response.data.links : [];
        damState.selectedAssetLinksLoading = false;
        renderSelectedAsset();
    } catch (error) {
        console.error(error);

        if (requestToken !== assetDetailsRequestToken || damState.selectedAssetId !== assetId) {
            return;
        }

        damState.selectedAssetLinks = [];
        damState.selectedAssetLinksLoading = false;
        renderSelectedAsset();
        setAssetStatus(getDamErrorMessage(error, t("dam.assetDetailsLoadFailed", "Unable to load asset details.")));
    }
}

async function handleCreateAssetLink() {
    const asset = damState.selectedAsset;

    if (!asset?.id || !damElements) {
        return;
    }

    const familyCode = String(damElements.linkFamilyCodeInput.value || "").trim();
    const productCode = String(damElements.linkProductCodeInput.value || "").trim();
    const role = String(damElements.linkRoleSelect.value || "").trim();
    const sortOrderValue = String(damElements.linkSortOrderInput.value || "").trim();
    const parsedSortOrder = Number.parseInt(sortOrderValue, 10);
    const sortOrder = Number.isFinite(parsedSortOrder) ? parsedSortOrder : 0;

    if (!familyCode && !productCode) {
        setAssetStatus(t("dam.linkTargetRequired", "Provide family code or product code."));
        return;
    }

    if (!role) {
        setAssetStatus(t("dam.linkRoleRequired", "Select link role."));
        return;
    }

    damState.selectedAssetLinkActionBusy = true;
    syncAssetLinkControls();
    setAssetStatus(t("dam.linkSaving", "Saving link..."));

    try {
        await fetchDamJson("link", {
            asset_id: asset.id,
            family_code: familyCode || null,
            product_code: productCode || null,
            role,
            sort_order: sortOrder,
        });
        await loadSelectedAssetDetails(asset.id);
        damElements.linkFamilyCodeInput.value = "";
        damElements.linkProductCodeInput.value = "";
        damElements.linkSortOrderInput.value = "0";
        setAssetStatus(t("dam.linkSaved", "Asset linked."));
    } catch (error) {
        console.error(error);
        setAssetStatus(getDamErrorMessage(error, t("dam.linkFailed", "Unable to link asset.")));
    } finally {
        damState.selectedAssetLinkActionBusy = false;
        syncAssetLinkControls();
    }
}

async function handleDeleteAssetLink(linkId) {
    const asset = damState.selectedAsset;

    if (!asset?.id || !linkId) {
        return;
    }

    damState.selectedAssetLinkActionBusy = true;
    syncAssetLinkControls();
    setAssetStatus(t("dam.unlinkStarted", "Removing link..."));

    try {
        await fetchDamDelete("unlink", {
            id: linkId,
        });
        await loadSelectedAssetDetails(asset.id);
        setAssetStatus(t("dam.unlinkDone", "Link removed."));
    } catch (error) {
        console.error(error);
        setAssetStatus(getDamErrorMessage(error, t("dam.unlinkFailed", "Unable to remove link.")));
    } finally {
        damState.selectedAssetLinkActionBusy = false;
        syncAssetLinkControls();
    }
}

function renderSelectedAsset() {
    if (!damElements) {
        return;
    }

    const asset = damState.selectedAsset;

    damElements.assetPreview.innerHTML = "";

    if (!asset) {
        damElements.assetPreview.appendChild(damElements.emptyAsset);
        damElements.assetModalTitle.textContent = t("dam.assetDetails", "Asset Details");
        damElements.assetSize.textContent = "-";
        damElements.assetFormat.textContent = "-";
        damElements.assetFolder.textContent = "-";
        damElements.openAssetButton.disabled = true;
        damElements.copyAssetUrlButton.disabled = true;
        resetAssetLinkForm();
        renderSelectedAssetLinks();
        syncAssetLinkControls();
        syncAssetLinkPanelVisibility();
        setAssetStatus("");
        return;
    }

    const previewImageUrl = resolveSelectedAssetPreviewUrl(asset);

    if (previewImageUrl) {
        const image = document.createElement("img");
        image.src = previewImageUrl;
        image.alt = asset.display_name || asset.filename || "Asset preview";
        image.className = "h-full max-h-full w-full max-w-full rounded-8 object-contain";
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

    const assetDisplayName = asset.display_name || asset.filename || t("dam.assetDetails", "Asset Details");
    damElements.assetModalTitle.textContent = assetDisplayName;
    damElements.assetSize.textContent = formatBytes(asset.bytes) || "-";
    damElements.assetFormat.textContent = String(asset.format || "-").toUpperCase();
    damElements.assetFolder.textContent = asset.asset_folder || "-";
    damElements.openAssetButton.disabled = !asset.secure_url;
    damElements.copyAssetUrlButton.disabled = !asset.secure_url;
    if (!DAM_ROLE_OPTIONS.includes(damElements.linkRoleSelect.value)) {
        damElements.linkRoleSelect.value = getDefaultAssetLinkRole(asset);
    }
    renderSelectedAssetLinks();
    syncAssetLinkControls();
    syncAssetLinkPanelVisibility();
    setAssetStatus("");
}

function renderSelectedAssetLinks() {
    if (!damElements) {
        return;
    }

    damElements.assetMetaList.querySelectorAll("[data-dam-dynamic-link-row]").forEach((row) => row.remove());
    damElements.linksList.innerHTML = "";

    if (!DAM_ASSET_MODAL_LINKING_ENABLED) {
        if (
            damState.selectedAsset
            && !damState.selectedAssetLinksLoading
            && Array.isArray(damState.selectedAssetLinks)
            && damState.selectedAssetLinks.length > 0
        ) {
            const linkedTargets = damState.selectedAssetLinks
                .map((link) => buildAssetLinkTargetText(link))
                .filter((value) => String(value || "").trim() !== "")
                .join(", ");

            if (linkedTargets !== "") {
                const targetItem = document.createElement("div");
                targetItem.className = "list-item";
                targetItem.dataset.damDynamicLinkRow = "true";

                const targetKey = document.createElement("span");
                targetKey.className = "list-key";
                targetKey.textContent = t("dam.linkTargetLabel", "Linked To");

                const targetValue = document.createElement("span");
                targetValue.className = "list-value break-all";
                targetValue.textContent = linkedTargets;

                targetItem.appendChild(targetKey);
                targetItem.appendChild(targetValue);
                damElements.assetMetaList.appendChild(targetItem);
            }
        }

        damElements.emptyLinks.hidden = true;
        return;
    }

    if (!damState.selectedAsset) {
        damElements.emptyLinks.textContent = t("dam.noLinks", "No links yet.");
        damElements.emptyLinks.hidden = false;
        return;
    }

    if (damState.selectedAssetLinksLoading) {
        damElements.emptyLinks.textContent = t("dam.loadingLinks", "Loading links...");
        damElements.emptyLinks.hidden = false;
        return;
    }

    if (!Array.isArray(damState.selectedAssetLinks) || damState.selectedAssetLinks.length === 0) {
        damElements.emptyLinks.textContent = t("dam.noLinks", "No links yet.");
        damElements.emptyLinks.hidden = false;
        return;
    }

    damElements.emptyLinks.hidden = true;
    const fragment = document.createDocumentFragment();

    damState.selectedAssetLinks.forEach((link) => {
        const targetItem = document.createElement("div");
        targetItem.className = "list-item";
        targetItem.dataset.damDynamicLinkRow = "true";

        const targetKey = document.createElement("span");
        targetKey.className = "list-key";
        targetKey.textContent = t("dam.linkTargetLabel", "Linked To");

        const targetValue = document.createElement("span");
        targetValue.className = "list-value break-all";
        targetValue.textContent = buildAssetLinkTargetText(link);

        targetItem.appendChild(targetKey);
        targetItem.appendChild(targetValue);

        const roleItem = document.createElement("div");
        roleItem.className = "list-item";
        roleItem.dataset.damDynamicLinkRow = "true";

        const roleKey = document.createElement("span");
        roleKey.className = "list-key";
        roleKey.textContent = t("dam.linkRole", "Role");

        const roleValue = document.createElement("span");
        roleValue.className = "list-value break-all";
        roleValue.textContent = formatDamRoleLabel(link.role || "");

        roleItem.appendChild(roleKey);
        roleItem.appendChild(roleValue);

        const sortItem = document.createElement("div");
        sortItem.className = "list-item";
        sortItem.dataset.damDynamicLinkRow = "true";

        const sortKey = document.createElement("span");
        sortKey.className = "list-key";
        sortKey.textContent = t("dam.linkSortOrder", "Sort Order");

        const sortValue = document.createElement("span");
        sortValue.className = "list-value break-all";
        sortValue.textContent = String(link.sort_order ?? 0);

        sortItem.appendChild(sortKey);
        sortItem.appendChild(sortValue);

        damElements.assetMetaList.appendChild(targetItem);
        damElements.assetMetaList.appendChild(roleItem);
        damElements.assetMetaList.appendChild(sortItem);

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-secondary btn-xs";
        button.addEventListener("click", () => {
            handleDeleteAssetLink(link.id);
        });

        const icon = document.createElement("i");
        icon.className = "ri-link-unlink-m text-icon-sm";
        icon.setAttribute("aria-hidden", "true");

        const label = document.createElement("span");
        label.textContent = t("dam.unlinkAssetButton", "Unlink");

        button.appendChild(icon);
        button.appendChild(label);
        const buttonRow = document.createElement("div");
        buttonRow.className = "flex justify-end border-b border-grey-secondary/60 pb-8 last:border-b-0 last:pb-0";
        buttonRow.appendChild(button);
        fragment.appendChild(buttonRow);
    });

    damElements.linksList.appendChild(fragment);
}

function syncAssetLinkControls() {
    if (!damElements) {
        return;
    }

    if (!DAM_ASSET_MODAL_LINKING_ENABLED) {
        damElements.linkFamilyCodeInput.disabled = true;
        damElements.linkProductCodeInput.disabled = true;
        damElements.linkRoleSelect.disabled = true;
        damElements.linkSortOrderInput.disabled = true;
        damElements.linkSubmitButton.disabled = true;
        damElements.toggleLinkingButton.disabled = true;
        damElements.toggleLinkingButton.classList.add("hidden");
        return;
    }

    const hasAsset = Boolean(damState.selectedAsset?.id);
    const canLink = hasAsset && canShowAssetLinkingControls();
    const disabled = !canLink || damState.selectedAssetLinksLoading || damState.selectedAssetLinkActionBusy;

    damElements.linkFamilyCodeInput.disabled = disabled;
    damElements.linkProductCodeInput.disabled = disabled;
    damElements.linkRoleSelect.disabled = disabled;
    damElements.linkSortOrderInput.disabled = disabled;
    damElements.linkSubmitButton.disabled = disabled;
    damElements.toggleLinkingButton.disabled = !canLink;
    damElements.toggleLinkingButton.classList.toggle("hidden", !canLink);
}

function syncAssetLinkPanelVisibility(options = {}) {
    if (!damElements) {
        return;
    }

    if (!DAM_ASSET_MODAL_LINKING_ENABLED) {
        damElements.linkingPanel.classList.add("hidden");
        damElements.toggleLinkingButton.classList.add("hidden");
        damElements.toggleLinkingButton.setAttribute("aria-expanded", "false");
        damElements.toggleLinkingButton.setAttribute("aria-pressed", "false");
        return;
    }

    const { syncPressed = true } = options;
    const hasAsset = Boolean(damState.selectedAsset?.id);
    const canLink = hasAsset && canShowAssetLinkingControls();
    const isHidden = !canLink || damState.assetLinkPanelHidden;
    const labelKey = isHidden ? "dam.showAssetLinks" : "dam.hideAssetLinks";
    const labelFallback = isHidden ? "Show linking" : "Hide linking";

    damElements.linkingPanel.classList.toggle("hidden", isHidden);
    damElements.toggleLinkingButton.classList.toggle("hidden", !canLink);
    damElements.toggleLinkingButton.setAttribute("aria-expanded", String(!isHidden));
    if (syncPressed) {
        damElements.toggleLinkingButton.setAttribute("aria-pressed", String(!isHidden));
    }
    damElements.toggleLinkingLabel.textContent = t(labelKey, labelFallback);
    damElements.toggleLinkingIcon.className = (isHidden ? "ri-eye-line" : "ri-eye-off-line") + " text-icon-lg";
}

function resetAssetLinkForm() {
    if (!damElements) {
        return;
    }

    damElements.linkFamilyCodeInput.value = "";
    damElements.linkProductCodeInput.value = "";
    damElements.linkSortOrderInput.value = "0";
    damElements.linkRoleSelect.value = DAM_ROLE_OPTIONS[0] || "";
}

function getDefaultAssetLinkRole(asset) {
    return DAM_ROLE_OPTIONS.includes(asset?.kind) ? asset.kind : (DAM_ROLE_OPTIONS[0] || "");
}

function buildAssetLinkTargetText(link) {
    const parts = [];

    if (link?.family_code) {
        parts.push(t("dam.linkFamilyPrefix", "Family") + " " + link.family_code);
    }

    if (link?.product_code) {
        parts.push(t("dam.linkProductPrefix", "Product") + " " + link.product_code);
    }

    return parts.length > 0 ? parts.join(" · ") : t("dam.linkGlobalTarget", "Global");
}

function updateFolderSummary() {
    if (!damElements) {
        return;
    }

    renderBreadcrumbs();
}

function syncFolderActionButtons() {
    if (!damElements) {
        return;
    }

    const canCreateChildren = getCreatableFolders().length > 0;
    const canUpload = Boolean(damState.currentFolder?.can_upload);

    damElements.openCreateFolderButton.disabled = !canCreateChildren;
    damElements.createFolderButton.disabled = !canCreateChildren;
    damElements.uploadTrigger.disabled = !canUpload;

    if (!canCreateChildren) {
        damElements.openCreateFolderButton.title = t("dam.folderActionBlocked", "No folder destinations available.");
        damElements.createFolderButton.title = t("dam.folderActionBlocked", "No folder destinations available.");
    } else {
        damElements.openCreateFolderButton.removeAttribute("title");
        damElements.createFolderButton.removeAttribute("title");
    }

    if (!canUpload) {
        damElements.uploadTrigger.title = t("dam.uploadActionBlocked", "Selected folder does not allow uploads.");
    } else {
        damElements.uploadTrigger.removeAttribute("title");
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

function hideDamToast(root, immediate = false) {
    const toast = root?.querySelector("[data-dam-toast]");

    if (!(root instanceof HTMLElement) || !(toast instanceof HTMLElement)) {
        return;
    }

    window.clearTimeout(damToastTimer);
    window.clearTimeout(damToastHideTimer);
    toast.classList.remove("is-visible");
    toast.setAttribute("aria-hidden", "true");

    if (immediate) {
        root.innerHTML = "";
        return;
    }

    damToastHideTimer = window.setTimeout(() => {
        root.innerHTML = "";
        damToastHideTimer = 0;
    }, DAM_TOAST_HIDE_DELAY);
}

function showDamToast(message, tone = "success") {
    const root = document.getElementById("damToastRoot");

    if (!(root instanceof HTMLElement) || String(message || "").trim() === "") {
        return;
    }

    window.clearTimeout(damToastTimer);
    window.clearTimeout(damToastHideTimer);
    root.innerHTML = "";

    const variant = DAM_TOAST_VARIANT[tone] || DAM_TOAST_VARIANT.info;
    const panel = document.createElement("div");
    const toast = document.createElement("div");
    const iconWrap = document.createElement("span");
    const icon = document.createElement("i");
    const copy = document.createElement("div");
    const title = document.createElement("p");
    const text = document.createElement("span");
    const actions = document.createElement("div");
    const closeButton = document.createElement("button");
    const closeIcon = document.createElement("i");

    panel.className = "panel border-0 bg-transparent";

    toast.className = DAM_TOAST_BASE_CLASS + " " + variant.className;
    toast.setAttribute("role", variant.role);
    toast.setAttribute("aria-hidden", "true");
    toast.dataset.damToast = "true";

    iconWrap.className = "toast-icon";
    icon.className = variant.iconClass + " text-icon-lg";
    icon.setAttribute("aria-hidden", "true");
    iconWrap.appendChild(icon);

    copy.className = "toast-copy";
    title.className = "toast-title";
    title.textContent = t(variant.titleKey, variant.titleFallback);
    text.className = "toast-text";
    text.textContent = String(message || "").trim();
    copy.append(title, text);

    actions.className = "toast-actions";
    closeButton.type = "button";
    closeButton.className = "btn btn-ghost btn-icon btn-xs";
    closeButton.setAttribute("aria-label", t("shared.actions.closeNotification", "Close notification"));
    closeButton.addEventListener("click", () => {
        hideDamToast(root);
    });
    closeIcon.className = "ri-close-line text-icon-md";
    closeIcon.setAttribute("aria-hidden", "true");
    closeButton.appendChild(closeIcon);
    actions.appendChild(closeButton);

    toast.append(iconWrap, copy, actions);
    panel.appendChild(toast);
    root.appendChild(panel);

    requestAnimationFrame(() => {
        toast.classList.add("is-visible");
        toast.setAttribute("aria-hidden", "false");
    });

    damToastTimer = window.setTimeout(() => {
        hideDamToast(root);
    }, DAM_TOAST_AUTOHIDE_DELAY);
}

function resolveSelectedAssetPreviewUrl(asset) {
    const resourceType = String(asset?.resource_type || "").toLowerCase();
    const format = String(asset?.format || "").toLowerCase();
    const secureUrl = normalizeDamAssetUrl(asset?.secure_url);

    if (resourceType === "image" && format !== "pdf" && secureUrl) {
        return secureUrl;
    }

    return resolveAssetThumbnailUrl(asset);
}

function getDamErrorMessage(error, fallback) {
    if (error instanceof Error && error.message.trim() !== "") {
        return error.message;
    }

    return fallback;
}

function buildAssetTitle(asset) {
    const name = asset.display_name || asset.filename || "";
    const size = formatBytes(asset.bytes || 0);
    return size ? name + "\n" + size : name;
}

function renderBreadcrumbs() {
    if (!damElements) {
        return;
    }

    damElements.breadcrumb.innerHTML = "";

    const folderIds = buildBreadcrumbFolderIds();

    if (folderIds.length === 0) {
        return;
    }

    const folderMap = buildFolderLookup();
    const fragment = document.createDocumentFragment();

    folderIds.forEach((folderId, index) => {
        const isCurrent = index === folderIds.length - 1;
        const item = document.createElement("li");
        item.className = "breadcrumb-item";

        const link = document.createElement("a");
        link.href = "#";
        link.className = "breadcrumb-link link-navigation link-xs";

        if (isCurrent) {
            link.setAttribute("aria-current", "page");
        } else {
            link.addEventListener("click", (event) => {
                event.preventDefault();
                loadDamFolder(folderId);
            });
        }

        const label = document.createElement("span");
        label.className = "link-label";
        label.textContent = resolveBreadcrumbLabel(folderId, folderMap, index === 0);

        link.appendChild(label);
        item.appendChild(link);
        fragment.appendChild(item);

        if (!isCurrent) {
            const separator = document.createElement("li");
            separator.className = "breadcrumb-separator";

            const icon = document.createElement("i");
            icon.className = "ri-arrow-right-s-line icon icon-xs";
            icon.setAttribute("aria-hidden", "true");

            separator.appendChild(icon);
            fragment.appendChild(separator);
        }
    });

    damElements.breadcrumb.appendChild(fragment);
}

function buildBreadcrumbFolderIds() {
    const currentPath = damState.currentFolder?.path || damState.currentFolderId || "";

    if (!currentPath) {
        return [];
    }

    const segments = currentPath.split("/").filter(Boolean);

    if (segments[0] === DAM_ROOT_FOLDER_ID) {
        segments.shift();
    }

    if (segments.length === 0) {
        return [];
    }

    let currentId = DAM_ROOT_FOLDER_ID;

    return segments.map((segment) => {
        currentId += "/" + segment;
        return currentId;
    });
}

function buildFolderLookup() {
    const folderMap = new Map();

    flattenFolderTree(damState.tree).forEach((folder) => {
        folderMap.set(folder.id, folder);
    });

    if (damState.currentFolder?.id) {
        folderMap.set(damState.currentFolder.id, damState.currentFolder);
    }

    damState.folders.forEach((folder) => {
        folderMap.set(folder.id, folder);
    });

    return folderMap;
}

function resolveBreadcrumbLabel(folderId, folderMap, isRoot) {
    const folder = folderMap.get(folderId) || null;

    if (folder) {
        return isRoot ? formatRootFolderLabel(folder) : folder.name;
    }

    const fallback = folderId.split("/").filter(Boolean).pop() || folderId;
    return isRoot ? fallback.replace(/^\d+_/, "").replace(/-/g, " ") : fallback;
}

function getRootFolders() {
    return Array.isArray(damState.tree) ? damState.tree : [];
}

function resolveActiveRootFolder() {
    const rootFolders = getRootFolders();

    if (rootFolders.length === 0) {
        return null;
    }

    const currentId = damState.currentFolder?.id || damState.currentFolderId || "";
    return rootFolders.find((folder) => currentId === folder.id || currentId.startsWith(folder.id + "/")) || rootFolders[0] || null;
}

function formatRootFolderLabel(folder) {
    if (!folder) {
        return t("dam.loadingFolders", "Loading folders...");
    }

    const scopeLabels = {
        datasheet: t("dam.root.datasheet", "Datasheet"),
        media: t("dam.root.media", "Media"),
        brand: t("dam.root.brand", "Brand"),
        products: t("dam.root.products", "Products"),
        configurator: t("dam.root.configurator", "Configurator"),
        support: t("dam.root.support", "Support"),
        store: t("dam.root.store", "Store"),
        website: t("dam.root.website", "Website"),
        eprel: t("dam.root.eprel", "EPREL"),
        archive: t("dam.root.archive", "Archive"),
    };

    return scopeLabels[folder.scope] || folder.name.replace(/^\d+_/, "").replace(/-/g, " ");
}

function formatDamRoleLabel(role) {
    return String(role || "")
        .split("-")
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function canShowAssetLinkingControls(asset = damState.selectedAsset) {
    const folderId = String(asset?.asset_folder || asset?.folder_id || "").trim();

    if (folderId === "") {
        return false;
    }

    return folderId === DAM_DEFAULT_FOLDER_ID || folderId.startsWith(DAM_DEFAULT_FOLDER_ID + "/");
}

function resolveAssetThumbnailUrl(asset) {
    const explicitThumbnailUrl = normalizeDamAssetUrl(asset?.thumbnail_url);
    const secureUrl = normalizeDamAssetUrl(asset?.secure_url);
    const resourceType = String(asset?.resource_type || "").toLowerCase();
    const format = String(asset?.format || "").toLowerCase();

    if (explicitThumbnailUrl) {
        return explicitThumbnailUrl;
    }

    if (resourceType === "image" && format !== "pdf" && secureUrl) {
        return secureUrl;
    }

    return "";
}

function normalizeDamAssetUrl(value) {
    return typeof value === "string" ? value.trim() : "";
}

function buildCloudinaryThumbnailUrl(url) {
    if (!url) {
        return "";
    }

    const uploadMarker = "/image/upload/";
    const parts = url.split(uploadMarker);

    if (parts.length !== 2) {
        return url;
    }

    const [base, remainder] = parts;
    if (!remainder || !remainder.startsWith("v")) {
        return url;
    }

    return base + uploadMarker + DAM_GRID_THUMB_TRANSFORM + "/" + remainder;
}

function buildCloudinaryDownloadUrl(url, filename = "") {
    if (!url) {
        return "";
    }

    const uploadMarker = "/upload/";
    const parts = url.split(uploadMarker);

    if (parts.length !== 2) {
        return url;
    }

    const [base, remainder] = parts;
    if (!remainder) {
        return url;
    }

    const attachmentQualifier = buildCloudinaryAttachmentQualifier(filename);
    return base + uploadMarker + attachmentQualifier + "/" + remainder;
}

function buildCloudinaryAttachmentQualifier(filename) {
    const baseName = buildCloudinaryAttachmentBaseName(filename);
    return baseName ? "fl_attachment:" + encodeURIComponent(baseName) : "fl_attachment";
}

function buildCloudinaryAttachmentBaseName(filename) {
    const rawName = String(filename || "").trim();

    if (!rawName) {
        return "";
    }

    const lastDotIndex = rawName.lastIndexOf(".");
    if (lastDotIndex <= 0) {
        return rawName;
    }

    return rawName.slice(0, lastDotIndex);
}

function buildCloudinaryFetchThumbnailUrl(url) {
    if (!url) {
        return "";
    }

    try {
        const parsedUrl = new URL(url);
        const pathSegments = parsedUrl.pathname.split("/").filter(Boolean);
        const cloudName = pathSegments[0] || "";

        if (!cloudName) {
            return "";
        }

        return parsedUrl.origin + "/" + cloudName + "/image/fetch/" + DAM_GRID_PDF_FETCH_TRANSFORM + "/" + encodeURIComponent(url);
    } catch (error) {
        console.error(error);
        return "";
    }
}

function syncRootDropdownSelection(folderId) {
    if (!damElements) {
        return;
    }

    damElements.rootMenu.querySelectorAll(".dropdown-item").forEach((item) => {
        item.setAttribute("aria-selected", String(item.dataset.value === folderId));
    });
}

function closeRootDropdown() {
    if (!damElements) {
        return;
    }

    damElements.rootDropdown.classList.remove("is-open");
    damElements.rootDropdown.querySelector(".dropdown-trigger")?.setAttribute("aria-expanded", "false");
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
