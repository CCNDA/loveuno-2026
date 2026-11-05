// 帳號管理的「修改」鈕（E-mail、密碼、手機）：按了打開下面對應的小表單（設計稿 253:15337 欄位旁的「修改」）。
// 沒有 JS 時鈕藏著、小表單全部直接顯示；有錯誤時（data-open="1"）一進來就打開。
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('button.acct-edit[data-panel]'), function (btn) {
        var panel = document.getElementById(btn.getAttribute('data-panel'));
        if (!panel) { return; }
        function set(open) {
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('is-open', open);
        }
        set(panel.getAttribute('data-open') === '1');
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var open = panel.hidden;
            set(open);
            if (open) {
                var first = panel.querySelector('input:not([type=hidden]), select');
                if (first) { first.focus(); }
            }
        });
    });
})();
