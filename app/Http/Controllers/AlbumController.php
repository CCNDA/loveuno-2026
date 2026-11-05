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

// 個人相簿：最多 9 張（設計稿 PhotoUploader uploadLimit={9}，可以一次選好幾張）；上傳後其他會員馬上看得到
// （熊叔叔 10/29「1不用」：相簿不用審核；大頭照、封面還是要審核）。有不適當的照片走檢舉。
// status 'rejected' 是以前審核不通過留下的一格，會員可以刪掉。
// 檔案放在私有的 avatars 磁碟，一律經過 show() 檢查權限才送出。
class AlbumController extends Controller
{
    public function edit(Request $request): View
    {
        $photos = AlbumPhoto::where('user_id', $request->user()->id)->orderBy('id')->get();

        return view('album.edit', [
            'photos' => $photos,
            'max' => AlbumPhoto::MAX,
            'left' => AlbumPhoto::MAX - $photos->where('status', '!=', 'rejected')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $left = AlbumPhoto::MAX - AlbumPhoto::countedFor($user->id);
        $request->validate([
            'photos' => ['required', 'array', 'max:' . max(0, $left)],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ], [
            'photos.required' => '請選照片。',
            'photos.max' => $left > 0 ? sprintf('相簿還可以放 %d 張，這次選太多了。', $left) : sprintf('相簿最多 %d 張，請先刪掉一張再放。', AlbumPhoto::MAX),
            'photos.*.mimes' => '請選 JPG 或 PNG 的照片。',
            'photos.*.max' => '照片太大了，請選 5MB 以下的。',
            'photos.*.dimensions' => '照片的寬、高要在 200～6000 像素之間。',
        ], ['photos' => '照片', 'photos.*' => '照片']);

        // 1. 全部先轉檔：有一張讀不出來就整批不放
        $jpegs = [];
        foreach ($request->file('photos') as $file) {
            try {
                $jpegs[] = AvatarImage::fit($file->getRealPath(), 1200);
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['photos' => '有一張照片讀不出來，請換一張試試看。']);
            }
        }
        // 2. 先寫檔案：寫失敗就不會留下沒有檔案的紀錄
        $paths = [];
        foreach ($jpegs as $jpeg) {
            $path = 'album/' . Str::random(40) . '.jpg';
            Storage::disk('avatars')->put($path, $jpeg);
            $paths[] = $path;
        }
        // 3. 再寫資料：鎖住這個人的帳號列，兩個上傳同時進來也不會超過 9 張；超過就把剛寫的檔案刪掉
        $saved = DB::transaction(function () use ($user, $paths) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->value('id');
            if (AlbumPhoto::countedFor($user->id) + count($paths) > AlbumPhoto::MAX) {
                return false;
            }
            foreach ($paths as $path) {
                $photo = new AlbumPhoto;
                $photo->forceFill(['user_id' => $user->id, 'path' => $path, 'status' => 'approved'])->save();
            }

            return true;
        });
        if (! $saved) {
            Storage::disk('avatars')->delete($paths);
            throw ValidationException::withMessages(['photos' => sprintf('相簿最多 %d 張，請先刪掉一張再放。', AlbumPhoto::MAX)]);
        }

        return redirect()->route('album.edit')->with('status', sprintf('%d 張照片放進相簿了。', count($paths)));
    }

    public function destroy(Request $request, AlbumPhoto $photo): RedirectResponse
    {
        abort_unless($photo->user_id === (int) $request->user()->id, 404);
        $path = $photo->path;
        $photo->delete(); // 先刪紀錄，再刪檔案：刪紀錄失敗就不會留下指到空檔案的紀錄
        if ($path !== '') {
            Storage::disk('avatars')->delete($path);
        }

        return redirect()->route('album.edit')->with('status', '照片刪掉了。');
    }

    public function show(Request $request, AlbumPhoto $photo): Response
    {
        abort_unless($photo->path !== '' && $photo->visibleTo($request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($photo->path), 404);

        return response(Storage::disk('avatars')->get($photo->path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // 個人化封面 10 選 1（設計稿 個人相簿「其他／個人化封面」）
    public function coverPreset(Request $request): RedirectResponse
    {
        $data = $request->validate(['cover_preset' => ['required', 'integer', 'between:1,' . count(\App\Models\Profile::COVER_PRESETS)]],
            [], ['cover_preset' => '封面']);
        $profile = $request->user()->profile ?? \App\Models\Profile::create(['user_id' => $request->user()->id]);
        $profile->forceFill(['cover_preset' => (int) $data['cover_preset']])->save();

        return redirect()->to(route('album.edit') . '#cover-title')->with('status', '封面換好了。');
    }
}
