@props(['id', 'title', 'description' => null])

<dialog id="{{ $id }}" class="erp-action-dialog" aria-labelledby="{{ $id }}-title" @if(old('_modal') === $id && $errors->any()) data-open-on-error @endif>
    <div class="erp-action-dialog-head">
        <div>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            @if($description)<p>{{ $description }}</p>@endif
        </div>
        <button type="button" class="erp-action-dialog-close" data-close-dialog aria-label="إغلاق">×</button>
    </div>
    @if(old('_modal') === $id && $errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    {{ $slot }}
</dialog>

@once
    @push('styles')
        <style>
            .erp-action-dialog{width:min(620px,calc(100vw - 24px));max-height:min(85vh,850px);padding:1.25rem;overflow:auto;border:1px solid var(--theme-border,var(--border,#ddd));border-radius:14px;color:var(--theme-text,var(--text-main,#172435));background:var(--theme-surface,var(--card-bg,#fff));box-shadow:0 25px 80px rgba(0,0,0,.22);direction:rtl}
            .erp-action-dialog::backdrop{background:rgba(12,22,36,.58)}
            .erp-action-dialog-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem}
            .erp-action-dialog-head h2{margin:0;font-size:1.2rem}.erp-action-dialog-head p{margin:.35rem 0 0;color:var(--theme-text-muted,var(--text-muted,#687482))}
            .erp-action-dialog-close{border:0;background:transparent;color:inherit;font-size:1.7rem;cursor:pointer;line-height:1}
            .erp-action-dialog form{margin:0}.erp-action-dialog .form-grid{margin-bottom:1rem}
        </style>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('click', event => {
                const opener = event.target.closest('[data-open-dialog]');
                if (opener) {
                    const dialog = document.getElementById(opener.dataset.openDialog);
                    if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
                }
                const closer = event.target.closest('[data-close-dialog]');
                if (closer) closer.closest('dialog')?.close();
            });
            document.querySelectorAll('.erp-action-dialog[data-open-on-error]').forEach(dialog => dialog.showModal());
        </script>
    @endpush
@endonce
