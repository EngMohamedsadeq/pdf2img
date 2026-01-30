<?php
$config = require __DIR__ . "/config.php";

$python = $config["python"];
$poppler = $config["poppler"];
$uploadsDir = $config["uploads_dir"];
$outputsDir = $config["outputs_dir"];
$baseDir = __DIR__;

$isAjax = isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest";

$sendJson = function(int $code, array $payload) use ($isAjax) {
    if (!$isAjax) return false;
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$toBytes = function($val){
    $val = trim((string)$val);
    if ($val === '') return 0;
    $unit = strtolower(substr($val, -1));
    $num = (int)$val;
    return match($unit){
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => (int)$val
    };
};

$postMax = ini_get('post_max_size');
$len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($len > 0 && $toBytes($postMax) > 0 && $len > $toBytes($postMax)) {
    $msg = "حجم الطلب أكبر من المسموح. حد post_max_size الحالي = $postMax";
    $sendJson(413, ["ok" => false, "error" => $msg]);
    http_response_code(413);
    die($msg);
}

if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
if (!is_dir($outputsDir)) mkdir($outputsDir, 0777, true);

if (!isset($_FILES["pdf"])) {
    $sendJson(400, ["ok" => false, "error" => "لم يتم إرسال ملف."]);
    http_response_code(400);
    die("لم يتم إرسال ملف.");
}

if ($_FILES["pdf"]["error"] !== UPLOAD_ERR_OK) {
    $err = $_FILES["pdf"]["error"];
    $um = ini_get('upload_max_filesize');
    $pm = ini_get('post_max_size');

    $msg = match ($err) {
        UPLOAD_ERR_INI_SIZE => "حجم الملف أكبر من المسموح (upload_max_filesize=$um).",
        UPLOAD_ERR_FORM_SIZE => "حجم الملف أكبر من المسموح (FORM_SIZE).",
        UPLOAD_ERR_PARTIAL => "تم رفع الملف بشكل جزئي.",
        UPLOAD_ERR_NO_FILE => "لم يتم اختيار أي ملف.",
        UPLOAD_ERR_NO_TMP_DIR => "مجلد الملفات المؤقتة غير موجود.",
        UPLOAD_ERR_CANT_WRITE => "تعذر كتابة الملف على القرص.",
        UPLOAD_ERR_EXTENSION => "تم إيقاف الرفع بسبب إضافة في PHP.",
        default => "فشل رفع الملف (رمز: $err).",
    };

    $sendJson(400, ["ok" => false, "error" => $msg]);
    http_response_code(400);
    die($msg);
}

$tmp = $_FILES["pdf"]["tmp_name"];
$origName = $_FILES["pdf"]["name"];

$fromPage = isset($_POST["from_page"]) ? (int)$_POST["from_page"] : 0;
$toPage   = isset($_POST["to_page"]) ? (int)$_POST["to_page"] : 0;
$dpi      = isset($_POST["dpi"]) ? (int)$_POST["dpi"] : 150;

$quality  = isset($_POST["quality"]) ? (int)$_POST["quality"] : 85;

$format = isset($_POST["format"]) ? strtolower(trim((string)$_POST["format"])) : "jpg";
if (!in_array($format, ["jpg","png","webp"], true)) $format = "jpg";

if ($fromPage < 0) $fromPage = 0;
if ($toPage < 0) $toPage = 0;

if ($dpi < 72) $dpi = 72;
if ($dpi > 400) $dpi = 400;

if ($quality < 40) $quality = 40;
if ($quality > 95) $quality = 95;

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== "pdf") {
    $sendJson(400, ["ok" => false, "error" => "الملف يجب أن يكون PDF فقط."]);
    http_response_code(400);
    die("الملف يجب أن يكون PDF فقط.");
}

$rand = function_exists('random_bytes')
        ? bin2hex(random_bytes(4))
        : substr(md5(uniqid((string)mt_rand(), true)), 0, 8);

$job = date("Ymd_His") . "_" . $rand;

$pdfPath = $uploadsDir . DIRECTORY_SEPARATOR . $job . ".pdf";
$outDir  = $outputsDir . DIRECTORY_SEPARATOR . $job;

if (!move_uploaded_file($tmp, $pdfPath)) {
    $sendJson(500, ["ok" => false, "error" => "تعذر حفظ الملف بعد رفعه."]);
    http_response_code(500);
    die("تعذر حفظ الملف.");
}

