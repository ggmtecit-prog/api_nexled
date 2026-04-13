<?php

/**
 * Cloudinary helpers — no SDK, plain cURL.
 *
 * Expects these constants (defined in appdatasheets/config.php):
 *   CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET
 */



/**
 * Returns the credential pair used for Upload API calls.
 *
 * @return array{api_key:string,api_secret:string}
 */
function cloudinaryUploadCredentials(): array {
    return [
        "api_key" => cloudinaryConfigValue("CLOUDINARY_API_KEY"),
        "api_secret" => cloudinaryConfigValue("CLOUDINARY_API_SECRET"),
    ];
}



/**
 * Returns the credential pair used for Admin API calls.
 * Falls back to the default Cloudinary credentials when dedicated
 * admin credentials are not configured.
 *
 * @return array{api_key:string,api_secret:string}
 */
function cloudinaryAdminCredentials(): array {
    $adminApiKey = cloudinaryConfigValue("CLOUDINARY_ADMIN_API_KEY");
    $adminApiSecret = cloudinaryConfigValue("CLOUDINARY_ADMIN_API_SECRET");

    return [
        "api_key" => $adminApiKey !== "" ? $adminApiKey : cloudinaryConfigValue("CLOUDINARY_API_KEY"),
        "api_secret" => $adminApiSecret !== "" ? $adminApiSecret : cloudinaryConfigValue("CLOUDINARY_API_SECRET"),
    ];
}



/**
 * Upload a local file to Cloudinary.
 *
 * @param  string $filePath     Temp path of the file (e.g. $_FILES["file"]["tmp_name"])
 * @param  string $publicId     Cloudinary public ID  (e.g. "nexled/photo/product/filename")
 * @param  string $resourceType "image" | "raw"
 * @param  array  $options      Extra signed upload parameters (e.g. asset_folder, display_name, overwrite)
 * @return array|null           Cloudinary response, or null on failure
 */
function cloudinaryUpload(string $filePath, string $publicId, string $resourceType, array $options = []): ?array {
    $result = cloudinaryUploadDetailed($filePath, $publicId, $resourceType, $options);
    return $result["ok"] ? ($result["data"] ?? null) : null;
}

/**
 * Upload a local file to Cloudinary and return diagnostic data on failure.
 *
 * @param  string $filePath
 * @param  string $publicId
 * @param  string $resourceType
 * @param  array  $options
 * @return array{ok:bool,data?:array,http_code:int,error?:string,response?:array|null,raw_response?:string|null}
 */
