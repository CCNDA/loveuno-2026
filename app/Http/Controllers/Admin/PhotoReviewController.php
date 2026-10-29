<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PhotoController;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// 管理員審核大頭照與個人化封面（只有 is_admin 的帳號進得來）
class PhotoReviewController extends Controller
{
    public function index(): View
    {
        $pending = [];
        foreach (array_keys(PhotoController::KINDS) as $kind) {
            $pending[$kind] = Profile::with('user:id,name')
                ->where($kind . '_status', 'pending')
                ->orderBy($kind . '_updated_at')
                ->get();
        }

        return view('admin.photos', ['pending' => $pending]);
    }

    public function decide(Request $request, Profile $profile): RedirectResponse
    {
        // 沒帶 decision（不是按按鈕送出）要明說，不能看起來像「通過了」（小布 0.4.0 複測觀察）
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'photo' => ['required', 'string'], // 管理員看到的是哪一張（*_path）
            'kind' => ['nullable', 'in:' . implode(',', array_keys(PhotoController::KINDS))],
        ], [
            'decision.required' => '請按「通過」或「不通過」其中一個按鈕。',
            'decision.in' => '請按「通過」或「不通過」其中一個按鈕。',
            'photo.required' => '找不到要審核的是哪一張，請重新整理頁面再審。',
        ]);
        $kind = $data['kind'] ?? 'photo';
        $label = PhotoController::KINDS[$kind]['label'];
        abort_unless($profile->{$kind . '_status'} === 'pending', 409);
        // 管理員打開頁面之後，會員又換了照片：不能讓「通過」套到沒看過的那張
        if (! hash_equals((string) $profile->{$kind . '_path'}, $data['photo'])) {
            return redirect()->route('admin.photos')->with('status', sprintf('%s 剛剛換了%s，請重新看過再決定。', $profile->user->name, $label));
        }

        if ($data['decision'] === 'approve') {
            $profile->forceFill([$kind . '_status' => 'approved'])->save();
            $msg = sprintf('%s 的%s通過了。', $profile->user->name, $label);
        } else {
            // 不通過就把檔案刪掉，不留在主機上
            if ($profile->{$kind . '_path'}) {
                Storage::disk('avatars')->delete($profile->{$kind . '_path'});
            }
            $profile->forceFill([$kind . '_status' => 'rejected', $kind . '_path' => null])->save();
            $msg = sprintf('%s 的%s不通過，檔案已刪除。', $profile->user->name, $label);
        }

        return redirect()->route('admin.photos')->with('status', $msg);
    }
}
