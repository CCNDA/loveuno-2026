// 登入頁：瀏覽器把 cookie 關掉時先提醒（Asana 1220224661011603）。
// 沒有 cookie 就留不住登入，按「登入」只會看到「頁面放太久了」，會友不知道為什麼。
(function () {
    var box = document.getElementById('cookie-warn');
    if (box && !navigator.cookieEnabled) {
        box.hidden = false;
    }
})();
