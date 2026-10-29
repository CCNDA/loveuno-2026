<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// 封鎖／解除封鎖。封鎖後雙方：不能傳紙條、看不到對方的檔案、日記、大頭照，也不會出現在對方的列表裡。
class BlockController extends Controller
{
    public function confirm(Request $request, User $user): View
    {
        // 對方已經封鎖我：跟打開對方檔案一樣回 404，不讓人從這裡比對出「我被封鎖了」
        abort_if($request->user()->is($user) || $user->hasBlocked($request->user()), 404);

        return view('blocks.confirm', ['member' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user) || $user->hasBlocked($me), 404);
        DB::table('blocks')->insertOrIgnore([
            'blocker_id' => $me->id, 'blocked_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->forgetCache($request, $me, $user);

        return redirect()->route('center.account')->with('status', sprintf('已封鎖 %s。對方不會收到通知。', $user->name));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::table('blocks')->where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();
        $this->forgetCache($request, $request->user(), $user);

        return back()->with('status', sprintf('已解除封鎖 %s。', $user->name));
    }

    // 封鎖名單是「每個請求快取一次」（User::blockRelatedIds），同一個請求裡改了就要清掉，不然後面拿到舊的
    private function forgetCache(Request $request, User ...$users): void
    {
        foreach ($users as $u) {
            $request->attributes->remove('block_ids:' . $u->id);
            request()->attributes->remove('block_ids:' . $u->id);
        }
    }
}
