@if (session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-error" style="margin-bottom:1rem">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error" style="margin-bottom:1rem">
        <strong>تعذر الحفظ:</strong>
        <ul style="margin:.5rem 0 0;padding-inline-start:1.25rem">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
