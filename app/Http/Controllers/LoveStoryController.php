<?php

namespace App\Http\Controllers;

use App\Models\LoveStory;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

// 愛的見證：列表、內頁不用登入；投稿要登入（男方、女方會員編號，投稿的人要是其中一位），管理員審核通過才公開
class LoveStoryController extends Controller
{
    public function index(): View
    {
        return view('love-stories.index', [
            'stories' => LoveStory::where('status', 'approved')->orderByDesc('approved_at')->orderByDesc('id')->paginate(12),
        ]);
    }

    public function show(Request $request, LoveStory $story): View
    {
        abort_unless($story->visibleTo($request->user()), 404);
        $story->load(['male.profile', 'female.profile']);

        return view('love-stories.show', ['story' => $story]);
    }

    public function create(Request $request): View
    {
        return view('love-stories.create', ['me' => $request->user()->load('profile')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:50'],
            'male_id' => ['required', 'integer'],
            'female_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ], [
            'male_id.integer' => '會員編號是數字，可以在對方的「帳號設定」看到。',
            'female_id.integer' => '會員編號是數字，可以在對方的「帳號設定」看到。',
            'photo.mimes' => '請選 JPG 或 PNG 的照片。',
            'photo.max' => '照片太大了，請選 5MB 以下的。',
            'photo.dimensions' => '照片的寬、高要在 200～6000 像素之間。',
        ], ['title' => '標題', 'male_id' => '男方會員編號', 'female_id' => '女方會員編號', 'body' => '見證內容', 'photo' => '照片']);

        $errors = [];
        foreach (['male_id' => '男', 'female_id' => '女'] as $key => $gender) {
            $u = User::with('profile')->find($data[$key]);
            if (! $u || $u->profile?->gender !== $gender) {
                $errors[$key] = sprintf('找不到這位%s會員，請再確認一次會員編號。', $gender === '男' ? '弟兄' : '姊妹');
            }
        }
        if (! $errors && ! in_array($me->id, [(int) $data['male_id'], (int) $data['female_id']], true)) {
            $errors['male_id'] = '請用男方或女方本人的帳號投稿（您的會員編號是 ' . $me->id . '）。';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $path = null;
        if ($request->hasFile('photo')) {
            try {
                $jpeg = AvatarImage::fit($request->file('photo')->getRealPath(), 1200);
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['photo' => '照片讀不出來，請換一張試試看。']);
            }
            $path = 'love/' . Str::random(40) . '.jpg';
            Storage::disk('avatars')->put($path, $jpeg);
        }
        $story = new LoveStory;
        $story->forceFill([
            'submitter_id' => $me->id, 'male_id' => (int) $data['male_id'], 'female_id' => (int) $data['female_id'],
            'title' => $data['title'], 'body' => $data['body'], 'photo_path' => $path, 'status' => 'pending',
        ])->save();

        return redirect()->route('love-stories.show', $story)->with('status', '謝謝您的投稿！管理員看過以後就會公開。');
    }

    public function photo(Request $request, LoveStory $story): Response
    {
        abort_unless($story->photo_path && $story->visibleTo($request->user()), 404);
        abort_unless(Storage::disk('avatars')->exists($story->photo_path), 404);

        return response(Storage::disk('avatars')->get($story->photo_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $story->status === 'approved' ? 'public, max-age=86400' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
