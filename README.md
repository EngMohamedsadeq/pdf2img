# pdf2img-web (PDF → Images)

أداة ويب بسيطة تعمل محليًا (PHP + Python) لرفع ملف PDF وتحويل كل صفحة إلى صورة (JPG/PNG/WEBP) **مع تسمية الصور تلقائيًا** بناءً على الرقم الموجود داخل الصفحة.  
إذا كان الـ PDF “سكان/صور” (لا يحتوي نصًا قابلًا للاستخراج)، يمكن تفعيل **OCR** اختياريًا باستخدام Tesseract لقراءة الرقم من الصورة.

---

## ماذا يفعل المشروع؟

- رفع ملف PDF من المتصفح
- تحويل كل صفحة إلى صورة
- استخراج أول رقم مناسب من كل صفحة وتسميته كاسم الصورة (مثال: `1234567.jpg`)
- عند تعذر استخراج الرقم:
  - (اختياري) تجربة OCR
  - وإن فشل، يتم التسمية مثل: `Page_12.jpg`
- تصفح النتائج وتعديل أسماء الصور يدويًا
- تنزيل جميع الصور كملف ZIP
- سجل للعمليات السابقة + نافذة حالة تعرض التقدم أثناء التحويل

---

## المتطلبات (Windows)

### 1) PHP 8+
تحقق:
```powershell
php -v
```

#### ZipArchive (مطلوب لميزة ZIP)
```powershell
php -r "var_dump(class_exists('ZipArchive'));"
```
يجب أن تكون النتيجة: `bool(true)`

إذا كانت `false` فعّل الامتداد في `php.ini`:
```ini
extension=zip
```

> **مهم:** المشروع يستخدم `shell_exec` لتشغيل Python من PHP، لذلك يجب ألا تكون هذه الدالة معطّلة في إعدادات PHP لديك.

---

### 2) Python 3
تحقق:
```powershell
python --version
pip --version
```

---

### 3) Poppler (مطلوب لـ pdf2image)
- حمّل Poppler للويندوز ثم فك الضغط
- ستحتاج لمسار `bin` مثل:
`D:\poppler\Library\bin`

> هذا المسار ستضعه داخل `config.php`.

---

### 4) مكتبات Python المطلوبة
```powershell
pip install pdfplumber pdf2image pillow
```

---

## OCR اختياري (لملفات PDF السكان/الصور)

### 1) تثبيت pytesseract
```powershell
python -m pip install pytesseract
```

### 2) تثبيت Tesseract OCR على ويندوز
تحقق:
```powershell
tesseract --version
```

(اختياري) معرفة مكانه:
```powershell
where tesseract
```

### 3) إذا `tesseract` غير موجود في PATH
ضع مساره عبر متغير بيئة (مرة واحدة):
```powershell
setx TESSERACT_CMD "C:\Program Files\Tesseract-OCR\tesseract.exe"
```
ثم أغلق وافتح PowerShell من جديد.

> `convert.py` يدعم تلقائيًا المتغير `TESSERACT_CMD` لاختيار مسار tesseract إذا لم يكن في PATH.

---

## تنزيل المشروع

### خيار 1: Clone
```powershell
git clone https://github.com/EngMohamedsadeq/pdf2img.git
cd pdf2img
```

### خيار 2: Download ZIP
من صفحة الريبو على GitHub: **Code → Download ZIP** ثم فك الضغط.

---

## إعداد المشروع

### 1) تعديل `config.php`
عدّل المسارات حسب جهازك. مثال:
```php
<?php
return [
  "python" => "python",
  "poppler" => "D:\\poppler\\Library\\bin",
  "uploads_dir" => __DIR__ . "\\uploads",
  "outputs_dir" => __DIR__ . "\\outputs",
];
```

إذا `python` ليس في PATH ضع المسار الكامل مثل:
`C:\Users\Name\AppData\Local\Programs\Python\Python313\python.exe`

