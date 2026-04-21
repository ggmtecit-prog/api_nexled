<?php

/**
 * Image Utilities
 *
 * Shared helpers for finding product images on disk.
 * Used by product-header.php, technical-drawing.php, and others.
 */

require_once dirname(__FILE__) . "/cloudinary.php";

// Base path to the image folder — change here when images move to their final location
if (!defined("IMAGES_BASE_PATH")) {
    define("IMAGES_BASE_PATH", dirname(__FILE__, 3) . "/appdatasheets");
}

if (!defined("DAM_RUNTIME_PRIMARY_FAMILIES")) {
    define("DAM_RUNTIME_PRIMARY_FAMILIES", ["11", "29", "30", "32", "48", "55", "58"]);
}

if (!defined("DAM_PRIMARY_SHARED_ROLES")) {
    define("DAM_PRIMARY_SHARED_ROLES", ["energy-label", "icon", "logo", "power-supply", "temperature"]);
}

if (!defined("PDF_RASTER_CACHE_PATH")) {
    define("PDF_RASTER_CACHE_PATH", sys_get_temp_dir() . DIRECTORY_SEPARATOR . "nexled-pdf-raster-cache");
}

if (!defined("PDF_REMOTE_CACHE_PATH")) {
    define("PDF_REMOTE_CACHE_PATH", PDF_RASTER_CACHE_PATH . DIRECTORY_SEPARATOR . "remote");
}

/**
 * Returns true when runtime should resolve this family from DAM only.
 *
 * @param  string|null $familyCode
 * @return bool
 */
function isDamPrimaryFamily(?string $familyCode): bool {
    $familyCode = trim((string) $familyCode);

    if ($familyCode === "") {
        return false;
    }

    return in_array($familyCode, DAM_RUNTIME_PRIMARY_FAMILIES, true);
}

/**
 * Returns true when shared datasheet assets should resolve from DAM only.
 *
 * @param  string $role
 * @return bool
 */
function isDamPrimarySharedRole(string $role): bool {
    $role = trim($role);

    if ($role === "") {
        return false;
    }

    return in_array($role, DAM_PRIMARY_SHARED_ROLES, true);
}



/**
 * Returns a PDF-safe image path.
 *
 * SVG files are rasterized to cached PNG files when a rasterizer is available.
 * Raster images are returned unchanged.
 *
 * @param  string $path  Full path with extension
 * @return string  PDF-safe path (original path or cached PNG path)
 */
function getPdfRenderableImagePath(string $path): string {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($extension !== "svg") {
        return $path;
    }

    $rasterizedPath = rasterizeSvgForPdf($path);
    return $rasterizedPath ?? $path;
}



/**
 * Rasterizes a local SVG file into a cached PNG for TCPDF.
 *
 * Railway cannot reliably render local SVG assets through HTML <img> tags.
 * When rsvg-convert or ImageMagick is available, we convert the SVG once and
 * reuse the cached PNG for subsequent PDF requests.
 *
 * @param  string $svgPath  Full path to the SVG file
 * @return string|null  Cached PNG path, or null when no rasterizer is available
 */
function rasterizeSvgForPdf(string $svgPath): ?string {
    if (!is_file($svgPath)) {
        return null;
    }

    $cacheDir = ensurePdfRasterCacheDirectory();

    if ($cacheDir === null) {
        return null;
    }

    $cacheKey = sha1($svgPath . "|" . filemtime($svgPath) . "|" . filesize($svgPath));
    $pngPath  = $cacheDir . DIRECTORY_SEPARATOR . pathinfo($svgPath, PATHINFO_FILENAME) . "-" . $cacheKey . ".png";

    if (is_file($pngPath) && filesize($pngPath) > 0) {
        return $pngPath;
    }

    if (rasterizeSvgWithRsvgConvert($svgPath, $pngPath)) {
        return $pngPath;
    }

    if (rasterizeSvgWithImageMagick($svgPath, $pngPath)) {
        return $pngPath;
    }

    return null;
}



/**
 * Ensures the PDF raster cache directory exists.
 *
 * @return string|null  Absolute cache path, or null if it cannot be created
 */
