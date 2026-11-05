// 即時存檔（熊叔叔 2026-11-05「沒有存檔是要做成即時存檔，這個要走 rest api 即時儲存」「要跳出訊息讓使用者知道」）
// <form data-autosave="PATCH 網址" data-autosave-groups="a b;c d">：下拉、勾選改了馬上存；文字停手 0.8 秒或離開欄位就存。
// 只送改的那一欄（同一組的一起送，例如生日的月和日）。存好跳出「已儲存」，錯了在欄位下面寫原因、也跳出訊息。
// 沒有 JS 時這支不會跑，表單照舊用存檔鈕整張送出。
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    var toast = document.getElementById('toast');
    var toastTimer = null;
    var stored = null;
    try { stored = sessionStorage.getItem('lu-toast'); sessionStorage.removeItem('lu-toast'); } catch (e) { /* 私密視窗 */ }

    function showToast(text, isError, long) {
        if (!toast) { return; }
        toast.textContent = text;
        toast.classList.toggle('toast-error', !!isError);
        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.hidden = true; }, (isError || long) ? 6000 : 2500);
    }

    if (stored) { setTimeout(function () { showToast(stored); }, 50); } // 換字體、語言後重新整理，訊息接著跳

    Array.prototype.forEach.call(document.querySelectorAll('form[data-autosave]'), function (form) {
        var url = form.getAttribute('data-autosave');
        var groups = (form.getAttribute('data-autosave-groups') || '').split(';').map(function (g) { return g.trim().split(/\s+/).filter(Boolean); });
        var timers = {}, lastSaved = {};
        form.classList.add('is-autosaving'); // CSS 把存檔鈕藏起來

        function baseName(el) { return el.name.replace(/\[\]$/, ''); }
        function groupOf(name) {
            for (var i = 0; i < groups.length; i++) { if (groups[i].indexOf(name) >= 0) { return groups[i]; } }
            return [name];
        }
        function valueOf(name) {
            var els = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
            if (!els.length) { return undefined; }
            var first = els[0];
            if (first.name.slice(-2) === '[]') { // 多選：沒勾任何一個就送空陣列
                return Array.prototype.filter.call(els, function (e) { return e.checked; }).map(function (e) { return e.value; });
            }
            if (first.type === 'checkbox') { return first.checked; }
            if (first.type === 'radio') {
                var on = Array.prototype.filter.call(els, function (e) { return e.checked; })[0];
                return on ? on.value : '';
            }
            return first.value;
        }
        function labelOf(name) {
            var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
            if (!el) { return name; }
            var fs = el.closest('fieldset');
            var lab = el.id ? form.querySelector('label[for="' + el.id + '"]') : null;
            var grouped = el.name.slice(-2) === '[]' || el.type === 'radio'; // 多選、單選用整組的標題（封面選項的 label 只有圖）
            var text = (grouped && fs && fs.querySelector('legend')) ? fs.querySelector('legend').textContent : (lab ? lab.textContent : name);
            return text.replace(/（.*?）|\(.*?\)/g, '').trim();
        }
        function fieldBox(name) {
            var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
            return el ? (el.closest('fieldset') || el.closest('.field') || el.parentNode) : null;
        }
        function setError(name, msg) {
            var box = fieldBox(name);
            if (!box) { return; }
            var id = 'autosave-' + name + '-error';
            var p = document.getElementById(id);
            var inputs = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
            if (!msg) {
                if (p) { p.remove(); }
                Array.prototype.forEach.call(inputs, function (e) { e.removeAttribute('aria-invalid'); });
                return;
            }
            if (!p) { p = document.createElement('p'); p.id = id; p.className = 'field-error'; box.appendChild(p); }
            p.textContent = msg;
            Array.prototype.forEach.call(inputs, function (e) {
                e.setAttribute('aria-invalid', 'true');
                var d = (e.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
                if (d.indexOf(id) < 0) { d.push(id); e.setAttribute('aria-describedby', d.join(' ')); }
            });
        }

        function allNames() {
            var seen = [];
            Array.prototype.forEach.call(form.elements, function (e) {
                if (!e.name || e.name === '_token' || e.name === '_method') { return; }
                var n = baseName(e);
                if (seen.indexOf(n) < 0) { seen.push(n); }
            });
            return seen;
        }
        var whole = form.hasAttribute('data-autosave-whole'); // 整張一起送（設定、交友條件：欄位少、彼此有關）

        function save(name) {
            var names = whole ? allNames() : groupOf(name), body = {};
            names.forEach(function (n) { var v = valueOf(n); if (v !== undefined) { body[n] = v; } });
            // data-autosave-together：這一組要嘛全填、要嘛全空才存（例如經文的書卷、章、節，填到一半不要跳錯誤）
            var together = (form.getAttribute('data-autosave-together') || '').split(/\s+/).filter(Boolean);
            if (together.indexOf(names[0]) >= 0) {
                var filled = names.filter(function (n) { var v = body[n]; return v !== undefined && v !== '' && v !== null; }).length;
                if (filled > 0 && filled < names.length) { return; }
            }
            var key = names.join(' '), sent = JSON.stringify(body);
            if (lastSaved[key] === sent) { return; } // 停手存過、離開欄位又觸發一次：內容一樣就不再送
            fetch(url, {
                method: 'PATCH', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
                body: JSON.stringify(body)
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; });
            }).then(function (res) {
                if (res.ok) {
                    lastSaved[key] = sent;
                    names.forEach(function (n) { setError(n, ''); });
                    var msg = '✓ 已儲存：' + (whole ? labelOf(name) : names.map(labelOf).join('、'));
                    if (res.j.note) { msg += '　' + res.j.note; }
                    if (res.j.reload) {
                        try { sessionStorage.setItem('lu-toast', msg); } catch (e) { /* 存不了就算了 */ }
                        location.reload();
                        return;
                    }
                    showToast(msg, false, !!res.j.note);
                    return;
                }
                if (res.status === 422 && res.j.errors) {
                    var msgs = [];
                    names.forEach(function (n) {
                        var errs = res.j.errors[n] || res.j.errors[n + '.0'] || null;
                        setError(n, errs ? errs[0] : '');
                        if (errs) { msgs.push(errs[0]); }
                    });
                    showToast('沒有存到：' + (msgs[0] || '請檢查欄位'), true);
                    return;
                }
                showToast(res.status === 419 ? '登入逾時了，請重新整理頁面再改。' : '沒有存到，網路可能斷了，請再試一次。', true);
            }).catch(function () { showToast('沒有存到，網路可能斷了，請再試一次。', true); });
        }

        form.addEventListener('change', function (e) {
            if (!e.target.name) { return; }
            var n = baseName(e.target);
            clearTimeout(timers[n]);
            save(n);
        });
        form.addEventListener('input', function (e) {
            var t = e.target;
            if (!t.name || !/^(text|tel|email|number|search|url)$|^textarea$/i.test(t.type || t.tagName)) { return; }
            var n = baseName(t);
            clearTimeout(timers[n]);
            timers[n] = setTimeout(function () { save(n); }, 800);
        });
    });
})();
