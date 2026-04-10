const APP_I18N_EVENT = "nexled:i18n-applied";
const APP_I18N_SOURCE_EVENT = "nexled:app-language-change";

(function () {
    function normalizeLanguage(value) {
        return String(value || "").trim().toLowerCase() === "pt" ? "pt" : "en";
    }

    function getCurrentLanguage() {
        return normalizeLanguage(window.NexLedAppShell?.getLanguage?.() || document.documentElement.lang || "en");
    }

    function getDictionary(language) {
        return window.NexLedLocales?.[normalizeLanguage(language)] || {};
    }

    function lookup(dictionary, key) {
        return key.split(".").reduce((value, segment) => {
            if (value && typeof value === "object" && segment in value) {
                return value[segment];
            }

            return undefined;
        }, dictionary);
    }

    function interpolate(text, variables) {
        return String(text).replace(/\{(\w+)\}/g, (_, token) => {
            return token in variables ? String(variables[token]) : "";
        });
    }

    function translate(key, variables = {}, fallback = "") {
        const language = getCurrentLanguage();
        const localized = lookup(getDictionary(language), key);
        const english = lookup(getDictionary("en"), key);
        const value = localized ?? english ?? fallback;

        return typeof value === "string" ? interpolate(value, variables) : fallback;
    }

    function applyDamAssetActionTranslations() {
        const actionMap = [
            { iconClass: "ri-download-line", key: "dam.assetAction.download" },
            { iconClass: "ri-eye-line", key: "dam.assetAction.preview" },
            { iconClass: "ri-archive-line", key: "dam.assetAction.archive" },
        ];

        document.querySelectorAll(".tooltip-wrapper").forEach((wrapper) => {
            const button = wrapper.querySelector("button");
            const icon = button?.querySelector("i");
            const tooltip = wrapper.querySelector("span");

            if (!button || !icon || !tooltip) {
                return;
            }

            const action = actionMap.find((item) => icon.classList.contains(item.iconClass));

            if (!action) {
                return;
            }

            const fallback = button.getAttribute("aria-label") || tooltip.textContent || "";
            const label = translate(action.key, {}, fallback);

            button.setAttribute("aria-label", label);
            tooltip.textContent = label;
        });
    }

    function applyTranslations() {
        const language = getCurrentLanguage();

        document.documentElement.lang = language;

        document.querySelectorAll("[data-i18n]").forEach((element) => {
            const fallback = element.dataset.i18nFallback || element.textContent || "";
            element.textContent = translate(element.dataset.i18n, {}, fallback);
        });

        document.querySelectorAll("[data-i18n-placeholder]").forEach((element) => {
            const fallback = element.getAttribute("placeholder") || "";
            element.setAttribute("placeholder", translate(element.dataset.i18nPlaceholder, {}, fallback));
        });

        document.querySelectorAll("[data-i18n-aria-label]").forEach((element) => {
            const fallback = element.getAttribute("aria-label") || "";
            element.setAttribute("aria-label", translate(element.dataset.i18nAriaLabel, {}, fallback));
        });

        document.querySelectorAll("[data-i18n-title]").forEach((element) => {
            const fallback = element.getAttribute("title") || "";
            element.setAttribute("title", translate(element.dataset.i18nTitle, {}, fallback));
        });

        applyDamAssetActionTranslations();

        window.dispatchEvent(new CustomEvent(APP_I18N_EVENT, {
            detail: { language },
        }));
    }

    document.addEventListener("DOMContentLoaded", applyTranslations);
    window.addEventListener(APP_I18N_SOURCE_EVENT, applyTranslations);

    window.NexLedI18n = {
        eventName: APP_I18N_EVENT,
        getLanguage: getCurrentLanguage,
        t: translate,
        apply: applyTranslations,
    };
})();