function ensurePdfRasterCacheDirectory(): ?string {
    if (is_dir(PDF_RASTER_CACHE_PATH)) {
        return PDF_RASTER_CACHE_PATH;
    }

    if (@mkdir(PDF_RASTER_CACHE_PATH, 0777, true) || is_dir(PDF_RASTER_CACHE_PATH)) {
        return PDF_RASTER_CACHE_PATH;
    }

    error_log("NexLed datasheet: unable to create raster cache directory at " . PDF_RASTER_CACHE_PATH);
    return null;
}

/**
 * Ensures the PDF remote-asset cache directory exists.
 *
 * @return string|null
 */
function ensurePdfRemoteCacheDirectory(): ?string {
    if (is_dir(PDF_REMOTE_CACHE_PATH)) {
        return PDF_REMOTE_CACHE_PATH;
    }

    if (@mkdir(PDF_REMOTE_CACHE_PATH, 0777, true) || is_dir(PDF_REMOTE_CACHE_PATH)) {
        return PDF_REMOTE_CACHE_PATH;
    }

    error_log("NexLed datasheet: unable to create remote cache directory at " . PDF_REMOTE_CACHE_PATH);
    return null;
}



/**
 * Rasterizes an SVG using rsvg-convert.
 *
 * @param  string $svgPath  Source SVG path
 * @param  string $pngPath  Target PNG path
 * @return bool  True when conversion succeeds
 */
function rasterizeSvgWithRsvgConvert(string $svgPath, string $pngPath): bool {
    $binary = findSystemCommand("rsvg-convert");

    if ($binary === null) {
        return false;
    }

    $command = escapeshellarg($binary)
        . " --background-color=white --keep-aspect-ratio --width=1800"
        . " --output " . escapeshellarg($pngPath)
        . " " . escapeshellarg($svgPath)
        . " 2>&1";

    exec($command, $output, $exitCode);

    if ($exitCode === 0 && is_file($pngPath) && filesize($pngPath) > 0) {
        return true;
    }

    if ($output !== []) {
        error_log("NexLed datasheet: rsvg-convert failed for {$svgPath}: " . implode(" | ", $output));
    }

    return false;
}



/**
 * Rasterizes an SVG using ImageMagick when available.
 *
 * @param  string $svgPath  Source SVG path
 * @param  string $pngPath  Target PNG path
 * @return bool  True when conversion succeeds
 */
function rasterizeSvgWithImageMagick(string $svgPath, string $pngPath): bool {
    $binary = findSystemCommand("magick");

    if ($binary !== null) {
        $command = escapeshellarg($binary)
            . " " . escapeshellarg($svgPath)
            . " -background white -alpha remove -density 300 "
            . escapeshellarg($pngPath)
            . " 2>&1";

        exec($command, $output, $exitCode);

        if ($exitCode === 0 && is_file($pngPath) && filesize($pngPath) > 0) {
            return true;
        }

        if ($output !== []) {
            error_log("NexLed datasheet: magick failed for {$svgPath}: " . implode(" | ", $output));
        }
    }

    $legacyConvert = findSystemCommand("convert");

    if ($legacyConvert === null) {
        return false;
    }

    $command = escapeshellarg($legacyConvert)
        . " " . escapeshellarg($svgPath)
        . " -background white -alpha remove -density 300 "
        . escapeshellarg($pngPath)
        . " 2>&1";

    exec($command, $output, $exitCode);

    if ($exitCode === 0 && is_file($pngPath) && filesize($pngPath) > 0) {
        return true;
    }

    if ($output !== []) {
        error_log("NexLed datasheet: convert failed for {$svgPath}: " . implode(" | ", $output));
    }

    return false;
}



/**
 * Finds a command on the current system PATH.
 *
 * @param  string $command  Binary name (e.g. "rsvg-convert")
 * @return string|null  Absolute path to the command, or null if unavailable
 */
