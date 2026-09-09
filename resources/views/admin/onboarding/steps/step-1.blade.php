<form method="POST" action="{{ route('onboarding.step.save', [$run, 1]) }}">
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>1. اختر نوع النشاط</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-profile-grid">
                @foreach($profiles as $item)
                    <label class="onb-profile">
                        <input
                            type="radio"
                            name="business_profile_code"
                            value="{{ $item->code }}"
                            @checked(old('business_profile_code', $run->business_profile_code) === $item->code)
                            required
                        >
                        <strong>{{ $item->name }}</strong>
                        <small>{{ $item->description }}</small>
                    </label>
                @endforeach
            </div>

            <div class="onb-actions">
                <span></span>
                <div class="onb-actions-end">
                    <button type="submit" class="btn btn-gold">حفظ ومتابعة</button>
                </div>
            </div>
        </div>
    </div>
</form>
