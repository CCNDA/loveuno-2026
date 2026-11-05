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
    // 預覽（設計稿）：用 textContent 放字，不會把內容當 HTML；照片用本機網址，不上傳
    var pbtn = dlg.querySelector('.write-preview-btn'), pv = document.getElementById('write-preview');
    if (pbtn && pv) {
        pbtn.hidden = false;
        pbtn.addEventListener('click', function () {
            var open = pv.hidden;
            if (open) {
                pv.querySelector('.write-preview-tag').textContent = document.getElementById('w-is_prayer').value === '1' ? '代禱事項' : '心情日記';
                pv.querySelector('.write-preview-heading').textContent = document.getElementById('w-title').value || '（還沒寫標題）';
                pv.querySelector('.write-preview-body').textContent = document.getElementById('w-body').value || '（還沒寫內文）';
                var f = document.getElementById('w-image').files[0], img = pv.querySelector('.write-preview-img');
                if (f) { img.src = URL.createObjectURL(f); img.hidden = false; } else { img.hidden = true; }
            }
            pv.hidden = !open;
            pbtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            pbtn.textContent = open ? '收起預覽' : '預覽';
            if (open) { pv.scrollIntoView({ block: 'nearest' }); } // 預覽在對話框最下面，打開時捲到看得到
        });
    }
    dlg.addEventListener('close', function () { if (opener) { opener.focus(); } });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } }); // 點對話框外面的暗處
})();
