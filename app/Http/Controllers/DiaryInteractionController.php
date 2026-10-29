<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\DiaryComment;
use App\Models\DiaryPrayer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

// 日記牆上的互動：「為你禱告」、留言、看附圖（設計稿首頁 DiaryWall）。
// 看不到的日記（不公開、或有封鎖關係）一律 404，跟日記內頁一樣。
class DiaryInteractionController extends Controller
{
    // 按一次是「為你禱告」，再按一次取消
    public function pray(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeReach($request, $diary);
        $me = $request->user();

        $deleted = DiaryPrayer::where('diary_id', $diary->id)->where('user_id', $me->id)->delete();
        if (! $deleted) {
            try {
                DiaryPrayer::create(['diary_id' => $diary->id, 'user_id' => $me->id]);
            } catch (UniqueConstraintViolationException) {
                // 連按兩下、兩個請求同時進來：已經有了就好
            }
        }

        return $this->backToDiary($request, $diary, $deleted ? '已經取消禱告。' : '謝謝您為這篇日記禱告。');
    }

    public function comment(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeReach($request, $diary);
        $fromWall = $request->input('from') === 'wall';
        // 日記牆一頁有 10 張卡片、10 個留言框：錯誤放在這一篇自己的 error bag，才知道是哪一張
        $v = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:500'],
        ], [], ['body' => '留言']);
        if ($v->fails()) {
            $to = $fromWall ? rtrim(route('home'), '/') . '/#d' . $diary->id : route('diaries.show', $diary) . '#comments-title';

            return redirect()->to($to)->withErrors($v, $fromWall ? 'comment' . $diary->id : 'default')->withInput();
        }

        $comment = new DiaryComment(['body' => $v->validated()['body']]);
        $comment->forceFill(['diary_id' => $diary->id, 'user_id' => $request->user()->id])->save();

        $to = $fromWall ? rtrim(route('home'), '/') . '/#d' . $diary->id : route('diaries.show', $diary) . '#comment-' . $comment->id;

        return redirect()->to($to)->with('status', '留言送出了。');
    }

    public function destroyComment(Request $request, Diary $diary, DiaryComment $comment): RedirectResponse
    {
        abort_unless($comment->diary_id === $diary->id, 404);
        $this->authorizeReach($request, $diary);
        abort_unless($comment->deletableBy($request->user()), 404);
        $comment->delete();

        return redirect()->to(route('diaries.show', $diary) . '#comments-title')->with('status', '留言刪掉了。');
    }

    public function image(Request $request, Diary $diary): Response
    {
        abort_unless($diary->imageVisibleTo($request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($diary->image_path), 404);

        return response(Storage::disk('avatars')->get($diary->image_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeReach(Request $request, Diary $diary): void
    {
        abort_unless($diary->isReachableBy($request->user()), 404);
    }

    // 從日記牆、日記列表、日記內頁按的，都回到原來那一頁的那一篇。
    // 不用 Referer（可以被帶成外站），也不用 session 的上一頁（頁面上的頭像、附圖也是 GET，會把它蓋掉），
    // 改由表單帶 from＝wall／list（＋page）／show，只認這三種。
    private function backToDiary(Request $request, Diary $diary, string $status): RedirectResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $url = match ($request->input('from')) {
            'wall' => rtrim(route('home'), '/') . '/', // route('home') 是「https://網域」沒有結尾的 /，補上才是「/#d1」
            'list' => route('diaries.index', $page > 1 ? ['page' => $page] : []),
            default => route('diaries.show', $diary),
        };

        return redirect()->to($url . '#d' . $diary->id)->with('status', $status);
    }
}
