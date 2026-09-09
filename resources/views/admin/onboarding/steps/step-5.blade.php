@php
    $plan = $run->module_plan ?? [];
    $selected = collect(old('selected_codes', $plan['selected_codes'] ?? []));
@endphp

<form method="POST" action="{{ route('onboarding.step.save', [$run, 5]) }}">
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>5. خطة الوحدات</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-warning" style="margin-bottom:1rem">
                الوحدات الأساسية مقفلة ومفعلة دائمًا. الوحدات الموصى بها حسب نوع النشاط تظهر بعلامة "موصى بها".
            </div>

            <div class="onb-module-list">
                @foreach($allModules as $module)
                    @php
                        $isCore = $module->type === \App\Enums\ModuleType::CORE;
                        $implemented = $module->isImplemented();
                        $recommended = in_array($module->code, $recommendedCodes, true);
                    @endphp

                    <label class="onb-module" style="{{ !$implemented ? 'opacity:.55' : '' }}">
                        <div class="onb-module-head">
                            <input
                                type="checkbox"
                                name="selected_codes[]"
                                value="{{ $module->code }}"
                                @checked($isCore || $selected->contains($module->code) || $recommended)
                                @disabled($isCore || !$implemented)
                            >

                            <div>
                                <strong>{{ $module->name }}</strong>
                                <small class="onb-help">{{ $module->description }}</small>

                                @if($isCore)
                                    <span class="onb-chip">أساسية</span>
                                @elseif($recommended)
                                    <span class="onb-chip">موصى بها</span>
                                @elseif(!$implemented)
                                    <span class="onb-chip">غير منفذة بعد</span>
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <label class="form-check" style="margin-top:1rem">
                <input
                    type="checkbox"
                    name="deactivate_unselected"
                    value="1"
                    @checked(old('deactivate_unselected', $plan['deactivate_unselected'] ?? false))
                >
                إيقاف الوحدات الاختيارية غير المحددة عند التطبيق
            </label>
            <small class="onb-help">
                اتركها غير محددة للحفاظ على أي وحدات حالية. إذا فعلتها، سيحاول النظام إيقاف الوحدات غير المختارة مع احترام الاعتماديات.
            </small>

            <div class="onb-actions">
                <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 4]) }}">السابق</a>
                <button type="submit" class="btn btn-gold">حفظ ومراجعة</button>
            </div>
        </div>
    </div>
</form>
