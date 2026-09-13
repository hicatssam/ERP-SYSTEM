@php
    $cmSetting = static fn(string $key, mixed $default = null): mixed =>
        old($key, \App\Models\SystemSetting::get($key, $default));

    $cmPreviewLocation = \App\Models\Location::query()
        ->branches()
        ->active()
        ->orderBy('id')
        ->first();

    $cmAsset = static function (?string $path): ?string {
        $path = trim((string) $path);
        if ($path === '') return null;
        if (str_starts_with($path,'http://') || str_starts_with($path,'https://') || str_starts_with($path,'//')) return $path;
        $normalized = ltrim(str_replace('\\','/',$path),'/');
        if (str_starts_with($normalized,'storage/')) return asset($normalized);
        if (file_exists(public_path($normalized))) return asset($normalized);
        return asset('storage/'.$normalized);
    };

    $brandName = (string) \App\Models\SystemSetting::get('system_name','اسم المطعم');
    $brandLogo = $cmAsset((string)\App\Models\SystemSetting::get('brand_logo',''))
        ?: $cmAsset((string)\App\Models\SystemSetting::get('brand_logo_small',''));

    $cover = $cmAsset((string)$cmSetting('customer_menu_cover_image',''));
    $introImage = $cmAsset((string)$cmSetting('customer_menu_intro_image','')) ?: $cover;
@endphp

