<?php

namespace App\Http\Controllers;

use App\Models\AlbumPhoto;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

// 個人相簿：最多 9 張；上傳後審核中，管理員通過後其他會員才看得到（跟大頭照一樣）。
// 檔案放在私有的 avatars 磁碟，一律經過 show() 檢查權限才送出。
class AlbumController extends Controller
{
    public function edit(Request $request): View
    {
        return view('album.edit', [
            'photos' => AlbumPhoto::where('user_id', $request->user()->id)->orderBy('id')->get(),
            'max' => AlbumPhoto::MAX,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ], [
            'photo.mimes' => '請選 JPG 或 PNG 的照片。',
            'photo.max' => '照片太大了，請選 5MB 以下的。',
            'photo.dimensions' => '照片的寬、高要在 200～6000 像素之間。',
        ], ['photo' => '照片']);

        try {
            $jpeg = AvatarImage::fit($request->file('photo')->getRealPath(), 1200);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['photo' => '這個檔案讀不出來，請換一張照片試試看。']);
        }

        $user = $request->user();
        $path = 'album/' . Str::random(40) . '.jpg';
        $saved = DB::transaction(function () use ($user, $path) {
            // 鎖住這個人的帳號列，兩個上傳同時進來也不會超過 9 張
            DB::table('users')->where('id', $user->id)->lockForUpdate()->value('id');
            if (AlbumPhoto::where('user_id', $user->id)->count() >= AlbumPhoto::MAX) {
                return false;
            }
            $photo = new AlbumPhoto;
            $photo->forceFill(['user_id' => $user->id, 'path' => $path, 'status' => 'pending'])->save();

            return true;
        });
        if (! $saved) {
            throw ValidationException::withMessages(['photo' => sprintf('相簿最多 %d 張，請先刪掉一張再放。', AlbumPhoto::MAX)]);
        }
        Storage::disk('avatars')->put($path, $jpeg);

        return redirect()->route('album.edit')->with('status', '照片放進相簿了，管理員審核通過後其他會員就看得到。');
    }

    public function destroy(Request $request, AlbumPhoto $photo): RedirectResponse
    {
        abort_unless($photo->user_id === (int) $request->user()->id, 404);
        Storage::disk('avatars')->delete($photo->path);
        $photo->delete();

        return redirect()->route('album.edit')->with('status', '照片刪掉了。');
    }

    public function show(Request $request, AlbumPhoto $photo): Response
    {
        abort_unless($photo->visibleTo($request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($photo->path), 404);

        return response(Storage::disk('avatars')->get($photo->path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
