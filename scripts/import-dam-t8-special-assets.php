<?php

declare(strict_types=1);

require dirname(__DIR__) . '/api/bootstrap.php';

$root = dirname(__DIR__);
$defaultBaseUrl = 'http://127.0.0.1/api_nexled/api';
$defaultApiKey = '7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b';

$options = getopt('', ['base-url::', 'api-key::', 'dry-run']);
$baseUrl = rtrim((string) ($options['base-url'] ?? $defaultBaseUrl), '/');
$apiKey = (string) ($options['api-key'] ?? $defaultApiKey);
$dryRun = array_key_exists('dry-run', $options);

$familyCode = '01';

$ecoProductCodes = ['T8AL602s', 'T8AL902s', 'T8AL972s', 'T8AL1202s', 'T8AL1502s'];
$talhoPinkProductCodes = ['T8PINKAL443s', 'T8PINKAL603s', 'T8PINKAL903s', 'T8PINKAL973s', 'T8PINKAL1203s', 'T8PINKAL1503s'];
$plainPinkProductCodes = ['T8PINK233s', 'T8PINK443s', 'T8PINK603s', 'T8PINK903s', 'T8PINK1203s', 'T8PINK1503s'];
$allSpecialProductCodes = array_values(array_unique(array_merge(
    $ecoProductCodes,
    $talhoPinkProductCodes,
    $plainPinkProductCodes
)));

$existingFinishAssetId = findDamAssetIdByFilename('acabamento-t8-alu.png');
$existingDrawingAssetId = findDamAssetIdByFilename('t8-fixo.svg');

$packshots = [
    [
        'label' => 'eco-packshot',
        'file_path' => $root . '/new_data_img/T8/2025/T8_ECO.png',
        'upload_filename' => 'T8_ECO.png',
        'product_codes' => $ecoProductCodes,
    ],
    [
        'label' => 'talho-pink-packshot',
        'file_path' => $root . '/new_data_img/T8/Pink/T8_Pink_tecto.png',
        'upload_filename' => 'T8_Pink_tecto.png',
        'product_codes' => $talhoPinkProductCodes,
    ],
    [
        'label' => 'plain-pink-packshot',
        'file_path' => $root . '/new_data_img/T8/Pink/T8_Pink.png',
        'upload_filename' => 'T8_Pink.png',
        'product_codes' => $plainPinkProductCodes,
    ],
];

$summary = [
    'uploaded' => 0,
    'reused' => 0,
    'linked' => 0,
    'failed' => 0,
];

echo "T8 family 01 special asset import\n";
echo "Base URL: {$baseUrl}\n";
echo "Dry run: " . ($dryRun ? 'yes' : 'no') . "\n\n";

if ($existingFinishAssetId <= 0) {
    fwrite(STDERR, "Missing existing DAM finish asset: acabamento-t8-alu.png\n");
    exit(1);
}

if ($existingDrawingAssetId <= 0) {
    fwrite(STDERR, "Missing existing DAM drawing asset: t8-fixo.svg\n");
    exit(1);
}

echo "Reusing finish asset {$existingFinishAssetId} for " . count($allSpecialProductCodes) . " product codes\n";
foreach ($allSpecialProductCodes as $productCode) {
    if ($dryRun) {
        echo " - finish -> {$productCode} [dry-run]\n";
        continue;
    }

    $linkResult = linkDamAsset($baseUrl, $apiKey, $existingFinishAssetId, $familyCode, 'finish', $productCode);
    if ($linkResult['status'] !== 'linked') {
        $summary['failed'] += 1;
        echo " - finish -> {$productCode} [failed: {$linkResult['message']}]\n";
        continue;
    }

    $summary['linked'] += 1;
    echo " - finish -> {$productCode} [linked]\n";
}

echo "\nReusing drawing asset {$existingDrawingAssetId} for " . count($allSpecialProductCodes) . " product codes\n";
foreach ($allSpecialProductCodes as $productCode) {
    if ($dryRun) {
        echo " - drawing -> {$productCode} [dry-run]\n";
        continue;
    }

    $linkResult = linkDamAsset($baseUrl, $apiKey, $existingDrawingAssetId, $familyCode, 'drawing', $productCode);
    if ($linkResult['status'] !== 'linked') {
        $summary['failed'] += 1;
        echo " - drawing -> {$productCode} [failed: {$linkResult['message']}]\n";
        continue;
    }

    $summary['linked'] += 1;
    echo " - drawing -> {$productCode} [linked]\n";
}

