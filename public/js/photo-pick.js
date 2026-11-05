// 照片框（.photo-pick ＋ label.photo-box）：選好照片在框裡顯示預覽和檔名（熊叔叔 11/5）
(function () {
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList || !input.classList.contains('photo-pick')) { return; }
        var box = document.querySelector('label.photo-box[for="' + input.id + '"]'); // 欄位標題也是 label for，要指定框
        if (!box) { return; }
        var text = box.querySelector('.camera-text'), old = box.querySelector('.photo-box-preview');
        if (!box.dataset.empty) { box.dataset.empty = text.textContent; } // 框原本的字，清掉時還原
        if (old) { old.remove(); }
        var n = input.files.length;
        if (n) {
            var img = document.createElement('img');
            img.className = 'photo-box-preview'; img.alt = '';
            img.src = URL.createObjectURL(input.files[0]);
            box.insertBefore(img, text);
            text.textContent = n > 1 ? '選了 ' + n + ' 張（點這裡重選）' : input.files[0].name + '（點這裡換一張）';
        } else {
            text.textContent = box.dataset.empty;
        }
    });
})();