if (!mkdir($outDir, 0777, true)) {
    $sendJson(500, ["ok" => false, "error" => "تعذر إنشاء مجلد النتائج."]);
    http_response_code(500);
    die("تعذر إنشاء مجلد النتائج.");
}

file_put_contents(
        $outDir . DIRECTORY_SEPARATOR . "meta.json",
        json_encode([
                "pdf_name" => $origName,
                "from_page" => $fromPage,
                "to_page" => $toPage,
                "dpi" => $dpi,
                "quality" => $quality,
                "format" => $format
        ], JSON_UNESCAPED_UNICODE)
);

file_put_contents(
        $outDir . DIRECTORY_SEPARATOR . "progress.json",
        json_encode([
                "status" => "starting",
                "done" => false,
                "current" => 0,
                "total" => 0,
                "message" => "تم بدء التحويل..."
        ], JSON_UNESCAPED_UNICODE)
);

$convertPy = $baseDir . DIRECTORY_SEPARATOR . "convert.py";

$cmd = escapeshellcmd($python) . " -X utf8 " .
        escapeshellarg($convertPy) . " " .
        escapeshellarg($pdfPath) . " " .
        escapeshellarg($outDir) . " " .
        escapeshellarg($poppler) . " " .
        escapeshellarg((string)$fromPage) . " " .
        escapeshellarg((string)$toPage) . " " .
        escapeshellarg((string)$dpi) . " " .
        escapeshellarg((string)$quality) . " " .
        escapeshellarg((string)$format);

if ($isAjax) {
    $log = $outDir . DIRECTORY_SEPARATOR . "convert.log";

    if (PHP_OS_FAMILY === 'Windows') {
        // Windows background
        $bgCmd = 'cmd /c start "" /B ' . $cmd . ' > ' . escapeshellarg($log) . ' 2>&1';
        @shell_exec($bgCmd);
    } else {
        // Linux/Colab background
        $bgCmd = 'nohup ' . $cmd . ' > ' . escapeshellarg($log) . ' 2>&1 &';
        @shell_exec($bgCmd);
    }

    $sendJson(200, [
        "ok" => true,
        "job" => $job,
        "message" => "تم بدء التحويل"
    ]);
}


$output = shell_exec($cmd);
if (!$output) {
    http_response_code(500);
    die("فشل تشغيل التحويل. تأكد أن PHP يسمح بتشغيل shell_exec وأن python يعمل.");
}

$data = json_decode($output, true);
if (!$data || empty($data["ok"])) {
    http_response_code(500);
    echo "<pre>";
    echo "خطأ من بايثون:\n";
    echo htmlspecialchars($output);
    echo "</pre>";
    exit;
}

$count = (int)($data["count"] ?? 0);
$files = $data["files"] ?? [];

$jobSafe = htmlspecialchars($job, ENT_QUOTES, 'UTF-8');
$jobUrl  = rawurlencode($job);

$title = "النتائج";
require __DIR__ . "/views/layout_top.php";
?>
<div class="wrap">
    <div class="top">
        <div>
            <h1>تم التحويل بنجاح</h1>
            <p>
                عدد الصفحات: <span class="chip"><?= $count ?></span>
                • النتائج:
                <a class="link" href="folder.php?job=<?= $jobUrl ?>">فتح مجلد الصور</a>
                <span class="chip">outputs/<?= $jobSafe ?></span>
            </p>
        </div>
        <div class="actions">
            <a class="btn" href="download_zip.php?job=<?= $jobUrl ?>">تحميل الكل ZIP</a>
            <a class="btn" href="index.php">رفع ملف آخر</a>
            <a class="btn" href="folder.php?job=<?= $jobUrl ?>">فتح مجلد النتائج</a>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($files as $f): ?>
            <?php
            $pageNum = (int)($f["page"] ?? 0);
            $file = (string)($f["file"] ?? "");
            $url = "outputs/" . $jobUrl . "/" . rawurlencode($file);
            ?>
            <div class="img-card">
                <a class="thumb" href="<?= $url ?>" target="_blank">
                    <img src="<?= $url ?>" alt="">
                </a>
                <div class="meta">
                    <div class="row">
                        <span class="chip">صفحة <?= $pageNum ?></span>
                        <a href="<?= $url ?>" download>تحميل</a>
                    </div>
                    <b><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></b>
                    <small>اضغط على الصورة لفتحها بحجم كامل</small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . "/views/layout_bottom.php"; ?>
