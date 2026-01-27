import os, re, json, sys
import pdfplumber
from pdf2image import convert_from_path

def safe_name(s: str) -> str:
    s = (s or "").strip()
    s = re.sub(r'[\\/:*?"<>|]+', '_', s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s[:120] if len(s) > 120 else s

_ARABIC_DIGITS = str.maketrans({
    "٠":"0","١":"1","٢":"2","٣":"3","٤":"4","٥":"5","٦":"6","٧":"7","٨":"8","٩":"9",
    "۰":"0","۱":"1","۲":"2","۳":"3","۴":"4","۵":"5","۶":"6","۷":"7","۸":"8","۹":"9",
})

def _normalize_digits(s: str) -> str:
    return (s or "").translate(_ARABIC_DIGITS)

def extract_first_number(text: str):
    if not text:
        return None
    text = _normalize_digits(text)
    text = re.sub(r'(?<=\d)\s+(?=\d)', '', text)
    m = re.search(r'\d{5,}(?:#\d+)?', text)
    return m.group(0) if m else None

def write_json_atomic(path: str, data: dict):
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False)
    os.replace(tmp, path)

def _setup_tesseract(pytesseract):
    tcmd = os.environ.get("TESSERACT_CMD", "").strip()
    if tcmd and os.path.isfile(tcmd):
        pytesseract.pytesseract.tesseract_cmd = tcmd
        return
    p1 = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
    p2 = r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe"
    if os.path.isfile(p1):
        pytesseract.pytesseract.tesseract_cmd = p1
    elif os.path.isfile(p2):
        pytesseract.pytesseract.tesseract_cmd = p2

def _detect_number_band(img, thr=200):
    gray = img.convert("L")
    w, h = gray.size
    px = gray.load()

    y0 = int(h * 0.35)
    y1 = int(h * 0.92)

    min_row = max(15, int(w * 0.02))
    max_row = min(220, int(w * 0.18))

    row_counts = []
    for y in range(y0, y1):
        c = 0
        for x in range(w):
            if px[x, y] < thr:
                c += 1
        row_counts.append(c)

    segs = []
    in_seg = False
    s = 0
    for i, c in enumerate(row_counts):
        if min_row <= c <= max_row:
            if not in_seg:
                s = i
                in_seg = True
        else:
            if in_seg:
                segs.append((s, i - 1))
                in_seg = False
    if in_seg:
        segs.append((s, len(row_counts) - 1))

    best = None
    best_score = -1
    for s, e in segs:
        length = e - s + 1
        if length < 6 or length > 120:
            continue
        total = sum(row_counts[s:e+1])
        center_y = y0 + (s + e) // 2
        score = total + center_y * 0.6
        if score > best_score:
            best = (y0 + s, y0 + e)
            best_score = score

    return best

def _tight_x_bounds(img, y0, y1, thr=200):
    gray = img.convert("L")
    w, h = gray.size
    px = gray.load()

    min_col = 3
    left = None
    right = None

    for x in range(w):
        c = 0
        for y in range(y0, y1 + 1):
            if px[x, y] < thr:
                c += 1
        if c >= min_col:
            left = x
            break

    for x in range(w - 1, -1, -1):
        c = 0
        for y in range(y0, y1 + 1):
            if px[x, y] < thr:
                c += 1
        if c >= min_col:
            right = x
            break

    return left, right

def _pick_best_number(cands):
    if not cands:
        return None
    filtered = []
    for s in cands:
        s = (s or "").strip()
        if not s:
            continue
        n = len(s.replace("#", ""))
        if 5 <= n <= 12:
            filtered.append(s)
    if not filtered:
        return None
    def score(s):
        n = len(s.replace("#", ""))
        return (-(abs(n - 7)), -n)
    filtered.sort(key=score, reverse=True)
    return filtered[0]

def ocr_first_number_from_image(img):
    try:
        from PIL import ImageOps, ImageEnhance
        import pytesseract
    except Exception:
        return None

    _setup_tesseract(pytesseract)

    if img.mode != "RGB":
        img = img.convert("RGB")

    band = _detect_number_band(img)
    crops = []

    if band:
        by0, by1 = band
        pad_y = 18
        y0 = max(0, by0 - pad_y)
        y1 = min(img.size[1] - 1, by1 + pad_y)

        left, right = _tight_x_bounds(img, y0, y1)
        if left is None or right is None or right <= left:
            crops.append(img.crop((0, y0, img.size[0], y1)))
        else:
            pad_x = 25
            x0 = max(0, left - pad_x)
            x1 = min(img.size[0], right + pad_x)
            crops.append(img.crop((x0, y0, x1, y1)))
    else:
        w, h = img.size
        for a, b in [(0.50, 0.70), (0.55, 0.80), (0.60, 0.88)]:
            y0 = int(h * a)
            y1 = int(h * b)
            crops.append(img.crop((0, y0, w, y1)))

    cfg = (
        "--oem 3 --psm 7 "
        "-c tessedit_char_whitelist=0123456789# "
        "-c classify_bln_numeric_mode=1 "
        "-c load_system_dawg=0 -c load_freq_dawg=0"
    )

    for crop in crops:
        from PIL import ImageOps, ImageEnhance
        gray = ImageOps.grayscale(crop)
        gray = ImageOps.autocontrast(gray)
        gray = ImageEnhance.Contrast(gray).enhance(3.2)
        gray = ImageEnhance.Sharpness(gray).enhance(2.2)
        gray = gray.resize((gray.size[0] * 3, gray.size[1] * 3))

        import pytesseract
        txt = pytesseract.image_to_string(gray, config=cfg, lang="eng")
        txt = _normalize_digits(txt)
        cleaned = re.sub(r'[^\d#]+', '', txt or '').strip()

        cands = re.findall(r'\d{5,}(?:#\d+)?', cleaned)
        best = _pick_best_number(cands)
        if best:
            return best

    return None

