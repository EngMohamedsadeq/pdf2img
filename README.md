# PDF to Images (PHP + Python)

هذا المشروع يقوم برفع ملف PDF وتحويل كل صفحة إلى صورة JPG وتسميتها حسب الرقم الموجود داخل الصفحة، مع إمكانية تحميل جميع الصور كملف ZIP.

---

## المتطلبات (Windows)

### 1) PHP
- تثبيت PHP 8+ والتأكد أنه يعمل من الطرفية:

```powershell
php -v
```

> ملاحظة: لميزة تحميل الكل ZIP يلزم توفر ZipArchive:
```powershell
php -r "var_dump(class_exists('ZipArchive'));"
```
لازم تكون النتيجة `bool(true)`.

---

### 2) Python
- تثبيت Python 3 (ويُفضّل تفعيل خيار **Add Python to PATH** أثناء التثبيت)
- التأكد:

```powershell
python --version
pip --version
```

---

### 3) Poppler
مطلوب لتحويل صفحات PDF إلى صور (pdf2image).

- حمّل Poppler للويندوز ثم فك الضغط.
- خذ المسار التالي داخل poppler:

`...\poppler\Library\bin`

مثال:
`D:\poppler\Library\bin`

---

### 4) تثبيت مكتبات Python
بعد تثبيت Python:

```powershell
pip install pdfplumber pdf2image pillow
```

---

## إعداد المشروع

### 1) تعديل config.php
افتح ملف `config.php` وعدّل المسارات حسب جهازك، مثال:

```php
<?php
return [
  "python" => "python",
  "poppler" => "D:\\poppler\\Library\\bin",
  "uploads_dir" => __DIR__ . "\\uploads",
  "outputs_dir" => __DIR__ . "\\outputs",
];
```

- إذا `python` ليس في PATH ضع مساره الكامل مثل:
`C:\Users\Name\AppData\Local\Programs\Python\Python313\python.exe`

---

### 2) رفع حدود رفع الملفات (مهم للـ PDF الكبير)
تحقق من ملف php.ini:

```powershell
php --ini
```

افتح ملف `php.ini` وعدّل (مثال مناسب):

```ini
upload_max_filesize = 200M
post_max_size = 220M
max_execution_time = 600
memory_limit = 512M
```

ثم أعد تشغيل السيرفر.

---

## تشغيل المشروع

افتح PowerShell داخل مجلد المشروع ثم شغّل:

```powershell
php -S localhost:8000
```

ثم افتح في المتصفح:

`http://localhost:8000/index.php`

---

## المجلدات

- `uploads/` : ملفات PDF المرفوعة
- `outputs/` : ناتج الصور لكل عملية + ملف meta.json (اسم الـ PDF الأصلي)

---

## استكشاف الأخطاء

### مشكلة: لم يتم إرسال ملف (خصوصاً مع ملفات كبيرة)
السبب غالباً حدود PHP (`upload_max_filesize` / `post_max_size`).
ارفع القيم في `php.ini` ثم أعد تشغيل السيرفر.

### مشكلة: فشل التحويل
- تأكد أن مسار Poppler صحيح في `config.php`
- تأكد أن Python يعمل من الطرفية
- تأكد من مكتبات Python:

```powershell
pip show pdfplumber pdf2image pillow
```

### مشكلة: تحميل ZIP لا يعمل
تأكد من توفر ZipArchive:

```powershell
php -r "var_dump(class_exists('ZipArchive'));"
```

إذا كانت `false` فعّل extension zip في php.ini:
```ini
extension=zip
```
ثم أعد تشغيل السيرفر.
