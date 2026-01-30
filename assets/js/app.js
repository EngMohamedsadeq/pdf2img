(() => {
    const form = document.getElementById('uploadForm');
    if (!form) return;

    const input = document.getElementById('pdfInput');
    const nameEl = document.getElementById('fileName');
    const infoEl = document.getElementById('fileInfo');
    const badgeEl = document.getElementById('fileBadge');
    const submitBtn = document.getElementById('submitBtn');
    const statusBtn = document.getElementById('statusBtn');

    const dlg = document.getElementById('statusDialog');
    const dlgTitle = document.getElementById('dlgTitle');
    const dlgClose = document.getElementById('dlgClose');
    const dlgOk = document.getElementById('dlgOk');

    const stepUpload = document.getElementById('stepUpload');
    const stepConvert = document.getElementById('stepConvert');
    const txtUpload = document.getElementById('txtUpload');
    const txtConvert = document.getElementById('txtConvert');

    const spinner = document.getElementById('spinner');
    const dlgMsg = document.getElementById('dlgMsg');

    const errorBox = document.getElementById('errorBox');
    const errorText = document.getElementById('errorText');

    const openResults = document.getElementById('openResults');

    let pollTimer = null;
    let currentJob = null;
    let lastData = null;
    let isBusy = false;
    let uploadPhaseMsg = 'بانتظار البدء...';

    function stopPoll(){
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function openDialog() {
        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) dlg.showModal();
        } else {
            dlg.setAttribute('open', 'open');
        }
    }

    function closeDialog() {
        if (typeof dlg.close === 'function') dlg.close();
        else dlg.removeAttribute('open');
    }

    function resetDialogUI() {
        errorBox.hidden = true;
        openResults.hidden = true;
        spinner.style.display = 'block';

        dlgTitle.textContent = 'تحويل PDF';
        dlgMsg.textContent = 'بانتظار البدء...';

        stepUpload.classList.remove('active','done','error');
        stepConvert.classList.remove('active','done','error');
        txtUpload.textContent = 'بانتظار البدء...';
        txtConvert.textContent = 'بانتظار البدء...';
    }

    function setError(message) {
        spinner.style.display = 'none';
        dlgTitle.textContent = 'تعذر إكمال العملية';
        dlgMsg.textContent = 'حدث خطأ أثناء التنفيذ.';
        errorBox.hidden = false;
        errorText.textContent = message || 'خطأ غير معروف.';
        stepConvert.classList.remove('active');
        stepConvert.classList.add('error');
        if (statusBtn) statusBtn.style.display = 'none';
    }

    function setUploadActive(msg) {
        stepUpload.classList.add('active');
        txtUpload.textContent = msg;
    }

    function setUploadDone(msg) {
        stepUpload.classList.remove('active');
        stepUpload.classList.add('done');
        txtUpload.textContent = msg;
    }

    function setConvertActive(msg) {
        stepConvert.classList.add('active');
        txtConvert.textContent = msg;
    }

    function setConvertDone(msg) {
        stepConvert.classList.remove('active');
        stepConvert.classList.add('done');
        txtConvert.textContent = msg;
    }

    function applyProgress(data, job){
        lastData = data;

        const status = data.status || 'waiting';
        const current = Number(data.current || 0);
        const total = Number(data.total || 0);
        const message = data.message || '';

        if (statusBtn) {
            const running = status !== 'done' && status !== 'error';
            statusBtn.style.display = running ? 'block' : 'none';
        }

        if (status === 'waiting' || status === 'starting') {
            setConvertActive(message || 'بدء التحويل...');
            dlgMsg.textContent = message || 'بدء التحويل...';
            return;
        }

        if (status === 'converting') {
            const text = total > 0 ? `جاري التحويل: ${current} / ${total}` : (message || 'جاري التحويل...');
            setConvertActive(text);
            dlgMsg.textContent = text;
            return;
        }

        if (status === 'done') {
            spinner.style.display = 'none';
            dlgTitle.textContent = 'تم التحويل بنجاح';
            const text = total > 0 ? `اكتمل التحويل: ${current} / ${total}` : 'اكتمل التحويل';
            setConvertDone(text);
            dlgMsg.textContent = text;

            openResults.hidden = false;
            openResults.href = `/pages/folder.php?job=${encodeURIComponent(job)}`;

            stopPoll();
            if (statusBtn) statusBtn.style.display = 'none';
            submitBtn.disabled = false;
            isBusy = false;

            return;
        }

        if (status === 'error') {
            setError(data.error || message || 'فشل التحويل.');
            stopPoll();
            submitBtn.disabled = false;
            isBusy = false;

            return;
        }
    }

    function startPoll(job) {
        stopPoll();
        currentJob = job;

        const url = `/actions/progress.php?job=${encodeURIComponent(job)}&t=${Date.now()}`;

        pollTimer = setInterval(async () => {
            try {
                const res = await fetch(url, { cache: 'no-store' });
                const data = await res.json();

                if (!data || data.ok === false) {
                    setError(data?.error || 'تعذر قراءة حالة التقدم.');
                    stopPoll();
                    submitBtn.disabled = false;
                    return;
                }

                applyProgress(data, job);

            } catch (e) {
                setError('تعذر الاتصال بسيرفر الحالة (progress).');
                stopPoll();
                submitBtn.disabled = false;
            }
        }, 800);
    }

    input?.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        nameEl.textContent = f.name;
        const mb = (f.size / (1024 * 1024)).toFixed(2);
        infoEl.textContent = `الحجم: ${mb} MB`;
        badgeEl.textContent = 'جاهز';
    });

    dlgClose?.addEventListener('click', closeDialog);
    dlgOk?.addEventListener('click', closeDialog);

    statusBtn?.addEventListener('click', () => {
        if (!isBusy) return;

        resetDialogUI();
        openDialog();

        if (!currentJob) {
            setUploadActive(uploadPhaseMsg || 'جاري رفع الملف...');
            dlgMsg.textContent = uploadPhaseMsg || 'جاري رفع الملف...';
            setConvertActive('بانتظار بدء التحويل...');
            return;
        }

        if (lastData) applyProgress(lastData, currentJob);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const f = input.files && input.files[0];
        if (!f) return;

        resetDialogUI();
        openDialog();

        isBusy = true;
        currentJob = null;
        lastData = null;

        if (statusBtn) statusBtn.style.display = 'block';

        submitBtn.disabled = true;

        uploadPhaseMsg = 'جاري رفع الملف...';
        setUploadActive(uploadPhaseMsg);
        dlgMsg.textContent = uploadPhaseMsg;

        try {
            const fd = new FormData(form);

            const res = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await res.json().catch(() => null);

            if (!res.ok || !data || data.ok === false) {
                const msg = data?.error || `فشل الرفع (HTTP ${res.status})`;
                setError(msg);
                submitBtn.disabled = false;
                isBusy = false;
                return;
            }

            const job = data.job;
            uploadPhaseMsg = 'تم رفع الملف بنجاح.';

            if (!job) {
                setError('لم يتم استلام رقم العملية (job).');
                submitBtn.disabled = false;
                return;
            }

            setUploadDone('تم رفع الملف بنجاح.');
            dlgMsg.textContent = 'بدء التحويل...';
            setConvertActive('بانتظار بدء التحويل...');

            if (statusBtn) statusBtn.style.display = 'block';

            startPoll(job);

        } catch (err) {
            setError('حدث خطأ أثناء رفع الملف.');
            submitBtn.disabled = false;
            isBusy = false;
        }
    });

})();


