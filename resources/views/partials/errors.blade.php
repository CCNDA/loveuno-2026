{{-- 表單錯誤摘要：每一條連到對應的欄位（id 跟欄位名稱一樣） --}}
@if ($errors->any())
    <div class="error-summary" role="alert" aria-labelledby="error-title">
        <h2 id="error-title">{{ $title ?? '還有地方要改' }}</h2>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    {{-- hobbies.0 這種陣列欄位的錯誤，連到整組（id="hobbies"） --}}
                    <li><a href="#{{ \Illuminate\Support\Str::before($field, '.') }}">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
