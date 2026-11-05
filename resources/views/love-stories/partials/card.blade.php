{{-- 愛的見證卡片（設計稿 PageWelcome LoveStory）：照片、標題、前 90 字。$story --}}
<li class="story-card">
    @if ($story->photo_path)
        <img src="{{ route('love-stories.photo', $story) }}" alt="" width="400" height="260" loading="lazy" class="story-img">
    @endif
    <h3><a href="{{ route('love-stories.show', $story) }}">{{ $story->title }}</a></h3>
    @if ($story->approved_at)<p class="story-date"><time datetime="{{ $story->approved_at->toDateString() }}">{{ $story->approved_at->format('Y/m/d') }}</time></p>@endif
    <p class="story-excerpt">{{ $story->excerpt() }}</p>
</li>
