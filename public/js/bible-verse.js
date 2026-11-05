// 最愛經文：選書卷、填章節就去 /bible/verse 查和合本，帶到下面（熊叔叔 11/5「聖經要自己帶出來」）
(function () {
    var out = document.getElementById('verse-preview');
    if (!out) { return; }
    var book = document.getElementById('verse_book'), ch = document.getElementById('verse_chapter'), vs = document.getElementById('verse_verse');
    var timer = null;
    function look() {
        if (!book.value || !ch.value || !vs.value) { out.textContent = '選好書卷、章、節，這裡會帶出經文。'; out.classList.remove('is-error'); return; }
        var q = '?book=' + encodeURIComponent(book.value) + '&chapter=' + encodeURIComponent(ch.value) + '&verse=' + encodeURIComponent(vs.value);
        fetch(out.getAttribute('data-url') + q, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                out.classList.toggle('is-error', !res.ok);
                out.textContent = res.ok ? res.j.text : book.value + ' 沒有 ' + ch.value + ' 章 ' + vs.value + ' 節，請再看一下。';
            })
            .catch(function () { out.textContent = '經文暫時查不到，請稍後再試。'; });
    }
    [book, ch, vs].forEach(function (el) {
        el.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(look, 400); });
        el.addEventListener('change', look);
    });
})();
