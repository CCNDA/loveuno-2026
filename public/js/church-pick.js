// 自我介紹「我的教會」（熊叔叔 11/6 決定 #11）：先選國家，教會名稱打字時列出名錄裡那個國家的教會（<datalist>）。
// 挑了建議裡的教會 → 隱藏欄 church_oid 記名錄編號；自己打的名稱（名錄找不到）→ church_oid 空白，照樣存。
// 同名的教會不只一間時，建議後面加（縣市 區）分開；存檔時伺服器會把名稱換回名錄的全名。
(function () {
    var input = document.getElementById('church'), oid = document.getElementById('church_oid');
    var country = document.getElementById('church_country'), list = document.getElementById('church-list');
    if (!input || !oid || !list || !window.fetch) { return; }
    var url = input.getAttribute('data-search'), timer = null, found = {}, asked = '';

    function fill(rows) {
        var count = {};
        list.textContent = '';
        found = {};
        rows.forEach(function (r) { count[r.name] = (count[r.name] || 0) + 1; });
        rows.forEach(function (r) {
            var v = count[r.name] > 1 && r.place ? r.name + '（' + r.place + '）' : r.name;
            var o = document.createElement('option');
            o.value = v;
            if (r.place) { o.label = r.place; }
            found[v] = r.oid;
            list.appendChild(o);
        });
    }
    function sync() { oid.value = found[input.value] ? String(found[input.value]) : ''; }

    input.addEventListener('input', function () {
        sync();
        clearTimeout(timer);
        var q = input.value.trim();
        if (!q || found[input.value]) { return; }
        timer = setTimeout(function () {
            var key = (country ? country.value : '') + '|' + q;
            if (key === asked) { return; }
            asked = key;
            fetch(url + '?country=' + encodeURIComponent(country ? country.value : '') + '&q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (rows) { fill(rows); sync(); })
                .catch(function () {});
        }, 250);
    });
    if (country) {
        country.addEventListener('change', function () { list.textContent = ''; found = {}; asked = ''; oid.value = ''; });
    }
})();
