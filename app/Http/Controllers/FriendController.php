<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 我的朋友（舊站 my_friends.php／manage_friend_list.php）與解除關係（設計稿 PagePersonal「解除關係」）
class FriendController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $rows = Friendship::where(fn ($q) => $q->where('user_id', $me->id)->orWhere('friend_id', $me->id))
            ->orderByDesc('level')->orderByDesc('leveled_at')->get();
        $blocked = $me->blockRelatedIds();
        $users = User::with('profile')->whereIn('id', $rows->map->otherThan($me))->whereNotIn('id', $blocked)->get()->keyBy('id');

        // 真實好友、聊天好友、普通好友，各一組
        $groups = [];
        foreach ([3, 2, 1] as $level) {
            $groups[$level] = $rows->where('level', $level)
                ->map(fn (Friendship $f) => ['friend' => $users->get($f->otherThan($me)), 'since' => $f->created_at])
                ->filter(fn ($row) => $row['friend'] !== null)
                ->values();
        }

        return view('friends.index', ['groups' => $groups]);
    }

    public function confirmDestroy(Request $request, User $user): View
    {
        abort_unless(Friendship::between($request->user(), $user), 404);

        return view('friends.delete', ['member' => $user, 'level' => $request->user()->friendLevelWith($user)]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(Friendship::between($request->user(), $user), 404);
        Friendship::remove($request->user(), $user);

        return redirect()->route('friends.index')->with('status', sprintf('已經和 %s 解除關係了。對方不會收到通知。', $user->name));
    }
}
