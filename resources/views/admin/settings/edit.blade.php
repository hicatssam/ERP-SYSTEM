@extends('layouts.app')

@section('title', 'إدارة العملات')
@section('page-title', 'إدارة العملات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">إدارة العملات</h1>
        <p class="page-subheading">
            تعريف مستقل للعملات والرموز والصور. هذه الوحدة لا تغيّر أسعار الصرف أو الفواتير الحالية.
        </p>
    </div>

    @can('system_currencies.manage')
        <div class="page-header-actions">
            <a
                href="{{ route('settings.currencies.create') }}"
                class="btn btn-gold"
            >
                + إضافة عملة
            </a>
        </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:1rem">
        {{ session('error') }}
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form
            method="GET"
            action="{{ route('settings.currencies.index') }}"
            class="currency-filter"
        >
            <input
                class="form-input"
                name="q"
                value="{{ request('q') }}"
                placeholder="ابحث بالكود أو الاسم أو الرمز..."
            >

            <select class="form-input" name="status">
                <option value="">كل الحالات</option>
                <option value="active" @selected(request('status') === 'active')>
                    فعال
                </option>
                <option value="inactive" @selected(request('status') === 'inactive')>
                    غير فعال
                </option>
            </select>

            <button class="btn btn-outline" type="submit">
                بحث
            </button>

            @if(request()->hasAny(['q', 'status']))
                <a
                    href="{{ route('settings.currencies.index') }}"
                    class="btn btn-ghost"
                >
                    مسح
                </a>
            @endif
        </form>
    </div>
</div>

<div class="currency-grid">
    @forelse($currencies as $currency)
        <article class="currency-card">
            <div class="currency-card-head">
                <div class="currency-avatar">
                    @if($currency->image_url)
                        <img
                            src="{{ $currency->image_url }}"
                            alt="{{ $currency->display_name }}"
                        >
                    @elseif($currency->icon)
                        <span>{{ $currency->icon }}</span>
                    @else
                        <span>{{ $currency->symbol }}</span>
                    @endif
                </div>

                <div class="currency-main">
                    <div class="currency-title-row">
                        <h3>{{ $currency->name_ar }}</h3>

                        @if($currency->is_default)
                            <span class="currency-badge default">
                                افتراضية
                            </span>
                        @endif

                        <span class="currency-badge {{ $currency->is_active ? 'active' : 'inactive' }}">
                            {{ $currency->is_active ? 'فعالة' : 'غير فعالة' }}
                        </span>
                    </div>

                    <div class="currency-code">
                        {{ $currency->code }}
                        <span>•</span>
                        {{ $currency->symbol }}
                    </div>

                    @if($currency->name_en)
                        <div class="currency-en">
                            {{ $currency->name_en }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="currency-meta">
                <div>
                    <span>الخانات العشرية</span>
                    <strong>{{ $currency->decimal_places }}</strong>
                </div>

                <div>
                    <span>ترتيب العرض</span>
                    <strong>{{ $currency->sort_order }}</strong>
                </div>
            </div>

            @can('system_currencies.manage')
                <div class="currency-actions">
                    <a
                        href="{{ route('settings.currencies.edit', $currency) }}"
                        class="btn btn-outline btn-sm"
                    >
                        تعديل
                    </a>

                    <form
                        method="POST"
                        action="{{ route('settings.currencies.toggle', $currency) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <button
                            class="btn btn-ghost btn-sm"
                            type="submit"
                        >
                            {{ $currency->is_active ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>

                    @unless($currency->is_default)
                        <form
                            method="POST"
                            action="{{ route('settings.currencies.default', $currency) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="btn btn-ghost btn-sm"
                                type="submit"
                            >
                                جعلها افتراضية
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('settings.currencies.destroy', $currency) }}"
                            onsubmit="return confirm('هل تريد حذف هذه العملة؟')"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                class="btn btn-ghost btn-sm"
                                type="submit"
                                style="color:var(--theme-danger, #dc2626)"
                            >
                                حذف
                            </button>
                        </form>
                    @endunless
                </div>
            @endcan
        </article>
    @empty
        <div class="card">
            <div class="card-body" style="text-align:center;padding:2rem">
                لا توجد عملات مطابقة.
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:1rem">
    {{ $currencies->links() }}
</div>

<style>
    .currency-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 180px auto auto;
        gap: .65rem;
        align-items: center;
    }

    .currency-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .currency-card {
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: var(--theme-radius, 12px);
        background: var(--theme-surface, #fff);
        padding: 1rem;
    }

    .currency-card-head {
        display: flex;
        gap: .9rem;
        align-items: center;
    }

    .currency-avatar {
        width: 64px;
        height: 64px;
        flex: 0 0 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        border: 1px solid var(--theme-border, #e5e7eb);
        background: var(--theme-bg, #f8fafc);
        font-size: 1.35rem;
        font-weight: 800;
        overflow: hidden;
    }

    .currency-avatar img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 7px;
    }

    .currency-main {
        min-width: 0;
        flex: 1;
    }

    .currency-title-row {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        align-items: center;
    }

    .currency-title-row h3 {
        margin: 0;
        font-size: 1rem;
    }

    .currency-code {
        margin-top: .3rem;
        font-weight: 800;
        direction: ltr;
        text-align: right;
    }

    .currency-code span {
        margin: 0 .25rem;
        color: var(--theme-text-muted, #6b7280);
    }

    .currency-en {
        margin-top: .2rem;
        color: var(--theme-text-muted, #6b7280);
        font-size: .8rem;
        direction: ltr;
        text-align: right;
    }

    .currency-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: .2rem .55rem;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
    }

    .currency-badge.default {
        background: color-mix(in srgb, var(--theme-warning, #d99b22) 15%, transparent);
        color: var(--theme-warning, #b7791f);
    }

    .currency-badge.active {
        background: color-mix(in srgb, var(--theme-success, #16a34a) 13%, transparent);
        color: var(--theme-success, #15803d);
    }

    .currency-badge.inactive {
        background: color-mix(in srgb, var(--theme-danger, #dc2626) 10%, transparent);
        color: var(--theme-danger, #b91c1c);
    }

    .currency-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
        margin-top: .9rem;
        padding-top: .9rem;
        border-top: 1px solid var(--theme-border, #e5e7eb);
    }

    .currency-meta div {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        font-size: .82rem;
    }

    .currency-meta span {
        color: var(--theme-text-muted, #6b7280);
    }

    .currency-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .9rem;
    }

    .currency-actions form {
        margin: 0;
    }

    @media(max-width: 900px) {
        .currency-grid {
            grid-template-columns: 1fr;
        }

        .currency-filter {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
