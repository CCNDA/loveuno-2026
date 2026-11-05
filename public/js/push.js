// 帳號設定「推播」開關：在這台裝置開啟／關閉推播（熊叔叔 10/29「Web app 加到主畫面可以做」；11/6 照設計稿 253:15337 改成開關）。
// iPhone 要先「加入主畫面」、從主畫面打開才能開推播（iOS 16.4 以上）。開關沒有 name，不會跟著即時存檔送出。
(function () {
  var box = document.getElementById('push-box');
  if (!box) return;
  var status = document.getElementById('push-status');
  var sw = document.getElementById('push-switch');
  var key = box.getAttribute('data-key');
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  function say(text) { status.textContent = text; }
  function b64ToBytes(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4);
    var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: JSON.stringify(body) });
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !key) {
    say(/iPhone|iPad/.test(navigator.userAgent)
      ? '推播：iPhone 要先用 Safari 的「分享 → 加入主畫面」，再從主畫面打開愛諾園，才能開。'
      : '推播：這個瀏覽器不能收推播，請改用 Chrome、Edge 或 Firefox。');
    return;
  }

  navigator.serviceWorker.register('/sw.js').then(function (reg) {
    return reg.pushManager.getSubscription().then(function (sub) {
      sw.checked = !!sub;
      sw.disabled = false;
      say(sub ? '推播：這台裝置已經開啟。' : '推播：一台裝置一台裝置開，打開開關時瀏覽器會詢問，按「允許」。');

      sw.addEventListener('change', function () {
        sw.disabled = true;
        if (sw.checked) {
          Notification.requestPermission().then(function (p) {
            if (p !== 'granted') { throw new Error('denied'); }
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(key) });
          }).then(function (s) {
            return post('/push/subscribe', s.toJSON());
          }).then(function (r) {
            if (r && !r.ok) { throw new Error('rejected'); }
            say('推播：這台裝置已經開啟。有人傳紙條、送禮物時會通知您。');
          }).catch(function (e) {
            sw.checked = false;
            say(e && e.message === 'denied' ? '推播沒有開啟：請在瀏覽器或手機設定裡允許愛諾園的通知。' : '開啟推播失敗，請稍後再試。');
          }).then(function () { sw.disabled = false; });
        } else {
          reg.pushManager.getSubscription().then(function (s) {
            if (!s) return;
            var endpoint = s.endpoint;
            return s.unsubscribe().then(function () { return post('/push/unsubscribe', { endpoint: endpoint }); });
          }).then(function () {
            say('推播：這台裝置已經關閉。');
          }).catch(function () {
            sw.checked = true;
            say('關閉推播失敗，請稍後再試。');
          }).then(function () { sw.disabled = false; });
        }
      });
    });
  }).catch(function () { say('推播：這個瀏覽器還不能收推播。'); });
})();
