<?php

require_once dirname(__FILE__) . "/../reference-decoder.php";

function parseShowcasePattern(string $pattern): array {
    $normalizedPattern = strtoupper(preg_replace("/[^A-Z0-9]/", "", $pattern));

    if ($normalizedPattern === "" || strlen($normalizedPattern) !== REFERENCE_LENGTH_FULL) {
        return [
            "ok" => false,
            "status_code" => 400,
            "error_code" => "showcase_invalid_pattern",
            "message" => "Showcase pattern must contain exactly 17 alphanumeric characters.",
        ];
    }

    $family = str_pad((string) validateFamily(substr($normalizedPattern, 0, REFERENCE_LENGTH_FAMILY)), 2, "0", STR_PAD_LEFT);

    if ($family === "00") {
        return [
            "ok" => false,
            "status_code" => 400,
            "error_code" => "showcase_invalid_pattern",
            "message" => "Showcase pattern contains an invalid family code.",
        ];
    }

    $parts = decodeReference($normalizedPattern);
    $locked = [
        "size" => $parts["size"],
        "color" => $parts["color"],
        "cri" => $parts["cri"],
        "series" => $parts["series"],
        "lens" => $parts["lens"],
    ];
    $expanded = [];

    foreach (["finish" => "XX", "cap" => "YY", "option" => "ZZ"] as $segment => $wildcard) {
        $value = strtoupper((string) ($parts[$segment] ?? ""));

        if ($value === $wildcard) {
            $expanded[] = $segment;
            continue;
        }

        $locked[$segment] = $value;
    }

    return [
        "ok" => true,
        "data" => [
            "pattern" => $normalizedPattern,
            "family" => $family,
            "locked" => $locked,
            "expanded" => $expanded,
        ],
    ];
}