def main():
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except Exception:
        pass

    if len(sys.argv) < 4:
        print(json.dumps({"ok": False, "error": "Usage: convert.py <pdf_path> <output_dir> <poppler_bin_path> [from_page] [to_page] [dpi] [quality] [format]"}, ensure_ascii=False))
        sys.exit(1)

    pdf_path = sys.argv[1]
    out_dir = sys.argv[2]
    poppler_path = sys.argv[3]

    from_page = int(sys.argv[4]) if len(sys.argv) >= 5 else 0
    to_page   = int(sys.argv[5]) if len(sys.argv) >= 6 else 0
    dpi       = int(sys.argv[6]) if len(sys.argv) >= 7 else 150

    quality   = int(sys.argv[7]) if len(sys.argv) >= 8 else 85
    fmt       = (sys.argv[8] if len(sys.argv) >= 9 else "jpg").lower().strip()

    if dpi < 72: dpi = 72
    if dpi > 400: dpi = 400

    if quality < 40: quality = 40
    if quality > 95: quality = 95

    if fmt not in ("jpg", "png", "webp"):
        fmt = "jpg"

    os.makedirs(out_dir, exist_ok=True)
    progress_path = os.path.join(out_dir, "progress.json")
    summary_path = os.path.join(out_dir, "summary.json")

    try:
        results = []
        used = set()
        unread_pages = []

        with pdfplumber.open(pdf_path) as pdf:
            total_pages = len(pdf.pages)

            start = from_page if from_page and from_page >= 1 else 1
            end = to_page if to_page and to_page >= 1 else total_pages

            if start > total_pages: start = total_pages
            if end > total_pages: end = total_pages
            if end < start: end = start

            range_total = (end - start) + 1

            write_json_atomic(progress_path, {
                "status": "starting",
                "done": False,
                "current": 0,
                "total": range_total,
                "message": "بدء التحويل..."
            })

            idx = 0
            for p in range(start, end + 1):
                page = pdf.pages[p - 1]
                idx += 1

                text = page.extract_text() or ""
                base = extract_first_number(text)
                source = "text" if base else ""

                img = convert_from_path(
                    pdf_path,
                    poppler_path=poppler_path,
                    first_page=p,
                    last_page=p,
                    dpi=dpi
                )[0]

                if not base:
                    base = ocr_first_number_from_image(img)
                    if base:
                        source = "ocr"

                if not base:
                    base = f"Page_{p}"
                    source = "fallback"
                    unread_pages.append(p)

                base = safe_name(base)
                name = base if base not in used else f"{base}_p{p}"
                used.add(name)

                ext = "jpg" if fmt == "jpg" else fmt
                filename = f"{name}.{ext}"
                out_path = os.path.join(out_dir, filename)

                if fmt == "jpg":
                    img = img.convert("RGB")
                    img.save(out_path, "JPEG", quality=quality, optimize=True, progressive=True)
                elif fmt == "png":
                    img.save(out_path, "PNG", optimize=True)
                else:
                    img = img.convert("RGB")
                    img.save(out_path, "WEBP", quality=quality, method=6)

                results.append({"page": p, "file": filename, "source": source})

                write_json_atomic(progress_path, {
                    "status": "converting",
                    "done": False,
                    "current": idx,
                    "total": range_total,
                    "message": f"تم تحويل {idx} من {range_total}"
                })

        unread_count = len(unread_pages)

        write_json_atomic(summary_path, {
            "ok": True,
            "total": len(results),
            "unread_count": unread_count,
            "unread_pages": unread_pages,
            "files": results
        })

        write_json_atomic(progress_path, {
            "status": "done",
            "done": True,
            "current": len(results),
            "total": range_total,
            "message": "اكتمل التحويل",
            "unread_count": unread_count
        })

        print(json.dumps({
            "ok": True,
            "count": len(results),
            "unread_count": unread_count,
            "unread_pages": unread_pages,
            "files": results
        }, ensure_ascii=False))

    except Exception as e:
        write_json_atomic(progress_path, {
            "status": "error",
            "done": True,
            "current": 0,
            "total": 0,
            "message": "فشل التحويل",
            "error": str(e)
        })
        print(json.dumps({"ok": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)

if __name__ == "__main__":
    main()
