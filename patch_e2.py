def patch(p, pairs):
    s = open(p, encoding="utf-8").read()
    for a, b in pairs:
        assert s.count(a) == 1, (p, a[:90], s.count(a))
        s = s.replace(a, b)
    open(p, "w", encoding="utf-8").write(s)
    print("patched", p)


patch("routes/web.php", [
("use App\\Http\\Controllers\\Admin\\PhotoReviewController;", "use App\\Http\\Controllers\\Admin\\PhotoReviewController;\nuse App\\Http\\Controllers\\AlbumController;"),
("""    Route::get('/me/intro', [ProfileController::class, 'editIntro'])->name('profile.intro');""",
 """    Route::get('/me/intro', [ProfileController::class, 'editIntro'])->name('profile.intro');
    // 個人相簿（最多 9 張，要審核）
    Route::get('/me/album', [AlbumController::class, 'edit'])->name('album.edit');
    Route::post('/me/album', [AlbumController::class, 'store'])->middleware('throttle:20,60,album-upload')->name('album.store');
    Route::delete('/me/album/{photo}', [AlbumController::class, 'destroy'])->whereNumber('photo')->name('album.destroy');
    Route::get('/album/{photo}', [AlbumController::class, 'show'])->whereNumber('photo')->name('album.show');"""),
("""        Route::get('/reports', [ReportReviewController::class, 'index'])->name('admin.reports');""",
 """        Route::post('/album/{photo}', [PhotoReviewController::class, 'decideAlbum'])->whereNumber('photo')->name('admin.album.decide');
        Route::get('/reports', [ReportReviewController::class, 'index'])->name('admin.reports');"""),
])

patch("app/Http/Controllers/MemberController.php", [
("""        $diaries = $tab === 'diary'""",
 """        $album = $tab === 'album'
            ? AlbumPhoto::where('user_id', $user->id)->orderBy('id')->get()->filter->visibleTo($viewer)->values()
            : collect();
        $diaries = $tab === 'diary'"""),
("""            'tab' => $tab,""", """            'tab' => $tab,
            'album' => $album,"""),
("use App\\Models\\Profile;", "use App\\Models\\AlbumPhoto;\nuse App\\Models\\Profile;"),
])

patch("resources/views/center/tabs.blade.php", [
("""            <li><a href="{{ route('profile.intro') }}" @if(($sub ?? '') === 'intro') aria-current="page" @endif>自我介紹</a></li>""",
 """            <li><a href="{{ route('profile.intro') }}" @if(($sub ?? '') === 'intro') aria-current="page" @endif>自我介紹</a></li>
            <li><a href="{{ route('album.edit') }}" @if(($sub ?? '') === 'album') aria-current="page" @endif>個人相簿</a></li>"""),
])

# 管理員審核：多一區「相簿照片」
patch("app/Http/Controllers/Admin/PhotoReviewController.php", [
("""        return view('admin.photos', ['pending' => $pending]);""",
 """        $album = AlbumPhoto::with('user:id,name')->where('status', 'pending')->orderBy('id')->get();

        return view('admin.photos', ['pending' => $pending, 'album' => $album]);"""),
("use App\\Models\\Profile;", "use App\\Models\\AlbumPhoto;\nuse App\\Models\\Profile;"),
])
s = open("app/Http/Controllers/Admin/PhotoReviewController.php", encoding="utf-8").read()
i = s.rindex("}")
s = s[:i].rstrip() + """

    // 相簿照片：通過就給別人看；不通過就刪檔、刪掉這一張
    public function decideAlbum(Request $request, AlbumPhoto $photo): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
        ], [
            'decision.required' => '請按「通過」或「不通過」其中一個按鈕。',
            'decision.in' => '請按「通過」或「不通過」其中一個按鈕。',
        ]);
        abort_unless($photo->status === 'pending', 409);
        $name = $photo->user->name;
        if ($data['decision'] === 'approve') {
            $photo->forceFill(['status' => 'approved'])->save();
            $msg = sprintf('%s 的相簿照片通過了。', $name);
        } else {
            Storage::disk('avatars')->delete($photo->path);
            $photo->delete();
            $msg = sprintf('%s 的相簿照片不通過，檔案已刪除。', $name);
        }

        return redirect()->route('admin.photos')->with('status', $msg);
    }
}
"""
open("app/Http/Controllers/Admin/PhotoReviewController.php", "w", encoding="utf-8").write(s)
print("decideAlbum")

patch("resources/views/admin/photos.blade.php", [
("""@endforeach
@endsection""",
 """@endforeach

<section aria-labelledby="review-album">
    <h2 id="review-album">相簿照片</h2>
    <p class="meta">待審核 {{ $album->count() }} 張</p>
    @if ($album->isEmpty())
        <p>目前沒有要審核的相簿照片。</p>
    @else
        <ul class="review-list">
            @foreach ($album as $ph)
                <li class="review-item">
                    <img src="{{ route('album.show', $ph) }}" alt="{{ $ph->user->name }} 的相簿照片" class="review-album" width="200" height="200">
                    <div>
                        <p class="member-name"><a href="{{ route('members.show', $ph->user) }}">{{ $ph->user->name }}</a></p>
                        <p class="meta">上傳時間 {{ $ph->created_at?->format('Y/n/j H:i') }}</p>
                        <form method="POST" action="{{ route('admin.album.decide', $ph) }}" class="review-actions">
                            @csrf
                            <button type="submit" name="decision" value="approve" class="button">通過</button>
                            <button type="submit" name="decision" value="reject" class="button button-secondary">不通過（刪掉）</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection"""),
])

s = open("public/css/app.css", encoding="utf-8").read()
s += """
/* 個人相簿 */
.album-grid { list-style: none; padding: 0; margin: 12px 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.album-item img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: var(--radius); display: block; background: #efe7dc; }
.album-item .meta { margin: 4px 0 0; }
.album-item .link-button { color: var(--brand); text-decoration: underline; padding: 0; min-height: 44px; }
.review-album { object-fit: cover; }
"""
open("public/css/app.css", "w", encoding="utf-8").write(s)
print("css")

d = "docs/design-deviations.md"
s = open(d, encoding="utf-8").read()
i = s.index("| 36 | 個人資料、自我介紹、會員檔案分頁 |")
j = s.index("\n", i)
row = ("| 37 | 個人相簿 | PageMemberProfile PersonalAlbum：「相簿照片(上限為9張)」、JPG／PNG 5M、「匯入資料」「其他」「個人化封面」；會員檔案「個人相簿」分頁 | "
       "0.20.0 起：會員中心 → 個人檔案 →「個人相簿」一次放一張、最多 9 張、可刪；跟大頭照一樣要管理員審核（審核照片頁多一區「相簿照片」），通過前只有本人和管理員看得到；會員檔案「個人相簿」分頁三欄縮圖，點了開大圖；照片長邊縮到 1200 | "
       "相簿是交友檔案的照片，比照大頭照審核（日記照片 10/29「1B」不審是另一件事）；「匯入資料」（舊站相簿匯入）等舊站資料匯入一起做；封面另外一頁 | 🟠 相簿要不要審核；一次可不可以放多張；舊站相簿要不要匯入 |\n")
s = s[:j + 1] + row + s[j + 1:]
s = s.replace("最後更新：2026-10-29（版本 0.19.0）", "最後更新：2026-10-29（版本 0.20.0）")
open(d, "w", encoding="utf-8").write(s)
open("VERSION", "w").write("0.20.0\n")
print("doc, version")
