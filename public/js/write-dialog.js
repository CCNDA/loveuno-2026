// 寫日記：字數（標題 0/150、內文 0/10000），首頁 ✏️ 跳出對話框（<dialog>，Esc 關、焦點回原按鈕由瀏覽器處理）
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-count]'), function (el) {
        var out = document.getElementById(el.id + '-count');
        if (!out) { return; }
        var n = out.querySelector('span');
        el.addEventListener('input', function () { n.textContent = Array.from(el.value).length; });
    });
    var dlg = document.getElementById('write-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') { return; } // 沒有 <dialog> 的舊瀏覽器：連結照舊到寫日記頁
    var opener = null;
    Array.prototype.forEach.call(document.querySelectorAll('[data-write-dialog]'), function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            opener = a;
            document.getElementById('w-is_prayer').value = a.getAttribute('data-write-dialog');
            dlg.showModal();
            document.getElementById('w-title').focus();
        });
    });
    dlg.querySelector('[data-close]').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('close', function () { if (opener) { opener.focus(); } });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } }); // 點對話框外面的暗處
})();
