<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// 管理員：站務公告的新增、修改、刪除
class AnnouncementAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.news.index', ['items' => Announcement::orderByDesc('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.news.form', ['item' => new Announcement(['published_at' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $a = Announcement::create($this->validated($request));

        return redirect()->route('admin.news')->with('status', sprintf('公告「%s」存好了。', $a->title));
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.news.form', ['item' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return redirect()->route('admin.news')->with('status', sprintf('公告「%s」改好了。', $announcement->title));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.news')->with('status', sprintf('公告「%s」刪掉了。', $announcement->title));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:5000'],
            'publish' => ['required', 'in:now,draft,at'],
            'published_at' => ['nullable', 'required_if:publish,at', 'date'],
        ], [
            'published_at.required_if' => '請填要發佈的時間。',
        ], ['title' => '標題', 'body' => '內容', 'published_at' => '發佈時間']);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            // 管理員填的是自己看到的時間（會員所在的時區），存成台北時間（app.timezone）
            'published_at' => match ($data['publish']) {
                'now' => now(),
                'draft' => null,
                'at' => Carbon::parse($data['published_at'], app()->bound('display_tz') ? app('display_tz') : config('app.timezone'))->setTimezone(config('app.timezone')),
            },
        ];
    }
}
