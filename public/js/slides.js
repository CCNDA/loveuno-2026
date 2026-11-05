// 個人首頁輪播（設計稿 Profile 上方 slide）：5 秒換一張；滑鼠移上去、鍵盤焦點在裡面時停；有「暫停輪播」鈕（WCAG 2.2.2）；
// 系統設定「減少動態」就不自動換，只留圓點自己點
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('.slides'), function (box) {
        var slides = box.querySelectorAll('.slide');
        var controls = box.querySelector('.slides-controls');
        if (slides.length < 2 || !controls) { return; }
        var dots = controls.querySelectorAll('.slides-dot');
        var pause = controls.querySelector('.slides-pause');
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var cur = 0, timer = null, paused = reduce, hover = false;
        controls.hidden = false;
        box.classList.add('slides-js');
        function show(i) {
            cur = (i + slides.length) % slides.length;
            Array.prototype.forEach.call(slides, function (s, k) { s.classList.toggle('is-current', k === cur); s.setAttribute('aria-hidden', k === cur ? 'false' : 'true'); });
            Array.prototype.forEach.call(dots, function (d, k) { d.setAttribute('aria-current', k === cur ? 'true' : 'false'); });
        }
        function tick() { if (!paused && !hover) { show(cur + 1); } }
        function start() { stop(); timer = setInterval(tick, 5000); }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function setPaused(p) { paused = p; pause.setAttribute('aria-pressed', p ? 'true' : 'false'); pause.textContent = p ? '繼續輪播' : '暫停輪播'; }
        Array.prototype.forEach.call(dots, function (d, k) { d.addEventListener('click', function () { show(k); start(); }); });
        pause.addEventListener('click', function () { setPaused(!paused); });
        box.addEventListener('mouseenter', function () { hover = true; });
        box.addEventListener('mouseleave', function () { hover = false; });
        box.addEventListener('focusin', function () { hover = true; });
        box.addEventListener('focusout', function () { hover = false; });
        setPaused(paused);
        show(0);
        start();
    });
})();
