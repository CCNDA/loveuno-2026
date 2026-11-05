// 字數計數（設計稿的「0/50」「0/1000」）：<input|textarea data-count id="x"> 配 <p id="x-count" class="char-count"><span>0</span>/50</p>
// 寫日記另由 write-dialog.js 處理；這支給自我介紹、聯絡我們等頁（小鹿 11/6 #13 #18）
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-count]'), function (el) {
        var out = document.getElementById(el.id + '-count');
        if (!out || out.hasAttribute('data-bound')) { return; }
        out.setAttribute('data-bound', '1');
        var n = out.querySelector('span');
        el.addEventListener('input', function () { n.textContent = Array.from(el.value).length; });
    });
})();
