<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoveStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// 管理員：愛的見證審核（通過才公開）；公開的也可以撤下
class LoveStoryReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.love-stories', [
            'pending' => LoveStory::with(['submitter', 'male', 'female'])->where('status', 'pending')->orderBy('id')->get(),
            'approved' => LoveStory::published()->get(),
        ]);
    }

    public function decide(Request $request, LoveStory $story): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']], [
            'decision.required' => '請按「通過」或「不通過」其中一個按鈕。',
            'decision.in' => '請按「通過」或「不通過」其中一個按鈕。',
        ]);
        if ($data['decision'] === 'approve') {
            abort_unless($story->status === 'pending', 409);
            $story->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

            return redirect()->route('admin.love-stories')->with('status', sprintf('「%s」公開了。', $story->title));
        }
        // 不通過或撤下：照片刪掉，文字留給投稿的人看結果
        $path = $story->photo_path;
        $story->forceFill(['status' => 'rejected', 'photo_path' => null, 'approved_at' => null])->save();
        if ($path) {
            Storage::disk('avatars')->delete($path);
        }

        return redirect()->route('admin.love-stories')->with('status', sprintf('「%s」不公開，照片已刪除。', $story->title));
    }
}
