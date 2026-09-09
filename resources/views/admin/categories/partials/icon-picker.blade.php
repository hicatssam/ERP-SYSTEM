@php
    $selectedIcon = old(
        'icon_key',
        isset($category) ? ($category->icon_key ?: 'sparkles') : 'sparkles'
    );

    $selectedColor = old(
        'icon_color',
        isset($category) ? ($category->icon_color ?: '#111111') : '#111111'
    );

    $icons = \App\Support\MenuIconLibrary::options();
@endphp

<div class="category-icon-designer" id="categoryIconDesigner">
    <div class="category-icon-head">
        <div>
            <label class="form-label">أيقونة الفئة</label>
            <p>اختر أيقونة نظيفة تظهر في منيو العميل وعلى بطاقات الأصناف.</p>
        </div>

        <label class="category-icon-color">
            <span>لون الأيقونة</span>
            <input
                type="color"
                name="icon_color"
                value="{{ $selectedColor }}"
                id="categoryIconColor"
            >
        </label>
    </div>

    <input
        type="hidden"
        name="icon_key"
        id="categoryIconKey"
        value="{{ $selectedIcon }}"
    >

    <div class="category-icon-grid">
        @foreach($icons as $key => $label)
            <button
                type="button"
                class="category-icon-choice {{ $selectedIcon === $key ? 'is-selected' : '' }}"
                data-icon-key="{{ $key }}"
                aria-pressed="{{ $selectedIcon === $key ? 'true' : 'false' }}"
            >
                <span class="category-icon-circle">
                    <x-menu-icon :name="$key" :size="34" :stroke="1.65" />
                </span>

                <strong>{{ $label }}</strong>

                <span class="category-icon-check">✓</span>
            </button>
        @endforeach
    </div>

    @error('icon_key')
        <span class="form-error">{{ $message }}</span>
    @enderror

    @error('icon_color')
        <span class="form-error">{{ $message }}</span>
    @enderror
</div>

<style>
.category-icon-designer{
    margin-top:1rem;
    padding:1rem;
    border:1px solid var(--border-light,#e8e8e8);
    border-radius:18px;
    background:linear-gradient(180deg,#fff,#fbfbfb);
}
.category-icon-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:.9rem;
}
.category-icon-head p{
    margin:.25rem 0 0;
    color:var(--text-muted,#777);
    font-size:.75rem;
}
.category-icon-color{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex:0 0 auto;
    padding:.45rem .55rem;
    border:1px solid var(--border-light,#e8e8e8);
    border-radius:12px;
    background:#fff;
    font-size:.7rem;
    font-weight:800;
}
.category-icon-color input{
    width:34px;
    height:34px;
    padding:2px;
    border:0;
    border-radius:9px;
    background:transparent;
    cursor:pointer;
}
.category-icon-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(112px,1fr));
    gap:.65rem;
}
.category-icon-choice{
    position:relative;
    min-height:124px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:.55rem;
    padding:.8rem .55rem;
    border:1px solid #ececec;
    border-radius:17px;
    background:#fff;
    color:#111;
    transition:.18s ease;
}
.category-icon-choice:hover{
    transform:translateY(-2px);
    border-color:#d7d7d7;
    box-shadow:0 12px 28px rgba(20,20,20,.07);
}
.category-icon-choice.is-selected{
    border-color:color-mix(in srgb,var(--gold,#c89a2b) 65%,#ddd);
    box-shadow:0 0 0 3px color-mix(in srgb,var(--gold,#c89a2b) 10%,transparent);
}
.category-icon-circle{
    width:64px;
    height:64px;
    display:grid;
    place-items:center;
    border:1px solid #eeeeee;
    border-radius:50%;
    background:#fafafa;
    color:var(--category-icon-color,#111);
    transition:.18s ease;
}
.category-icon-choice.is-selected .category-icon-circle{
    color:#fff;
    background:var(--category-icon-color,#111);
    border-color:var(--category-icon-color,#111);
}
.category-icon-choice strong{
    font-size:.72rem;
    font-weight:850;
}
.category-icon-check{
    position:absolute;
    top:7px;
    left:7px;
    width:21px;
    height:21px;
    display:grid;
    place-items:center;
    border-radius:50%;
    color:#fff;
    background:var(--gold,#c89a2b);
    font-size:.64rem;
    font-weight:900;
    opacity:0;
    transform:scale(.7);
    transition:.18s ease;
}
.category-icon-choice.is-selected .category-icon-check{
    opacity:1;
    transform:scale(1);
}
@media(max-width:640px){
    .category-icon-head{flex-direction:column}
    .category-icon-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .category-icon-choice{min-height:105px}
    .category-icon-circle{width:54px;height:54px}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('categoryIconDesigner');
    if (!root) return;

    const hidden = root.querySelector('#categoryIconKey');
    const color = root.querySelector('#categoryIconColor');
    const choices = [...root.querySelectorAll('[data-icon-key]')];

    const syncColor = () => {
        root.style.setProperty(
            '--category-icon-color',
            color?.value || '#111111'
        );
    };

    choices.forEach((button) => {
        button.addEventListener('click', () => {
            hidden.value = button.dataset.iconKey || 'sparkles';

            choices.forEach((item) => {
                const selected = item === button;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        });
    });

    color?.addEventListener('input', syncColor);
    syncColor();
});
</script>