function findSystemCommand(string $command): ?string {
    $lookupCommand = PHP_OS_FAMILY === "Windows"
        ? "where " . escapeshellarg($command) . " 2>NUL"
        : "command -v " . escapeshellarg($command) . " 2>/dev/null";

    exec($lookupCommand, $output, $exitCode);

    if ($exitCode !== 0 || $output === []) {
        return null;
    }

    $binary = trim((string) $output[0]);

    if ($binary === "") {
        return null;
    }

    if (
        PHP_OS_FAMILY === "Windows" &&
        strtolower($command) === "convert" &&
        str_ends_with(strtolower(str_replace("\\", "/", $binary)), "/windows/system32/convert.exe")
    ) {
        return null;
    }

    return $binary;
}



/**
 * Tries to find an image file by checking multiple extensions.
 *
 * Tries: .png, .jpg, .jpeg, .svg — in that order.
 * For PNG files: flattens transparency to a white background,
 * because the PDF library (TCPDF) does not support transparent PNGs.
 *
 * @param  string $path  Full path without extension
 * @return string|null   Full path with extension if found, null otherwise
 */
function findImage(string $path): ?string {
    static $cache = [];

    if (array_key_exists($path, $cache)) {
        return $cache[$path];
    }

    foreach ([".png", ".jpg", ".jpeg", ".svg"] as $ext) {
        if (!file_exists($path . $ext)) {
            continue;
        }

        if ($ext === ".png" && extension_loaded("gd")) {
            $src   = imagecreatefrompng($path . $ext);
            $w     = imagesx($src);
            $h     = imagesy($src);
            $flat  = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($flat, 255, 255, 255);
            imagefill($flat, 0, 0, $white);
            imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
            imagepng($flat, $path . $ext);
            imagedestroy($src);
            imagedestroy($flat);
        }

        return $cache[$path] = ($path . $ext);
    }

    return $cache[$path] = null;
}

/**
 * Returns true when the value points to a remote HTTP(S) asset.
 *
 * @param  string $value
 * @return bool
 */
function isRemoteAssetUrl(string $value): bool {
    return preg_match("#^https?://#i", trim($value)) === 1;
}

/**
 * Converts a Cloudinary SVG delivery URL into a PNG delivery URL for PDF use.
 *
 * @param  string $url
 * @return string
 */
function getCloudinaryRasterizedUrl(string $url): string {
    $normalizedUrl = trim($url);

    if (
        !isRemoteAssetUrl($normalizedUrl) ||
        stripos($normalizedUrl, "cloudinary.com") === false ||
        !preg_match("/\\.svg(?:\\?|$)/i", $normalizedUrl)
    ) {
        return $url;
    }

    $transformedUrl = preg_replace("#/upload/#", "/upload/f_png/", $normalizedUrl, 1);

    if (!is_string($transformedUrl) || $transformedUrl === "") {
        return $url;
    }

    $transformedUrl = preg_replace("/\\.svg(\\?.*)?$/i", ".png$1", $transformedUrl);
    return is_string($transformedUrl) && $transformedUrl !== "" ? $transformedUrl : $url;
}

/**
 * Downloads a remote DAM asset into a temp cache so TCPDF can consume it.
 *
 * @param  string $url
 * @return string|null
 */
function cacheRemoteAssetForPdf(string $url): ?string {
    if (!isRemoteAssetUrl($url)) {
        return null;
    }

    $cacheDir = ensurePdfRemoteCacheDirectory();

    if ($cacheDir === null) {
        return null;
    }

    $parsedPath = (string) (parse_url($url, PHP_URL_PATH) ?? "");
    $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
    $allowedExtensions = ["png", "jpg", "jpeg", "gif", "svg", "webp", "pdf"];

    if (!in_array($extension, $allowedExtensions, true)) {
        $extension = "bin";
    }

    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . sha1($url) . "." . $extension;

    if (is_file($cachePath) && filesize($cachePath) > 0) {
        return $cachePath;
    }

    $content = null;
    $contentType = null;

    if (function_exists("curl_init")) {
        $curl = curl_init($url);

        if ($curl !== false) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_USERAGENT, "NexLed PDF Asset Resolver");
            curl_setopt($curl, CURLOPT_HEADER, true);

            $response = curl_exec($curl);

            if (is_string($response)) {
                $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $content = substr($response, $headerSize);
                $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            }

            curl_close($curl);
        }
    }

    if (!is_string($content)) {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "timeout" => 15,
                "ignore_errors" => true,
                "header" => "User-Agent: NexLed PDF Asset Resolver\r\n",
            ],
        ]);

        $download = @file_get_contents($url, false, $context);

        if (is_string($download)) {
            $content = $download;

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (stripos($headerLine, "Content-Type:") === 0) {
                        $contentType = trim(substr($headerLine, strlen("Content-Type:")));
                        break;
                    }
                }
            }
        }
    }

    if (!is_string($content) || $content === "") {
        return null;
    }

    if ($extension === "bin") {
        $contentType = strtolower(trim((string) $contentType));
        $extension = match (true) {
            str_contains($contentType, "image/svg") => "svg",
            str_contains($contentType, "image/png") => "png",
            str_contains($contentType, "image/jpeg") => "jpg",
            str_contains($contentType, "image/gif") => "gif",
            str_contains($contentType, "image/webp") => "webp",
            str_contains($contentType, "application/pdf") => "pdf",
            default => "bin",
        };
        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . sha1($url) . "." . $extension;
    }

    if (@file_put_contents($cachePath, $content) === false) {
        return null;
    }

    return $cachePath;
}

