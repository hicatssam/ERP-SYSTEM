@extends('layouts.app')

@section('title', 'بطاقات إعلانات المنيو')
@section('page-title', 'بطاقات إعلانات المنيو')

@section('content')
@php
    $resolveImage = static function (?string $image): ?string {
        if (blank($image)) {
            return null;
        }
        $image = trim($image);
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '//')) {
            return $image;
        }
        $normalized = ltrim($image, '/');
        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }
        if (file_exists(public_path($normalized))) {
            return asset($normalized);
        }
        return asset('storage/' . $normalized);
    };
@endphp

<div class="mb-page">
    <div class="mb-header">
        <div>
            <h1>بطاقات إعلانات المنيو</h1>
            <p>البانرات الترويجية التي تظهر أعلى صفحة منيو الطلب للزبائن. أضف أكثر من تصميم وهي تتناوب تلقائيًا.</p>
        </div>
        <button type="button" class="mb-btn mb-btn-primary" onclick="mbOpenModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            إضافة بانر جديد
        </button>
    </div>

    @if(session('success'))
        <div class="mb-alert mb-alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-grid">
        @forelse($banners as $banner)
            <div class="mb-card {{ $banner->is_active ? '' : 'is-inactive' }}">
                <div class="mb-card-media">
                    @if($resolveImage($banner->image))
                        <img src="{{ $resolveImage($banner->image) }}" alt="">
                    @endif
                    @if($banner->badge_text)
                        <span class="mb-card-badge">{{ $banner->badge_text }}</span>
                    @endif
                </div>
                <div class="mb-card-body">
                    <strong>{{ $banner->title ?: 'بدون عنوان' }}</strong>
                    @if($banner->subtitle)<small>{{ $banner->subtitle }}</small>@endif
                    <div class="mb-card-meta">
                        <span class="mb-chip">{{ $banner->location?->name ?? 'كل الفروع' }}</span>
                        <span class="mb-chip">ترتيب {{ $banner->sort_order }}</span>
                        @if(!$banner->is_active)<span class="mb-chip mb-chip-off">متوقف</span>@endif
                    </div>
                </div>
                <div class="mb-card-actions">
                    <button type="button" class="mb-icon-btn" title="تعديل" onclick='mbOpenModal(@json(array_merge($banner->toArray(), ["image_url" => $resolveImage($banner->image)])))'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                    </button>
                    <form method="POST" action="{{ route('restaurant.menu.banners.toggle-active', $banner) }}">
                        @csrf
                        <button type="submit" class="mb-icon-btn" title="{{ $banner->is_active ? 'إيقاف' : 'تفعيل' }}">
                            @if($banner->is_active)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                            @endif
                        </button>
                    </form>
                    <form method="POST" action="{{ route('restaurant.menu.banners.destroy', $banner) }}" onsubmit="return confirm('حذف هذا البانر نهائيًا؟')">
                        @csrf @method('DELETE')
                        <button type="submit" class="mb-icon-btn mb-icon-btn-danger" title="حذف">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="mb-empty">
                لا توجد بانرات بعد. أضف أول تصميم وسيظهر مباشرة أعلى منيو الطلب.
            </div>
        @endforelse
    </div>
</div>

