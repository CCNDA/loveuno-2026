// 送出表單被擋下、頁面回來時，焦點移到錯誤摘要（鍵盤、螢幕報讀的人不用自己找；GOV.UK 錯誤摘要的做法）。小鹿 11/6 複檢時看到焦點停在頁面最上面
(function () {
    var box = document.querySelector('.error-summary');
    if (!box || box.offsetParent === null) { return; }
    if (!box.hasAttribute('tabindex')) { box.setAttribute('tabindex', '-1'); }
    box.focus();
})();
