{{-- 會員狀態（列表和會員頁共用）：$m = User --}}
@if ($m->deleted_at)
    已註銷
@elseif ($m->suspended_at)
    <strong>停權中</strong>（{{ $m->suspended_reason }}）
@elseif (! $m->is_active)
    會員自己暫停
@elseif (! $m->email_verified_at || ! $m->phone_verified_at)
    {{ ! $m->email_verified_at ? 'Email 還沒驗證' : '手機還沒驗證' }}
@else
    正常
@endif
@if ($m->is_super_admin)（最高管理員）@elseif ($m->is_admin)（管理員）@endif
