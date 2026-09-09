@once
<style>
.attendance-settings-entry{
    display:flex;
    align-items:flex-start;
    gap:.8rem;
    min-height:112px;
    padding:1rem;
    border:1px solid var(--border);
    border-radius:15px;
    background:var(--surface);
    color:var(--text);
    text-decoration:none;
    transition:.18s ease;
}
.attendance-settings-entry:hover{
    border-color:color-mix(in srgb,var(--theme-primary) 55%,var(--border));
    transform:translateY(-1px);
}
.attendance-settings-entry-icon{
    width:42px;
    height:42px;
    flex:0 0 42px;
    display:grid;
    place-items:center;
    border-radius:12px;
    color:var(--theme-primary);
    background:color-mix(in srgb,var(--theme-primary) 10%,transparent);
}
.attendance-settings-entry strong{
    display:block;
    font-size:.88rem;
}
.attendance-settings-entry p{
    margin:.28rem 0 0;
    color:var(--text-muted);
    font-size:.72rem;
    line-height:1.7;
}
</style>
@endonce

@can('settings.manage')
    @if(\Illuminate\Support\Facades\Route::has('settings.attendance-payroll.edit'))
        <a
            href="{{ route('settings.attendance-payroll.edit') }}"
            class="attendance-settings-entry"
        >
            <div class="attendance-settings-entry-icon">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
            </div>

            <div>
                <strong>الحضور والرواتب</strong>
                <p>
                    تفعيل الحضور وأجهزة البصمة وسياسات التأخير والغياب والساعات الإضافية.
                </p>
            </div>
        </a>
    @endif
@endcan