---

### 2) رفع حدود رفع الملفات (للـ PDF الكبير)
اعرف ملف الإعدادات الذي تستخدمه PHP:
```powershell
php --ini
```

ثم عدّل قيمًا مثل:
```ini
upload_max_filesize = 200M
post_max_size = 220M
max_execution_time = 600
memory_limit = 512M
```
ثم أعد تشغيل السيرفر.

---

## تشغيل المشروع محليًا

### خيار 1: سيرفر PHP المدمج
من داخل مجلد المشروع:
```powershell
php -S localhost:8000
```

افتح:
`http://localhost:8000/index.php`

### خيار 2: عبر WAMP/XAMPP (اختياري)
- ضع المشروع داخل `www` (أو `htdocs`)
- افتح الرابط حسب إعدادك، مثل:
`http://localhost/pdf2img/index.php`

---

## أين تُحفظ الملفات؟

- `uploads/` : ملفات PDF المرفوعة
- `outputs/` : ناتج الصور لكل عملية (مجلد باسم job) + ملفات مثل `meta.json` و `progress.json`

> يفضّل عدم رفع `uploads/` و `outputs/` إلى GitHub لأنها بيانات تشغيل.

### `.gitignore` مقترح
```gitignore
/uploads/
/outputs/
*.log
.DS_Store
Thumbs.db
```

---

## كيف تعمل التسمية؟

1) يحاول استخراج النص من الصفحة (`pdfplumber`) ثم يبحث عن رقم مناسب (مثل 5 أرقام أو أكثر).  
2) إذا لم يجد رقمًا، يحاول OCR (إن كان Tesseract/pytesseract متوفرين).  
3) إذا فشل، يسمي الصورة: `Page_<رقم_الصفحة>`.

إذا تكرر نفس الاسم بين الصفحات، يتم إضافة لاحقة تلقائيًا مثل: `_p12` لتجنب التعارض.

---

## تعديل اسم الصورة من الواجهة
داخل صفحة “مجلد النتائج” ستجد حقلًا لكل صورة مع زر **تغيير الاسم**.  
يتم ذلك عبر `rename.php` مع الحفاظ على الامتداد الأصلي (jpg/png/webp).

---

## استكشاف الأخطاء (Troubleshooting)

### 1) “لم يتم إرسال ملف” خصوصًا مع ملفات كبيرة
السبب غالبًا حدود PHP (`upload_max_filesize` / `post_max_size`). ارفع القيم في `php.ini` ثم أعد تشغيل السيرفر.

### 2) فشل التحويل أو لا تظهر صور
- تأكد من مسار Poppler صحيح في `config.php`
- تأكد أن Python يعمل
- تأكد من الحزم:
```powershell
pip show pdfplumber pdf2image pillow
```
- تأكد أن `shell_exec` غير معطل في إعدادات PHP

### 3) OCR لا يعمل أو لا يقرأ الرقم
- تأكد من Tesseract:
```powershell
tesseract --version
```
- إذا ليس في PATH استخدم متغير البيئة `TESSERACT_CMD` (مذكور أعلاه)
- جرّب رفع الـ DPI إلى 200 من واجهة المشروع لتحسين القراءة (خاصة للصور الصغيرة)

### 4) تحميل ZIP لا يعمل
تأكد من ZipArchive وفعّل `extension=zip` في `php.ini` إن لزم.

---

## ملاحظات أمان
هذا مشروع محلي/خاص. لأنه يشغّل أوامر نظام (`shell_exec`) ويتعامل مع ملفات مرفوعة:  
إذا ستنشره على سيرفر عام، أضف حماية (تسجيل دخول/حصر IP/قيود رفع/تنظيف الملفات) قبل فتحه للناس.

---

## الترخيص
اختر ترخيصًا مناسبًا (مثل MIT) وأضف ملف `LICENSE`.
