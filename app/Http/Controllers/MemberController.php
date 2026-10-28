<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
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
