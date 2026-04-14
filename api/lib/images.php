<?php

/**
 * Image Utilities
 *
 * Shared helpers for finding product images on disk.
 * Used by product-header.php, technical-drawing.php, and others.
 */

// Base path to the image folder — change here when images move to their final location
if (!defined("IMAGES_BASE_PATH")) {
    define("IMAGES_BASE_PATH", dirname(__FILE__, 3) . "/appdatasheets");
}

if (!defined("PDF_RASTER_CACHE_PATH")) {
    define("PDF_RASTER_CACHE_PATH", sys_get_temp_dir() . DIRECTORY_SEPARATOR . "nexled-pdf-raster-cache");
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

        return $path . $ext;
    }

    return null;
}
