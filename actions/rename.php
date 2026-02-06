<?php
$config = require __DIR__ . "/../config/config.php";
$outputsDir = (string)($config["outputs_dir"] ?? "");

$sendJson = function (int $code, array $payload) {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

ini_set('display_errors', '0');

if ($outputsDir === "") {
    $sendJson(500, ["ok" => false, "error" => "outputs_dir غير مضبوط في config.php"]);
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    $sendJson(405, ["ok" => false, "error" => "Method not allowed"]);
}

$utfLen = function(string $s): int {
    if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
    return strlen($s);
};

$utfSub = function(string $s, int $start, int $len): string {
    if (function_exists('mb_substr')) return mb_substr($s, $start, $len, 'UTF-8');
    return substr($s, $start, $len);
};

$job = (string)($_POST["job"] ?? "");
$old = (string)($_POST["old"] ?? "");
$newBase = (string)($_POST["new_base"] ?? "");

$job = rawurldecode($job);

if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/i', $job)) {
    $sendJson(400, ["ok" => false, "error" => "Job غير صالح."]);
}

$dir = $outputsDir . DIRECTORY_SEPARATOR . $job;
if (!is_dir($dir)) {
    $sendJson(404, ["ok" => false, "error" => "المجلد غير موجود."]);
}

$old = basename($old);
if ($old === "" || !preg_match('/\.(jpg|png|webp)$/i', $old)) {
    $sendJson(400, ["ok" => false, "error" => "اسم الملف غير صالح."]);
}

$newBase = trim($newBase);
if ($newBase === "") {
    $sendJson(400, ["ok" => false, "error" => "الاسم الجديد فارغ."]);
}

$newBase = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $newBase);
$newBase = preg_replace('/\s+/u', ' ', $newBase);
$newBase = trim($newBase);

if ($utfLen($newBase) > 120) {
    $newBase = $utfSub($newBase, 0, 120);
}

$oldPath = $dir . DIRECTORY_SEPARATOR . $old;
if (!is_file($oldPath)) {
    $sendJson(404, ["ok" => false, "error" => "الملف غير موجود."]);
}

$ext = strtolower(pathinfo($old, PATHINFO_EXTENSION));
$newName = $newBase . "." . $ext;

$try = 0;
$targetPath = $dir . DIRECTORY_SEPARATOR . $newName;

while (is_file($targetPath)) {
    $try++;
    $newName = $newBase . "_" . $try . "." . $ext;
    $targetPath = $dir . DIRECTORY_SEPARATOR . $newName;
    if ($try > 999) {
        $sendJson(409, ["ok" => false, "error" => "تعذر إيجاد اسم متاح."]);
    }
}

if (!@rename($oldPath, $targetPath)) {
    $sendJson(500, ["ok" => false, "error" => "فشل إعادة تسمية الملف. (صلاحيات/مسار)"]);
}

$sendJson(200, ["ok" => true, "old" => $old, "new" => $newName]);