(() => {
    const grid = document.getElementById('jobsGrid');
    if (!grid) return;

    const q = document.getElementById('q');
    const st = document.getElementById('st');
    const sort = document.getElementById('sort');
    const reset = document.getElementById('resetFilters');
    const countEl = document.getElementById('jobsCount');
    const hint = document.getElementById('resultHint');

    const cards = Array.from(grid.querySelectorAll('.job-card'));

    function norm(s){
        return (s || '')
            .toString()
            .trim()
            .toLowerCase();
    }

    function apply(){
        const query = norm(q?.value || '');
        const status = (st?.value || 'all').toString();
        const mode = (sort?.value || 'new').toString();

        const items = cards
            .map(el => ({
                el,
                pdf: norm(el.dataset.pdf || ''),
                job: norm(el.dataset.job || ''),
                status: (el.dataset.status || 'ready').toString(),
                mtime: Number(el.dataset.mtime || 0),
                count: Number(el.dataset.count || 0),
            }))
            .filter(it => {
                const okQ = !query || it.pdf.includes(query) || it.job.includes(query);
                const okS = status === 'all' || it.status === status;
                return okQ && okS;
            });

        let sorted = items.slice();

        if (mode === 'new') sorted.sort((a,b) => b.mtime - a.mtime);
        else if (mode === 'old') sorted.sort((a,b) => a.mtime - b.mtime);
        else if (mode === 'count_desc') sorted.sort((a,b) => b.count - a.count || b.mtime - a.mtime);
        else if (mode === 'count_asc') sorted.sort((a,b) => a.count - b.count || b.mtime - a.mtime);
        else if (mode === 'name_asc') sorted.sort((a,b) => (a.pdf > b.pdf ? 1 : -1));
        else if (mode === 'name_desc') sorted.sort((a,b) => (a.pdf < b.pdf ? 1 : -1));

        cards.forEach(c => c.style.display = 'none');
        sorted.forEach(it => {
            it.el.style.display = '';
            grid.appendChild(it.el);
        });

        if (countEl) countEl.textContent = String(sorted.length);

        if (hint) {
            if (!sorted.length) hint.textContent = 'لا توجد نتائج مطابقة.';
            else hint.textContent = `عرض ${sorted.length} نتيجة.`;
        }
    }

    q?.addEventListener('input', apply);
    st?.addEventListener('change', apply);
    sort?.addEventListener('change', apply);

    reset?.addEventListener('click', () => {
        if (q) q.value = '';
        if (st) st.value = 'all';
        if (sort) sort.value = 'new';
        apply();
    });

    apply();
})();


