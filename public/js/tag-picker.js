// 慣用語言、興趣、喜愛的運動：設計稿是「標籤輸入」（熊叔叔 11/6 決定 #11）。
// 沒有 JS 時照舊是勾選框；有 JS 時把勾選框藏起來，改成「已選的標籤（按一下拿掉）＋輸入框（打字或點一下列出還沒選的）」，最多 3 個。
// 真正送出的還是那些勾選框：這裡只是幫忙勾、取消勾，再發 change 事件，自動存檔照舊。
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('fieldset[data-tags]'), function (fs) {
        var boxes = Array.prototype.slice.call(fs.querySelectorAll('input[type=checkbox]'));
        var grid = fs.querySelector('.check-grid');
        if (!boxes.length || !grid) { return; }
        var max = parseInt(fs.getAttribute('data-tags'), 10) || 3;
        var min = parseInt(fs.getAttribute('data-tags-min'), 10) || 0; // 必填的至少留幾個（小鹿 11/6：拿掉最後一個會存不進去、畫面卻清空）
        var name = fs.querySelector('legend').textContent.replace(/（.*?）/g, '');

        var box = document.createElement('div');
        box.className = 'tag-box';
        var chips = document.createElement('ul');
        chips.className = 'tag-chips';
        chips.setAttribute('aria-label', '已選的' + name);
        chips.tabIndex = -1; // 加滿時焦點放這裡（放在「拿掉」鈕上，同一個 Enter 會把它按下去；小霏 11/6）
        var label = document.createElement('label');
        label.className = 'visually-hidden';
        label.htmlFor = fs.id + '-add';
        label.textContent = '加一個' + name;
        var input = document.createElement('input');
        input.type = 'text';
        input.id = fs.id + '-add';
        input.className = 'tag-add';
        input.autocomplete = 'off';
        input.setAttribute('list', fs.id + '-options');
        if (document.getElementById(fs.id + '-hint')) { input.setAttribute('aria-describedby', fs.id + '-hint'); }
        var list = document.createElement('datalist');
        list.id = fs.id + '-options';
        box.appendChild(chips);
        box.appendChild(label);
        box.appendChild(input);
        box.appendChild(list);
        grid.parentNode.insertBefore(box, grid.nextSibling);
        fs.classList.add('is-tags');

        function toggle(b, on) {
            b.checked = on;
            b.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function render(focusLast) {
            var chosen = boxes.filter(function (b) { return b.checked; });
            var locked = chosen.length <= min;
            chips.textContent = '';
            list.textContent = '';
            chosen.forEach(function (b) {
                var li = document.createElement('li'), btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'tag-chip';
                btn.textContent = b.value;
                btn.setAttribute('aria-label', locked ? '「' + b.value + '」（至少要留 ' + min + ' 個，要換的話先加別的再拿掉）' : '拿掉「' + b.value + '」');
                if (locked) { btn.disabled = true; btn.title = '至少要留 ' + min + ' 個，先加別的再拿掉'; }
                btn.addEventListener('click', function () { toggle(b, false); render(); input.focus(); });
                li.appendChild(btn);
                chips.appendChild(li);
            });
            boxes.forEach(function (b) {
                if (!b.checked) { var o = document.createElement('option'); o.value = b.value; list.appendChild(o); }
            });
            var full = chosen.length >= max;
            input.disabled = full;
            input.placeholder = full ? '最多 ' + max + ' 個' : '打字或點這裡選';
            if (focusLast && full) { chips.focus(); }
        }
        function addTyped() {
            var hit = boxes.filter(function (b) { return !b.checked && b.value === input.value.trim(); })[0];
            if (!hit) { return false; }
            toggle(hit, true);
            input.value = '';
            render(true);
            if (!input.disabled) { input.focus(); }
            return true;
        }
        input.addEventListener('input', addTyped);
        // Enter 在標籤欄是「加入這個標籤」，不是送出整張表（小霏 11/6：按 Enter 表單被送出，存到一半的資料）
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            addTyped();
        });
        render();
    });
})();
