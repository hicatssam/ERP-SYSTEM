@extends('layouts.app')

@section('title', 'إعلانات المنيو')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-heading">
            إعلانات المنيو
        </h1>

        <p class="page-subheading">
            إدارة البنرات الإعلانية اللي بتظهر بأعلى صفحة المنيو (حسب الفرع أو لكل الفروع)
        </p>
    </div>

    <button
        type="button"
        class="btn btn-gold"
        onclick="bnOpenAddModal()"
    >
        + إضافة إعلان
    </button>
</div>


@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>فيه أخطاء لازم تنتبه لها:</strong>
        <ul style="margin:0.4rem 0 0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<div class="bn-grid">

    @forelse($banners as $banner)

        @php
            $now = now();
            $isScheduledOut = ($banner->starts_at && $banner->starts_at->isFuture())
                || ($banner->ends_at && $banner->ends_at->isPast());
        @endphp

        <div class="bn-card">

            <div class="bn-media">
                @if($banner->image_url)
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}">
                @else
                    <div class="bn-media-placeholder">
                        <i class="fa-solid fa-image"></i>
                    </div>
                @endif

                <span class="bn-scope-badge">
                    {{ $banner->location?->name ?? 'كل الفروع' }}
                </span>
            </div>

            <div class="bn-body">

                @if($banner->kicker)
                    <span class="bn-kicker">{{ $banner->kicker }}</span>
                @endif

                <h3 class="bn-title">{{ $banner->title }}</h3>

                @if($banner->subtitle)
                    <p class="bn-subtitle">{{ $banner->subtitle }}</p>
                @endif

                @if($banner->cta_text)
                    <div class="bn-cta-preview">
                        <i class="fa-solid fa-arrow-up-left-from-circle"></i>
                        {{ $banner->cta_text }}
                        @if($banner->cta_link)
                            <small>→ {{ $banner->cta_link }}</small>
                        @endif
                    </div>
                @endif

            </div>

            <div class="bn-footer">

                <div class="bn-meta">

                    @if($banner->is_active && !$isScheduledOut)
                        <span class="badge badge-success">فعال</span>
                    @elseif($banner->is_active && $isScheduledOut)
                        <span class="badge badge-warning">مجدول - غير ظاهر الآن</span>
                    @else
                        <span class="badge badge-danger">موقوف</span>
                    @endif

                    <span class="bn-sort">الترتيب: {{ $banner->sort_order }}</span>

                </div>

                <div class="bn-actions">

                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        onclick="bnOpenEditModal({
                            id: '{{ $banner->id }}',
                            location_id: '{{ $banner->location_id }}',
                            title: `{{ addslashes($banner->title) }}`,
                            kicker: `{{ addslashes($banner->kicker) }}`,
                            subtitle: `{{ addslashes($banner->subtitle) }}`,
                            cta_text: `{{ addslashes($banner->cta_text) }}`,
                            cta_link: `{{ addslashes($banner->cta_link) }}`,
                            sort_order: '{{ $banner->sort_order }}',
                            is_active: {{ $banner->is_active ? 'true' : 'false' }},
                            starts_at: '{{ optional($banner->starts_at)->format('Y-m-d\TH:i') }}',
                            ends_at: '{{ optional($banner->ends_at)->format('Y-m-d\TH:i') }}',
                            imageUrl: '{{ $banner->image_url }}',
                            updateUrl: '{{ route('banners.update', $banner) }}'
                        })"
                    >
                        تعديل
                    </button>

                    <form
                        method="POST"
                        action="{{ route('banners.destroy', $banner) }}"
                        onsubmit="return confirm('هل تريد حذف هذا الإعلان؟')"
                        style="display:inline"
                    >
                        @csrf
                        @method('DELETE')

                        <button class="btn btn-danger btn-sm" type="submit">
                            حذف
                        </button>
                    </form>

                </div>

            </div>

        </div>

    @empty

        <div class="bn-empty-state">
            <p>لا توجد إعلانات مضافة حاليًا.</p>
            <button type="button" class="btn btn-gold" onclick="bnOpenAddModal()">+ إضافة أول إعلان</button>
        </div>

    @endforelse

