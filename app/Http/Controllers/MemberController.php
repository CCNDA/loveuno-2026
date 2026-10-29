<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    // 認識朋友：示範會員列表，可以用性別、居住地篩選
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'gender' => ['nullable', 'in:男,女'],
            'place' => ['nullable', 'string', 'max:20'],
        ], ['gender.in' => '性別只能選弟兄或姊妹。'], ['gender' => '性別', 'place' => '居住地']);

        $members = User::query()
            ->whereHas('profile')
            ->whereNotNull('email_verified_at') // 還沒驗證 email 的新註冊會員不列出來
            ->with('profile')
            ->when($filters['gender'] ?? null, fn ($q, $g) => $q->whereHas('profile', fn ($p) => $p->where('gender', $g)))
            ->when($filters['place'] ?? null, fn ($q, $pl) => $q->whereHas('profile', fn ($p) => $p->where('live_place', $pl)))
            // 示範會員排前面，舊站匯入的測試帳號（legacy_id 有值）排後面，展示時第一眼看到的是有內容的檔案
            ->orderByRaw('legacy_id IS NOT NULL')->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $places = Profile::where('live_place', '<>', '')
            ->distinct()->orderBy('live_place')->pluck('live_place');

        return view('members.index', [
            'members' => $members,
            'places' => $places,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $viewer = $request->user();
        $diaries = $user->diaries()->visibleTo($viewer)
            ->orderByDesc('published_on')->orderByDesc('id')
            ->limit(5)->get();

        return view('members.show', [
            'member' => $user->load('profile'),
            'diaries' => $diaries,
            'isMe' => $viewer->is($user),
        ]);
    }

    public function me(Request $request): RedirectResponse
    {
        return redirect()->route('members.show', $request->user());
    }
}
