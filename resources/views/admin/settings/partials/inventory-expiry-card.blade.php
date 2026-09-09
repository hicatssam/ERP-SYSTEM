@can('inventory.expiry-alerts.settings')
    @if(\Illuminate\Support\Facades\Route::has('inventory.expiry.settings'))
        <a
            href="{{ route('inventory.expiry.settings') }}"
            class="settings-entry"
            style="text-decoration:none"
        >
            <div>
                <strong>صلاحية المخزون والتنبيهات</strong>
                <p>
                     فترات 60/30/7 أيام وقنوات النظام والبريد و WhatsApp. 
                </p>
            </div>
        </a>
    @endif
@endcan
