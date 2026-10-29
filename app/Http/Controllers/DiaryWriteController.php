<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 寫日記、改日記、刪日記：只有作者本人可以（別人一律 404，不讓人知道這篇存在）
class DiaryWriteController extends Controller
{
    public function create(): View
    {
        return view('diaries.form', ['diary' => new Diary(['visibility' => 'public'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $jpeg = $this->imageFromRequest($request); // 先轉檔：讀不出來就整篇不存，免得日記存了、照片沒了
        $diary = $request->user()->diaries()->create($data + ['published_on' => now()->toDateString()]);
        $this->saveImage($diary, $jpeg);

        return redirect()->route('diaries.show', $diary)->with('status', $this->statusMessage('日記發表了。', $jpeg));
    }

    public function edit(Request $request, Diary $diary): View
    {
        $this->authorizeOwner($request, $diary);

        return view('diaries.form', ['diary' => $diary]);
    }

    public function update(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeOwner($request, $diary);
        $data = $this->validated($request);
        $jpeg = $this->imageFromRequest($request);
        $diary->update($data);
        if ($jpeg !== null) {
            $this->saveImage($diary, $jpeg);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($diary);
        }

        return redirect()->route('diaries.show', $diary)->with('status', $this->statusMessage('日記改好了。', $jpeg));
    }

    public function destroy(Request $request, Diary $diary): RedirectResponse
    {
        $this->authorizeOwner($request, $diary);
        $this->deleteImage($diary);
        $diary->delete(); // 禱告、留言跟著刪（cascade）

        return redirect()->route('diaries.index')->with('status', '日記刪掉了。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['required', 'in:public,private'],
            'is_prayer' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'image.mimes' => '請選 JPG、PNG 或 WebP 的照片。',
            'image.max' => '照片太大了，請選 5MB 以下的。',
            'image.dimensions' => '照片的寬、高要在 200～6000 像素之間。',
        ], ['title' => '標題', 'body' => '內容', 'visibility' => '誰看得到', 'is_prayer' => '代禱', 'image' => '照片']);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'visibility' => $data['visibility'],
            'is_prayer' => $request->boolean('is_prayer'),
        ];
    }

    private function imageFromRequest(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        try {
            return AvatarImage::fit($request->file('image')->getRealPath(), 1200);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['image' => '這個檔案讀不出來，請換一張照片試試看。']);
        }
    }

    // 新照片要重新審核；舊檔刪掉
    private function saveImage(Diary $diary, ?string $jpeg): void
    {
        if ($jpeg === null) {
            return;
        }
        $old = $diary->image_path;
        $path = Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg);
        $diary->forceFill(['image_path' => $path, 'image_status' => 'pending', 'image_updated_at' => now()])->save();
        if ($old && $old !== $path) {
            Storage::disk('avatars')->delete($old);
        }
    }

    private function deleteImage(Diary $diary): void
    {
        if ($diary->image_path) {
            Storage::disk('avatars')->delete($diary->image_path);
            $diary->forceFill(['image_path' => null, 'image_status' => '', 'image_updated_at' => now()])->save();
        }
    }

    private function statusMessage(string $done, ?string $jpeg): string
    {
        return $jpeg === null ? $done : $done . '照片要等管理員審核通過，其他會員才看得到。';
    }

    // 刪除前先確認一次（大會現場展示時避免誤刪）
    public function confirmDestroy(Request $request, Diary $diary): View
    {
        $this->authorizeOwner($request, $diary);

        return view('diaries.delete', ['diary' => $diary]);
    }

    private function authorizeOwner(Request $request, Diary $diary): void
    {
        abort_unless($diary->isOwnedBy($request->user()), 404);
    }
}
