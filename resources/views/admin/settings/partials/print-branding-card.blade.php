@once
<style>
.print-branding-settings-entry{
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
.print-branding-settings-entry:hover{
    border-color:color-mix(in srgb,var(--theme-primary) 55%,var(--border));
    transform:translateY(-1px);
}
.print-branding-settings-entry-icon{
    width:42px;
    height:42px;
    flex:0 0 42px;
    display:grid;
    place-items:center;
    border-radius:12px;
    color:var(--theme-primary);
    background:color-mix(in srgb,var(--theme-primary) 10%,transparent);
}
.print-branding-settings-entry strong{
    display:block;
    font-size:.88rem;
}
.print-branding-settings-entry p{
    margin:.28rem 0 0;
    color:var(--text-muted);
    font-size:.72rem;
    line-height:1.7;
}
</style>
@endonce

@can('settings.manage')
    @if(
        \Illuminate\Support\Facades\Route::has(
            'settings.print-branding.edit'
        )
    )
        <a
            href="{{ route('settings.print-branding.edit') }}"
            class="print-branding-settings-entry"
        >
            <div class="print-branding-settings-entry-icon">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9V2h12v7"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
            </div>

            <div>
                <strong>
                    هوية المستندات والطباعة
                </strong>

                <p>
                    تصميم الفواتير والسندات والقسائم وكشوف الحساب من مكان واحد.
                </p>
            </div>
        </a>
    @endif
@endcan
