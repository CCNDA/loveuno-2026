// 身份認證拍照框：拍好（選好）照片後，框裡的「拍照」換成檔名，讓人知道有選到
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('.camera-input'), function (input) {
        input.addEventListener('change', function () {
            var t = document.querySelector('label[for="' + input.id + '"] .camera-text');
            if (t) { t.textContent = input.files.length ? '已選：' + input.files[0].name + '（按這裡重拍）' : '拍照'; }
        });
    });
})();
