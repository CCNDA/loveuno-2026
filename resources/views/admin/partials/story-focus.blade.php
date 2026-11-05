{{-- 照片焦點：點照片上的臉（有 JS），或直接填百分比再按「設定」。$s --}}
@if ($s->photo_path)
<form method="POST" action="{{ route('admin.love-stories.focus', $s) }}" class="story-focus" id="focus-{{ $s->id }}">
    @csrf
    <p class="hint">點一下照片上的臉，裁成方形、長條時就會以臉為中心（現在：左右 {{ $s->photo_focus_x ?? 50 }}%、上下 {{ $s->photo_focus_y ?? 30 }}%{{ $s->photo_focus_x === null ? '，還沒設，先用上方' : '' }}）。</p>
    <div class="story-focus-pic">
        <img src="{{ route('love-stories.photo', $s) }}" alt="「{{ $s->title }}」的照片，點臉的位置" data-focus-pick>
        <span class="story-focus-dot" aria-hidden="true" style="left: {{ $s->photo_focus_x ?? 50 }}%; top: {{ $s->photo_focus_y ?? 30 }}%"></span>
    </div>
    <p class="story-focus-nums">
        <label>左右 <input name="x" type="number" min="0" max="100" value="{{ $s->photo_focus_x ?? 50 }}" class="input-short">%</label>
        <label>上下 <input name="y" type="number" min="0" max="100" value="{{ $s->photo_focus_y ?? 30 }}" class="input-short">%</label>
        <button type="submit" class="button button-light">設定焦點<span class="visually-hidden">「{{ $s->title }}」</span></button>
    </p>
</form>
@endif
