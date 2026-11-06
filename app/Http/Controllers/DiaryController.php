<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiaryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // ?mine=1：側邊選單「我的日記」（設計稿 Figma 1156:11227）只列自己的
        $mine = $request->boolean('mine');
        // 大家的日記就是生活日記牆（/）：舊的「心情日記」列表頁拿掉（熊叔叔 11/6 TG「/diaries 應該直接在生活日記牆，心情日記這個應該拿掉了」）
        if (! $mine) {
            return redirect()->route('home');
        }
        $diaries = Diary::visibleTo($request->user())
            ->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', $request->user()->blockRelatedIds())
            ->whereHas('user', fn ($q) => $q->active()->orWhere('id', $request->user()->id))
            ->with('user:id,name,is_active')
            ->withInteractions($request->user())
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(10);

        return view('diaries.index', ['diaries' => $diaries->withQueryString(), 'mine' => $mine]);
    }

    public function show(Request $request, Diary $diary): View
    {
        // 不公開的日記、有封鎖關係的，別人看到的是 404（不讓人知道這篇存在）
        $me = $request->user();
        abort_unless($diary->isReachableBy($me), 404);

        $diary->load('user:id,name,is_active')->loadInteractions($me);
        // 留言：舊的在上面（照時間讀下來）；跟我有封鎖關係的人的留言不顯示
        $comments = $diary->comments()->with('user.profile')->whereHas('user', fn ($u) => $u->active())
            ->whereNotIn('user_id', $me->blockRelatedIds())
            ->orderBy('created_at')->orderBy('id')->get();
        $comments->each->setRelation('diary', $diary); // deletableBy 要用，不再查一次

        return view('diaries.show', ['diary' => $diary, 'comments' => $comments]);
    }
}
