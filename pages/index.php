<?php
$title = "تحويل PDF إلى صور";
require __DIR__ . "/../views/layouts/layout_top.php";
?>

<div class="wrap center">
    <div class="card">
        <div class="card-body">

            <div class="header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div class="brand">
                    <div class="logo"></div>
                    <div class="title">
                        <h1>تحويل PDF إلى صور</h1>
                        <p>ارفع ملف PDF وسيتم استخراج الصفحات كصور بأسماء الأرقام داخل الصفحات.</p>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button"
                            class="btn"
                            style="padding:10px 14px;"
                            onclick="window.open('/pages/history.php','_blank')">
                        السجل
                    </button>

                    <span class="badge">Local • PHP + Python</span>
                </div>
            </div>


            <p class="hint">
                ملاحظة: إذا كان الـ PDF “سكان/صور” قد لا تظهر الأرقام كأسماء (حينها نحتاج OCR).
                إذا الاسم تكرر بين الصفحات سيتم إضافة رقم الصفحة تلقائيًا.
            </p>

            <form id="uploadForm" class="uploader" action="/actions/upload.php" method="post" enctype="multipart/form-data">
                <div class="file">
                    <input id="pdfInput" type="file" name="pdf" accept="application/pdf" required>
                    <div class="meta">
                        <b id="fileName">اختر ملف PDF</b>
                        <span id="fileInfo">اضغط هنا لتحديد الملف</span>
                    </div>
                    <span class="badge" id="fileBadge">PDF</span>
                </div>

                <div class="opts">
                    <div class="opt">
                        <label class="lbl" for="fromPage">من صفحة</label>
                        <input class="in" id="fromPage" name="from_page" type="number" min="1" placeholder="مثال: 1">
                    </div>

                    <div class="opt">
                        <label class="lbl" for="toPage">إلى صفحة</label>
                        <input class="in" id="toPage" name="to_page" type="number" min="1" placeholder="مثال: 20">
                    </div>

                    <div class="opt">
                        <label class="lbl" for="dpi">الجودة (DPI)</label>
                        <select class="in" id="dpi" name="dpi">
                            <option value="120">خفيف (120)</option>
                            <option value="150" selected>متوسط (150)</option>
                            <option value="200">عالي (200)</option>
                        </select>
                    </div>

                    <div class="opt">
                        <label class="lbl" for="format">صيغة الصورة</label>
                        <select class="in" id="format" name="format">
                            <option value="jpg" selected>JPG</option>
                            <option value="png">PNG</option>
                            <option value="webp">WEBP</option>
                        </select>
                    </div>

                    <div class="opt">
                        <label class="lbl" for="quality">ضغط JPG/WEBP (Quality)</label>
                        <select class="in" id="quality" name="quality">
                            <option value="70">70 (أصغر)</option>
                            <option value="80">80</option>
                            <option value="85" selected>85 (مناسب)</option>
                            <option value="92">92 (أعلى)</option>
                        </select>
                    </div>
                </div>

                <button id="submitBtn" type="submit">تحويل الآن</button>

                <button id="statusBtn" type="button" class="btn secondary"
                        style="display:none; width:100%; margin-top:10px;">
                    عرض حالة التحويل
                </button>

            </form>
        </div>

        <div class="footer">
            <div>سيتم حفظ النتائج داخل <b>storage/outputs</b></div>
            <div>© <?= date('Y') ?> • <a class="link" href="/pages/index.php">إعادة التعيين</a> • <a class="link" href="/pages/history.php">السجل</a></div>
        </div>
    </div>
</div>

<dialog id="statusDialog" class="dlg">
    <div class="dlg-head">
        <div class="dlg-title" id="dlgTitle">...</div>
        <button type="button" class="dlg-x" id="dlgClose" aria-label="close">×</button>
    </div>

    <div class="dlg-body">
        <div class="steps">
            <div class="step" id="stepUpload">
                <span class="dot"></span>
                <div>
                    <b>رفع الملف</b>
                    <div class="muted" id="txtUpload">بانتظار البدء...</div>
                </div>
            </div>

            <div class="step" id="stepConvert">
                <span class="dot"></span>
                <div>
                    <b>تحويل الصفحات</b>
                    <div class="muted" id="txtConvert">بانتظار البدء...</div>
                </div>
            </div>
        </div>

        <div class="progress">
            <div class="spinner" id="spinner"></div>
            <div class="progress-text" id="dlgMsg">...</div>
        </div>

        <div class="error-box" id="errorBox" hidden>
            <b>سبب الفشل:</b>
            <div id="errorText"></div>
        </div>
    </div>

    <div class="dlg-foot">
        <a class="btn" id="openResults" href="#" hidden>فتح النتائج</a>
        <button class="btn secondary" type="button" id="dlgOk">حسنًا</button>
    </div>
</dialog>

<?php require __DIR__ . "/../views/layouts/layout_bottom.php"; ?>
