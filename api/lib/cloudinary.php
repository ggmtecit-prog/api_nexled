<?php

/**
 * Cloudinary helpers — no SDK, plain cURL.
 *
 * Expects these constants (defined in appdatasheets/config.php):
 *   CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET
 */



/**
 * Upload a local file to Cloudinary.
 *
 * @param  string $filePath     Temp path of the file (e.g. $_FILES["file"]["tmp_name"])
 * @param  string $publicId     Cloudinary public ID  (e.g. "nexled/photo/product/filename")
 * @param  string $resourceType "image" | "raw"
 * @return array|null           Cloudinary response, or null on failure
 */
function cloudinaryUpload(string $filePath, string $publicId, string $resourceType): ?array {
    $timestamp = time();

    $params  = ["public_id" => $publicId, "timestamp" => $timestamp];
    ksort($params);
    $signStr   = implode("&", array_map(fn($k, $v) => "$k=$v", array_keys($params), array_values($params)));
    $signature = sha1($signStr . CLOUDINARY_API_SECRET);

    $url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/$resourceType/upload";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     [
        "file"      => new CURLFile($filePath),
        "public_id" => $publicId,
        "api_key"   => CLOUDINARY_API_KEY,
        "timestamp" => $timestamp,
        "signature" => $signature,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);
    return isset($data["secure_url"]) ? $data : null;
}



/**
 * Delete an asset from Cloudinary via the Admin API.
 *
 * @param  string $publicId     Cloudinary public ID
 * @param  string $resourceType "image" | "raw"
 * @return bool
 */
function cloudinaryDelete(string $publicId, string $resourceType): bool {
    $url  = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME
          . "/resources/$resourceType/upload?public_ids[]=" . urlencode($publicId);
    $auth = base64_encode(CLOUDINARY_API_KEY . ":" . CLOUDINARY_API_SECRET);

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
