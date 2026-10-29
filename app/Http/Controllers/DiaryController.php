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
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(10);

        return view('diaries.index', ['diaries' => $diaries]);
    }

    public function show(Request $request, Diary $diary): View
    {
        // 不公開的日記，別人看到的是 404（不讓人知道這篇存在）
        abort_unless($diary->isVisibleTo($request->user()), 404);
        abort_if($request->user()->blockedEitherWay($diary->user), 404);

        return view('diaries.show', ['diary' => $diary->load('user:id,name')]);
    }
}