(() => {
    function build(select){
        if (select.dataset.customized === '1') return;

        const opt = select.closest('.opt');
        if (!opt) return;

        const wrap = document.createElement('div');
        wrap.className = 'cselect';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cselect-btn';

        const menu = document.createElement('div');
        menu.className = 'cselect-menu';

        function setBtn(){
            const o = select.options[select.selectedIndex];
            btn.textContent = o ? o.textContent : '';
        }

        function rebuildItems(){
            menu.innerHTML = '';
            Array.from(select.options).forEach((o) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'cselect-item';
                item.textContent = o.textContent;
                item.setAttribute('aria-selected', o.selected ? 'true' : 'false');

                item.addEventListener('click', () => {
                    select.value = o.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    setBtn();
                    Array.from(menu.children).forEach(x => x.setAttribute('aria-selected', 'false'));
                    item.setAttribute('aria-selected', 'true');
                    wrap.classList.remove('open');
                });

                menu.appendChild(item);
            });
        }

        setBtn();
        rebuildItems();

        btn.addEventListener('click', () => {
            document.querySelectorAll('.cselect.open').forEach(x => { if (x !== wrap) x.classList.remove('open'); });
            wrap.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) wrap.classList.remove('open');
        });

        select.addEventListener('change', () => {
            setBtn();
            rebuildItems();
        });

        select.classList.add('cselect-native');
        select.dataset.customized = '1';

        wrap.appendChild(btn);
        wrap.appendChild(menu);

        select.insertAdjacentElement('beforebegin', wrap);
    }

    function init(){
        document.querySelectorAll('.opts select.in').forEach(build);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
