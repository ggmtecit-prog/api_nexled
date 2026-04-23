<?php

/**
 * Input Validation
 *
 * All user-supplied values must pass through these functions
 * before being used in SQL queries or file paths.
 *
 * Called once at the API entry points (endpoints and engine).
 */

/** Allowed language codes. Used as SQL column name suffixes, so must be whitelisted. */
const ALLOWED_LANGS = ["pt", "en", "es"];

/** Allowed IP rating values from the form. */
const ALLOWED_IP_RATINGS = ["0", "IP20", "IP40", "IP42", "IP45", "IP60", "IP64", "IP65", "IP66", "IP67"];

/** Allowed exact-product datasheet design variants. */
const ALLOWED_DATASHEET_DESIGN_VARIANTS = ["classic", "modern"];



/**
 * Validates and returns a language code.
 * Returns "pt" if the value is not in the allowed list.
 *
 * @param  string $lang  Raw value from user input
 * @return string  Safe language code
 */
function validateLang(string $lang): string {
    return in_array($lang, ALLOWED_LANGS, true) ? $lang : "pt";
}



/**
 * Validates and returns an IP rating override.
 * Returns "0" (auto-detect) if the value is not in the allowed list.
 *
 * @param  string $ip  Raw value from user input
 * @return string  Safe IP rating value
 */
function validateIpRating(string $ip): string {
    return in_array($ip, ALLOWED_IP_RATINGS, true) ? $ip : "0";
}

/**
 * Validates and returns a datasheet design variant.
 * Returns "classic" when omitted.
 * Returns null when a non-empty unsupported value is supplied.
 *
 * @param  ?string $variant  Raw value from user input
 * @return ?string  Safe variant string, or null if unsupported
 */
function validateDesignVariant(?string $variant): ?string {
    $normalizedVariant = strtolower(trim((string) $variant));

    if ($normalizedVariant === "") {
        return "classic";
    }

    return in_array($normalizedVariant, ALLOWED_DATASHEET_DESIGN_VARIANTS, true)
        ? $normalizedVariant
        : null;
}



/**
 * Sanitizes a product reference code.
 * Strips everything except alphanumeric characters and returns the first 17 chars.
 *
 * @param  string $reference  Raw value from user input
 * @return string  Safe reference string
 */
function validateReference(string $reference): string {
    return substr(preg_replace("/[^a-zA-Z0-9]/", "", $reference), 0, 17);
}



/**
 * Sanitizes a product family code.
 * Must be a positive integer.
 * Returns 0 if the value is not numeric (callers should check for 0 and reject).
 *
 * @param  mixed $family  Raw value from user input
 * @return int  Safe integer family code, or 0 if invalid
 */
function validateFamily(mixed $family): int {
    $val = intval($family);
    return $val > 0 ? $val : 0;
}
