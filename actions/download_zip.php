<?php
$boot = require __DIR__ . "/../bootstrap.php";
$outputsDir = $boot["outputsDir"];

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

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die("ZipArchive غير متوفر في PHP. فعّل zip extension.");
}

$files = array_merge(
    glob($dir . DIRECTORY_SEPARATOR . "*.jpg") ?: [],
    glob($dir . DIRECTORY_SEPARATOR . "*.png") ?: [],
    glob($dir . DIRECTORY_SEPARATOR . "*.webp") ?: []
);
sort($files);

if (!$files) {
    http_response_code(404);
    die("لا توجد صور داخل هذا المجلد.");
}

$metaPath = $dir . DIRECTORY_SEPARATOR . "meta.json";
$pdfBase = "images_" . $job;

if (is_file($metaPath)) {
    $meta = json_decode((string)file_get_contents($metaPath), true);
    if (is_array($meta) && !empty($meta["pdf_name"])) {
        $pdfName = (string)$meta["pdf_name"];
        $pdfBase = pathinfo($pdfName, PATHINFO_FILENAME);
    }
}

$pdfBase = preg_replace('/[\\\\\\/:"*?<>|]+/u', '_', $pdfBase);
$pdfBase = trim($pdfBase);
if ($pdfBase === '') $pdfBase = "images_" . $job;

$tmpDir = sys_get_temp_dir();
$zipPath = $tmpDir . DIRECTORY_SEPARATOR . "zip_" . $job . "_" . uniqid() . ".zip";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die("تعذر إنشاء ملف ZIP.");
}

foreach ($files as $path) {
    $zip->addFile($path, basename($path));
}

$zip->close();

$downloadName = $pdfBase . ".zip";

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($zipPath);

@unlink($zipPath);
exit;
