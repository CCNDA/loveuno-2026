@extends('layouts.app')

@section('title', '身份認證')

@section('content')
<script src="{{ asset('js/verify.js') }}?v={{ $appVersion }}" defer></script>
@include('center.tabs', ['current' => 'profile', 'sub' => 'verify'])
<h2>身份認證</h2>
<p class="prose">為了提高您在愛諾園的帳號可信度，我們提供下面幾種認證。通過的認證會在您的交友檔案上顯示「已認證」。</p>

<section aria-labelledby="v-phone" class="verify-card">
    <h3 id="v-phone">手機號碼認證</h3>
    <p class="verify-status">已通過 @if ($user->phone)（{{ \App\Support\Sms::mask($user->phone) }}）@endif</p>
    <p><a href="{{ route('center.account') }}#phone-title">更換手機號碼</a>（在「帳號管理」，要輸入密碼）</p>
</section>

@php($job = $items->get('job'))
<section aria-labelledby="v-job" class="verify-card">
    <h3 id="v-job">職業 Email 認證</h3>
    <p class="hint">大型企業（學校）都會讓員工（學生）用公司網址的 Email。我們會寄一封認證信到您的公司（學校）Email，點了信裡的連結後，管理員會在 3-5 個工作天內確認。除了認證之外，我們不會將此 Email 作為任何其他用途，也不會聯絡或詢問您的公司（學校）任何關於您的資料；您的任職公司（學校）不會顯示於您的交友檔案中。</p>
    @php($jobErrors = $errors->getBag('job'))
    @if ($job)<p class="verify-status">目前：{{ $job->statusLabel() }}@if ($job->company !== '')（{{ $job->company }}）@endif</p>@endif
    @if ($jobErrors->has('company') && $job?->status === 'approved')<p class="field-error" role="alert">{{ $jobErrors->first('company') }}</p>@endif
    @if (! $job || $job->status === 'rejected' || $job->status === 'email_sent')
        <form method="POST" action="{{ route('verify.job') }}" class="form" novalidate>
            @csrf
            @foreach ([['company', '公司(學校)名稱', 'text', 50, 'organization'], ['company_url', '公司(學校)首頁網址', 'url', 200, 'url'], ['company_email', '公司(學校) Email', 'email', 100, 'work email']] as [$key, $label, $type, $max, $ac])
                <div class="field">
                    <label for="{{ $key }}">{{ $label }}</label>
                    @if ($key === 'company_email')<div class="verify-row">@endif
                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $max }}" autocomplete="{{ $ac }}" value="{{ $jobErrors->any() ? old($key) : $job?->{$key} }}" required
                           @if ($jobErrors->has($key)) aria-invalid="true" aria-describedby="{{ $key }}-error" @endif>
                    @if ($key === 'company_email')<button type="submit" class="button verify-go">{{ $job && $job->status === 'email_sent' ? '重寄認證信' : '立即驗證' }}</button></div>@endif
                    @if ($jobErrors->has($key))<p id="{{ $key }}-error" class="field-error" role="alert">{{ $jobErrors->first($key) }}</p>@endif
                </div>
            @endforeach
        </form>
    @endif
</section>

@foreach ([
    'id' => ['身分證明文件認證', '請手持有照片的證件拍照（身分證／駕照／護照／證照）之清晰照片。管理員會在 3-5 個工作天內人工審核。'],
    'church' => ['教會週報認證', '請手持週報／受洗證書／會友證拍照（一個月內的主日週報，人像和週報上的教會名稱、日期要清楚）。管理員會在 3-5 個工作天內人工審核。'],
] as $kind => [$title, $desc])
    @php($v = $items->get($kind))
    <section aria-labelledby="v-{{ $kind }}" class="verify-card">
        <h3 id="v-{{ $kind }}">{{ $title }}</h3>
        <p id="{{ $kind }}-hint" class="hint">{{ $desc }}檔案格式：JPG、PNG，5MB 以內。此檔案不會顯示於您的交友檔案中，只有管理員看得到，審核完就刪掉。</p>
        @if ($v)<p class="verify-status">目前：{{ $v->statusLabel() }}</p>@endif
        @if ($kind === 'church' && ! $hasChurch)
            <p>您還沒有在自我介紹裡填寫您的教會，請先<a href="{{ route('profile.intro') }}">填寫教會資訊</a>，才能做教會週報認證。</p>
        @elseif (! $v || $v->status === 'rejected')
            <form method="POST" action="{{ route('verify.' . $kind) }}" class="form" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="field">
                    @php($err = $errors->getBag($kind)->first('photo'))
                    {{-- 設計稿 118:8011：一個大框（相機＋「拍照」），整個框可以按；手機直接開相機（capture），電腦是選檔案 --}}
                    <input id="{{ $kind }}-photo" name="photo" type="file" accept="image/jpeg,image/png" capture="environment" required class="camera-input"
                           aria-describedby="{{ $kind }}-hint{{ $err ? ' ' . $kind . '-error' : '' }}" @if ($err) aria-invalid="true" @endif>
                    <div class="verify-row">
                        <label for="{{ $kind }}-photo" class="camera-box"><img src="{{ asset('images/brand/camera.svg') }}" alt="" width="40" height="40"><span class="camera-text">拍照</span><span class="visually-hidden">（{{ $title }}）</span></label>
                        <button type="submit" class="button verify-go">送出<span class="visually-hidden">{{ $title }}的照片</span></button>
                    </div>
                    @if ($err)<p id="{{ $kind }}-error" class="field-error">{{ $err }}</p>@endif
                </div>
            </form>
        @endif
    </section>
@endforeach
@endsection
