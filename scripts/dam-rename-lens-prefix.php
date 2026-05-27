<?php

/**
 * DAM Asset Rename — Lens-Code Prefix Migration
 *
 * Adds lens-code prefix to packshot + finish asset filenames so the DAM lookup
 * in findDamProductAsset() can discriminate between lenses unambiguously.
 *
 * Old naming: alu_01 (in nexled/datasheet/packshots/clear/)
 * New naming: l1s1_alu_01 (same folder — clear/series-1 → prefix l1s1)
 *
 * Run: php scripts/dam-rename-lens-prefix.php [--family=11] [--dry-run] [--role=packshot]
 *
 * --family   2-digit family code to process (required)
 * --dry-run  Preview changes without renaming
 * --role     "packshot", "finish", or "all" (default: all)
 */

define("BASE_PATH", dirname(__DIR__));
require_once BASE_PATH . "/api/bootstrap.php";
require_once BASE_PATH . "/api/lib/cloudinary.php";

// ---------------------------------------------------------------------------
// CLI argument parsing
// ---------------------------------------------------------------------------

$opts = getopt("", ["family:", "dry-run", "role:"]);

$family  = isset($opts["family"]) ? str_pad(trim((string) $opts["family"]), 2, "0", STR_PAD_LEFT) : "";
$dryRun  = isset($opts["dry-run"]);
$roleArg = strtolower(trim((string) ($opts["role"] ?? "all")));

if ($family === "" || !preg_match('/^\d{2}$/', $family)) {
    fwrite(STDERR, "Error: --family is required (2-digit code, e.g. --family=11)\n");
    exit(1);
}

$roles = match ($roleArg) {
    "packshot" => ["packshot"],
    "finish"   => ["finish"],
    default    => ["packshot", "finish"],
};

echo ($dryRun ? "[DRY RUN] " : "") . "Family: $family | Roles: " . implode(", ", $roles) . "\n\n";

// ---------------------------------------------------------------------------
// Lens folder → (lens_code, series_code) mapping
//
// Based on the DAM folder model established during family rollout.
// "clear"   → lens 1 / series 1 (default HE series)
// "clear-2" → lens 1 / series 2
// "clear-4" → lens 1 / series 4  (add more as needed)
// "frost"   → lens 2 / no series
// "frostc"  → lens 3 / no series
// "generic" → lens 0 / no series (no-lens products)
// ---------------------------------------------------------------------------

const LENS_FOLDER_MAP = [
    "clear"     => ["lens" => "1", "series" => "1"],
    "clear-2"   => ["lens" => "1", "series" => "2"],
    "clear-4"   => ["lens" => "1", "series" => "4"],
    "frostc"    => ["lens" => "3", "series" => ""],
    "frost"     => ["lens" => "2", "series" => ""],
    "generic"   => ["lens" => "0", "series" => ""],
];

function lensPrefix(string $folderName): ?string {
    $folderName = strtolower(trim($folderName));
    $map = LENS_FOLDER_MAP;

    if (!array_key_exists($folderName, $map)) {
        return null; // unknown folder — skip
    }

    $entry  = $map[$folderName];
    $lc     = $entry["lens"];
    $series = $entry["series"];

    return $series !== "" ? "l{$lc}s{$series}" : "l{$lc}";
}

// ---------------------------------------------------------------------------
// Query: all packshot/finish assets linked for this family
// ---------------------------------------------------------------------------

$con = connectDBDam();

if (!$con instanceof mysqli) {
    fwrite(STDERR, "Error: DAM DB connection failed.\n");
    exit(1);
}

foreach ($roles as $role) {
    echo "=== Role: $role ===\n";

    $stmt = mysqli_prepare($con, "
        SELECT
            a.`id`           AS asset_id,
            a.`filename`     AS filename,
            a.`display_name` AS display_name,
            a.`public_id`    AS public_id,
            a.`secure_url`   AS secure_url,
            f.`path`         AS folder_path
        FROM `dam_asset_links` l
        JOIN `dam_assets`  a ON a.`id`       = l.`asset_id`
        LEFT JOIN `dam_folders` f ON f.`folder_id` = a.`folder_id`
        WHERE l.`family_code` = ?
          AND l.`role` = ?
        ORDER BY a.`id` ASC
    ");

    if (!$stmt) {
        fwrite(STDERR, "Prepare failed: " . mysqli_error($con) . "\n");
        continue;
    }

    mysqli_stmt_bind_param($stmt, "ss", $family, $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows   = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);

    echo "Found " . count($rows) . " assets.\n";

    $renamed  = 0;
    $skipped  = 0;
    $errors   = 0;
    $alreadyDone = 0;

    foreach ($rows as $row) {
        $assetId    = (int) $row["asset_id"];
        $filename   = (string) $row["filename"];
        $displayName = (string) $row["display_name"];
        $publicId   = (string) $row["public_id"];
        $secureUrl  = (string) $row["secure_url"];
        $folderPath = (string) $row["folder_path"];

        // Determine lens folder from the end of the path
        $folderSegments = array_filter(explode("/", $folderPath));
        $lensFolder     = strtolower((string) (end($folderSegments) ?: ""));
        $prefix         = lensPrefix($lensFolder);

        if ($prefix === null) {
            echo "  SKIP  [asset_id=$assetId] $filename — unknown lens folder '$lensFolder'\n";
            $skipped++;
            continue;
        }

        // Skip if already prefixed
        if (str_starts_with($filename, $prefix . "_")) {
            echo "  DONE  [asset_id=$assetId] $filename — already has prefix '$prefix'\n";
            $alreadyDone++;
            continue;
        }

        // Build new names
        $newFilename    = $prefix . "_" . $filename;
        $newDisplayName = $prefix . "_" . ($displayName !== "" ? $displayName : $filename);

        // public_id = folder/filename_without_extension (Cloudinary convention)
        // Replace only the last segment of public_id
        $pidParts        = explode("/", $publicId);
        $oldBasename     = (string) array_pop($pidParts);
        $newBasename     = $prefix . "_" . $oldBasename;
        $newPublicId     = implode("/", $pidParts) . "/" . $newBasename;

        echo "  RENAME [asset_id=$assetId] $filename → $newFilename\n";
        echo "         public_id: $publicId → $newPublicId\n";

        if ($dryRun) {
            $renamed++;
            continue;
        }

        // --- Cloudinary rename ---
        $newSecureUrl = cloudinaryRename($publicId, $newPublicId);

        if ($newSecureUrl === null) {
            fwrite(STDERR, "  ERROR  Cloudinary rename failed for asset_id=$assetId ($publicId)\n");
            $errors++;
            continue;
        }

        // --- DB update ---
        $upd = mysqli_prepare($con, "
            UPDATE `dam_assets`
            SET `filename` = ?, `display_name` = ?, `public_id` = ?, `secure_url` = ?
            WHERE `id` = ?
        ");

        if (!$upd) {
            fwrite(STDERR, "  ERROR  DB prepare failed for asset_id=$assetId\n");
            $errors++;
            continue;
        }

        mysqli_stmt_bind_param($upd, "ssssi", $newFilename, $newDisplayName, $newPublicId, $newSecureUrl, $assetId);
        $ok = mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        if (!$ok) {
            fwrite(STDERR, "  ERROR  DB update failed for asset_id=$assetId\n");
            $errors++;
            continue;
        }

        $renamed++;
    }

    echo "\nSummary for $role: renamed=$renamed  already_done=$alreadyDone  skipped=$skipped  errors=$errors\n\n";
}

closeDB($con);

echo ($dryRun ? "[DRY RUN COMPLETE — no changes made]\n" : "Migration complete.\n");
