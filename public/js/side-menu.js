// 側邊選單（設計稿 Figma 1156:11227）
// 寬螢幕（≥1200px）：固定在左邊，☰／✕ 可以收起來、再打開（熊叔叔 11/5「電腦版也可以隱藏選單」），記在這台瀏覽器。
// 窄螢幕：☰ 打開的抽屜——Esc 關、點外面關、焦點留在選單裡、關掉後焦點回到 ☰。
(function () {
    var toggle = document.querySelector('.menu-toggle');
    var menu = document.getElementById('side-menu');
    if (!toggle || !menu) { return; }
    var root = document.documentElement;
    var closeBtn = menu.querySelector('.side-menu-close');
    var panel = menu.querySelector('.side-menu-panel');
    var docked = window.matchMedia('(min-width: 75rem)'); // 跟 app.css 的固定側邊選單同一個寬度
    // 抽屜開著時背後整頁 inert：螢幕閱讀器的虛擬游標讀不到、也點不到（小布 11/5 審查）
    function background() {
        return Array.prototype.filter.call(document.body.children, function (el) { return el !== menu && el.tagName !== 'SCRIPT'; });
    }
    function collapsed() { return root.classList.contains('side-menu-collapsed'); }
    function sync() {
        var shown = docked.matches ? !collapsed() : !menu.hidden;
        toggle.setAttribute('aria-expanded', shown ? 'true' : 'false');
    }
    function setCollapsed(c) {
        root.classList.toggle('side-menu-collapsed', c);
        try { localStorage.setItem('lu-menu', c ? 'collapsed' : 'open'); } catch (e) { /* 私密視窗存不了就算了 */ }
        sync();
    }

    function open() {
        menu.hidden = false;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'side-menu-title');
        background().forEach(function (el) { el.inert = true; });
        root.classList.add('side-menu-open');
        sync();
        closeBtn.focus();
    }
    function close(keepFocus) {
        menu.hidden = true;
        ['role', 'aria-modal', 'aria-labelledby'].forEach(function (a) { panel.removeAttribute(a); });
        background().forEach(function (el) { el.inert = false; });
        root.classList.remove('side-menu-open');
        sync();
        if (!keepFocus) { toggle.focus(); }
    }

    toggle.addEventListener('click', function () {
        if (docked.matches) { setCollapsed(!collapsed()); return; }
        menu.hidden ? open() : close();
    });
    closeBtn.addEventListener('click', function () {
        if (docked.matches) { setCollapsed(true); toggle.focus(); return; }
        close();
    });
    menu.addEventListener('click', function (e) { if (!docked.matches && !panel.contains(e.target)) { close(); } });
    // 抽屜開著時把視窗拉寬：變成固定在左邊，抽屜的狀態（inert、對話框）要收掉
    docked.addEventListener('change', function () {
        if (docked.matches && !menu.hidden) { close(true); }
        sync();
    });
    document.addEventListener('keydown', function (e) {
        if (menu.hidden) { return; } // 固定在左邊時 menu 還是 hidden（CSS 讓它顯示），不攔鍵盤
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'Tab') { // 焦點留在選單裡
            var f = panel.querySelectorAll('a[href], button');
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
    sync();
})();
