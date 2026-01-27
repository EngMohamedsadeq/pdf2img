<?php
$config = require __DIR__ . "/config.php";
$outputsDir = $config["outputs_dir"];

$job = $_GET["job"] ?? "";
if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/i', $job)) {
    http_response_code(400);
    die("Job غير صالح.");
}

$dir = $outputsDir . DIRECTORY_SEPARATOR . $job;
if (!is_dir($dir)) {
    http_response_code(404);
    die("المجلد غير موجود.");
}

$rrmdir = function($path) use (&$rrmdir) {
    if (!is_dir($path)) return;
    $items = scandir($path);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === "." || $item === "..") continue;
        $p = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) $rrmdir($p);
        else @unlink($p);
    }
    @rmdir($path);
};

$rrmdir($dir);

header("Location: history.php");
exit;
