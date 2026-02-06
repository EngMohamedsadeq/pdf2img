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

$jobSafe = htmlspecialchars($job, ENT_QUOTES, 'UTF-8');
$jobUrl  = rawurlencode($job);

$all = array_merge(
        glob($dir . DIRECTORY_SEPARATOR . "*.jpg") ?: [],
        glob($dir . DIRECTORY_SEPARATOR . "*.png") ?: [],
        glob($dir . DIRECTORY_SEPARATOR . "*.webp") ?: []
);
sort($all);

$summaryPath = $dir . DIRECTORY_SEPARATOR . "summary.json";
$unreadCount = null;
$unreadPages = [];
if (is_file($summaryPath)) {
    $sum = json_decode((string)file_get_contents($summaryPath), true);
    if (is_array($sum)) {
        $unreadCount = (int)($sum["unread_count"] ?? 0);
        $unreadPages = $sum["unread_pages"] ?? [];
        if (!is_array($unreadPages)) $unreadPages = [];
    }
}

$title = "مجلد النتائج";
require __DIR__ . "/../views/layouts/layout_top.php";
?>
<div class="wrap">
    <div class="top">
        <div>
            <h1>مجلد النتائج</h1>
            <p>
                المجلد: <span class="chip">storage/outputs/<?= $jobSafe ?></span>
                • عدد الصور: <span class="chip"><?= count($all) ?></span>
                <?php if ($unreadCount !== null): ?>
                    • لم تُقرأ أسماؤها تلقائيًا: <span class="chip"><?= $unreadCount ?></span>
                <?php endif; ?>
            </p>

            <?php if ($unreadCount !== null && $unreadCount > 0): ?>
                <p class="hint">
                    الصفحات التي فشل فيها استخراج الرقم:
                    <b><?= htmlspecialchars(implode(", ", array_map('strval', $unreadPages)), ENT_QUOTES, 'UTF-8') ?></b>
                </p>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a class="btn" href="index.php">رفع ملف آخر</a>
            <a class="btn" href="../actions/download_zip.php?job=<?= $jobUrl ?>">تحميل الكل ZIP</a>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($all as $path): ?>
            <?php
            $file = basename($path);
            $url  = "../storage/outputs/" . $jobUrl . "/" . rawurlencode($file);
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $base = pathinfo($file, PATHINFO_FILENAME);
            ?>
            <div class="img-card">
                <a class="thumb" href="<?= $url ?>" target="_blank">
                    <img src="<?= $url ?>" alt="">
                </a>

                <div class="meta">
                    <div class="row" style="align-items:center; gap:10px;">
                        <a href="<?= $url ?>" download>تحميل</a>
                        <span class="chip"><?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
                        <input class="in" type="text"
                               placeholder="اكتب الاسم الجديد بدون الامتداد"
                               value="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>"
                               data-old="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>"
                               style="width:100%;">

                        <button class="btn" type="button" onclick="renameFile(this)" style="width:100%;">
                            تغيير الاسم
                        </button>
                    </div>

                    <small style="display:block; margin-top:8px;">
                        الاسم الحالي: <b><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></b>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    async function renameFile(btn){
        const box = btn.parentElement;
        const input = box.querySelector('input');
        const oldName = input.getAttribute('data-old');
        const newBase = (input.value || '').trim();

        if(!newBase){
            alert('اكتب اسم جديد');
            return;
        }

        btn.disabled = true;

        try{
            const res = await fetch('../actions/rename.php', {
                method: 'POST',
                headers: {
                    'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With':'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    job: '<?= $jobUrl ?>',
                    old: oldName,
                    new_base: newBase
                })
            });

            const text = await res.text();
            let data = null;

            try { data = JSON.parse(text); } catch (_) {}

            if (!res.ok) {
                const msg = (data && data.error) ? data.error : (text ? text.slice(0, 200) : `HTTP ${res.status}`);
                alert(`فشل الطلب: ${msg}`);
                return;
            }

            if (!data || data.ok !== true) {
                alert((data && data.error) ? data.error : 'رد غير صالح من السيرفر');
                return;
            }

            location.reload();

        }catch(e){
            alert('خطأ في الاتصال: ' + (e && e.message ? e.message : ''));
        }finally{
            btn.disabled = false;
        }
    }
</script>

<?php require __DIR__ . "/../views/layouts/layout_bottom.php"; ?>
