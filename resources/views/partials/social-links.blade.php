{{-- 設計稿四個圓形社群圖示（Facebook、LINE、Telegram、星星＝Google 評論；連結是 Nien Chen 在 Figma 留的）。
     $light：側邊選單用白底版（設計稿 menu 1156:11227），頁尾用蜜桃色版（footer 822:12183） --}}
@php($sfx = ($light ?? false) ? '-light' : '')
<ul class="{{ $class }}">
    <li><a href="{{ config('loveuno.links.facebook') }}" rel="noopener"><img src="{{ asset("images/brand/social-facebook{$sfx}.svg") }}" alt="協會 Facebook" width="40" height="40" loading="lazy"></a></li>
    <li><a href="{{ config('loveuno.links.line') }}" rel="noopener"><img src="{{ asset("images/brand/social-line{$sfx}.svg") }}" alt="協會 LINE" width="40" height="40" loading="lazy"></a></li>
    <li><a href="{{ config('loveuno.links.telegram') }}" rel="noopener"><img src="{{ asset("images/brand/social-telegram{$sfx}.svg") }}" alt="協會 Telegram" width="40" height="40" loading="lazy"></a></li>
    <li><a href="{{ config('loveuno.links.google_review') }}" rel="noopener"><img src="{{ asset("images/brand/social-star{$sfx}.svg") }}" alt="協會 Google 評論" width="40" height="40" loading="lazy"></a></li>
</ul>
