// 圖片點了放大看（熊叔叔 11/5「這個需要點選他可以放大觀看，不然字都好小」）
// <img data-zoom>：點圖放大；<button data-zoom-src="…">：點按鈕放大那張。用 <dialog>，Esc 或點外面關，關掉焦點回原處（瀏覽器處理）
(function () {
    var dlg = document.createElement('dialog');
    dlg.className = 'zoom-dialog';
    dlg.setAttribute('aria-label', '放大的圖片');
    var close = document.createElement('button');
    close.type = 'button'; close.className = 'dialog-close'; close.setAttribute('aria-label', '關閉'); close.textContent = '✕';
    var img = document.createElement('img');
    dlg.appendChild(close); dlg.appendChild(img);
    document.body.appendChild(dlg);
    // 很寬的圖（例如封面 551×165）：手機直拿時改成可以左右滑著看（小布 11/6「放大後跟原本一樣寬，經文還是看不清」）
    img.addEventListener('load', function () { dlg.classList.toggle('zoom-wide', img.naturalWidth > img.naturalHeight * 2); });
    function open(src, alt) { img.src = src; img.alt = alt || ''; if (dlg.showModal) { dlg.showModal(); } else { window.open(src); } }
    close.addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
    Array.prototype.forEach.call(document.querySelectorAll('img[data-zoom]'), function (el) {
        el.style.cursor = 'zoom-in'; el.tabIndex = 0; el.setAttribute('role', 'button');
        el.addEventListener('click', function () { open(el.src, el.alt); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(el.src, el.alt); } });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-zoom-src]'), function (b) {
        b.addEventListener('click', function () { open(b.getAttribute('data-zoom-src'), b.getAttribute('aria-label')); });
    });
})();
