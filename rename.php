<?php
$config = require __DIR__ . "/config.php";
$outputsDir = $config["outputs_dir"];

$isAjax = isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest";

$sendJson = function(int $code, array $payload) use ($isAjax) {
    if (!$isAjax) return false;
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$job = $_POST["job"] ?? "";
$old = $_POST["old"] ?? "";
$newBase = $_POST["new_base"] ?? "";

$job = rawurldecode($job);

if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/i', $job)) {
    $sendJson(400, ["ok" => false, "error" => "Job غير صالح."]);
}

$dir = $outputsDir . DIRECTORY_SEPARATOR . $job;
if (!is_dir($dir)) {
    $sendJson(404, ["ok" => false, "error" => "المجلد غير موجود."]);
}

$old = basename((string)$old);
if ($old === "" || !preg_match('/\.(jpg|png|webp)$/i', $old)) {
    $sendJson(400, ["ok" => false, "error" => "اسم الملف غير صالح."]);
}

$newBase = trim((string)$newBase);
if ($newBase === "") {
    $sendJson(400, ["ok" => false, "error" => "الاسم الجديد فارغ."]);
}

$newBase = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $newBase);
$newBase = preg_replace('/\s+/u', ' ', $newBase);
$newBase = trim($newBase);
if (mb_strlen($newBase, 'UTF-8') > 120) {
    $newBase = mb_substr($newBase, 0, 120, 'UTF-8');
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
    $sendJson(500, ["ok" => false, "error" => "فشل إعادة تسمية الملف."]);
}

$sendJson(200, ["ok" => true, "old" => $old, "new" => $newName]);
