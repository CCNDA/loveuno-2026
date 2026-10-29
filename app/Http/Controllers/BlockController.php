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
        abort_if($request->user()->is($user), 404);

        return view('blocks.confirm', ['member' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user), 404);
        DB::table('blocks')->insertOrIgnore([
            'blocker_id' => $me->id, 'blocked_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('center.account')->with('status', sprintf('已封鎖 %s。對方不會收到通知。', $user->name));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::table('blocks')->where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return back()->with('status', sprintf('已解除封鎖 %s。', $user->name));
    }
}
