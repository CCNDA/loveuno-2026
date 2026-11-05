// 通知鈴浮動框（<details>）：點外面、按 Esc 關；按 Esc 時焦點回到鈴
(function () {
    var box = document.querySelector('details.notif');
    if (!box) { return; }
    document.addEventListener('click', function (e) { if (box.open && !box.contains(e.target)) { box.open = false; } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && box.open) { box.open = false; box.querySelector('summary').focus(); }
    });
})();