<div class="mb-overlay" id="mbOverlay">
    <div class="mb-modal">
        <div class="mb-modal-head">
            <h2 id="mbModalTitle">إضافة بانر جديد</h2>
            <button type="button" class="mb-icon-btn" onclick="mbCloseModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="mbForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="mbMethod" value="POST">

            <label class="mb-field">
                صورة البانر @if(true)<span id="mbImageRequiredStar" class="mb-required">*</span>@endif
                <input type="file" name="image" id="mbImage" accept=".jpg,.jpeg,.png,.webp">
                <small>المقاس المفضل: عريض (مثال 1200×600). أقصى حجم 4MB.</small>
            </label>
            <div id="mbImagePreview" class="mb-image-preview" hidden><img alt=""></div>

            <div class="mb-field-row">
                <label class="mb-field">العنوان<input type="text" name="title" id="mbTitle" maxlength="120" placeholder="خصم 30%"></label>
                <label class="mb-field">نص الشارة<input type="text" name="badge_text" id="mbBadgeText" maxlength="30" placeholder="خصم 30%"></label>
            </div>

            <label class="mb-field">الوصف<input type="text" name="subtitle" id="mbSubtitle" maxlength="200" placeholder="على أول طلب إلك اليوم"></label>

            <div class="mb-field-row">
                <label class="mb-field">الفرع
                    <select name="location_id" id="mbLocation">
                        <option value="">كل الفروع</option>
                        @foreach($locations as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mb-field">ترتيب الظهور<input type="number" name="sort_order" id="mbSortOrder" min="0" max="9999" value="0"></label>
            </div>

            <div class="mb-field-row">
                <label class="mb-field">تاريخ البداية (اختياري)<input type="datetime-local" name="starts_at" id="mbStartsAt"></label>
                <label class="mb-field">تاريخ الانتهاء (اختياري)<input type="datetime-local" name="ends_at" id="mbEndsAt"></label>
            </div>

            <label class="mb-checkbox"><input type="checkbox" name="is_active" id="mbIsActive" value="1" checked> مفعّل ويظهر للزبائن</label>

            <button type="submit" class="mb-btn mb-btn-primary mb-btn-block">حفظ البانر</button>
        </form>
    </div>
</div>

<style>
.mb-page, .mb-page * { box-sizing: border-box; }
.mb-page {
    --mb-accent: var(--theme-accent, #d7a514);
    --mb-primary: var(--theme-primary, #1f2937);
    --mb-surface: var(--theme-surface, #ffffff);
    --mb-bg: var(--theme-bg, #f5f6f8);
    --mb-text: var(--theme-text, #20242c);
    --mb-muted: var(--theme-muted, #747b86);
    --mb-border: var(--theme-border, #e3e6eb);
    --mb-danger: var(--theme-danger, #d64545);
    color: var(--mb-text);
    direction: rtl;
}
.mb-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.mb-header h1{margin:0;font-size:1.25rem;font-weight:900}
.mb-header p{margin:5px 0 0;color:var(--mb-muted);max-width:520px;font-size:.88rem}
.mb-btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--mb-border);background:var(--mb-surface);color:var(--mb-text);padding:10px 16px;border-radius:12px;font-weight:800;font-size:.86rem;cursor:pointer}
.mb-btn svg{width:16px;height:16px}
.mb-btn-primary{background:var(--mb-primary);color:#fff;border-color:var(--mb-primary)}
.mb-btn-block{width:100%;justify-content:center;margin-top:6px}
.mb-alert{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:.86rem;font-weight:700}
.mb-alert-success{background:#e8f7ef;color:#159b62}

.mb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.mb-card{background:var(--mb-surface);border:1px solid var(--mb-border);border-radius:16px;overflow:hidden;position:relative}
.mb-card.is-inactive{opacity:.55}
.mb-card-media{height:130px;background:var(--mb-bg);position:relative}
.mb-card-media img{width:100%;height:100%;object-fit:cover}
.mb-card-badge{position:absolute;top:10px;left:10px;background:var(--mb-primary);color:#fff;font-size:.72rem;font-weight:800;padding:4px 10px;border-radius:999px}
.mb-card-body{padding:12px 14px}
.mb-card-body strong{display:block;font-size:.92rem}
.mb-card-body small{display:block;color:var(--mb-muted);font-size:.78rem;margin-top:2px}
.mb-card-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.mb-chip{background:var(--mb-bg);color:var(--mb-muted);font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:999px}
.mb-chip-off{background:#fdecea;color:var(--mb-danger)}
.mb-card-actions{display:flex;gap:6px;padding:0 14px 14px}
.mb-icon-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--mb-border);background:var(--mb-surface);color:var(--mb-text);display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.mb-icon-btn svg{width:15px;height:15px}
.mb-icon-btn-danger{color:var(--mb-danger);border-color:#fdecea}
.mb-empty{grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--mb-muted);border:1px dashed var(--mb-border);border-radius:16px}

.mb-overlay{position:fixed;inset:0;background:rgba(15,15,15,.5);display:none;align-items:center;justify-content:center;z-index:200;padding:16px}
.mb-overlay.open{display:flex}
.mb-modal{background:var(--mb-surface);border-radius:18px;width:min(520px,100%);max-height:88vh;overflow:auto;padding:20px}
.mb-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.mb-modal-head h2{margin:0;font-size:1.05rem}
.mb-field{display:block;font-size:.82rem;font-weight:700;color:var(--mb-muted);margin-bottom:12px}
.mb-field input, .mb-field select{display:block;width:100%;margin-top:6px;border:1px solid var(--mb-border);border-radius:11px;padding:10px;font-size:.88rem;color:var(--mb-text)}
.mb-field small{display:block;color:var(--mb-muted);font-size:.72rem;margin-top:4px;font-weight:400}
.mb-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.mb-required{color:var(--mb-danger)}
.mb-image-preview{margin:-4px 0 12px;border-radius:11px;overflow:hidden;height:120px;background:var(--mb-bg)}
.mb-image-preview img{width:100%;height:100%;object-fit:cover}
.mb-checkbox{display:flex;align-items:center;gap:8px;font-size:.86rem;font-weight:700;margin-bottom:14px}
@media(max-width:520px){ .mb-field-row{grid-template-columns:1fr} }
</style>

<script>
function mbOpenModal(banner) {
    const form = document.getElementById('mbForm');
    const isEdit = !!(banner && banner.id);

    document.getElementById('mbModalTitle').textContent = isEdit ? 'تعديل البانر' : 'إضافة بانر جديد';
    document.getElementById('mbMethod').value = isEdit ? 'POST' : 'POST';
    form.action = isEdit
        ? {!! json_encode(route('restaurant.menu.banners.update', ['banner' => '__ID__'])) !!}.replace('__ID__', banner.id)
        : @json(route('restaurant.menu.banners.store'));

    document.getElementById('mbImageRequiredStar').hidden = isEdit;
    document.getElementById('mbImage').required = !isEdit;

    document.getElementById('mbTitle').value = banner?.title || '';
    document.getElementById('mbBadgeText').value = banner?.badge_text || '';
    document.getElementById('mbSubtitle').value = banner?.subtitle || '';
    document.getElementById('mbLocation').value = banner?.location_id || '';
    document.getElementById('mbSortOrder').value = banner?.sort_order ?? 0;
    document.getElementById('mbIsActive').checked = banner ? !!banner.is_active : true;
    document.getElementById('mbStartsAt').value = banner?.starts_at ? banner.starts_at.slice(0, 16) : '';
    document.getElementById('mbEndsAt').value = banner?.ends_at ? banner.ends_at.slice(0, 16) : '';

    const preview = document.getElementById('mbImagePreview');
    if (banner?.image_url) {
        preview.hidden = false;
        preview.querySelector('img').src = banner.image_url;
    } else {
        preview.hidden = true;
    }

    document.getElementById('mbOverlay').classList.add('open');
}

function mbCloseModal() {
    document.getElementById('mbOverlay').classList.remove('open');
    document.getElementById('mbForm').reset();
}

document.getElementById('mbImage').addEventListener('change', e => {
    const file = e.target.files[0];
    const preview = document.getElementById('mbImagePreview');
    if (!file) return;
    preview.hidden = false;
    preview.querySelector('img').src = URL.createObjectURL(file);
});

document.getElementById('mbOverlay').addEventListener('click', e => {
    if (e.target.id === 'mbOverlay') mbCloseModal();
});
</script>
@endsection
