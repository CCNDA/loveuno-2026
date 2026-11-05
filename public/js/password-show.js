// 登入頁「顯示密碼」眼睛（設計稿 24px/Show）：按一下密碼變明碼，再按一下藏回去
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('.password-show'), function (btn) {
        var input = document.getElementById(btn.getAttribute('aria-controls'));
        if (!input) { return; }
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });
})();