echo "\nUploading/reusing packshots\n";
foreach ($packshots as $packshot) {
    if (!is_file($packshot['file_path'])) {
        $summary['failed'] += 1;
        echo " - {$packshot['label']} [missing file]\n";
        continue;
    }

    if ($dryRun) {
        echo " - {$packshot['label']} => {$packshot['upload_filename']} [dry-run]\n";
        continue;
    }

    $uploadResult = uploadOrReuseDamAsset(
        $baseUrl,
        $apiKey,
        'nexled/datasheet/packshots/generic',
        $packshot['file_path'],
        $packshot['upload_filename']
    );

    if ($uploadResult['status'] === 'failed') {
        $summary['failed'] += 1;
        echo " - {$packshot['label']} [failed upload: {$uploadResult['message']}]\n";
        continue;
    }

    if ($uploadResult['status'] === 'uploaded') {
        $summary['uploaded'] += 1;
    } else {
        $summary['reused'] += 1;
    }

    foreach ($packshot['product_codes'] as $productCode) {
        $linkResult = linkDamAsset($baseUrl, $apiKey, (int) $uploadResult['asset_id'], $familyCode, 'packshot', $productCode);

        if ($linkResult['status'] !== 'linked') {
            $summary['failed'] += 1;
            echo "   - {$productCode} [failed link: {$linkResult['message']}]\n";
            continue;
        }

        $summary['linked'] += 1;
        echo "   - {$productCode} [linked]\n";
    }
}

echo "\nSummary\n";
echo " uploaded: {$summary['uploaded']}\n";
echo " reused: {$summary['reused']}\n";
echo " linked: {$summary['linked']}\n";
echo " failed: {$summary['failed']}\n";

exit($summary['failed'] > 0 ? 1 : 0);

function findDamAssetIdByFilename(string $filename): int
{
    $con = connectDBDam();
    $stmt = mysqli_prepare(
        $con,
        "SELECT `id` FROM `dam_assets`
         WHERE `filename` = ? OR `display_name` = ?
         ORDER BY `id` DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'ss', $filename, $filename);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    closeDB($con);

    return (int) ($row['id'] ?? 0);
}

function uploadOrReuseDamAsset(string $baseUrl, string $apiKey, string $folderId, string $filePath, string $filename): array
{
    $url = $baseUrl . '/?endpoint=dam&action=upload';
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'folder_id' => $folderId,
        'file' => new CURLFile($filePath, null, $filename),
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'status' => 'failed',
            'message' => $curlError !== '' ? $curlError : 'curl failed',
        ];
    }

    $payload = json_decode($rawResponse, true);
    $assetId = (int) ($payload['data']['asset']['id'] ?? 0);

    if (($payload['ok'] ?? false) === true && ($httpCode === 200 || $httpCode === 201) && $assetId > 0) {
        return [
            'status' => 'uploaded',
            'asset_id' => $assetId,
            'message' => 'ok',
        ];
    }

    if ($httpCode === 409) {
        $existingId = (int) ($payload['error']['details']['id'] ?? 0);

        if ($existingId <= 0) {
            $existingId = findDamAssetIdByFilename($filename);
        }

        if ($existingId > 0) {
            return [
                'status' => 'reused',
                'asset_id' => $existingId,
                'message' => 'already exists',
            ];
        }
    }

    $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

    return [
        'status' => 'failed',
        'message' => $errorMessage !== '' ? $errorMessage : ('HTTP ' . $httpCode),
    ];
}

function linkDamAsset(string $baseUrl, string $apiKey, int $assetId, string $familyCode, string $role, string $productCode): array
{
    $url = $baseUrl . '/?endpoint=dam&action=link';
    $body = json_encode([
        'asset_id' => $assetId,
        'family_code' => $familyCode,
        'product_code' => $productCode,
        'role' => $role,
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'status' => 'failed',
            'message' => $curlError !== '' ? $curlError : 'curl failed',
        ];
    }

    $payload = json_decode($rawResponse, true);

    if (($payload['ok'] ?? false) === true && ($httpCode === 200 || $httpCode === 201)) {
        return [
            'status' => 'linked',
            'message' => 'ok',
        ];
    }

    $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

    return [
        'status' => 'failed',
        'message' => $errorMessage !== '' ? $errorMessage : ('HTTP ' . $httpCode),
    ];
}
