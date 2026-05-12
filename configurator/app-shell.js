const APP_SHELL_LANGUAGE_KEY = "nexled-app-language";
const APP_SHELL_LANGUAGE_EVENT = "nexled:app-language-change";

// Bump API_CACHE_VERSION to invalidate every cached entry on next load.
const API_CACHE_VERSION = "v1";
const API_CACHE_PREFIX = "nx-api-cache:" + API_CACHE_VERSION + ":";

// Detect ?cache=bust on this page load. If present, remember it for the
// session, then strip from URL so a future browser refresh doesn't keep
// busting forever. Consumers (apiCacheRemember + apiFetch) read this flag.
window.__nxCacheBustOnce = false;
(function () {
    try {
        const params = new URLSearchParams(window.location.search);
        if (params.get("cache") === "bust") {
            window.__nxCacheBustOnce = true;
            params.delete("cache");
            const newSearch = params.toString();
            const newUrl = window.location.pathname + (newSearch ? "?" + newSearch : "") + window.location.hash;
            window.history.replaceState({}, "", newUrl);
        }
    } catch (e) { /* private mode / sandbox — leave flag false */ }
})();

function apiCacheGet(key) {
    try {
        const raw = window.localStorage.getItem(API_CACHE_PREFIX + key);
        if (!raw) return null;
        const d = JSON.parse(raw);
        if (!d || typeof d.exp !== "number" || d.exp < Date.now()) {
            window.localStorage.removeItem(API_CACHE_PREFIX + key);
            return null;
        }
        return d.val;
    } catch (e) { return null; }
}

function apiCacheSet(key, val, ttlSeconds) {
    try {
        window.localStorage.setItem(API_CACHE_PREFIX + key, JSON.stringify({ exp: Date.now() + ttlSeconds * 1000, val }));
    } catch (e) { /* quota or private mode — silent fall-through */ }
}

async function apiCacheRemember(key, ttlSeconds, fetcher) {
    if (!window.__nxCacheBustOnce) {
        const cached = apiCacheGet(key);
        if (cached !== null && cached !== undefined) return cached;
    }
    const fresh = await fetcher();
    if (fresh !== null && fresh !== undefined) apiCacheSet(key, fresh, ttlSeconds);
    return fresh;
}

function nxClearApiCache() {
    try {
        const toRemove = [];
        for (let i = 0; i < window.localStorage.length; i++) {
            const k = window.localStorage.key(i);
            if (k && k.indexOf(API_CACHE_PREFIX) === 0) toRemove.push(k);
        }
        toRemove.forEach(function (k) { window.localStorage.removeItem(k); });
    } catch (e) { /* silent */ }
}

function nxRefreshData() {
    nxClearApiCache();
    try {
        const url = new URL(window.location.href);
        url.searchParams.set("cache", "bust");
        window.location.replace(url.toString());
    } catch (e) {
        window.location.reload();
    }
}

// Append &cache=bust to a URL when the bust flag is set this page load.
// Used by both apiFetch implementations (script.js, code-explorer.js) so
// the bust intent propagates from frontend to server in one trip.
function nxApplyCacheBustToPath(path) {
    if (!window.__nxCacheBustOnce) return path;
    return path + (path.indexOf("?") >= 0 ? "&" : "?") + "cache=bust";
}

// Shared API base resolver. Returns absolute URL to the API root.
//
// Local dev (localhost / 127.0.0.1): resolve relative to the page so
//   http://localhost/api_nexled/configurator/X.html -> http://localhost/api_nexled/api
//
// Production / external hosts (Railway, alwaysdata.net, anything else):
//   always use the canonical Railway URL so pages hosted on a different
//   origin (e.g. nexled.alwaysdata.net) correctly reach the API, not
//   themselves.
const NX_DEFAULT_API_BASE = "https://apinexled-production.up.railway.app/api";
const NX_LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "[::1]"]);

function nxResolveApiBase() {
    if (typeof window === "undefined" || !window.location) return NX_DEFAULT_API_BASE;
    if (window.location.protocol === "file:") return "http://localhost/api_nexled/api";
    if (NX_LOCAL_HOSTS.has(window.location.hostname.toLowerCase())) {
        try {
            return new URL("../api", window.location.href).toString().replace(/\/+$/, "");
        } catch (_) {}
    }
    return NX_DEFAULT_API_BASE;
}

