const APP_SHELL_LANGUAGE_KEY = "nexled-app-language";
const APP_SHELL_LANGUAGE_EVENT = "nexled:app-language-change";
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

    function syncSelector(selector, language) {
        const trigger = selector.querySelector(".language-selector-trigger");
        const value = selector.querySelector(".language-selector-value");
        const flag = selector.querySelector(".language-selector-current .language-selector-flag");
        const options = Array.from(selector.querySelectorAll(".language-selector-option"));

        options.forEach((option) => {
            option.setAttribute("aria-selected", String(option.dataset.appLang === language.app));
        });

        if (trigger) {
            trigger.setAttribute("aria-label", "Current language: " + language.label);
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
