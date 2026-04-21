<?php

require_once dirname(__FILE__) . "/../images.php";

function resolveCustomDatasheetAssetOverride(array $override): ?string {
    $source = strtolower(trim((string) ($override["source"] ?? "")));

    if ($source === "dam") {
        return resolveCustomDatasheetDamAsset((string) ($override["asset_id"] ?? ""));
    }

    if ($source === "local") {
        return resolveCustomDatasheetLocalAsset((string) ($override["asset_key"] ?? ""));
    }

    return null;
}

function resolveCustomDatasheetDamAsset(string $assetId): ?string {
    $assetId = trim($assetId);

    if ($assetId === "" || !function_exists("tryConnectDBDam")) {
        return null;
    }

    $con = tryConnectDBDam();

    if (!$con) {
        return null;
    }

    $row = null;

    if (ctype_digit($assetId)) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT `secure_url`,`public_id`,`resource_type`
             FROM `dam_assets`
             WHERE `id` = ?
             LIMIT 1"
        );

        if ($stmt) {
            $assetNumericId = intval($assetId);
            mysqli_stmt_bind_param($stmt, "i", $assetNumericId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
        }
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT `secure_url`,`public_id`,`resource_type`
             FROM `dam_assets`
             WHERE `public_id` = ?
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $assetId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
        }
    }

    closeDB($con);

    if (!is_array($row)) {
        return null;
    }

    $secureUrl = trim((string) ($row["secure_url"] ?? ""));

    if ($secureUrl !== "") {
        return $secureUrl;
    }

    $publicId = trim((string) ($row["public_id"] ?? ""));
    $resourceType = trim((string) ($row["resource_type"] ?? "image"));

    if ($publicId === "" || !function_exists("cloudinaryFindResourceSecureUrl")) {
        return null;
    }

    return cloudinaryFindResourceSecureUrl($publicId, $resourceType !== "" ? $resourceType : "image");
}

function resolveCustomDatasheetLocalAsset(string $assetKey): ?string {
    $assetKey = trim(str_replace("\\", "/", $assetKey));

    if ($assetKey === "" || str_contains($assetKey, "..")) {
        return null;
    }

    $basePath = realpath(IMAGES_BASE_PATH);

    if (!is_string($basePath) || $basePath === "") {
        return null;
    }

    $candidatePath = realpath($basePath . DIRECTORY_SEPARATOR . ltrim($assetKey, "/"));

    if (!is_string($candidatePath) || $candidatePath === "") {
        return null;
    }

    $normalizedCandidate = str_replace("\\", "/", $candidatePath);
    $normalizedBase = str_replace("\\", "/", $basePath);

    if (!str_starts_with($normalizedCandidate, $normalizedBase . "/") && $normalizedCandidate !== $normalizedBase) {
        return null;
    }

    if (!is_file($candidatePath)) {
        return null;
    }

    return $candidatePath;
}
