// 帳號設定「推播」：在這台裝置開啟／關閉推播（熊叔叔 10/29「Web app 加到主畫面可以做」）。
// iPhone 要先「加入主畫面」、從主畫面打開才能開推播（iOS 16.4 以上）。
(function () {
  var box = document.getElementById('push-box');
  if (!box) return;
  var status = document.getElementById('push-status');
  var onBtn = document.getElementById('push-on');
  var offBtn = document.getElementById('push-off');
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
    say('這個瀏覽器還不能收推播。iPhone 請先用 Safari 的「分享 → 加入主畫面」，再從主畫面打開愛諾園。');
    return;
  }

  navigator.serviceWorker.register('/sw.js').then(function (reg) {
    return reg.pushManager.getSubscription().then(function (sub) {
      say(sub ? '這台裝置已經開啟推播。' : '這台裝置還沒開啟推播。');
      onBtn.hidden = !!sub;
      offBtn.hidden = !sub;

      onBtn.addEventListener('click', function () {
        Notification.requestPermission().then(function (p) {
          if (p !== 'granted') { say('沒有開啟：請在瀏覽器或手機設定裡允許愛諾園的通知。'); return; }
          reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(key) }).then(function (s) {
            return post('/push/subscribe', s.toJSON());
          }).then(function (r) {
            if (r && !r.ok) { throw new Error('rejected'); }
            say('這台裝置已經開啟推播。有人傳紙條、送禮物時會通知您。');
            onBtn.hidden = true; offBtn.hidden = false;
          }).catch(function () { say('開啟推播失敗，請稍後再試。'); });
        });
      });
      offBtn.addEventListener('click', function () {
        reg.pushManager.getSubscription().then(function (s) {
          if (!s) return;
          var endpoint = s.endpoint;
          return s.unsubscribe().then(function () { return post('/push/unsubscribe', { endpoint: endpoint }); });
        }).then(function () {
          say('這台裝置已經關閉推播。');
          onBtn.hidden = false; offBtn.hidden = true;
        }).catch(function () { say('關閉推播失敗，請稍後再試。'); });
      });
    });
  }).catch(function () { say('這個瀏覽器還不能收推播。'); });
})();
