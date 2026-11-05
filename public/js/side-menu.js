// 側邊選單（設計稿 Figma 1156:11227）：開／關、Esc 關、點外面關、關掉後焦點回到選單按鈕
(function () {
    var toggle = document.querySelector('.menu-toggle');
    var menu = document.getElementById('side-menu');
    if (!toggle || !menu) { return; }
    var closeBtn = menu.querySelector('.side-menu-close');
    var panel = menu.querySelector('.side-menu-panel');
    // 選單開著時背後整頁 inert：螢幕閱讀器的虛擬游標讀不到、也點不到（小布 11/5 審查）
    function background() {
        return Array.prototype.filter.call(document.body.children, function (el) { return el !== menu && el.tagName !== 'SCRIPT'; });
    }

    var docked = window.matchMedia('(min-width: 75rem)'); // 跟 app.css 的固定側邊選單同一個寬度

    function open() {
        menu.hidden = false;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'side-menu-title');
        background().forEach(function (el) { el.inert = true; });
        toggle.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('side-menu-open');
        closeBtn.focus();
    }
    function close(keepFocus) {
        menu.hidden = true;
        ['role', 'aria-modal', 'aria-labelledby'].forEach(function (a) { panel.removeAttribute(a); });
        background().forEach(function (el) { el.inert = false; });
        toggle.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('side-menu-open');
        if (!keepFocus) { toggle.focus(); }
    }
    // 抽屜開著時把視窗拉寬：變成固定在左邊，抽屜的狀態（inert、對話框）要收掉
    docked.addEventListener('change', function () { if (docked.matches && !menu.hidden) { close(true); } });
    toggle.addEventListener('click', function () { menu.hidden ? open() : close(); });
    closeBtn.addEventListener('click', function () { close(); });
    menu.addEventListener('click', function (e) { if (!panel.contains(e.target)) { close(); } });
    document.addEventListener('keydown', function (e) {
        if (menu.hidden) { return; }
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'Tab') { // 焦點留在選單裡
            var f = panel.querySelectorAll('a[href], button');
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
})();
