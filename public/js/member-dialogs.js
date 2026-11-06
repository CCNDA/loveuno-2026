// 個人首頁的「送禮物」「約會」「傳紙條」按鈕：改成在這頁跳出對話框（熊叔叔 11/6 決定 #7）。
// 按鈕本來就是連到各自的頁面：瀏覽器沒有 <dialog> 就照舊換頁。Esc、✕、點外面的暗處都能關，關了焦點回到按鈕。
// 送出失敗回到這頁時，帶 data-open 的那個對話框自動打開，焦點放在錯誤摘要。
(function () {
    var dialogs = document.querySelectorAll('dialog.member-dialog');
    if (!dialogs.length || typeof dialogs[0].showModal !== 'function') { return; }

    function open(dlg, opener) {
        Array.prototype.forEach.call(dialogs, function (d) { if (d.open && d !== dlg) { d.close(); } });
        dlg.openedBy = opener || null;
        dlg.showModal();
        var first = dlg.querySelector('.error-summary') || dlg.querySelector('input:not([type=hidden]), select, textarea, a.button');
        if (first) {
            if (first.classList.contains('error-summary')) { first.setAttribute('tabindex', '-1'); }
            first.focus();
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-dialog]'), function (a) {
        var dlg = document.getElementById('dlg-' + a.getAttribute('data-dialog'));
        if (!dlg) { return; }
        a.addEventListener('click', function (e) {
            e.preventDefault();
            open(dlg, a.closest('dialog') ? (a.closest('dialog').openedBy || a) : a);
        });
    });

    Array.prototype.forEach.call(dialogs, function (dlg) {
        dlg.querySelector('[data-close]').addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('click', function (e) { // 點對話框外面的暗處（對話框有內距和信封邊框，點到那些地方 target 也是 dlg，要看座標）
            var r = dlg.getBoundingClientRect();
            if (e.target === dlg && (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom)) { dlg.close(); }
        });
        dlg.addEventListener('close', function () { if (dlg.openedBy && document.contains(dlg.openedBy)) { dlg.openedBy.focus(); } });
        if (dlg.hasAttribute('data-open')) { open(dlg, document.querySelector('[data-dialog="' + dlg.id.replace('dlg-', '') + '"]')); }
    });
})();
