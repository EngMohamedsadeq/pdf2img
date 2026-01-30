<?php
declare(strict_types=1);

$config = require __DIR__ . "/config/config.php";

$uploadsDir = $config["uploads_dir"];
$outputsDir = $config["outputs_dir"];

if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
if (!is_dir($outputsDir)) mkdir($outputsDir, 0777, true);

return [
    "config" => $config,
    "uploadsDir" => $uploadsDir,
    "outputsDir" => $outputsDir,
];
