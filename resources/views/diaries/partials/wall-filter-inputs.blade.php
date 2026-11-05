{{-- 從動態牆送出（禱告、留言）後回到同樣的篩選；值在 DiaryInteractionController::wallUrl() 再驗一次 --}}
@if (($wallScope ?? 'all') === 'friends')<input type="hidden" name="wall_scope" value="friends">@endif
@if (in_array($wallKind ?? 'all', ['diary', 'prayer'], true))<input type="hidden" name="wall_kind" value="{{ $wallKind }}">@endif
