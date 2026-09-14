@extends('layouts.app')

@section('title', 'بطاقات إعلانات المنيو')
@section('page-title', 'بطاقات إعلانات المنيو')

@section('content')
@php
    $resolveImage = static function (?string $image): ?string {
        if (blank($image)) return null;
        $image = trim($image);
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '//')) return $image;
        $normalized = ltrim($image, '/');
        if (str_starts_with($normalized, 'storage/')) return asset($normalized);
        if (file_exists(public_path($normalized))) return asset($normalized);
        return asset('storage/' . $normalized);
    };
@endphp

<div class="mb-page">
    <div class="mb-header">
        <div>
            <h1>بطاقات إعلانات المنيو</h1>
            <p>الإعلانات الظاهرة أعلى منيو الزبون. اربط الإعلان بمنتج ليذهب زر «اطلب الآن» مباشرة إلى صفحة المنتج.</p>
        </div>
        <button type="button" class="mb-btn mb-btn-primary" onclick="mbOpenModal()">+ إضافة بانر جديد</button>
    </div>

    @if(session('success'))<div class="mb-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="mb-grid">
        @forelse($banners as $banner)
            <article class="mb-card {{ $banner->is_active ? '' : 'is-off' }}">
                <div class="mb-media">
                    @if($resolveImage($banner->image))<img src="{{ $resolveImage($banner->image) }}" alt="">@endif
                    @if($banner->badge_text)<span>{{ $banner->badge_text }}</span>@endif
                </div>
                <div class="mb-body">
                    <strong>{{ $banner->title ?: 'بدون عنوان' }}</strong>
                    @if($banner->subtitle)<small>{{ $banner->subtitle }}</small>@endif
                    <div class="mb-chips">
                        <b>{{ $banner->location?->name ?? 'كل الفروع' }}</b>
                        @if($banner->product)<b>المنتج: {{ $banner->product->name_ar ?: $banner->product->name }}</b>@endif
                        @if(!$banner->is_active)<b class="danger">متوقف</b>@endif
                    </div>
                </div>
                <div class="mb-actions">
                    <button type="button" onclick='mbOpenModal(@json(array_merge($banner->toArray(), ["image_url" => $resolveImage($banner->image)])))'>تعديل</button>
                    <form method="POST" action="{{ route('restaurant.menu.banners.toggle-active', $banner) }}">@csrf<button type="submit">{{ $banner->is_active ? 'إيقاف' : 'تفعيل' }}</button></form>
                    <form method="POST" action="{{ route('restaurant.menu.banners.destroy', $banner) }}" onsubmit="return confirm('حذف هذا البانر نهائيًا؟')">@csrf @method('DELETE')<button type="submit" class="danger-btn">حذف</button></form>
                </div>
            </article>
        @empty
            <div class="mb-empty">لا توجد بانرات بعد.</div>
        @endforelse
    </div>
</div>