// Shared API transport. Pure mechanics: build URL with bust, send X-API-Key,
// read body, parse JSON safely. Never throws on HTTP errors — callers decide
// whether to throw and how to report (badge state, error UX) per page.
// Returns { ok, status, payload, rawText }.
async function apiCoreFetch(apiBase, path, apiKey) {
    const finalPath = nxApplyCacheBustToPath(path);
    const response = await fetch(apiBase + finalPath, {
        headers: { "X-API-Key": apiKey },
    });
    const rawText = await response.text();
    let payload = null;
    if (rawText !== "") {
        try { payload = JSON.parse(rawText); } catch (_) { payload = null; }
    }
    return { ok: response.ok, status: response.status, payload, rawText };
}

const APP_SHELL_LANGUAGES = {
    en: {
        app: "en",
        code: "gb",
        label: "English",
    },
    pt: {
        app: "pt",
        code: "pt",
        label: "Portuguese",
    },
};

(function () {
    let currentLanguage = normalizeLanguage(readStoredLanguage());

    function normalizeLanguage(value) {
        const normalized = String(value || "").trim().toLowerCase();

        if (normalized === "pt") {
            return normalized;
        }

        return "en";
    }

    function readStoredLanguage() {
        try {
            return window.localStorage.getItem(APP_SHELL_LANGUAGE_KEY);
        } catch (error) {
            console.warn("Unable to read the saved app language.", error);
            return "";
        }
    }

    function storeLanguage(language) {
        try {
            window.localStorage.setItem(APP_SHELL_LANGUAGE_KEY, language);
        } catch (error) {
            console.warn("Unable to persist the app language.", error);
        }
    }

    function getFlagSource(code) {
        return "https://flagcdn.com/w40/" + code + ".png";
    }

    function getFlagSourceSet(code) {
        return "https://flagcdn.com/w80/" + code + ".png 2x";
    }

    function getLanguageKey(language) {
        return language.app === "pt" ? "shared.language.portuguese" : "shared.language.english";
    }

    function getCurrentLanguageAriaLabel(language) {
        const fallback = "Current language: " + language.label;
        const translatedLabel = window.NexLedI18n?.t?.(getLanguageKey(language), {}, language.label) || language.label;

        return window.NexLedI18n?.t?.("shared.language.current", {
            language: translatedLabel,
        }, fallback) || fallback;
    }

    function syncSelector(selector, language) {
        const trigger = selector.querySelector(".language-selector-trigger");
        const value = selector.querySelector(".language-selector-value");
        const flag = selector.querySelector(".language-selector-current .language-selector-flag");
        const options = Array.from(selector.querySelectorAll(".language-selector-option"));

        options.forEach((option) => {
            option.setAttribute("aria-selected", String(option.dataset.appLang === language.app));
        });

        if (trigger) {
            trigger.setAttribute("aria-label", getCurrentLanguageAriaLabel(language));
        }

        if (value) {
            value.textContent = language.label;
        }

        if (flag) {
            flag.src = getFlagSource(language.code);
            flag.srcset = getFlagSourceSet(language.code);
        }

        selector.classList.add("has-value");
        selector.dataset.currentLanguage = language.app;
    }

    function applyLanguage(language, options = {}) {
        const nextLanguage = APP_SHELL_LANGUAGES[normalizeLanguage(language)];
        const shouldPersist = options.persist !== false;
        const shouldEmit = options.emit !== false;

        currentLanguage = nextLanguage.app;

        document.querySelectorAll("[data-app-language-selector]").forEach((selector) => {
            syncSelector(selector, nextLanguage);
        });

        if (shouldPersist) {
            storeLanguage(nextLanguage.app);
        }

        if (shouldEmit) {
            window.dispatchEvent(new CustomEvent(APP_SHELL_LANGUAGE_EVENT, {
                detail: { language: nextLanguage.app },
            }));
        }

        return nextLanguage.app;
    }

    function bindSelector(selector) {
        if (selector.dataset.appLanguageBound === "true") {
            return;
        }

        selector.dataset.appLanguageBound = "true";

        selector.querySelectorAll(".language-selector-option").forEach((option) => {
            const applyOptionLanguage = () => {
                applyLanguage(option.dataset.appLang || option.dataset.code || "en");
            };

            option.addEventListener("click", applyOptionLanguage);
            option.addEventListener("keydown", (event) => {
                if (event.key === "Enter" || event.key === " ") {
                    applyOptionLanguage();
                }
            });
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-app-language-selector]").forEach((selector) => {
            bindSelector(selector);
        });

        applyLanguage(currentLanguage, {
            emit: false,
            persist: false,
        });
    });

    window.NexLedAppShell = {
        getLanguage() {
            return currentLanguage;
        },
        setLanguage(language) {
            return applyLanguage(language);
        },
    };
})();
