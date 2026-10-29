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

// 大頭照與個人化封面：上傳後是「審核中」，只有本人和管理員看得到；管理員通過後其他會員才看得到。
// 檔案放在 avatars 磁碟（測試站是本機私有目錄，正式站可以用 AVATAR_DISK_DRIVER 換成 R2 私有 bucket），
// 不放 public/，一律經過這裡檢查登入與權限才送出。
// 哪一種由 route 的 kind 決定（routes/web.php 用 ->defaults('kind', ...)）。
class PhotoController extends Controller
{
    // 寬、高、最小寬、最小高、名稱、回到哪個編輯頁。封面最小 900×300：再小就會被放大超過 1.33 倍、變模糊
    public const KINDS = [
        'photo' => ['w' => 600, 'h' => 600, 'min_w' => 200, 'min_h' => 200, 'label' => '大頭照', 'edit' => 'photo.edit'],
        'cover' => ['w' => 1200, 'h' => 400, 'min_w' => 900, 'min_h' => 300, 'label' => '封面', 'edit' => 'cover.edit'],
    ];

    public function edit(Request $request): View
    {
        $kind = $this->kind($request);

        return view('photos.edit', ['profile' => $this->profileOf($request->user()), 'kind' => $kind, 'cfg' => self::KINDS[$kind]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);
        $cfg = self::KINDS[$kind];
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                sprintf('dimensions:min_width=%d,min_height=%d,max_width=6000,max_height=6000', $cfg['min_w'], $cfg['min_h'])],
        ], [
            'photo.mimes' => '請選 JPG、PNG 或 WebP 的照片。',
            'photo.max' => '照片太大了，請選 5MB 以下的。',
            'photo.dimensions' => sprintf('照片的寬要在 %d～6000、高要在 %d～6000 像素之間。', $cfg['min_w'], $cfg['min_h']),
        ], ['photo' => '照片']);

        try {
            $jpeg = AvatarImage::fromUpload($request->file('photo')->getRealPath(), $cfg['w'], $cfg['h']);
        } catch (RuntimeException) {
            return back()->withErrors(['photo' => '這個檔案讀不出來，請換一張照片試試看。']);
        }

        $profile = $this->profileOf($request->user());
        $old = $profile->{$kind . '_path'};
        $path = Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg);

        $profile->forceFill([
            $kind . '_path' => $path,
            $kind . '_status' => 'pending',
            $kind . '_updated_at' => now(),
        ])->save();
        if ($old && $old !== $path) {
            Storage::disk('avatars')->delete($old);
        }

        return redirect()->route($cfg['edit'])->with('status', $cfg['label'] . '上傳好了，管理員審核通過後其他會員就看得到。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);
        $profile = $this->profileOf($request->user());
        if ($profile->{$kind . '_path'}) {
            Storage::disk('avatars')->delete($profile->{$kind . '_path'});
        }
        $profile->forceFill([$kind . '_path' => null, $kind . '_status' => '', $kind . '_updated_at' => now()])->save();

        return redirect()->route(self::KINDS[$kind]['edit'])->with('status', self::KINDS[$kind]['label'] . '刪掉了。');
    }

    public function show(Request $request, User $user): Response
    {
        $kind = $this->kind($request);
        $profile = $user->profile;
        abort_unless($profile && $profile->imageVisibleTo($kind, $request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($profile->{$kind . '_path'}), 404);

        return response(Storage::disk('avatars')->get($profile->{$kind . '_path'}), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function kind(Request $request): string
    {
        $kind = (string) ($request->route('kind') ?? 'photo');
        abort_unless(isset(self::KINDS[$kind]), 404);

        return $kind;
    }

    private function profileOf(User $user): Profile
    {
        return $user->profile ?? Profile::create(['user_id' => $user->id]);
    }
}