</div>


{{-- Modal: إضافة إعلان --}}

<div id="bnAddModalBackdrop" class="bn-modal-backdrop" style="display:none">
    <div class="bn-modal">

        <div class="bn-modal-header">
            <span class="card-title">إضافة إعلان جديد</span>
            <button type="button" class="bn-modal-close" onclick="bnCloseAddModal()">×</button>
        </div>

        <form
            method="POST"
            action="{{ route('banners.store') }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="bn-modal-body">
                @include('admin.banners._form-fields', ['prefix' => 'create'])
            </div>

            <div class="bn-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="bnCloseAddModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold">+ إضافة الإعلان</button>
            </div>
        </form>

    </div>
</div>


{{-- Modal: تعديل إعلان --}}

<div id="bnEditModalBackdrop" class="bn-modal-backdrop" style="display:none">
    <div class="bn-modal">

        <div class="bn-modal-header">
            <span class="card-title">تعديل الإعلان</span>
            <button type="button" class="bn-modal-close" onclick="bnCloseEditModal()">×</button>
        </div>

        <form
            id="bnEditForm"
            method="POST"
            action=""
            enctype="multipart/form-data"
        >
            @csrf
            @method('PUT')

            <div class="bn-modal-body">
                @include('admin.banners._form-fields', ['prefix' => 'edit'])
            </div>

            <div class="bn-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="bnCloseEditModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold">حفظ التعديلات</button>
            </div>
        </form>

    </div>
</div>


