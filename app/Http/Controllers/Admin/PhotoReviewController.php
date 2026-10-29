<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// 管理員審核大頭照（只有 is_admin 的帳號進得來）
class PhotoReviewController extends Controller
{
    public function index(): View
    {
        $pending = Profile::with('user:id,name')
            ->where('photo_status', 'pending')
            ->orderBy('photo_updated_at')
            ->get();

        return view('admin.photos', ['pending' => $pending]);
    }

    public function decide(Request $request, Profile $profile): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        abort_unless($profile->photo_status === 'pending', 409);

        if ($data['decision'] === 'approve') {
            $profile->forceFill(['photo_status' => 'approved'])->save();
            $msg = sprintf('%s 的照片通過了。', $profile->user->name);
        } else {
            // 不通過就把檔案刪掉，不留在主機上
            if ($profile->photo_path) {
                Storage::disk('avatars')->delete($profile->photo_path);
            }
            $profile->forceFill(['photo_status' => 'rejected', 'photo_path' => null])->save();
            $msg = sprintf('%s 的照片不通過，檔案已刪除。', $profile->user->name);
        }

        return redirect()->route('admin.photos')->with('status', $msg);
    }
}
