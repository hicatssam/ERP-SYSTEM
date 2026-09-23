@extends('layouts.app')

@section('title', 'بنرات المنيو')
@section('page-title', 'بنرات المنيو')

@section('content')
<div class="card" style="margin-bottom: 1rem;">
    <div class="card-header">
        <div>
            <h3 style="margin:0;">بنرات منيو الفرع</h3>
            <p style="margin:.35rem 0 0;color:var(--text-muted);">
                إدارة الصور الترويجية التي تظهر أعلى منيو العملاء.
            </p>
        </div>
    </div>

    <div class="card-body">
        @if($locations->count() > 1)
            <form method="GET" action="{{ route('restaurant.menu.banners.index') }}" style="margin-bottom:1rem;">
                <label for="banner-location">الفرع</label>
                <select
                    id="banner-location"
                    name="location_id"
                    class="form-control"
                    onchange="this.form.submit()"
                >
                    @foreach($locations as $branch)
                        <option
                            value="{{ $branch->id }}"
                            @selected((int) $branch->id === (int) $location->id)
                        >
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        @can('restaurant_menu.manage')
            <form
                method="POST"
                action="{{ route('restaurant.menu.banners.store') }}"
                enctype="multipart/form-data"
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.9rem;align-items:end;"
            >
                @csrf
                <input type="hidden" name="location_id" value="{{ $location->id }}">

                <div>
                    <label>الصورة *</label>
                    <input class="form-control" type="file" name="image" accept="image/*" required>
                </div>

                <div>
                    <label>العنوان</label>
                    <input class="form-control" type="text" name="title" maxlength="150" value="{{ old('title') }}">
                </div>

                <div>
                    <label>النص الفرعي</label>
                    <input class="form-control" type="text" name="subtitle" maxlength="255" value="{{ old('subtitle') }}">
                </div>

                <div>
                    <label>الشارة</label>
                    <input class="form-control" type="text" name="badge_text" maxlength="80" value="{{ old('badge_text') }}">
                </div>

                <div>
                    <label>الرابط</label>
                    <input class="form-control" type="text" name="link_url" maxlength="1000" value="{{ old('link_url') }}">
                </div>

                <div>
                    <label>الترتيب</label>
                    <input class="form-control" type="number" min="0" max="9999" name="sort_order" value="{{ old('sort_order', 0) }}">
                </div>

                <div>
                    <label>يبدأ من</label>
                    <input class="form-control" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
                </div>

                <div>
                    <label>ينتهي في</label>
                    <input class="form-control" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
                </div>

                <label style="display:flex;gap:.5rem;align-items:center;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>فعال</span>
                </label>

                <div>
                    <button class="btn btn-primary" type="submit">إضافة البانر</button>
                </div>
            </form>
        @endcan
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:1rem;">
    @forelse($banners as $banner)
        <div class="card">
            <div style="aspect-ratio:16/6;overflow:hidden;border-radius:14px 14px 0 0;background:#f3f4f6;">
                <img
                    src="{{ asset('storage/' . ltrim($banner->image, '/')) }}"
                    alt="{{ $banner->title ?: 'بانر المنيو' }}"
                    style="width:100%;height:100%;object-fit:cover;"
                >
            </div>

            <div class="card-body">
                <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;">
                    <div>
                        <strong>{{ $banner->title ?: 'بدون عنوان' }}</strong>
                        @if($banner->subtitle)
                            <div style="color:var(--text-muted);font-size:.85rem;margin-top:.3rem;">
                                {{ $banner->subtitle }}
                            </div>
                        @endif
                    </div>

                    <span class="badge {{ $banner->is_active ? 'badge-success' : 'badge-secondary' }}">
                        {{ $banner->is_active ? 'فعال' : 'متوقف' }}
                    </span>
                </div>

                <div style="margin-top:.75rem;color:var(--text-muted);font-size:.8rem;">
                    {{ $banner->location_id ? $location->name : 'عام لجميع الفروع' }}
                    · ترتيب {{ $banner->sort_order }}
                </div>

                @if($banner->location_id)
                    @can('restaurant_menu.manage')
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;">
                            <form method="POST" action="{{ route('restaurant.menu.banners.toggle-active', $banner) }}">
                                @csrf
                                <input type="hidden" name="location_id" value="{{ $location->id }}">
                                <button class="btn btn-outline" type="submit">
                                    {{ $banner->is_active ? 'إيقاف' : 'تفعيل' }}
                                </button>
                            </form>

                            <details style="flex:1;">
                                <summary class="btn btn-outline" style="cursor:pointer;">تعديل</summary>

                                <form
                                    method="POST"
                                    action="{{ route('restaurant.menu.banners.update', $banner) }}"
                                    enctype="multipart/form-data"
                                    style="display:grid;gap:.65rem;margin-top:.75rem;"
                                >
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $location->id }}">
                                    <input class="form-control" type="file" name="image" accept="image/*">
                                    <input class="form-control" type="text" name="title" value="{{ $banner->title }}" placeholder="العنوان">
                                    <input class="form-control" type="text" name="subtitle" value="{{ $banner->subtitle }}" placeholder="النص الفرعي">
                                    <input class="form-control" type="text" name="badge_text" value="{{ $banner->badge_text }}" placeholder="الشارة">
                                    <input class="form-control" type="text" name="link_url" value="{{ $banner->link_url }}" placeholder="الرابط">
                                    <input class="form-control" type="number" min="0" max="9999" name="sort_order" value="{{ $banner->sort_order }}">
                                    <input type="hidden" name="is_active" value="0">
                                    <label style="display:flex;gap:.5rem;align-items:center;">
                                        <input type="checkbox" name="is_active" value="1" @checked($banner->is_active)>
                                        <span>فعال</span>
                                    </label>
                                    <button class="btn btn-primary" type="submit">حفظ التعديل</button>
                                </form>
                            </details>

                            <form
                                method="POST"
                                action="{{ route('restaurant.menu.banners.destroy', $banner) }}"
                                onsubmit="return confirm('هل تريد حذف هذا البانر؟')"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="location_id" value="{{ $location->id }}">
                                <button class="btn btn-danger" type="submit">حذف</button>
                            </form>
                        </div>
                    @endcan
                @else
                    <div style="margin-top:1rem;color:var(--text-muted);font-size:.8rem;">
                        البانر العام يظهر هنا للمعاينة فقط ولا يتم تعديله من شاشة الفرع.
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body" style="text-align:center;color:var(--text-muted);">
                لا توجد بنرات لهذا الفرع حتى الآن.
            </div>
        </div>
    @endforelse
</div>
@endsection
