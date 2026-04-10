<?php

// GET    /api/?endpoint=assets&action=get&product=XX[&type=photo|drawing|datasheet]
// POST   /api/?endpoint=assets&action=upload
//          multipart body: file (binary), product_code (string), type (photo|drawing|datasheet)
// DELETE /api/?endpoint=assets&action=delete&id=123

/*
 * Run this SQL once in info_nexled_2024 to create the assets table:
 *
 * CREATE TABLE assets (
 *     id            INT AUTO_INCREMENT PRIMARY KEY,
 *     product_code  VARCHAR(20)  NOT NULL,
 *     type          ENUM('photo','drawing','datasheet') NOT NULL,
 *     filename      VARCHAR(255) NOT NULL,
 *     public_id     VARCHAR(255) NOT NULL,
 *     resource_type VARCHAR(20)  NOT NULL DEFAULT 'image',
 *     url           VARCHAR(500) NOT NULL,
 *     created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     INDEX idx_product (product_code),
 *     INDEX idx_type    (type)
 * );
 */

require_once dirname(__FILE__, 2) . "/lib/cloudinary.php";

$action = $_GET["action"] ?? null;

switch ($action) {
    case "get":    assetGet();    break;
    case "upload": assetUpload(); break;
    case "delete": assetDelete(); break;
    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid or missing action. Use: get, upload, delete"]);
        break;
}



// ---------------------------------------------------------------------------
// GET — list assets for a product
// ---------------------------------------------------------------------------

function assetGet(): void {
    $productCode = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET["product"] ?? "");
    $typeFilter  = $_GET["type"] ?? null;

    if (!$productCode) {
        http_response_code(400);
        echo json_encode(["error" => "Missing product parameter"]);
        return;
    }

    $con = connectDBInf();

    $validTypes = ["photo", "drawing", "datasheet"];

    if ($typeFilter && in_array($typeFilter, $validTypes)) {
        $stmt = mysqli_prepare($con,
            "SELECT id, type, filename, url, created_at
             FROM assets
             WHERE product_code = ? AND type = ?
             ORDER BY created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, "ss", $productCode, $typeFilter);
    } else {
        $stmt = mysqli_prepare($con,
            "SELECT id, type, filename, url, created_at
             FROM assets
             WHERE product_code = ?
             ORDER BY type, created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, "s", $productCode);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $assets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $assets[] = $row;
    }

    closeDB($con);

    echo json_encode(["product" => $productCode, "assets" => $assets]);
}



// ---------------------------------------------------------------------------
// UPLOAD — upload a file to Cloudinary and save metadata to DB
// ---------------------------------------------------------------------------

function assetUpload(): void {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }

    $productCode = preg_replace("/[^a-zA-Z0-9_-]/", "", $_POST["product_code"] ?? "");
    $type        = $_POST["type"] ?? "";

    $allowedExtensions = [
        "photo"     => ["jpg", "jpeg", "png", "webp"],
        "drawing"   => ["svg", "png", "pdf", "dxf", "dwg"],
        "datasheet" => ["pdf"],
    ];

    if (!$productCode) {
        http_response_code(400);
        echo json_encode(["error" => "Missing product_code"]);
        return;
    }

    if (!array_key_exists($type, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid type. Use: photo, drawing, datasheet"]);
        return;
    }

    if (empty($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["error" => "No file uploaded or upload error"]);
        return;
    }

    $file     = $_FILES["file"];
    $ext      = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $baseName = pathinfo($file["name"], PATHINFO_FILENAME);
    $safeName = preg_replace("/[^a-zA-Z0-9_-]/", "", $baseName) ?: uniqid("asset_");

    if (!in_array($ext, $allowedExtensions[$type])) {
        http_response_code(400);
        echo json_encode([
            "error"   => "Extension .$ext is not allowed for type '$type'",
            "allowed" => $allowedExtensions[$type],
        ]);
        return;
    }

    $imageExts    = ["jpg", "jpeg", "png", "webp", "gif", "svg"];
    $resourceType = in_array($ext, $imageExts) ? "image" : "raw";
    $publicId     = "nexled/$type/$productCode/$safeName";
    $filename     = "$safeName.$ext";

    $cloudResult = cloudinaryUpload($file["tmp_name"], $publicId, $resourceType);

    if (!$cloudResult) {
        http_response_code(500);
        echo json_encode(["error" => "Cloudinary upload failed"]);
        return;
    }

    $url = $cloudResult["secure_url"];

    $con  = connectDBInf();
    $stmt = mysqli_prepare($con,
        "INSERT INTO assets (product_code, type, filename, public_id, resource_type, url)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssssss", $productCode, $type, $filename, $publicId, $resourceType, $url);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($con);
    closeDB($con);

    echo json_encode([
        "id"           => $id,
        "product_code" => $productCode,
        "type"         => $type,
        "filename"     => $filename,
        "url"          => $url,
    ]);
}



// ---------------------------------------------------------------------------
// DELETE — remove from Cloudinary and DB
// ---------------------------------------------------------------------------

function assetDelete(): void {
    if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }

    $id = intval($_GET["id"] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing id parameter"]);
        return;
    }

    $con  = connectDBInf();
    $stmt = mysqli_prepare($con, "SELECT public_id, resource_type FROM assets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        closeDB($con);
        http_response_code(404);
        echo json_encode(["error" => "Asset not found"]);
        return;
    }

    $row          = mysqli_fetch_assoc($result);
    $publicId     = $row["public_id"];
    $resourceType = $row["resource_type"];

    if (!cloudinaryDelete($publicId, $resourceType)) {
        closeDB($con);
        http_response_code(500);
        echo json_encode(["error" => "Cloudinary delete failed"]);
        return;
    }

    $stmt = mysqli_prepare($con, "DELETE FROM assets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    closeDB($con);

    echo json_encode(["deleted" => true, "id" => $id]);
}
