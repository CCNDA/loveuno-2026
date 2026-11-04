<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 站務公告（設計稿 PageInformation 列表、PageInformationDetail 內頁）：不用登入也看得到
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('news.index', [
            'items' => Announcement::published()->orderByDesc('published_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        abort_unless($announcement->isPublished() || $request->user()?->is_admin, 404); // 草稿只有管理員看得到（預覽）

        return view('news.show', ['item' => $announcement]);
    }
}
