<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 寫日記、改日記、刪日記：只有作者本人可以（別人一律 404，不讓人知道這篇存在）
class DiaryWriteController extends Controller
{
    public function create(): View
    {
        return view('diaries.form', ['diary' => new Diary(['visibility' => 'public'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $diary = $request->user()->diaries()->create($this->validated($request) + ['published_on' => now()->toDateString()]);

        return redirect()->route('diaries.show', $diary)->with('status', '日記發表了。');
    }

    public function edit(Request $request, Diary $diary): View
    {
        $this->authorizeOwner($request, $diary);

        return view('diaries.form', ['diary' => $diary]);
    }

    public function update(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeOwner($request, $diary);
        $diary->update($this->validated($request));

        return redirect()->route('diaries.show', $diary)->with('status', '日記改好了。');
    }

    public function destroy(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeOwner($request, $diary);
        $diary->delete();

        return redirect()->route('diaries.index')->with('status', '日記刪掉了。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['required', 'in:public,private'],
        ], [], ['title' => '標題', 'body' => '內容', 'visibility' => '誰看得到']);
    }

    private function authorizeOwner(Request $request, Diary $diary): void
    {
        abort_unless($diary->user_id === $request->user()->id, 404);
    }
}
