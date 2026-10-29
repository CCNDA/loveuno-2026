<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiaryController extends Controller
{
    public function index(Request $request): View
    {
        $diaries = Diary::visibleTo($request->user())
            ->whereNotIn('user_id', $request->user()->blockRelatedIds())
            ->with('user:id,name')
            ->withInteractions($request->user())
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(10);

        return view('diaries.index', ['diaries' => $diaries]);
    }

    public function show(Request $request, Diary $diary): View
    {
        // 不公開的日記、有封鎖關係的，別人看到的是 404（不讓人知道這篇存在）
        $me = $request->user();
        abort_unless($diary->isReachableBy($me), 404);

        $diary->load('user:id,name')->loadInteractions($me);
        // 留言：舊的在上面（照時間讀下來）；跟我有封鎖關係的人的留言不顯示
        $comments = $diary->comments()->with('user.profile')
            ->whereNotIn('user_id', $me->blockRelatedIds())
            ->orderBy('created_at')->orderBy('id')->get();
        $comments->each->setRelation('diary', $diary); // deletableBy 要用，不再查一次

        return view('diaries.show', ['diary' => $diary, 'comments' => $comments]);
    }
}