/**
 * Normalizes a PDF asset path so remote DAM URLs become local cache files.
 *
 * @param  string $path
 * @return string
 */
function getPdfSafeAssetPath(string $path): string {
    $resolvedPath = trim($path);

    if ($resolvedPath === "") {
        return $path;
    }

    if (isRemoteAssetUrl($resolvedPath)) {
        $cachedPath = cacheRemoteAssetForPdf(getCloudinaryRasterizedUrl($resolvedPath));

        if ($cachedPath !== null) {
            $resolvedPath = $cachedPath;
        } else {
            return $path;
        }
    }

    return getPdfRenderableImagePath($resolvedPath);
}

/**
 * Builds conservative product-slug candidates from an internal product ID.
 *
 * Examples:
 * - ShelfLED/24v/47      -> shelfled-24v-47, shelfled-47
 * - ShelfLED/24v/47/Eco  -> shelfled-24v-47-eco, shelfled-47-eco, shelfled-47
 *
 * These candidates are only used for DAM lookups and must stay permissive
 * enough to match existing intake choices without inventing new product data.
 *
 * @param  string $productId
 * @return array<int, string>
 */
function buildProductSlugCandidates(string $productId): array {
    $parts = array_values(array_filter(array_map(
        static fn($value) => trim((string) $value),
        explode("/", $productId)
    )));

    if ($parts === []) {
        return [];
    }

    $candidates = [];
    $count = count($parts);
    $joinedWithDashes = implode("-", $parts);
    $joinedCompact = implode("", $parts);

    $candidates[] = nexledNormalizeAssetStem($joinedWithDashes);
    $candidates[] = nexledNormalizeAssetStem($joinedCompact);

    if ($count >= 3) {
        $candidates[] = nexledNormalizeAssetStem($parts[0] . "-" . $parts[2]);
        $candidates[] = nexledNormalizeAssetStem($parts[0] . $parts[2]);
    }

    if ($count >= 4) {
        $candidates[] = nexledNormalizeAssetStem($parts[0] . "-" . $parts[2] . "-" . $parts[3]);
        $candidates[] = nexledNormalizeAssetStem($parts[0] . $parts[2] . $parts[3]);
    }

    if ($count >= 2) {
        $candidates[] = nexledNormalizeAssetStem($parts[0] . "-" . $parts[1]);
        $candidates[] = nexledNormalizeAssetStem($parts[0] . $parts[1]);
    }

    return array_values(array_filter(array_unique($candidates)));
}

/**
 * Finds a product asset in DAM using family/kind plus conservative slug/filename matching.
 *
 * This is a fallback only when legacy local assets are missing.
 * It never invents assets: no match means null.
 *
 * @param  string $familyCode
 * @param  string $productId
 * @param  string $kind
 * @param  array<int, string> $filenameCandidates
 * @return string|null
 */
