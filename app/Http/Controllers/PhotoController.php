<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

// 大頭照：上傳後是「審核中」，只有本人和管理員看得到；管理員通過後其他會員才看得到。
// 檔案放在 avatars 磁碟（測試站是本機私有目錄，正式站可以用 AVATAR_DISK 換成 R2 私有 bucket），
// 不放 public/，一律經過這裡檢查登入與權限才送出。
class PhotoController extends Controller
{
    public function edit(Request $request): View
    {
        return view('photos.edit', ['profile' => $this->profileOf($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ], [
            'photo.mimes' => '請選 JPG、PNG 或 WebP 的照片。',
            'photo.max' => '照片太大了，請選 5MB 以下的。',
            'photo.dimensions' => '照片的寬和高要在 200～6000 像素之間。',
        ], ['photo' => '照片']);

        try {
            $jpeg = AvatarImage::fromUpload($request->file('photo')->getRealPath());
        } catch (RuntimeException) {
            return back()->withErrors(['photo' => '這個檔案讀不出來，請換一張照片試試看。']);
        }

        $profile = $this->profileOf($request->user());
        $old = $profile->photo_path;
        $path = Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg);

        $profile->forceFill([
            'photo_path' => $path,
            'photo_status' => 'pending',
            'photo_updated_at' => now(),
        ])->save();
        if ($old && $old !== $path) {
            Storage::disk('avatars')->delete($old);
        }

        return redirect()->route('photo.edit')->with('status', '照片上傳好了，管理員審核通過後其他會員就看得到。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $profile = $this->profileOf($request->user());
        if ($profile->photo_path) {
            Storage::disk('avatars')->delete($profile->photo_path);
        }
        $profile->forceFill(['photo_path' => null, 'photo_status' => '', 'photo_updated_at' => now()])->save();

        return redirect()->route('photo.edit')->with('status', '照片刪掉了。');
    }

    public function show(Request $request, User $user): Response
    {
        $profile = $user->profile;
        abort_unless($profile && $profile->photoVisibleTo($request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($profile->photo_path), 404);

        return response(Storage::disk('avatars')->get($profile->photo_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function profileOf(User $user): Profile
    {
        return $user->profile ?? Profile::create(['user_id' => $user->id]);
    }
}
