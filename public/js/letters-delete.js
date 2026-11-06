// 傳紙條情書清單：按垃圾桶前先問一次（只刪自己這邊，刪了畫面上救不回來）。沒勾就交給伺服器回「請先勾選」
(function () {
    var form = document.getElementById('letters-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var n = form.querySelectorAll('input[name="ids[]"]:checked').length;
        if (n > 0 && !window.confirm('要刪除勾選的 ' + n + ' 封信嗎？只會從您這邊刪除，對方還看得到。')) {
            e.preventDefault();
        }
    });
})();