function findDamProductAsset(string $familyCode, string $productId, string $kind, array $filenameCandidates = []): ?string {
    static $cache = [];
    static $linkRowsCache = [];
    static $sharedConnection = null;

    $cacheKey = implode("|", [
        trim($familyCode),
        trim($productId),
        trim($kind),
        implode(",", $filenameCandidates),
    ]);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!function_exists("tryConnectDBDam")) {
        return $cache[$cacheKey] = null;
    }

    if (!$sharedConnection instanceof mysqli) {
        $sharedConnection = tryConnectDBDam();
    }

    $con = $sharedConnection;

    if (!$con instanceof mysqli) {
        return $cache[$cacheKey] = null;
    }

    $familyCode = trim($familyCode);
    $kind = trim($kind);

    if ($familyCode === "" || $kind === "") {
        return $cache[$cacheKey] = null;
    }

    $roleMap = [
        "product_media_packshot" => "packshot",
        "technical_finish" => "finish",
        "technical_diagram" => "diagram",
        "technical_drawing" => "drawing",
    ];
    $role = $roleMap[$kind] ?? $kind;
    $rowsCacheKey = $familyCode . "|" . $role;

    if (!array_key_exists($rowsCacheKey, $linkRowsCache)) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT a.`filename`, a.`display_name`, a.`public_id`, a.`secure_url`, l.`product_code`
             FROM `dam_asset_links` l
             JOIN `dam_assets` a ON a.`id` = l.`asset_id`
             WHERE a.`resource_type` = 'image'
               AND l.`family_code` = ?
               AND l.`role` = ?
             ORDER BY l.`sort_order` ASC, a.`id` DESC
             LIMIT 200"
        );

        if (!$stmt) {
            return $cache[$cacheKey] = null;
        }

        mysqli_stmt_bind_param($stmt, "ss", $familyCode, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $linkRowsCache[$rowsCacheKey] = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $linkRowsCache[$rowsCacheKey][] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }

    $slugCandidates = buildProductSlugCandidates($productId);
    $stemCandidates = array_values(array_filter(array_unique(array_map(
        "nexledNormalizeAssetStem",
        $filenameCandidates
    ))));

    $bestUrl = null;
    $bestScore = 0;

    foreach ($linkRowsCache[$rowsCacheKey] as $row) {
        $secureUrl = trim((string) ($row["secure_url"] ?? ""));

        if ($secureUrl === "") {
            continue;
        }

        $score = 0;
        $rowFilename = nexledNormalizeAssetStem((string) ($row["filename"] ?? ""));
        $rowDisplayName = nexledNormalizeAssetStem((string) ($row["display_name"] ?? ""));
        $rowPublicId = nexledNormalizeAssetStem((string) ($row["public_id"] ?? ""));
        $rowProductCode = nexledNormalizeAssetStem((string) ($row["product_code"] ?? ""));

        if ($rowProductCode !== "" && in_array($rowProductCode, $slugCandidates, true)) {
            $score += 40;
        }

        foreach ($stemCandidates as $stemCandidate) {
            if ($stemCandidate === "") {
                continue;
            }

            if ($rowFilename === $stemCandidate) {
                $score += 60;
                break;
            }

            if (
                str_ends_with($rowFilename, "-" . $stemCandidate) ||
                str_ends_with($rowFilename, $stemCandidate)
            ) {
                $score += 55;
                break;
            }

            if ($rowDisplayName === $stemCandidate) {
                $score += 50;
                break;
            }

            if (
                str_ends_with($rowDisplayName, "-" . $stemCandidate) ||
                str_ends_with($rowDisplayName, $stemCandidate)
            ) {
                $score += 45;
                break;
            }

            if (str_contains($rowPublicId, strtolower($stemCandidate))) {
                $score += 20;
                break;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestUrl = $secureUrl;
        }
    }

    return $cache[$cacheKey] = ($bestScore > 0 ? $bestUrl : null);
}

/**
 * Finds a shared DAM asset by role plus filename/display/public_id matching.
 *
 * Shared assets are not linked through dam_asset_links. They are selected
 * directly from dam_assets by `kind`.
 *
 * @param  string $role
 * @param  array<int, string> $filenameCandidates
 * @param  array<int, string> $preferredFormats
 * @return string|null
 */