function cloudinaryUploadDetailed(string $filePath, string $publicId, string $resourceType, array $options = []): array {
    $missingConfig = cloudinaryMissingUploadConfig();

    if ($missingConfig !== []) {
        return [
            "ok" => false,
            "http_code" => 0,
            "error" => "Cloudinary upload is not configured. Missing: " . implode(", ", $missingConfig),
            "response" => null,
            "raw_response" => null,
        ];
    }

    $credentials = cloudinaryUploadCredentials();
    $timestamp = time();

    $params = array_merge($options, [
        "public_id" => $publicId,
        "timestamp" => $timestamp,
    ]);
    $signature = cloudinaryBuildSignature($params, $credentials["api_secret"]);

    $url = "https://api.cloudinary.com/v1_1/" . cloudinaryConfigValue("CLOUDINARY_CLOUD_NAME") . "/$resourceType/upload";

    $postFields = array_merge($options, [
        "file" => new CURLFile($filePath),
        "public_id" => $publicId,
        "api_key" => $credentials["api_key"],
        "timestamp" => $timestamp,
        "signature" => $signature,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = is_string($response) ? json_decode($response, true) : null;

    if ($httpCode === 200 && is_array($data) && isset($data["secure_url"])) {
        return [
            "ok" => true,
            "data" => $data,
            "http_code" => $httpCode,
        ];
    }

    $errorMessage = "";

    if (is_array($data) && isset($data["error"]["message"])) {
        $errorMessage = (string) $data["error"]["message"];
    } elseif ($curlError !== "") {
        $errorMessage = $curlError;
    } elseif (is_string($response) && trim($response) !== "") {
        $errorMessage = trim($response);
    } elseif ($httpCode > 0) {
        $errorMessage = "Upload API returned HTTP " . $httpCode . ".";
    } else {
        $errorMessage = "Cloudinary upload failed.";
    }

    return [
        "ok" => false,
        "http_code" => $httpCode,
        "error" => $errorMessage,
        "response" => is_array($data) ? $data : null,
        "raw_response" => is_string($response) ? $response : null,
    ];
}



/**
 * Delete an asset from Cloudinary via the Admin API.
 *
 * @param  string $publicId     Cloudinary public ID
 * @param  string $resourceType "image" | "raw"
 * @return bool
 */
function cloudinaryDelete(string $publicId, string $resourceType): bool {
    if (cloudinaryMissingAdminConfig() !== []) {
        return false;
    }

    $credentials = cloudinaryAdminCredentials();
    $url  = "https://api.cloudinary.com/v1_1/" . cloudinaryConfigValue("CLOUDINARY_CLOUD_NAME")
          . "/resources/$resourceType/upload?public_ids[]=" . urlencode($publicId);
    $auth = base64_encode($credentials["api_key"] . ":" . $credentials["api_secret"]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ["Authorization: Basic $auth"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return false;
    $data = json_decode($response, true);
    return ($data["deleted"][$publicId] ?? "") === "deleted";
}

/**
 * Create a folder in Cloudinary and treat existing folders as success.
 *
 * @param  string $folderPath
 * @return array{ok:bool,created:bool,already_exists:bool,http_code:int,error?:string,response?:array|null,raw_response?:string|null}
 */
function cloudinaryCreateFolderDetailed(string $folderPath): array {
    if (cloudinaryMissingAdminConfig() !== []) {
        return [
            "ok" => false,
            "created" => false,
            "already_exists" => false,
            "http_code" => 0,
            "error" => "Cloudinary admin API is not configured.",
            "response" => null,
            "raw_response" => null,
        ];
    }

    $normalizedFolderPath = trim($folderPath, "/");

    if ($normalizedFolderPath === "") {
        return [
            "ok" => false,
            "created" => false,
            "already_exists" => false,
            "http_code" => 0,
            "error" => "Folder path is required.",
            "response" => null,
            "raw_response" => null,
        ];
    }

    $credentials = cloudinaryAdminCredentials();
    $encodedFolderPath = cloudinaryEncodeFolderPath($normalizedFolderPath);
    $url = "https://api.cloudinary.com/v1_1/" . cloudinaryConfigValue("CLOUDINARY_CLOUD_NAME") . "/folders/" . $encodedFolderPath;
    $auth = base64_encode($credentials["api_key"] . ":" . $credentials["api_secret"]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $auth"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = is_string($response) ? json_decode($response, true) : null;
    $errorMessage = "";

    if (is_array($data) && isset($data["error"]["message"])) {
        $errorMessage = (string) $data["error"]["message"];
    } elseif ($curlError !== "") {
        $errorMessage = $curlError;
    } elseif (is_string($response) && trim($response) !== "") {
        $errorMessage = trim($response);
    }

    if ($httpCode === 200 || $httpCode === 201) {
        return [
            "ok" => true,
            "created" => true,
            "already_exists" => false,
            "http_code" => $httpCode,
            "response" => is_array($data) ? $data : null,
            "raw_response" => is_string($response) ? $response : null,
        ];
    }

    if ($httpCode === 409 || stripos($errorMessage, "already exists") !== false) {
        return [
            "ok" => true,
            "created" => false,
            "already_exists" => true,
            "http_code" => $httpCode,
            "response" => is_array($data) ? $data : null,
            "raw_response" => is_string($response) ? $response : null,
        ];
    }

    if ($errorMessage === "") {
        $errorMessage = $httpCode > 0
            ? "Folder API returned HTTP " . $httpCode . "."
            : "Cloudinary folder creation failed.";
    }

    return [
        "ok" => false,
        "created" => false,
        "already_exists" => false,
        "http_code" => $httpCode,
        "error" => $errorMessage,
        "response" => is_array($data) ? $data : null,
        "raw_response" => is_string($response) ? $response : null,
    ];
}

function cloudinaryCreateFolder(string $folderPath): bool {
    $result = cloudinaryCreateFolderDetailed($folderPath);
    return $result["ok"] ?? false;
}

/**
 * Delete a folder in Cloudinary and treat missing folders as success.
 *
 * @param  string $folderPath
 * @return array{ok:bool,deleted:bool,already_missing:bool,http_code:int,error?:string,response?:array|null,raw_response?:string|null}
 */
function cloudinaryDeleteFolderDetailed(string $folderPath): array {
    if (cloudinaryMissingAdminConfig() !== []) {
        return [
            "ok" => false,
            "deleted" => false,
            "already_missing" => false,
            "http_code" => 0,
            "error" => "Cloudinary admin API is not configured.",
            "response" => null,
            "raw_response" => null,
        ];
    }

    $normalizedFolderPath = trim($folderPath, "/");

    if ($normalizedFolderPath === "") {
        return [
            "ok" => false,
            "deleted" => false,
            "already_missing" => false,
            "http_code" => 0,
            "error" => "Folder path is required.",
            "response" => null,
            "raw_response" => null,
        ];
    }

    $credentials = cloudinaryAdminCredentials();
    $encodedFolderPath = cloudinaryEncodeFolderPath($normalizedFolderPath);
    $url = "https://api.cloudinary.com/v1_1/" . cloudinaryConfigValue("CLOUDINARY_CLOUD_NAME") . "/folders/" . $encodedFolderPath;
    $auth = base64_encode($credentials["api_key"] . ":" . $credentials["api_secret"]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $auth"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = is_string($response) ? json_decode($response, true) : null;
    $errorMessage = "";

    if (is_array($data) && isset($data["error"]["message"])) {
        $errorMessage = (string) $data["error"]["message"];
    } elseif ($curlError !== "") {
        $errorMessage = $curlError;
    } elseif (is_string($response) && trim($response) !== "") {
        $errorMessage = trim($response);
    }

    if ($httpCode === 200) {
        return [
            "ok" => true,
            "deleted" => true,
            "already_missing" => false,
            "http_code" => $httpCode,
            "response" => is_array($data) ? $data : null,
            "raw_response" => is_string($response) ? $response : null,
        ];
    }

    if ($httpCode === 404 || stripos($errorMessage, "not found") !== false || stripos($errorMessage, "can't find folder") !== false) {
        return [
            "ok" => true,
            "deleted" => false,
            "already_missing" => true,
            "http_code" => $httpCode,
            "response" => is_array($data) ? $data : null,
            "raw_response" => is_string($response) ? $response : null,
        ];
    }

    if ($errorMessage === "") {
        $errorMessage = $httpCode > 0
            ? "Folder API returned HTTP " . $httpCode . "."
            : "Cloudinary folder deletion failed.";
    }

    return [
        "ok" => false,
        "deleted" => false,
        "already_missing" => false,
        "http_code" => $httpCode,
        "error" => $errorMessage,
        "response" => is_array($data) ? $data : null,
        "raw_response" => is_string($response) ? $response : null,
    ];
}

function cloudinaryBuildSignature(array $params, string $apiSecret): string {
    $signatureParams = [];

    foreach ($params as $key => $value) {
        if ($value === null || $value === "" || $key === "file") {
            continue;
        }

        $signatureParams[$key] = cloudinaryStringifyValue($value);
    }

    ksort($signatureParams);

    $parts = [];

    foreach ($signatureParams as $key => $value) {
        $parts[] = $key . "=" . $value;
    }

    return sha1(implode("&", $parts) . $apiSecret);
}

function cloudinaryStringifyValue($value): string {
    if (is_bool($value)) {
        return $value ? "true" : "false";
    }

    return (string) $value;
}

function cloudinaryConfigValue(string $name): string {
    if (!defined($name)) {
        return "";
    }

    return trim((string) constant($name));
}

function cloudinaryEncodeFolderPath(string $folderPath): string {
    $segments = array_filter(explode("/", trim($folderPath, "/")), static function ($segment) {
        return $segment !== "";
    });

    return implode("/", array_map("rawurlencode", $segments));
}

function cloudinaryMissingUploadConfig(): array {
    $missing = [];

    foreach (["CLOUDINARY_CLOUD_NAME", "CLOUDINARY_API_KEY", "CLOUDINARY_API_SECRET"] as $name) {
        if (cloudinaryConfigValue($name) === "") {
            $missing[] = $name;
        }
    }

    return $missing;
}

function cloudinaryMissingAdminConfig(): array {
    $missing = [];

    if (cloudinaryConfigValue("CLOUDINARY_CLOUD_NAME") === "") {
        $missing[] = "CLOUDINARY_CLOUD_NAME";
    }

    if (cloudinaryConfigValue("CLOUDINARY_ADMIN_API_KEY") === "" && cloudinaryConfigValue("CLOUDINARY_API_KEY") === "") {
        $missing[] = "CLOUDINARY_ADMIN_API_KEY";
    }

    if (cloudinaryConfigValue("CLOUDINARY_ADMIN_API_SECRET") === "" && cloudinaryConfigValue("CLOUDINARY_API_SECRET") === "") {
        $missing[] = "CLOUDINARY_ADMIN_API_SECRET";
    }

    return $missing;
}
