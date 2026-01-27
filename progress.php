<?php
$config = require __DIR__ . "/config.php";
$outputsDir = $config["outputs_dir"];

$job = $_GET["job"] ?? "";
header("Content-Type: application/json; charset=utf-8");

if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/i', $job)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "job" => $job, "error" => "Job غير صالح."], JSON_UNESCAPED_UNICODE);
    exit;
}

$dir = $outputsDir . DIRECTORY_SEPARATOR . $job;
if (!is_dir($dir)) {
    http_response_code(404);
    echo json_encode(["ok" => false, "job" => $job, "error" => "المجلد غير موجود."], JSON_UNESCAPED_UNICODE);
    exit;
}

$progressPath = $dir . DIRECTORY_SEPARATOR . "progress.json";
$logPath = $dir . DIRECTORY_SEPARATOR . "convert.log";

$tailFile = function(string $path, int $lines = 25): string {
    if (!is_file($path)) return "";
    $data = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($data) || !$data) return "";
    $slice = array_slice($data, -$lines);
    $text = trim(implode("\n", $slice));
    return $text;
};

$basePayload = [
    "ok" => true,
    "job" => $job,
    "status" => "waiting",
    "done" => false,
    "current" => 0,
    "total" => 0,
    "message" => "بانتظار بدء التحويل..."
];

// إذا progress.json موجود -> نعتمد عليه كمرجع أساسي
if (is_file($progressPath)) {
    $raw = @file_get_contents($progressPath);
    $data = json_decode((string)$raw, true);

    if (is_array($data)) {
        $data["ok"] = true;
        $data["job"] = $job;

        // إذا status=error وما فيه error واضح، حاول نجيب من log
        if (($data["status"] ?? "") === "error" && empty($data["error"])) {
            $tail = $tailFile($logPath, 30);
            if ($tail !== "") $data["error"] = $tail;
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // progress.json موجود لكنه خربان
    $tail = $tailFile($logPath, 30);
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "job" => $job,
        "error" => "ملف progress.json غير صالح.",
        "details" => $tail ?: null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// إذا لا يوجد progress.json:
// - إذا يوجد convert.log وفيه نص يدل على خطأ -> نرجع error
// - وإلا نرجع waiting
$tail = $tailFile($logPath, 30);
if ($tail !== "" && preg_match('/traceback|error|exception|fatal/i', $tail)) {
    echo json_encode([
        "ok" => true,
        "job" => $job,
        "status" => "error",
        "done" => true,
        "current" => 0,
        "total" => 0,
        "message" => "فشل التحويل",
        "error" => $tail
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحويل غالبًا بدأ لكن progress.json لم يُكتب بعد (أول ثواني)
echo json_encode($basePayload, JSON_UNESCAPED_UNICODE);
