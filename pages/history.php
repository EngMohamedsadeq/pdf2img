<?php
$boot = require __DIR__ . "/../bootstrap.php";
$outputsDir = $boot["outputsDir"];

$title = "سجل التحويلات";
require __DIR__ . "/../views/layouts/layout_top.php";

$dirs = glob($outputsDir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) ?: [];

function status_key(string $s): string {
    return match($s){
        "مكتمل" => "done",
        "قيد التحويل" => "converting",
        "بدأ التحويل" => "starting",
        "فشل" => "error",
        default => "ready"
    };
}

$jobs = [];
foreach ($dirs as $dir) {
    $job = basename($dir);
    if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/i', $job)) continue;

    $metaPath = $dir . DIRECTORY_SEPARATOR . "meta.json";
    $pdfName = $job;
    if (is_file($metaPath)) {
        $meta = json_decode((string)file_get_contents($metaPath), true);
        if (is_array($meta) && !empty($meta["pdf_name"])) {
            $pdfName = (string)$meta["pdf_name"];
        }
    }

    $count = count(glob($dir . DIRECTORY_SEPARATOR . "*.jpg") ?: []);
    $status = "جاهز";

    $progressPath = $dir . DIRECTORY_SEPARATOR . "progress.json";
    if (is_file($progressPath)) {
        $p = json_decode((string)file_get_contents($progressPath), true);
        if (is_array($p) && !empty($p["status"])) {
            if ($p["status"] === "converting") $status = "قيد التحويل";
            elseif ($p["status"] === "starting") $status = "بدأ التحويل";
            elseif ($p["status"] === "error") $status = "فشل";
            elseif ($p["status"] === "done") $status = "مكتمل";
        }
    }

    $jobs[] = [
            "job" => $job,
            "dir" => $dir,
            "pdf" => $pdfName,
            "count" => $count,
            "status" => $status,
            "status_key" => status_key($status),
            "mtime" => @filemtime($dir) ?: 0,
    ];
}

usort($jobs, fn($a, $b) => $b["mtime"] <=> $a["mtime"]);
?>

<div class="wrap">
    <div class="top">
        <div>
            <h1>سجل التحويلات</h1>
            <p>عدد العمليات: <span class="chip" id="jobsCount"><?= count($jobs) ?></span></p>
        </div>
        <div class="actions">
            <a class="btn" href="/pages/index.php">رفع ملف جديد</a>
        </div>
    </div>

    <div class="filterbar">
        <div class="filterbar-left">
            <div class="fitem">
                <input class="fin" id="q" type="text" placeholder="بحث باسم PDF أو رقم Job...">
            </div>

            <div class="fitem">
                <select class="fin" id="st">
                    <option value="all" selected>كل الحالات</option>
                    <option value="done">مكتمل</option>
                    <option value="converting">قيد التحويل</option>
                    <option value="starting">بدأ التحويل</option>
                    <option value="error">فشل</option>
                    <option value="ready">جاهز</option>
                </select>
            </div>

            <div class="fitem">
                <select class="fin" id="sort">
                    <option value="new" selected>الأحدث</option>
                    <option value="old">الأقدم</option>
                    <option value="count_desc">الأكثر صورًا</option>
                    <option value="count_asc">الأقل صورًا</option>
                    <option value="name_asc">الاسم (أ-ي)</option>
                    <option value="name_desc">الاسم (ي-أ)</option>
                </select>
            </div>
        </div>

        <div class="filterbar-right">
            <div class="muted" id="resultHint">استخدم البحث والفلترة لتضييق النتائج.</div>
            <button type="button" class="btn secondary" id="resetFilters">إعادة التعيين</button>
        </div>
    </div>

    <div class="grid" id="jobsGrid">
        <?php foreach ($jobs as $it): ?>
            <?php
            $jobUrl = rawurlencode($it["job"]);
            $pdfSafe = htmlspecialchars($it["pdf"], ENT_QUOTES, 'UTF-8');
            $jobSafe = htmlspecialchars($it["job"], ENT_QUOTES, 'UTF-8');
            $statusSafe = htmlspecialchars($it["status"], ENT_QUOTES, 'UTF-8');

            $stClass = "st-ready";
            if ($it["status"] === "قيد التحويل" || $it["status"] === "بدأ التحويل") $stClass = "st-working";
            elseif ($it["status"] === "فشل") $stClass = "st-error";
            elseif ($it["status"] === "مكتمل") $stClass = "st-done";
            ?>
            <div class="img-card job-card"
                 data-job="<?= $jobSafe ?>"
                 data-pdf="<?= $pdfSafe ?>"
                 data-status="<?= htmlspecialchars($it["status_key"], ENT_QUOTES, 'UTF-8') ?>"
                 data-mtime="<?= (int)$it["mtime"] ?>"
                 data-count="<?= (int)$it["count"] ?>">

                <div class="job-head">
                    <div class="job-title">
                        <b><?= $pdfSafe ?></b>
                        <small class="job-id"><?= $jobSafe ?></small>
                    </div>
                    <span class="status-pill <?= $stClass ?>"><?= $statusSafe ?></span>
                </div>

                <div class="job-stats">
                    <span class="chip">الصور: <?= (int)$it["count"] ?></span>
                    <span class="chip">آخر تعديل: <?= date('Y-m-d H:i', (int)$it["mtime"]) ?></span>
                </div>

                <div class="job-actions">
                    <a class="act primary" href="/pages/folder.php?job=<?= $jobUrl ?>">فتح</a>
                    <a class="act" href="/actions/download_zip.php?job=<?= $jobUrl ?>">تحميل ZIP</a>
                    <a class="act danger" href="/actions/delete_job.php?job=<?= $jobUrl ?>" onclick="return confirm('هل أنت متأكد من حذف هذه النتائج؟');">حذف</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!count($jobs)): ?>
            <div class="img-card job-card" data-job="" data-pdf="" data-status="ready" data-mtime="0" data-count="0">
                <div class="job-head">
                    <div class="job-title">
                        <b>لا توجد نتائج بعد</b>
                        <small class="job-id">—</small>
                    </div>
                    <span class="status-pill st-ready">جاهز</span>
                </div>
                <div class="job-stats">
                    <span class="chip">ابدأ برفع ملف PDF من الصفحة الرئيسية.</span>
                </div>
                <div class="job-actions">
                    <a class="act primary" href="index.php">رفع ملف جديد</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . "/../views/layouts/layout_bottom.php"; ?>
