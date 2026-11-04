// 回報問題／建議：記下畫面大小（例如 390x844），管理員看得出是手機還是電腦、版面可能怎麼擠
(function () {
    var el = document.getElementById('feedback-screen');
    if (el && !el.value) {
        el.value = Math.round(window.innerWidth) + 'x' + Math.round(window.innerHeight);
    }
})();
