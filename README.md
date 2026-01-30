# pdf2img-web (PDF → Images)

أداة ويب محلية (PHP + Python) لرفع ملف PDF وتحويل كل صفحة إلى صورة (JPG/PNG/WEBP) مع **تسمية الصور تلقائيًا** اعتمادًا على أول رقم مناسب داخل الصفحة.  
يدعم **OCR اختياريًا** (Tesseract) عندما يكون الـ PDF عبارة عن صور/سكان ولا يحتوي نصًا قابلاً للاستخراج.

---

## المميزات

- رفع ملف PDF من المتصفح
- تحويل كل صفحة إلى صورة (JPG/PNG/WEBP)
- استخراج رقم من كل صفحة لاستخدامه كاسم للصورة (مثال: `1234567.jpg`)
- دعم OCR اختياريًا عند تعذر استخراج النص
- إعادة تسمية الصور من الواجهة
- تنزيل النتائج كملف ZIP
- حفظ سجل العمليات داخل مجلد `outputs/`

---

## التقنيات المستخدمة

- **PHP 8+**
- **Python 3**
- مكتبات Python: `pdfplumber`, `pdf2image`, `pillow`
- **Poppler** (مطلوب للتحويل عبر `pdf2image`)
- (اختياري) **Tesseract OCR** + `pytesseract`

---

## المتطلبات

### 1) PHP 8+
تأكد من توفر الامتداد الخاص بـ ZIP (لتحميل النتائج كملف ZIP):
```powershell
php -r "var_dump(class_exists('ZipArchive'));"
```

### 2) Python 3
```powershell
python --version
pip --version
```

### 3) Poppler
ثبّت Poppler للويندوز وحدد مسار `bin` (مثال):
`D:\poppler\Library\bin`

### 4) تثبيت مكتبات Python
```powershell
pip install pdfplumber pdf2image pillow
```

### (اختياري) OCR
```powershell
pip install pytesseract
```
ثم ثبّت Tesseract OCR وتأكد أنه يعمل:
```powershell
tesseract --version
```

---

## التثبيت

```powershell
git clone https://github.com/EngMohamedsadeq/pdf2img.git
cd pdf2img
```

---

## الإعداد

### 1) تعديل `config.php`
عدّل المسارات حسب جهازك:

```php
<?php
return [
  "python" => "python",
  "poppler" => "D:\\poppler\\Library\\bin",
  "uploads_dir" => __DIR__ . "\\uploads",
  "outputs_dir" => __DIR__ . "\\outputs",
];
```

> إذا كان Python غير موجود في PATH استخدم المسار الكامل لملف `python.exe`.

### (اختياري) تحديد مسار Tesseract
إذا كان `tesseract` غير موجود في PATH يمكنك تحديده عبر متغير بيئة:
```powershell
setx TESSERACT_CMD "C:\Program Files\Tesseract-OCR\tesseract.exe"
```

---

## التشغيل محليًا

باستخدام سيرفر PHP المدمج:

```powershell
php -S localhost:8000
```

ثم افتح:
`http://localhost:8000/index.php`

---

## هيكلة المجلدات

- `uploads/` ملفات PDF المرفوعة
- `outputs/` ناتج كل عملية (مجلد Job) + ملفات حالة/بيانات مثل `meta.json`

> يُفضّل عدم رفع `uploads/` و `outputs/` إلى GitHub.

### `.gitignore` مقترح
```gitignore
/uploads/
/outputs/
*.log
.DS_Store
Thumbs.db
```

---

## الترخيص

هذا المشروع مرخّص تحت **MIT License**. راجع ملف `LICENSE` لمزيد من التفاصيل.