<div class="mb-overlay" id="mbOverlay" aria-hidden="true">
    <div class="mb-modal" role="dialog" aria-modal="true">
        <div class="mb-modal-head"><h2 id="mbModalTitle">إضافة بانر جديد</h2><button type="button" onclick="mbCloseModal()">×</button></div>
        <form id="mbForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="mbMethod" value="POST">

            <label class="mb-field">صورة البانر <span id="mbImageRequired">*</span>
                <input type="file" name="image" id="mbImage" accept=".jpg,.jpeg,.png,.webp">
                <small>يفضل 1200×600 أو أي مقاس 16:9، حتى 4MB.</small>
            </label>
            <div class="mb-preview" id="mbPreview" hidden><img alt="معاينة"></div>

            <div class="mb-row">
                <label class="mb-field">العنوان<input type="text" name="title" id="mbTitle" maxlength="120"></label>
                <label class="mb-field">الشارة<input type="text" name="badge_text" id="mbBadgeText" maxlength="30" placeholder="عرض اليوم"></label>
            </div>
            <label class="mb-field">الوصف<input type="text" name="subtitle" id="mbSubtitle" maxlength="200"></label>

            <div class="mb-row">
                <label class="mb-field">الفرع
                    <select name="location_id" id="mbLocation"><option value="">كل الفروع</option>@foreach($locations as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                </label>
                <label class="mb-field">المنتج المرتبط
                    <select name="product_id" id="mbProduct"><option value="">بدون منتج محدد</option>@foreach($products as $product)<option value="{{ $product['id'] }}">{{ $product['name'] }}</option>@endforeach</select>
                    <small>إذا اخترت منتجًا، زر «اطلب الآن» يفتح نفس المنتج في الفرع الحالي.</small>
                </label>
            </div>

            <div class="mb-row">
                <label class="mb-field">ترتيب الظهور<input type="number" name="sort_order" id="mbSortOrder" min="0" max="9999" value="0"></label>
                <label class="mb-field">رابط خارجي اختياري<input type="url" name="link_url" id="mbLinkUrl" placeholder="https://..."></label>
            </div>

            <div class="mb-row">
                <label class="mb-field">تاريخ البداية<input type="datetime-local" name="starts_at" id="mbStartsAt"></label>
                <label class="mb-field">تاريخ الانتهاء<input type="datetime-local" name="ends_at" id="mbEndsAt"></label>
            </div>

            <label class="mb-check"><input type="checkbox" name="is_active" id="mbIsActive" value="1" checked> مفعّل ويظهر للزبائن</label>
            <button class="mb-btn mb-btn-primary mb-save" type="submit">حفظ البانر</button>
        </form>
    </div>
</div>

<style>
.mb-page,.mb-overlay,.mb-page *,.mb-overlay *{box-sizing:border-box}.mb-page{direction:rtl}.mb-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px;flex-wrap:wrap}.mb-header h1{margin:0;font-size:1.25rem}.mb-header p{margin:5px 0 0;color:#777;max-width:650px}.mb-btn{border:1px solid #ddd;background:#fff;padding:10px 16px;border-radius:12px;font-weight:800}.mb-btn-primary{background:var(--theme-primary,#8f1438);color:#fff;border-color:transparent}.mb-alert,.mb-errors{padding:12px 14px;border-radius:12px;margin-bottom:14px}.mb-alert{background:#eaf8ef;color:#15714b}.mb-errors{background:#fff0f0;color:#a32020}.mb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}.mb-card{background:#fff;border:1px solid #e7e7e7;border-radius:18px;overflow:hidden}.mb-card.is-off{opacity:.55}.mb-media{height:160px;position:relative;background:#f5f5f5}.mb-media img{width:100%;height:100%;object-fit:cover}.mb-media span{position:absolute;top:10px;left:10px;background:#8f1438;color:#fff;border-radius:999px;padding:5px 10px;font-size:.72rem;font-weight:900}.mb-body{padding:13px 14px}.mb-body strong,.mb-body small{display:block}.mb-body small{color:#777;margin-top:3px}.mb-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.mb-chips b{font-size:.7rem;background:#f4f4f4;border-radius:999px;padding:4px 8px;color:#666}.mb-chips .danger{color:#a32020;background:#fff0f0}.mb-actions{display:flex;gap:7px;padding:0 14px 14px}.mb-actions form{margin:0}.mb-actions button{border:1px solid #ddd;background:#fff;border-radius:10px;padding:8px 10px;font-weight:700}.mb-actions .danger-btn{color:#b42318}.mb-empty{grid-column:1/-1;padding:45px;text-align:center;border:1px dashed #ddd;border-radius:16px;color:#777}.mb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:16px;z-index:9999}.mb-overlay.open{display:flex}.mb-modal{width:min(620px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;padding:20px;direction:rtl}.mb-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.mb-modal-head h2{margin:0}.mb-modal-head button{width:36px;height:36px;border:0;border-radius:50%;background:#f2f2f2;font-size:1.3rem}.mb-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mb-field{display:block;font-size:.82rem;font-weight:800;color:#666;margin-bottom:12px}.mb-field input,.mb-field select{display:block;width:100%;margin-top:6px;border:1px solid #ddd;border-radius:11px;padding:10px;background:#fff}.mb-field small{display:block;margin-top:4px;font-weight:500;color:#888}.mb-preview{height:170px;border-radius:12px;overflow:hidden;margin-bottom:12px}.mb-preview img{width:100%;height:100%;object-fit:cover}.mb-check{display:flex;gap:8px;align-items:center;font-weight:800;margin-bottom:14px}.mb-save{width:100%;justify-content:center}@media(max-width:600px){.mb-row{grid-template-columns:1fr}.mb-modal{padding:15px}}
</style>

<script>
function mbOpenModal(banner=null){
 const isEdit=!!(banner&&banner.id),form=document.getElementById('mbForm');
 document.getElementById('mbModalTitle').textContent=isEdit?'تعديل البانر':'إضافة بانر جديد';
 document.getElementById('mbMethod').value=isEdit?'PUT':'POST';
 form.action=isEdit?{!! json_encode(route('restaurant.menu.banners.update',['banner'=>'__ID__'])) !!}.replace('__ID__',banner.id):@json(route('restaurant.menu.banners.store'));
 const image=document.getElementById('mbImage');image.required=!isEdit;image.value='';document.getElementById('mbImageRequired').hidden=isEdit;
 document.getElementById('mbTitle').value=banner?.title||'';document.getElementById('mbBadgeText').value=banner?.badge_text||'';document.getElementById('mbSubtitle').value=banner?.subtitle||'';document.getElementById('mbLocation').value=banner?.location_id||'';document.getElementById('mbProduct').value=banner?.product_id||'';document.getElementById('mbLinkUrl').value=banner?.link_url||'';document.getElementById('mbSortOrder').value=banner?.sort_order??0;document.getElementById('mbStartsAt').value=banner?.starts_at?String(banner.starts_at).slice(0,16):'';document.getElementById('mbEndsAt').value=banner?.ends_at?String(banner.ends_at).slice(0,16):'';document.getElementById('mbIsActive').checked=banner?!!banner.is_active:true;
 const preview=document.getElementById('mbPreview');if(banner?.image_url){preview.hidden=false;preview.querySelector('img').src=banner.image_url}else preview.hidden=true;
 document.getElementById('mbOverlay').classList.add('open');document.body.style.overflow='hidden';
}
function mbCloseModal(){document.getElementById('mbOverlay').classList.remove('open');document.body.style.overflow=''}
document.getElementById('mbImage').addEventListener('change',e=>{const f=e.target.files[0],p=document.getElementById('mbPreview');if(!f)return;p.hidden=false;p.querySelector('img').src=URL.createObjectURL(f)});document.getElementById('mbOverlay').addEventListener('click',e=>{if(e.target.id==='mbOverlay')mbCloseModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape')mbCloseModal()});
</script>
@endsection
