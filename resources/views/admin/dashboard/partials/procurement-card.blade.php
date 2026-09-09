@can('dashboard.procurement')
    <a href="{{ route('procurement.dashboard') }}" class="card" style="display:block;text-decoration:none;margin-top:1rem">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div>
                <div class="card-title">المشتريات والموردون</div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:.25rem">أوامر الشراء، الاستلام، فواتير
                    الموردين والذمم.</div>
            </div>
            <span class="btn btn-outline btn-sm">فتح</span>
        </div>
    </a>
@endcan
