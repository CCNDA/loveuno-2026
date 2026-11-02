<!doctype html>
{{-- 伺服器出錯時版面本身可能也壞了，這一頁不用版面、不讀資料庫；還是要能回報（回應表單兩面審查 11/2） --}}
<html lang="zh-Hant-TW">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>網站出了一點問題 - 愛諾園</title>
<style>body{font-family:system-ui,sans-serif;font-size:18px;line-height:1.7;max-width:40rem;margin:2rem auto;padding:0 16px;color:#3d2a22;background:#fffaf5}a{color:#8a4a32}</style>
</head>
<body>
<main>
<h1>網站出了一點問題</h1>
<p>不是您的錯。我們會儘快修好，請稍後再試一次。</p>
<p><a href="/">回首頁</a></p>
<p><a href="/feedback?from={{ urlencode(request()->getRequestUri()) }}" target="_blank" rel="noopener">回報問題<span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">（開新分頁）</span></a>：告訴我們您剛剛在做什麼。</p>
</main>
</body>
</html>
