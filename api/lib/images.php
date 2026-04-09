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