function findDamSharedAsset(string $role, array $filenameCandidates = [], array $preferredFormats = []): ?string {
    static $cache = [];
    static $sharedRowsCache = [];
    static $sharedConnection = null;

    $cacheKey = implode("|", [
        trim($role),
        implode(",", $filenameCandidates),
        implode(",", $preferredFormats),
    ]);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!function_exists("tryConnectDBDam")) {
        return $cache[$cacheKey] = null;
    }

    if (!$sharedConnection instanceof mysqli) {
        $sharedConnection = tryConnectDBDam();
    }

    $con = $sharedConnection;

    if (!$con instanceof mysqli) {
        return $cache[$cacheKey] = null;
    }

    $role = trim($role);

    if ($role === "") {
        return $cache[$cacheKey] = null;
    }

    if (!array_key_exists($role, $sharedRowsCache)) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT `filename`, `display_name`, `public_id`, `secure_url`, `format`
             FROM `dam_assets`
             WHERE `resource_type` = 'image'
               AND `kind` = ?
             ORDER BY `id` DESC
             LIMIT 300"
        );

        if (!$stmt) {
            return $cache[$cacheKey] = null;
        }

        mysqli_stmt_bind_param($stmt, "s", $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $sharedRowsCache[$role] = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sharedRowsCache[$role][] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }

    $stemCandidates = array_values(array_filter(array_unique(array_map(
        "nexledNormalizeAssetStem",
        $filenameCandidates
    ))));

    $formatPriority = array_values(array_filter(array_map(
        static fn($value) => strtolower(trim((string) $value)),
        $preferredFormats
    )));

    $bestUrl = null;
    $bestScore = 0;

    foreach ($sharedRowsCache[$role] as $row) {
        $secureUrl = trim((string) ($row["secure_url"] ?? ""));

        if ($secureUrl === "") {
            continue;
        }

        $score = 0;
        $rowFilename = nexledNormalizeAssetStem((string) ($row["filename"] ?? ""));
        $rowDisplayName = nexledNormalizeAssetStem((string) ($row["display_name"] ?? ""));
        $rowPublicId = strtolower((string) ($row["public_id"] ?? ""));
        $rowFormat = strtolower(trim((string) ($row["format"] ?? "")));

        foreach ($stemCandidates as $stemCandidate) {
            if ($stemCandidate === "") {
                continue;
            }

            if ($rowFilename === $stemCandidate) {
                $score += 80;
                break;
            }

            if ($rowDisplayName === $stemCandidate) {
                $score += 70;
                break;
            }

            if (str_contains($rowPublicId, strtolower($stemCandidate))) {
                $score += 30;
                break;
            }
        }

        foreach ($formatPriority as $index => $preferredFormat) {
            if ($rowFormat === $preferredFormat) {
                $score += max(1, 12 - $index);
                break;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestUrl = $secureUrl;
        }
    }

    return $cache[$cacheKey] = ($bestScore > 0 ? $bestUrl : null);
}

/**
 * Resolves a shared asset from DAM first, then local disk.
 *
 * @param  string $role
 * @param  array<int, string> $filenameCandidates
 * @param  string|null $localBasePath
 * @param  array<int, string> $preferredFormats
 * @return string|null
 */
function findDamOrLocalSharedAsset(string $role, array $filenameCandidates, ?string $localBasePath = null, array $preferredFormats = []): ?string {
    $damAsset = findDamSharedAsset($role, $filenameCandidates, $preferredFormats);

    if ($damAsset !== null) {
        return $damAsset;
    }

    if ($localBasePath !== null && trim($localBasePath) !== "") {
        return findImage($localBasePath);
    }

    return null;
}

/**
 * Normalizes a filename/slug candidate into a comparable asset stem.
 *
 * @param  string $value
 * @return string
 */
function nexledNormalizeAssetStem(string $value): string {
    $value = trim($value);

    if ($value === "") {
        return "";
    }

    $value = basename($value);
    $value = preg_replace("/\\.[a-z0-9]+$/i", "", $value) ?? $value;
    $value = strtolower($value);
    $value = preg_replace("/[^a-z0-9]+/", "-", $value) ?? "";
    return trim($value, "-");
}
