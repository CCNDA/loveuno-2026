// 愛諾園的 service worker：只處理推播（不快取頁面）。
// 推播沒有內容（伺服器不做加密），收到後回網站抓「您有幾封新紙條」再顯示（app/Http/Controllers/PushController::summary）。
self.addEventListener('push', function (event) {
  event.waitUntil(
    fetch('/push/summary', { credentials: 'include', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; })
      .then(function (d) {
        d = d || { title: '愛諾園', body: '您有新的消息。', url: '/' };
        return self.registration.showNotification(d.title, {
          body: d.body,
          icon: '/images/app/icon-192.png',
          badge: '/images/app/icon-192.png',
          tag: 'loveuno',
          renotify: true,
          data: { url: d.url }
        });
      })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      if ('focus' in list[i]) { list[i].navigate(url); return list[i].focus(); }
    }
    return clients.openWindow(url);
  }));
});
