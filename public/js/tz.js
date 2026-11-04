// 時區自動偵測（熊叔叔 10/29「直接偵測」）：把瀏覽器的時區存在 cookie「tz」，網站照這個時區顯示幾點幾分。
// 沒有 JavaScript 的話就用台北時間，不影響使用。
(function () {
  try {
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!tz) return;
    var m = document.cookie.match(/(?:^|; )tz=([^;]*)/);
    var current = m ? decodeURIComponent(m[1]) : '';
    if (current !== tz) {
      document.cookie = 'tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }
  } catch (e) {
    // 舊瀏覽器沒有 Intl：維持台北時間
  }
})();