<style>
    .bn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem}
    .bn-card{display:flex;flex-direction:column;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e5e5);border-radius:12px;overflow:hidden;transition:border-color .15s ease,box-shadow .15s ease}
    .bn-card:hover{border-color:var(--gold,#c9a24a);box-shadow:0 4px 14px rgba(0,0,0,.06)}
    .bn-media{position:relative;height:150px;background:#f2ece0}
    .bn-media img{width:100%;height:100%;object-fit:cover;display:block}
    .bn-media-placeholder{width:100%;height:100%;display:grid;place-items:center;color:#b8a67c;font-size:1.8rem}
    .bn-scope-badge{position:absolute;top:9px;inset-inline-start:9px;padding:.2rem .6rem;border-radius:999px;background:rgba(23,16,6,.75);color:#f0d68b;font-size:.72rem;font-weight:700}
    .bn-body{padding:1rem 1.1rem 0}
    .bn-kicker{display:block;font-size:.72rem;font-weight:700;color:var(--gold,#c9a24a);margin-bottom:.2rem}
    .bn-title{margin:0 0 .3rem;font-size:1rem;font-weight:700}
    .bn-subtitle{margin:0;font-size:.85rem;opacity:.7}
    .bn-cta-preview{margin-top:.6rem;font-size:.78rem;opacity:.7;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
    .bn-cta-preview small{opacity:.7;direction:ltr}
    .bn-footer{margin-top:1rem;padding:.85rem 1.1rem;border-top:1px dashed var(--border-color,#eee);display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
    .bn-meta{display:flex;align-items:center;gap:.6rem}
    .bn-sort{font-size:.75rem;opacity:.55}
    .bn-actions{display:flex;gap:.4rem;white-space:nowrap}
    .bn-empty-state{grid-column:1/-1;text-align:center;padding:3rem 1rem;display:flex;flex-direction:column;align-items:center;gap:1rem;opacity:.85}
    .bn-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000;padding:1rem}
    .bn-modal{background:var(--card-bg,#fff);width:min(560px,92vw);max-height:88vh;overflow-y:auto;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .bn-modal-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border-color,#e5e5e5)}
    .bn-modal-body{padding:1rem 1.25rem}
    .bn-modal-footer{display:flex;justify-content:flex-end;gap:.5rem;padding:1rem 1.25rem;border-top:1px solid var(--border-color,#e5e5e5)}
    .bn-modal-close{background:none;border:none;font-size:1.4rem;line-height:1;cursor:pointer;opacity:.6}
    .bn-modal-close:hover{opacity:1}
    .bn-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
    .bn-form-grid .full{grid-column:1/-1}
    .bn-form-grid label{font-size:.83rem;font-weight:700;opacity:.8;display:block;margin-bottom:.3rem}
    .bn-form-grid input,.bn-form-grid select,.bn-form-grid textarea{width:100%;border:1px solid var(--border-color,#e2e2e2);border-radius:10px;padding:.6rem .7rem;outline:none}
    .bn-current-image{margin-top:.4rem;display:flex;align-items:center;gap:.6rem}
    .bn-current-image img{width:64px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color,#eee)}
    .bn-checkbox-label{display:flex;align-items:center;gap:.4rem;cursor:pointer;margin-top:1.6rem}
</style>


<script>
    function bnOpenAddModal(){
        document.getElementById('bnAddModalBackdrop').style.display='flex';
    }
    function bnCloseAddModal(){
        document.getElementById('bnAddModalBackdrop').style.display='none';
    }

    function bnOpenEditModal(data){
        const form=document.getElementById('bnEditForm');
        form.action=data.updateUrl;

        document.getElementById('edit_location_id').value=data.location_id||'';
        document.getElementById('edit_title').value=data.title==='null'?'':data.title;
        document.getElementById('edit_kicker').value=data.kicker==='null'?'':data.kicker;
        document.getElementById('edit_subtitle').value=data.subtitle==='null'?'':data.subtitle;
        document.getElementById('edit_cta_text').value=data.cta_text==='null'?'':data.cta_text;
        document.getElementById('edit_cta_link').value=data.cta_link==='null'?'':data.cta_link;
        document.getElementById('edit_sort_order').value=data.sort_order||0;
        document.getElementById('edit_is_active').checked=!!data.is_active;
        document.getElementById('edit_starts_at').value=data.starts_at||'';
        document.getElementById('edit_ends_at').value=data.ends_at||'';

        const preview=document.getElementById('edit_current_image');
        if(data.imageUrl){
            preview.innerHTML=`<img src="${data.imageUrl}" alt=""><small>الصورة الحالية (ارفع صورة جديدة لاستبدالها)</small>`;
        }else{
            preview.innerHTML='';
        }

        document.getElementById('bnEditModalBackdrop').style.display='flex';
    }
    function bnCloseEditModal(){
        document.getElementById('bnEditModalBackdrop').style.display='none';
    }

    document.getElementById('bnAddModalBackdrop').addEventListener('click',function(e){
        if(e.target===this) bnCloseAddModal();
    });
    document.getElementById('bnEditModalBackdrop').addEventListener('click',function(e){
        if(e.target===this) bnCloseEditModal();
    });

    @if($errors->any() && old('_method') === 'PUT')
        document.addEventListener('DOMContentLoaded', function(){
            bnOpenEditModal({
                location_id: '{{ old('location_id') }}',
                title: `{{ addslashes(old('title','')) }}`,
                kicker: `{{ addslashes(old('kicker','')) }}`,
                subtitle: `{{ addslashes(old('subtitle','')) }}`,
                cta_text: `{{ addslashes(old('cta_text','')) }}`,
                cta_link: `{{ addslashes(old('cta_link','')) }}`,
                sort_order: '{{ old('sort_order',0) }}',
                is_active: {{ old('is_active') ? 'true' : 'false' }},
                starts_at: '{{ old('starts_at') }}',
                ends_at: '{{ old('ends_at') }}',
                imageUrl: '',
                updateUrl: window.location.href
            });
        });
    @elseif($errors->any())
        document.addEventListener('DOMContentLoaded', function(){ bnOpenAddModal(); });
    @endif
</script>

@endsection
