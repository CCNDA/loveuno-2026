{{-- 表單錯誤摘要：每一條連到對應的欄位（id 跟欄位名稱一樣） --}}
@if ($errors->any())
    <div class="error-summary" role="alert" aria-labelledby="error-title">
        <h2 id="error-title">{{ $title ?? '還有地方要改' }}</h2>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a href="#{{ $field }}">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
