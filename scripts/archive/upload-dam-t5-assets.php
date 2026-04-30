<?php

declare(strict_types=1);

require dirname(__DIR__) . '/api/bootstrap.php';
require dirname(__DIR__) . '/api/lib/cloudinary.php';

$root = dirname(__DIR__);

$assets = [
    [
        'folder' => 'nexled/datasheet/packshots/generic',
        'name' => 'T5_Clear_Alu.png',
        'file' => $root . '/new_data_img/T5/T5 Clear Alu.png',
    ],
    [
        'folder' => 'nexled/datasheet/packshots/generic',
        'name' => 'T5_Clear_Alu_LB.png',
        'file' => $root . '/new_data_img/T5/T5 Clear Alu LB.png',
    ],
    [
        'folder' => 'nexled/datasheet/packshots/generic',
        'name' => 'T5_Frost_Alu.png',
        'file' => $root . '/new_data_img/T5/T5 Frost Alu.png',
    ],
    [
        'folder' => 'nexled/datasheet/packshots/generic',
        'name' => 'T5_Frost_Alu_LB.png',
        'file' => $root . '/new_data_img/T5/T5 Frost Alu LB.png',
    ],
    [
        'folder' => 'nexled/datasheet/finishes/clear',
        'name' => 'acabamento-t5-alu.png',
        'file' => $root . '/new_data_img/T5/acabamentos/T5 acabamento alu.png',
    ],
    [
        'folder' => 'nexled/datasheet/finishes/frost',
        'name' => 'acabamento-t5-alu.png',
        'file' => $root . '/new_data_img/T5/acabamentos/T5 acabamento alu.png',
    ],
    [
        'folder' => 'nexled/datasheet/drawings',
        'name' => 't5.svg',
        'file' => $root . '/new_data_img/T5/desenhos/catalogo/desenho t5.svg',
    ],
    [
        'folder' => 'nexled/datasheet/drawings',
        'name' => 't5_sfio.svg',
        'file' => $root . '/new_data_img/T5/desenhos/catalogo/ligacao xx02zz.svg',
    ],
    [
        'folder' => 'nexled/datasheet/diagrams',
        'name' => 't5_clear.svg',
        'file' => $root . '/new_data_img/T5/diagramas/diagrama t5 clear.svg',
    ],
    [
        'folder' => 'nexled/datasheet/diagrams',
        'name' => 't5_frost.svg',
        'file' => $root . '/new_data_img/T5/diagramas/diagrama t5 frost.svg',
    ],
];

$failures = 0;

foreach ($assets as $asset) {
    $file = $asset['file'];
    $folder = $asset['folder'];
    $name = $asset['name'];

    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        $failures += 1;
        continue;
    }

    $publicId = cloudinaryDamBuildPublicId($folder, $name);
    $result = cloudinaryUploadDetailed($file, $publicId, 'image', [
        'overwrite' => 'true',
        'use_filename' => 'false',
        'unique_filename' => 'false',
    ]);

    if (($result['ok'] ?? false) !== true) {
        $message = (string) ($result['error'] ?? 'upload failed');
        fwrite(STDERR, "Failed {$name}: {$message}\n");
        $failures += 1;
        continue;
    }

    echo "Uploaded {$name} -> {$publicId}\n";
}

exit($failures > 0 ? 1 : 0);