<div class="cmv4" id="cmV4Builder">
    <div class="cmv4-head">
        <div>
            <span>Customer Menu V4</span>
            <h3>مصمم تجربة العميل</h3>
            <p>تحكم بالواجهة الافتتاحية، شخصية المطعم، المنيو، المنتجات، طاقم العمل والهوية من مكان واحد.</p>
        </div>

        @if($cmPreviewLocation)
            <a class="btn btn-outline btn-sm" href="{{ route('customer-menu.show',['location'=>$cmPreviewLocation->code]) }}" target="_blank" rel="noopener">
                فتح المنيو العام
            </a>
        @endif
    </div>

    <div class="cmv4-preview"
         style="
            --p:{{ $cmSetting('customer_menu_primary_color','#704C34') }};
            --a:{{ $cmSetting('customer_menu_accent_color','#D79A55') }};
            --bg:{{ $cmSetting('customer_menu_background_color','#F7F3EE') }};
            --s:{{ $cmSetting('customer_menu_surface_color','#FFFFFF') }};
            --t:{{ $cmSetting('customer_menu_text_color','#241D18') }};
            --m:{{ $cmSetting('customer_menu_muted_color','#7D746C') }};
         ">
        <div class="cmv4-preview-intro" id="cmv4PreviewIntro" @if($introImage) style="background-image:linear-gradient(rgba(10,8,7,.46),rgba(10,8,7,.46)),url('{{ $introImage }}')" @endif>
            <div>
                @if($brandLogo)<img src="{{ $brandLogo }}" alt="">@endif
                <small id="cmv4PreviewEyebrow">{{ $cmSetting('customer_menu_intro_eyebrow','') }}</small>
                <strong id="cmv4PreviewIntroTitle">{{ $cmSetting('customer_menu_intro_title','أهلاً بك') }}</strong>
                <span id="cmv4PreviewIntroSubtitle">{{ $cmSetting('customer_menu_intro_subtitle','تجربة ألذ تبدأ من هنا') }}</span>
                <button type="button" tabindex="-1" id="cmv4PreviewIntroCta">{{ $cmSetting('customer_menu_intro_cta','اطلب الآن') }}</button>
            </div>
        </div>

        <div class="cmv4-preview-menu">
            <header>
                <div>
                    @if($brandLogo)<img src="{{ $brandLogo }}" alt="">@endif
                    <b>{{ $brandName }}</b>
                </div>
                <span>طلباتي</span>
            </header>

            <section class="hero" @if($cover) style="background-image:linear-gradient(90deg,rgba(20,14,10,.68),rgba(20,14,10,.16)),url('{{ $cover }}')" @endif>
                <div>
                    <small>الفرع</small>
                    <strong id="cmv4PreviewTitle">{{ $cmSetting('customer_menu_title','اطلب مباشرة') }}</strong>
                    <span id="cmv4PreviewSubtitle">{{ $cmSetting('customer_menu_subtitle','اختر طلبك وسنجهزه لك') }}</span>
                </div>
            </section>

            <div class="chips"><i>الكل</i><i>قهوة</i><i>حلويات</i></div>

            <div class="cards">
                <article><div></div><b>لاتيه</b><small>مشروب ساخن</small><strong>18 ₪</strong></article>
                <article><div></div><b>تشيز كيك</b><small>قطعة يومية</small><strong>22 ₪</strong></article>
                <aside><b>طلبك</b><small>لاتيه × 1</small><strong>18 ₪</strong></aside>
            </div>
        </div>
    </div>

    <div class="cmv4-tabs">
        <button type="button" class="active" data-cmv4-tab="experience">التجربة</button>
        <button type="button" data-cmv4-tab="intro">الافتتاحية</button>
        <button type="button" data-cmv4-tab="layout">التصميم</button>
        <button type="button" data-cmv4-tab="showcase">Showcase V5</button>
        <button type="button" data-cmv4-tab="team">طاقم العمل</button>
        <button type="button" data-cmv4-tab="ordering">الطلب</button>
        <button type="button" data-cmv4-tab="status">حالة الطلب</button>
    </div>

    <div class="cmv4-pane active" data-cmv4-pane="experience">
        <div class="cmv4-grid">
            <div class="wide">
                <label class="form-label">تشغيل منيو الطلب العام</label>
                <div class="boolean-options">
                    <label><input type="radio" name="customer_menu_enabled" value="1" {{ (string)$cmSetting('customer_menu_enabled',1)==='1'?'checked':'' }}> مفعّل</label>
                    <label><input type="radio" name="customer_menu_enabled" value="0" {{ (string)$cmSetting('customer_menu_enabled',1)==='0'?'checked':'' }}> معطّل</label>
                </div>
            </div>

            <div>
                <label class="form-label">شخصية التصميم</label>
                <select class="form-input" name="customer_menu_experience_preset">
                    @foreach(['restaurant'=>'مطعم فاخر','cafe'=>'كافيه','bakery'=>'مخبز وحلويات','minimal'=>'Minimal'] as $v=>$l)
                        <option value="{{ $v }}" @selected((string)$cmSetting('customer_menu_experience_preset','cafe')===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">العنوان الرئيسي</label>
                <input class="form-input" name="customer_menu_title" value="{{ $cmSetting('customer_menu_title','اطلب مباشرة') }}" maxlength="100" data-cmv4-text="cmv4PreviewTitle">
            </div>

            <div class="wide">
                <label class="form-label">النص المساعد</label>
                <input class="form-input" name="customer_menu_subtitle" value="{{ $cmSetting('customer_menu_subtitle','اختر طلبك وسنجهزه لك') }}" maxlength="180" data-cmv4-text="cmv4PreviewSubtitle">
            </div>
        </div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="intro">
        <div class="cmv4-grid">
            <div>
                <label class="form-label">الواجهة الافتتاحية</label>
                <select class="form-input" name="customer_menu_intro_mode">
                    <option value="off" @selected((string)$cmSetting('customer_menu_intro_mode','first_visit')==='off')>معطلة</option>
                    <option value="first_visit" @selected((string)$cmSetting('customer_menu_intro_mode','first_visit')==='first_visit')>أول زيارة في الجلسة</option>
                    <option value="always" @selected((string)$cmSetting('customer_menu_intro_mode','first_visit')==='always')>كل مرة</option>
                </select>
            </div>

            <div>
                <label class="form-label">محاذاة النص</label>
                <select class="form-input" name="customer_menu_intro_align">
                    <option value="center" @selected((string)$cmSetting('customer_menu_intro_align','center')==='center')>وسط</option>
                    <option value="right" @selected((string)$cmSetting('customer_menu_intro_align','center')==='right')>يمين</option>
                </select>
            </div>

            <div>
                <label class="form-label">نص صغير فوق العنوان</label>
                <input class="form-input" name="customer_menu_intro_eyebrow" value="{{ $cmSetting('customer_menu_intro_eyebrow','') }}" maxlength="80" data-cmv4-text="cmv4PreviewEyebrow">
            </div>

            <div>
                <label class="form-label">عنوان الافتتاحية</label>
                <input class="form-input" name="customer_menu_intro_title" value="{{ $cmSetting('customer_menu_intro_title','أهلاً بك') }}" maxlength="120" data-cmv4-text="cmv4PreviewIntroTitle">
            </div>

            <div class="wide">
                <label class="form-label">وصف الافتتاحية</label>
                <input class="form-input" name="customer_menu_intro_subtitle" value="{{ $cmSetting('customer_menu_intro_subtitle','تجربة ألذ تبدأ من هنا') }}" maxlength="220" data-cmv4-text="cmv4PreviewIntroSubtitle">
            </div>

            <div>
                <label class="form-label">نص زر الدخول</label>
                <input class="form-input" name="customer_menu_intro_cta" value="{{ $cmSetting('customer_menu_intro_cta','اطلب الآن') }}" maxlength="60" data-cmv4-text="cmv4PreviewIntroCta">
            </div>

            <div>
                <label class="form-label">إظهار الشعار</label>
                <select class="form-input" name="customer_menu_intro_show_logo">
                    <option value="1" @selected((string)$cmSetting('customer_menu_intro_show_logo',1)==='1')>نعم</option>
                    <option value="0" @selected((string)$cmSetting('customer_menu_intro_show_logo',1)==='0')>لا</option>
                </select>
            </div>

            <div class="wide">
                <label class="form-label">صورة الافتتاحية</label>
                <input class="form-input" type="file" name="customer_menu_intro_image" accept="image/png,image/jpeg,image/webp" data-cmv4-image="cmv4PreviewIntro">
                @if($cmSetting('customer_menu_intro_image',''))
                    <label class="cmv4-remove"><input type="checkbox" name="remove_customer_menu_intro_image" value="1"> حذف الصورة الحالية</label>
                @endif
            </div>
        </div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="layout">
        <div class="cmv4-grid">
            <div><label class="form-label">اللون الرئيسي</label><input class="form-input" type="color" name="customer_menu_primary_color" value="{{ $cmSetting('customer_menu_primary_color','#704C34') }}"></div>
            <div><label class="form-label">Accent</label><input class="form-input" type="color" name="customer_menu_accent_color" value="{{ $cmSetting('customer_menu_accent_color','#D79A55') }}"></div>
            <div><label class="form-label">الخلفية</label><input class="form-input" type="color" name="customer_menu_background_color" value="{{ $cmSetting('customer_menu_background_color','#F7F3EE') }}"></div>
            <div><label class="form-label">Surface</label><input class="form-input" type="color" name="customer_menu_surface_color" value="{{ $cmSetting('customer_menu_surface_color','#FFFFFF') }}"></div>
            <div><label class="form-label">النص</label><input class="form-input" type="color" name="customer_menu_text_color" value="{{ $cmSetting('customer_menu_text_color','#241D18') }}"></div>
            <div><label class="form-label">النص الثانوي</label><input class="form-input" type="color" name="customer_menu_muted_color" value="{{ $cmSetting('customer_menu_muted_color','#7D746C') }}"></div>
            <div><label class="form-label">الحدود</label><input class="form-input" type="color" name="customer_menu_border_color" value="{{ $cmSetting('customer_menu_border_color','#E9E1D8') }}"></div>

            <div>
                <label class="form-label">عرض المحتوى</label>
                <input class="form-input" type="number" name="customer_menu_content_width" min="980" max="1700" value="{{ $cmSetting('customer_menu_content_width',1460) }}">
            </div>

            <div>
                <label class="form-label">المسافة بين الأقسام</label>
                <input class="form-input" type="number" name="customer_menu_section_gap" min="20" max="100" value="{{ $cmSetting('customer_menu_section_gap',42) }}">
            </div>

            <div>
                <label class="form-label">Radius المنتج</label>
                <input class="form-input" type="number" name="customer_menu_product_card_radius" min="0" max="40" value="{{ $cmSetting('customer_menu_product_card_radius',18) }}">
            </div>

            <div>
                <label class="form-label">ظل المنتجات</label>
                <select class="form-input" name="customer_menu_product_card_shadow">
                    @foreach(['none'=>'بدون','soft'=>'ناعم','deep'=>'عميق'] as $v=>$l)
                        <option value="{{ $v }}" @selected((string)$cmSetting('customer_menu_product_card_shadow','soft')===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">شكل الهيدر</label>
                <select class="form-input" name="customer_menu_nav_style">
                    <option value="floating" @selected((string)$cmSetting('customer_menu_nav_style','floating')==='floating')>Floating</option>
                    <option value="solid" @selected((string)$cmSetting('customer_menu_nav_style','floating')==='solid')>Solid</option>
                </select>
            </div>

            <div>
                <label class="form-label">الخط</label>
                <select class="form-input" name="customer_menu_font_family">
                    <option value="Cairo" @selected((string)$cmSetting('customer_menu_font_family','Cairo')==='Cairo')>Cairo</option>
                    <option value="Tajawal" @selected((string)$cmSetting('customer_menu_font_family','Cairo')==='Tajawal')>Tajawal</option>
                </select>
            </div>

            <div>
                <label class="form-label">نسبة صورة المنتج</label>
                <select class="form-input" name="customer_menu_card_image_ratio">
                    <option value="4-3" @selected((string)$cmSetting('customer_menu_card_image_ratio','4-3')==='4-3')>4:3</option>
                    <option value="1-1" @selected((string)$cmSetting('customer_menu_card_image_ratio','4-3')==='1-1')>1:1</option>
                    <option value="3-4" @selected((string)$cmSetting('customer_menu_card_image_ratio','4-3')==='3-4')>3:4</option>
                </select>
            </div>

            <div>
                <label class="form-label">ملاءمة الصور</label>
                <select class="form-input" name="customer_menu_image_fit">
                    <option value="cover" @selected((string)$cmSetting('customer_menu_image_fit','cover')==='cover')>Cover</option>
                    <option value="contain" @selected((string)$cmSetting('customer_menu_image_fit','cover')==='contain')>Contain</option>
                </select>
            </div>

            <div>
                <label class="form-label">إظهار Featured</label>
                <select class="form-input" name="customer_menu_show_featured">
                    <option value="1" @selected((string)$cmSetting('customer_menu_show_featured',1)==='1')>نعم</option>
                    <option value="0" @selected((string)$cmSetting('customer_menu_show_featured',1)==='0')>لا</option>
                </select>
            </div>

            <div>
                <label class="form-label">عنوان Featured</label>
                <input class="form-input" name="customer_menu_featured_title" value="{{ $cmSetting('customer_menu_featured_title','اختياراتنا لك') }}" maxlength="100">
            </div>

            <div>
                <label class="form-label">عدد Featured</label>
                <input class="form-input" type="number" name="customer_menu_featured_limit" min="2" max="8" value="{{ $cmSetting('customer_menu_featured_limit',4) }}">
            </div>

            <div class="wide">
                <label class="form-label">صورة الـHero</label>
                <input class="form-input" type="file" name="customer_menu_cover_image" accept="image/png,image/jpeg,image/webp">
                @if($cmSetting('customer_menu_cover_image',''))
                    <label class="cmv4-remove"><input type="checkbox" name="remove_customer_menu_cover_image" value="1"> حذف صورة الـHero الحالية</label>
                @endif
            </div>

            <div><label class="form-label">ارتفاع Hero</label><input class="form-input" type="number" name="customer_menu_hero_height" min="220" max="620" value="{{ $cmSetting('customer_menu_hero_height',430) }}"></div>
            <div><label class="form-label">Overlay %</label><input class="form-input" type="number" name="customer_menu_cover_overlay" min="0" max="90" value="{{ $cmSetting('customer_menu_cover_overlay',48) }}"></div>
        </div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="showcase">
        <div class="cmv4-grid">
            <div><label class="form-label">تشغيل Showcase</label><select class="form-input" name="customer_menu_showcase_enabled"><option value="1" @selected((string)$cmSetting('customer_menu_showcase_enabled',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_showcase_enabled',1)==='0')>لا</option></select></div>
            <div><label class="form-label">اللون الداكن</label><input class="form-input" type="color" name="customer_menu_showcase_dark_color" value="{{ $cmSetting('customer_menu_showcase_dark_color','#202427') }}"></div>
            <div><label class="form-label">النص الصغير</label><input class="form-input" name="customer_menu_showcase_eyebrow" value="{{ $cmSetting('customer_menu_showcase_eyebrow','تجربة الطلب الجديدة') }}" maxlength="100"></div>
            <div><label class="form-label">العنوان الكبير</label><input class="form-input" name="customer_menu_showcase_title" value="{{ $cmSetting('customer_menu_showcase_title','') }}" maxlength="140" placeholder="فارغ = عنوان المنيو"></div>
            <div class="wide"><label class="form-label">الوصف</label><input class="form-input" name="customer_menu_showcase_subtitle" value="{{ $cmSetting('customer_menu_showcase_subtitle','') }}" maxlength="260" placeholder="فارغ = الوصف الرئيسي"></div>
            <div><label class="form-label">زر الطلب</label><input class="form-input" name="customer_menu_showcase_primary_cta" value="{{ $cmSetting('customer_menu_showcase_primary_cta','اطلب الآن') }}" maxlength="60"></div>
            <div><label class="form-label">الزر الثانوي</label><input class="form-input" name="customer_menu_showcase_secondary_cta" value="{{ $cmSetting('customer_menu_showcase_secondary_cta','استكشف المنيو') }}" maxlength="60"></div>
            <div><label class="form-label">ارتفاع Showcase</label><input class="form-input" type="number" name="customer_menu_showcase_height" min="560" max="900" value="{{ $cmSetting('customer_menu_showcase_height',700) }}"></div>
            <div><label class="form-label">عمق الفاصل المائل</label><input class="form-input" type="number" name="customer_menu_showcase_diagonal_depth" min="40" max="180" value="{{ $cmSetting('customer_menu_showcase_diagonal_depth',105) }}"></div>
            <div><label class="form-label">بطاقات المعلومات الجانبية</label><select class="form-input" name="customer_menu_showcase_show_info_cards"><option value="1" @selected((string)$cmSetting('customer_menu_showcase_show_info_cards',1)==='1')>إظهار</option><option value="0" @selected((string)$cmSetting('customer_menu_showcase_show_info_cards',1)==='0')>إخفاء</option></select></div>
            <div><label class="form-label">شريط الفئات داخل Hero</label><select class="form-input" name="customer_menu_showcase_show_category_strip"><option value="1" @selected((string)$cmSetting('customer_menu_showcase_show_category_strip',1)==='1')>إظهار</option><option value="0" @selected((string)$cmSetting('customer_menu_showcase_show_category_strip',1)==='0')>إخفاء</option></select></div>
        </div>
        <div class="cmv4-note">المنتج الرئيسي يُختار تلقائياً من أول صنف متاح لديه صورة. الخلفية تستخدم صورة Hero الحالية، لذلك تقدر تغيرها من تبويب التصميم بدون تعديل الكود.</div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="team">
        <div class="cmv4-grid">
            <div>
                <label class="form-label">إظهار طاقم العمل</label>
                <select class="form-input" name="customer_menu_show_team">
                    <option value="1" @selected((string)$cmSetting('customer_menu_show_team',0)==='1')>نعم</option>
                    <option value="0" @selected((string)$cmSetting('customer_menu_show_team',0)==='0')>لا</option>
                </select>
            </div>

            <div>
                <label class="form-label">عدد الموظفين</label>
                <input class="form-input" type="number" name="customer_menu_team_limit" min="1" max="12" value="{{ $cmSetting('customer_menu_team_limit',6) }}">
            </div>

            <div>
                <label class="form-label">عنوان القسم</label>
                <input class="form-input" name="customer_menu_team_title" value="{{ $cmSetting('customer_menu_team_title','طاقمنا') }}" maxlength="100">
            </div>

            <div>
                <label class="form-label">الوصف</label>
                <input class="form-input" name="customer_menu_team_subtitle" value="{{ $cmSetting('customer_menu_team_subtitle','الوجوه التي تصنع تجربتك') }}" maxlength="180">
            </div>

            <div>
                <label class="form-label">شكل البطاقة</label>
                <select class="form-input" name="customer_menu_team_card_style">
                    <option value="portrait" @selected((string)$cmSetting('customer_menu_team_card_style','portrait')==='portrait')>Portrait</option>
                    <option value="compact" @selected((string)$cmSetting('customer_menu_team_card_style','portrait')==='compact')>Compact</option>
                </select>
            </div>

            <div>
                <label class="form-label">إظهار Footer</label>
                <select class="form-input" name="customer_menu_show_footer">
                    <option value="1" @selected((string)$cmSetting('customer_menu_show_footer',1)==='1')>نعم</option>
                    <option value="0" @selected((string)$cmSetting('customer_menu_show_footer',1)==='0')>لا</option>
                </select>
            </div>

            <div class="wide">
                <label class="form-label">نص Footer</label>
                <input class="form-input" name="customer_menu_footer_text" value="{{ $cmSetting('customer_menu_footer_text','') }}" maxlength="220">
            </div>
        </div>

        <div class="cmv4-note">
            طاقم العمل يُسحب تلقائياً من الموظفين النشطين المرتبطين بنفس الفرع، ويستخدم صورة الموظف والمسمى الوظيفي من ملف الموظف.
        </div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="ordering">
        <div class="cmv4-grid">
            <div><label class="form-label">إظهار البحث</label><select class="form-input" name="customer_menu_show_search"><option value="1" @selected((string)$cmSetting('customer_menu_show_search',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_show_search',1)==='0')>لا</option></select></div>
            <div><label class="form-label">إظهار الفئات</label><select class="form-input" name="customer_menu_show_categories"><option value="1" @selected((string)$cmSetting('customer_menu_show_categories',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_show_categories',1)==='0')>لا</option></select></div>
            <div><label class="form-label">إظهار الوصف</label><select class="form-input" name="customer_menu_show_descriptions"><option value="1" @selected((string)$cmSetting('customer_menu_show_descriptions',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_show_descriptions',1)==='0')>لا</option></select></div>
            <div><label class="form-label">داخل المطعم</label><select class="form-input" name="customer_menu_allow_dine_in"><option value="1" @selected((string)$cmSetting('customer_menu_allow_dine_in',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_allow_dine_in',1)==='0')>لا</option></select></div>
            <div><label class="form-label">سفري</label><select class="form-input" name="customer_menu_allow_takeaway"><option value="1" @selected((string)$cmSetting('customer_menu_allow_takeaway',1)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_allow_takeaway',1)==='0')>لا</option></select></div>
            <div><label class="form-label">استلام من الباب</label><select class="form-input" name="customer_menu_allow_outdoor"><option value="1" @selected((string)$cmSetting('customer_menu_allow_outdoor',0)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_allow_outdoor',0)==='0')>لا</option></select></div>
            <div><label class="form-label">وقت تحضير افتراضي (دقائق)</label><input type="number" min="1" max="600" class="form-input" name="customer_menu_default_prep_minutes" value="{{ $cmSetting('customer_menu_default_prep_minutes', 12) }}"><small style="color:var(--text-muted)">يُستخدم للمنتجات التي لم تحدد لها وقت تحضير خاص.</small></div>
            <div><label class="form-label">دقائق إضافية لكل طلب بالطابور</label><input type="number" min="0" max="60" class="form-input" name="customer_menu_queue_minutes_per_order" value="{{ $cmSetting('customer_menu_queue_minutes_per_order', 4) }}"><small style="color:var(--text-muted)">كل طلب نشط حاليًا بالمطبخ بيضيف هالوقت لتقدير انتظار الزبون الجديد.</small></div>
            <div><label class="form-label">توصيل</label><select class="form-input" name="customer_menu_allow_delivery"><option value="1" @selected((string)$cmSetting('customer_menu_allow_delivery',0)==='1')>نعم</option><option value="0" @selected((string)$cmSetting('customer_menu_allow_delivery',0)==='0')>لا</option></select></div>
            <div class="wide"><label class="form-label">نص زر إرسال الطلب</label><input class="form-input" name="customer_menu_checkout_button_text" value="{{ $cmSetting('customer_menu_checkout_button_text','إرسال الطلب') }}" maxlength="80"></div>
        </div>
    </div>

    <div class="cmv4-pane" data-cmv4-pane="status">
        <div class="cmv4-grid">
            <div><label class="form-label">Toast</label><select class="form-input" name="customer_menu_status_toast_enabled"><option value="1" @selected((string)$cmSetting('customer_menu_status_toast_enabled',1)==='1')>مفعّل</option><option value="0" @selected((string)$cmSetting('customer_menu_status_toast_enabled',1)==='0')>معطّل</option></select></div>
            <div><label class="form-label">الصوت</label><select class="form-input" name="customer_menu_status_sound_enabled"><option value="1" @selected((string)$cmSetting('customer_menu_status_sound_enabled',1)==='1')>مفعّل</option><option value="0" @selected((string)$cmSetting('customer_menu_status_sound_enabled',1)==='0')>معطّل</option></select></div>

            @foreach([
                'received'=>['تم استلام طلبك','وصل طلبك للفرع وهو بانتظار القبول.'],
                'accepted'=>['تم قبول الطلب','تم قبول طلبك وسيدخل التحضير الآن.'],
                'preparing'=>['طلبك قيد التحضير','فريقنا يجهز طلبك الآن.'],
                'ready'=>['طلبك جاهز 🎉','طلبك جاهز الآن للاستلام.'],
                'completed'=>['تم تسليم الطلب','شكراً لاختيارك لنا.'],
                'cancelled'=>['تم إلغاء الطلب','تم إلغاء الطلب.'],
            ] as $key=>$defaults)
                <div>
                    <label class="form-label">{{ $defaults[0] }}</label>
                    <input class="form-input" name="customer_menu_status_{{ $key }}_title" value="{{ $cmSetting('customer_menu_status_'.$key.'_title',$defaults[0]) }}" maxlength="100">
                </div>
                <div>
                    <label class="form-label">رسالة {{ $defaults[0] }}</label>
                    <input class="form-input" name="customer_menu_status_{{ $key }}_message" value="{{ $cmSetting('customer_menu_status_'.$key.'_message',$defaults[1]) }}" maxlength="180">
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
.cmv4{margin:1rem 0 1.4rem;padding:1rem;border:1px solid var(--border,#e5e7eb);border-radius:18px;background:#fff}
.cmv4-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem}
.cmv4-head span{display:block;color:#8a6d45;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}
.cmv4-head h3{margin:.2rem 0 0;font-size:1.08rem}
.cmv4-head p{margin:.35rem 0 0;color:#6b7280;font-size:.78rem;line-height:1.7}
.cmv4-preview{overflow:hidden;border:1px solid #e9e2da;border-radius:18px;background:var(--bg);box-shadow:0 16px 50px rgba(20,15,12,.08)}
.cmv4-preview-intro{min-height:240px;display:grid;place-items:center;background:#1d1713 center/cover no-repeat;color:#fff;text-align:center}
.cmv4-preview-intro>div{max-width:520px;padding:1.5rem}
.cmv4-preview-intro img{width:56px;height:56px;object-fit:contain;margin-bottom:.6rem;border-radius:14px;background:rgba(255,255,255,.12);padding:4px}
.cmv4-preview-intro small,.cmv4-preview-intro strong,.cmv4-preview-intro span{display:block}
.cmv4-preview-intro small{font-size:.65rem;opacity:.8}
.cmv4-preview-intro strong{margin-top:.35rem;font-size:2rem;font-weight:900}
.cmv4-preview-intro span{margin-top:.35rem;font-size:.72rem;opacity:.8}
.cmv4-preview-intro button{margin-top:.8rem;border:0;border-radius:999px;padding:.55rem 1rem;background:#fff;color:#221a15;font-weight:900}
.cmv4-preview-menu{padding:.8rem;background:var(--bg)}
.cmv4-preview-menu header{display:flex;align-items:center;justify-content:space-between;padding:.45rem .55rem;background:var(--s);border-radius:12px}
.cmv4-preview-menu header>div{display:flex;align-items:center;gap:.45rem}
.cmv4-preview-menu header img{width:32px;height:32px;object-fit:contain}
.cmv4-preview-menu header b{font-size:.68rem}
.cmv4-preview-menu header span{font-size:.55rem;color:var(--m)}
.cmv4-preview-menu .hero{min-height:150px;display:flex;align-items:flex-end;margin-top:.6rem;padding:1rem;border-radius:14px;background:linear-gradient(135deg,var(--p),var(--a));background-position:center;background-size:cover;color:#fff}
.cmv4-preview-menu .hero small,.cmv4-preview-menu .hero strong,.cmv4-preview-menu .hero span{display:block}
.cmv4-preview-menu .hero small{font-size:.5rem;opacity:.8}.cmv4-preview-menu .hero strong{font-size:1.4rem;font-weight:900}.cmv4-preview-menu .hero span{font-size:.56rem;opacity:.82}
.cmv4-preview-menu .chips{display:flex;gap:.35rem;margin:.6rem 0}.cmv4-preview-menu .chips i{font-style:normal;padding:.3rem .55rem;border-radius:999px;background:var(--s);font-size:.48rem}.cmv4-preview-menu .chips i:first-child{background:var(--p);color:#fff}
.cmv4-preview-menu .cards{display:grid;grid-template-columns:1fr 1fr .8fr;gap:.45rem}
.cmv4-preview-menu article,.cmv4-preview-menu aside{padding:.45rem;border-radius:11px;background:var(--s)}
.cmv4-preview-menu article>div{aspect-ratio:4/3;border-radius:8px;background:linear-gradient(135deg,#e5d5c3,#caa172)}
.cmv4-preview-menu article b,.cmv4-preview-menu article small,.cmv4-preview-menu article strong,.cmv4-preview-menu aside b,.cmv4-preview-menu aside small,.cmv4-preview-menu aside strong{display:block}
.cmv4-preview-menu article b,.cmv4-preview-menu aside b{margin-top:.35rem;font-size:.52rem}.cmv4-preview-menu article small,.cmv4-preview-menu aside small{margin-top:.2rem;color:var(--m);font-size:.43rem}.cmv4-preview-menu article strong,.cmv4-preview-menu aside strong{margin-top:.4rem;color:var(--p);font-size:.48rem}
.cmv4-tabs{display:flex;gap:.4rem;margin:1rem 0 .75rem;overflow-x:auto}.cmv4-tabs button{flex:0 0 auto;border:1px solid #e5e7eb;border-radius:999px;background:#fff;padding:.45rem .75rem;font-size:.7rem;font-weight:800}.cmv4-tabs button.active{background:#111827;color:#fff;border-color:#111827}
.cmv4-pane{display:none}.cmv4-pane.active{display:block}
.cmv4-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.cmv4-grid .wide{grid-column:1/-1}
.cmv4-note{margin-top:.8rem;padding:.7rem .8rem;border-radius:10px;background:#f8fafc;color:#64748b;font-size:.7rem;line-height:1.7}
.cmv4-remove{display:block;margin-top:.35rem;color:#b42318;font-size:.68rem}
@media(max-width:760px){.cmv4-head{flex-direction:column}.cmv4-grid{grid-template-columns:1fr}.cmv4-grid .wide{grid-column:auto}.cmv4-preview-menu .cards{grid-template-columns:1fr 1fr}.cmv4-preview-menu aside{display:none}}
</style>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('cmV4Builder');if(!root)return;
    root.querySelectorAll('[data-cmv4-tab]').forEach(btn=>btn.addEventListener('click',()=>{
        root.querySelectorAll('[data-cmv4-tab]').forEach(x=>x.classList.toggle('active',x===btn));
        root.querySelectorAll('[data-cmv4-pane]').forEach(p=>p.classList.toggle('active',p.dataset.cmv4Pane===btn.dataset.cmv4Tab));
    }));
    root.querySelectorAll('[data-cmv4-text]').forEach(input=>input.addEventListener('input',()=>{
        const el=document.getElementById(input.dataset.cmv4Text);if(el)el.textContent=input.value;
    }));
    root.querySelectorAll('[data-cmv4-image]').forEach(input=>input.addEventListener('change',()=>{
        const file=input.files?.[0];if(!file)return;
        const target=document.getElementById(input.dataset.cmv4Image);if(!target)return;
        const reader=new FileReader();reader.onload=e=>target.style.backgroundImage=`linear-gradient(rgba(10,8,7,.46),rgba(10,8,7,.46)),url('${e.target.result}')`;reader.readAsDataURL(file);
    }));
});
</script>
