// 聯絡我們「預覽」（設計稿 785:10490；熊叔叔 11/6 決定）：按了跳出對話框列出要送的內容，可以「修改」或直接「送出」。
// 沒有 JS 時這顆鈕不出現，表單照舊直接送出。內容一律用 textContent 放，不當 HTML
(function () {
    var form = document.querySelector('form.contact-form');
    var btn = document.getElementById('contact-preview-btn');
    var dlg = document.getElementById('contact-preview');
    if (!form || !btn || !dlg || typeof dlg.showModal !== 'function') { return; }
    btn.hidden = false;
    function val(name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) { return ''; }
        if (el.tagName === 'SELECT') { return el.value ? el.options[el.selectedIndex].text : ''; }
        return el.value.trim();
    }
    btn.addEventListener('click', function () {
        ['name', 'email', 'subject', 'body'].forEach(function (n) {
            var out = dlg.querySelector('[data-preview="' + n + '"]');
            out.textContent = val(n) || '（還沒填）';
            out.classList.toggle('is-empty', !val(n));
        });
        dlg.showModal();
    });
    dlg.querySelector('[data-preview-edit]').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('[data-preview-send]').addEventListener('click', function () {
        dlg.close();
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
    });
})();
