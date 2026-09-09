@extends('layouts.app')

@section('title', 'إعداد العميل')

@section('content')
    @include('admin.onboarding.partials.styles')

    @php
        $labels = [
            1 => 'نوع النشاط',
            2 => 'بيانات المنشأة',
            3 => 'الهوية والثيم',
            4 => 'التشغيل',
            5 => 'الوحدات',
            6 => 'المراجعة',
        ];
    @endphp

    <div class="onb-shell">
        <div class="onb-top">
            <div>
                <h1 class="page-heading">إعداد النظام لعميل جديد</h1>
                <p class="page-subheading">
                    معالج آمن يربط إعدادات النظام الحالية بدون إنشاء أنظمة مكررة.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('onboarding.destroy', $run) }}"
                onsubmit="return confirm('هل تريد إلغاء مسودة التعديل والرجوع للإعداد الحالي؟');"
            >
                @csrf
                @method('DELETE')

                <button class="btn btn-ghost" type="submit">
                    إلغاء التعديل
                </button>
            </form>
        </div>

        <div class="onb-progress">
            @foreach($labels as $number => $label)
                <div
                    class="onb-step-dot
                        {{ $number < $step ? 'done' : '' }}
                        {{ $number === $step ? 'active' : '' }}"
                >
                    {{ $label }}
                </div>
            @endforeach
        </div>

        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom: 1rem;">
                <strong>يرجى مراجعة الحقول التالية:</strong>

                <ul style="margin: .5rem 0 0;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('admin.onboarding.steps.step-' . $step)
    </div>
@endsection