// 照片框（.photo-pick ＋ label.photo-box）：選好照片在框裡顯示預覽和檔名（熊叔叔 11/5）
(function () {
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList || !input.classList.contains('photo-pick')) { return; }
        var box = document.querySelector('label[for="' + input.id + '"]');
        if (!box) { return; }
        var text = box.querySelector('.camera-text'), old = box.querySelector('.photo-box-preview');
        if (old) { old.remove(); }
        if (input.files.length) {
            var img = document.createElement('img');
            img.className = 'photo-box-preview'; img.alt = '';
            img.src = URL.createObjectURL(input.files[0]);
            box.insertBefore(img, text);
            text.textContent = input.files[0].name + '（點這裡換一張）';
        } else {
            text.textContent = '點這裡選照片';
        }
    });
})();
