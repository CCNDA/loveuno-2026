// 生活日記牆瀑布流：「載入更多日記」捲進畫面就自動抓下一批接在後面（熊叔叔 11/5）
// 抓失敗就留著連結，使用者自己按也能到下一頁
(function () {
    var list = document.querySelector('.home-wall .wall-list');
    var status = document.getElementById('wall-status');
    if (!list || !('IntersectionObserver' in window)) { return; }
    var busy = false;
    function watch() {
        var more = document.querySelector('[data-wall-more]');
        if (!more) { return; }
        var io = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting || busy) { return; }
            busy = true;
            io.disconnect();
            more.textContent = '載入中…';
            fetch(more.href, { headers: { 'X-Wall-More': '1' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var items = doc.querySelectorAll('.wall-list > li');
                    Array.prototype.forEach.call(items, function (li) { list.appendChild(document.adoptNode(li)); });
                    var oldP = more.closest('.wall-more'), newP = doc.querySelector('.wall-more');
                    if (newP) { oldP.replaceWith(document.adoptNode(newP)); } else { oldP.remove(); }
                    if (status) { status.textContent = '又載入了 ' + items.length + ' 篇日記'; }
                    busy = false;
                    watch();
                })
                .catch(function () { more.textContent = '載入更多日記'; busy = false; });
        }, { rootMargin: '400px 0px' });
        io.observe(more);
    }
    watch();
})();
