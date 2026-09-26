@extends('layouts.app')

@section('title', 'طلبات الكيك')
@section('page-title', 'طلبات الكيك الخاصة')

@section('content')
@php
    $statusLabels = collect(\App\Enums\CakeOrderStatus::cases())
        ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
        ->all();

    $legacyStatusLabels = [
        'pending_deposit' => 'بانتظار دفع العربون',
        'deposit_paid' => 'تم دفع العربون',
        'in_decoration' => 'قيد التزيين',
        'dispatched_to_branch' => 'تم الإرسال إلى الفرع',
        'received_at_branch' => 'تم الاستلام في الفرع',
        'ready_for_pickup' => 'جاهز لاستلام العميل',
        'delivered' => 'مكتمل',
        'canceled' => 'ملغى',
    ];

    $statusLabels = array_merge($legacyStatusLabels, $statusLabels);

    $statusClasses = [
        'draft' => 'neutral',
        'pending' => 'warning',
        'in_progress' => 'progress',
        'ready' => 'ready',
        'completed' => 'success',
        'cancelled' => 'danger',

        // Legacy display tones until every environment runs the migration.
        'pending_deposit' => 'warning',
        'deposit_paid' => 'warning',
        'pending_factory_review' => 'warning',
        'modification_requested' => 'warning',
        'accepted' => 'progress',
        'scheduled' => 'progress',
        'in_preparation' => 'progress',
        'decorating' => 'progress',
        'in_decoration' => 'progress',
        'quality_check' => 'progress',
        'sent_to_branch' => 'ready',
        'dispatched_to_branch' => 'ready',
        'received_by_branch' => 'ready',
        'received_at_branch' => 'ready',
        'ready_for_customer' => 'ready',
        'ready_for_pickup' => 'ready',
        'delivered' => 'success',
        'delayed' => 'progress',
        'issue_open' => 'progress',
        'rejected' => 'danger',
        'canceled' => 'danger',
    ];

    $terminalStatuses = [
        'completed',
        'delivered',
        'rejected',
        'cancelled',
        'canceled',
    ];

    $cakeValueLabels = [
        'chocolate' => 'شوكولاتة',
        'vanilla' => 'فانيلا',
        'red_velvet' => 'ريد فيلفت',
        'strawberry' => 'فراولة',
        'fruit' => 'فواكه',
        'caramel' => 'كراميل',
        'coffee' => 'قهوة',
        'oreo' => 'أوريو',
        'lotus' => 'لوتس',
        'custom' => 'حسب الطلب',
        'small' => 'صغير',
        'medium' => 'متوسط',
        'large' => 'كبير',
        'x_large' => 'كبير جداً',
        'extra_large' => 'كبير جداً',
        'mini' => 'ميني',
    ];

    $formatCakeValue = static function ($value) use ($cakeValueLabels): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
            return $value;
        }

        return $cakeValueLabels[strtolower($value)]
            ?? str_replace('_', ' ', $value);
    };

    $hasFilters = request()->filled('q')
        || request()->filled('status')
        || request()->filled('date_from')
        || request()->filled('date_to');
@endphp

<div class="cake-page">
    <header class="cake-hero">
        <div>
            <span class="cake-eyebrow">إدارة الطلبات المخصصة</span>
            <h1>طلبات الكيك الخاصة</h1>
            <p>رتّب الأولويات وتابع التسليم والإنتاج من شاشة واحدة واضحة.</p>
        </div>

        <div class="cake-hero-actions">
            @can('reports.view')
                <a
                    href="{{ route('reports.show', 'cake-production') }}"
                    class="btn btn-outline cake-report-btn"
                >
                    <span aria-hidden="true">▤</span>
                    تقرير إنتاج الكيك الموحد
                </a>
            @endcan

            @can('cake_orders.create')
                <a href="{{ route('cake-orders.create') }}" class="btn btn-gold cake-create-btn">
                    <span aria-hidden="true">＋</span>
                    طلب كيك جديد
                </a>
            @endcan
        </div>
    </header>

    @if(session('success'))
        <div class="cake-alert success" role="status">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="cake-alert danger" role="alert">{{ session('error') }}</div>
    @endif

    <section class="cake-summary" aria-label="ملخص طلبات الكيك">
        <div class="summary-card total">
            <span class="summary-icon" aria-hidden="true">▦</span>
            <div>
                <small>إجمالي الطلبات</small>
                <strong>{{ $summary['total'] ?? $orders->total() }}</strong>
            </div>
        </div>

        <div class="summary-card overdue">
            <span class="summary-icon" aria-hidden="true">!</span>
            <div>
                <small>متأخرة وتحتاج تدخلاً</small>
                <strong>{{ $summary['overdue'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="summary-card today">
            <span class="summary-icon" aria-hidden="true">●</span>
            <div>
                <small>تسليم اليوم</small>
                <strong>{{ $summary['due_today'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="summary-card soon">
            <span class="summary-icon" aria-hidden="true">↗</span>
            <div>
                <small>خلال 3 أيام</small>
                <strong>{{ $summary['due_soon'] ?? 0 }}</strong>
            </div>
        </div>
    </section>

    <section class="card cake-filter-card" aria-label="البحث والتصفية">
        <form method="GET" action="{{ route('cake-orders.index') }}" class="cake-filter-grid">
            <label class="filter-field search-field">
                <span>بحث سريع</span>
                <span class="input-with-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
                        <path d="m16.5 16.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        class="form-input"
                        placeholder="رقم الطلب، اسم العميل أو الهاتف"
                    >
                </span>
            </label>

            <label class="filter-field">
                <span>الحالة</span>
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    @foreach(\App\Enums\CakeOrderStatus::workflowCases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="filter-field">
                <span>من تاريخ</span>
                <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
            </label>

            <label class="filter-field">
                <span>إلى تاريخ</span>
                <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
            </label>

            <div class="filter-buttons">
                <button class="btn btn-gold" type="submit">عرض النتائج</button>

                @if($hasFilters)
                    <a href="{{ route('cake-orders.index') }}" class="btn btn-ghost">مسح</a>
                @endif
            </div>
        </form>

        @if($hasFilters)
            <div class="active-filters">
                <span>التصفية مفعّلة</span>
                <strong>{{ $orders->total() }} نتيجة</strong>
            </div>
        @endif
    </section>

    <div class="cake-list-heading">
        <div>
            <h2>قائمة الطلبات</h2>
            <span>{{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} من {{ $orders->total() }}</span>
        </div>
    </div>

    <section class="cake-grid">
        @forelse($orders as $order)
            @php
                $statusValue = $order->status instanceof \BackedEnum
                    ? $order->status->value
                    : (string) $order->status;
                $statusLabel = $statusLabels[$statusValue] ?? 'حالة غير معروفة';
                $statusTone = $statusClasses[$statusValue] ?? 'neutral';

                $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $imageAttachments = $order->attachments->filter(function ($attachment) use ($imageExtensions) {
                    $extension = strtolower(pathinfo((string) $attachment->file_path, PATHINFO_EXTENSION));

                    return in_array($extension, $imageExtensions, true);
                });

                $cakeAttachment = $imageAttachments->firstWhere('attachment_type', 'final_cake_image')
                    ?? $imageAttachments->firstWhere('attachment_type', 'customer_design')
                    ?? $imageAttachments->firstWhere('attachment_type', 'reference_image')
                    ?? $imageAttachments->first();

                $cakeImage = null;

                if ($cakeAttachment?->file_path) {
                    $imagePath = ltrim((string) $cakeAttachment->file_path, '/');
                    $cakeImage = str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')
                        ? $imagePath
                        : asset(str_starts_with($imagePath, 'storage/') ? $imagePath : 'storage/' . $imagePath);
                }

                $displayPrice = (float) ($order->net_price ?? $order->total_price ?? 0);
                $cakeType = $formatCakeValue($order->cake_type);
                $cakeSize = $formatCakeValue($order->cake_size);
                $isTerminal = in_array($statusValue, $terminalStatuses, true);
                $daysToDelivery = $order->required_date
                    ? (int) today()->diffInDays($order->required_date, false)
                    : null;

                if ($isTerminal || $daysToDelivery === null) {
                    $deadlineTone = 'muted';
                    $deadlineLabel = $order->required_date ? 'موعد التسليم' : 'الموعد غير محدد';
                } elseif ($daysToDelivery < 0) {
                    $deadlineTone = 'late';
                    $deadlineLabel = 'متأخر ' . abs($daysToDelivery) . ' يوم';
                } elseif ($daysToDelivery === 0) {
                    $deadlineTone = 'today';
                    $deadlineLabel = 'تسليم اليوم';
                } elseif ($daysToDelivery === 1) {
                    $deadlineTone = 'soon';
                    $deadlineLabel = 'تسليم غداً';
                } elseif ($daysToDelivery <= 3) {
                    $deadlineTone = 'soon';
                    $deadlineLabel = 'متبقي ' . $daysToDelivery . ' أيام';
                } else {
                    $deadlineTone = 'muted';
                    $deadlineLabel = 'متبقي ' . $daysToDelivery . ' يوم';
                }
            @endphp

            <article class="cake-order-card {{ $cakeImage ? 'has-image' : 'no-image' }}">
                @if($cakeImage)
                    <a class="cake-visual" href="{{ route('cake-orders.show', $order) }}" tabindex="-1" aria-hidden="true">
                        <img src="{{ $cakeImage }}" alt="" loading="lazy">
                    </a>
                @else
                    <div class="cake-visual cake-visual-placeholder" aria-hidden="true">
                        <span class="placeholder-cake">♨</span>
                        <span>{{ $cakeType ?? 'كيك خاص' }}</span>
                    </div>
                @endif

                <div class="cake-card-content">
                    <div class="cake-card-head">
                        <div>
                            <a href="{{ route('cake-orders.show', $order) }}" class="order-number">
                                {{ $order->order_number ?? 'طلب رقم ' . $order->id }}
                            </a>
                            <span class="created-time">{{ $order->created_at?->diffForHumans() }}</span>
                        </div>

                        <span class="status-pill {{ $statusTone }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="customer-line">
                        <span class="customer-avatar">
                            {{ mb_substr($order->customer?->name ?? 'ع', 0, 1) }}
                        </span>
                        <div>
                            <strong>{{ $order->customer?->name ?? 'عميل غير محدد' }}</strong>
                            @if($order->customer?->phone)
                                <a href="tel:{{ $order->customer->phone }}" onclick="event.stopPropagation()">
                                    {{ $order->customer->phone }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="cake-specs">
                        @if($cakeType)
                            <span>{{ $cakeType }}</span>
                        @endif
                        @if($cakeSize)
                            <span>{{ $cakeSize }}</span>
                        @endif
                        @if($order->persons_count)
                            <span>{{ $order->persons_count }} شخص</span>
                        @endif
                        @if($order->cake_weight)
                            <span>{{ rtrim(rtrim(number_format((float) $order->cake_weight, 2), '0'), '.') }} كغم</span>
                        @endif
                        @if(! $cakeType && ! $cakeSize && ! $order->persons_count && ! $order->cake_weight)
                            <span class="muted-spec">لا توجد مواصفات مختصرة</span>
                        @endif
                    </div>

                    <div class="delivery-box {{ $deadlineTone }}">
                        <div>
                            <small>{{ $deadlineLabel }}</small>
                            <strong>
                                {{ $order->required_date?->format('Y-m-d') ?? '—' }}
                                @if($order->required_time)
                                    <span>· {{ substr((string) $order->required_time, 0, 5) }}</span>
                                @endif
                            </strong>
                        </div>
                        <span class="delivery-icon" aria-hidden="true">◷</span>
                    </div>

                    <div class="branch-price-row">
                        <div class="branch-flow">
                            <small>مسار الطلب</small>
                            <strong>
                                {{ $order->originBranch?->name ?? 'فرع غير محدد' }}
                                @if($order->factory && $order->factory_location_id !== $order->origin_branch_id)
                                    <span aria-hidden="true">←</span>
                                    {{ $order->factory->name }}
                                @endif
                            </strong>
                        </div>
                        <div class="cake-price">
                            <small>الإجمالي</small>
                            <strong>₪{{ number_format($displayPrice, 2) }}</strong>
                        </div>
                    </div>

                    <div class="cake-card-actions">
                        <a href="{{ route('cake-orders.show', $order) }}" class="btn btn-gold btn-sm">
                            فتح الطلب
                        </a>

                        @if($statusValue === 'draft')
                            @can('cake_orders.edit')
                                <a href="{{ route('cake-orders.edit', $order) }}" class="btn btn-outline btn-sm">
                                    تعديل
                                </a>
                            @endcan
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="cake-empty">
                <span aria-hidden="true">⌕</span>
                <strong>لا توجد طلبات مطابقة</strong>
                <p>غيّر عوامل التصفية أو أنشئ طلب كيك جديدًا.</p>
                @if($hasFilters)
                    <a href="{{ route('cake-orders.index') }}" class="btn btn-outline btn-sm">عرض كل الطلبات</a>
                @endif
            </div>
        @endforelse
    </section>

    @if($orders->hasPages())
        <div class="cake-pagination">{{ $orders->links() }}</div>
    @endif
</div>

<style>
.cake-page{display:grid;gap:1rem}.cake-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.4rem 0}.cake-eyebrow{display:block;margin-bottom:.25rem;color:var(--gold);font-size:.72rem;font-weight:800}.cake-hero h1{margin:0;color:var(--text);font-size:1.35rem;font-weight:900}.cake-hero p{margin:.35rem 0 0;color:var(--text-muted);font-size:.82rem}.cake-hero-actions{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}.cake-create-btn,.cake-report-btn{min-height:44px;white-space:nowrap}.cake-alert{padding:.85rem 1rem;border:1px solid;border-radius:12px;font-size:.84rem}.cake-alert.success{color:#166534;background:#f0fdf4;border-color:#bbf7d0}.cake-alert.danger{color:#991b1b;background:#fef2f2;border-color:#fecaca}
.cake-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.summary-card{display:flex;align-items:center;gap:.75rem;min-height:86px;padding:.85rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:0 2px 8px rgba(15,23,42,.035)}.summary-card .summary-icon{display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border-radius:11px;font-weight:900}.summary-card div{display:grid;gap:.15rem}.summary-card small{color:var(--text-muted);font-size:.7rem}.summary-card strong{font-size:1.25rem}.summary-card.total .summary-icon{color:#b77900;background:#fff7db}.summary-card.overdue .summary-icon{color:#dc2626;background:#fee2e2}.summary-card.today .summary-icon{color:#0284c7;background:#e0f2fe}.summary-card.soon .summary-icon{color:#7c3aed;background:#ede9fe}
.cake-filter-card{padding:1rem;border-radius:14px}.cake-filter-grid{display:grid;grid-template-columns:minmax(220px,1.5fr) repeat(3,minmax(140px,1fr)) auto;gap:.75rem;align-items:end}.filter-field{display:grid;gap:.38rem;color:var(--text-muted);font-size:.7rem;font-weight:700}.input-with-icon{position:relative;display:block}.input-with-icon svg{position:absolute;right:.8rem;top:50%;z-index:1;transform:translateY(-50%);color:var(--text-muted);pointer-events:none}.input-with-icon .form-input{padding-right:2.45rem}.filter-buttons{display:flex;gap:.45rem}.filter-buttons .btn{min-height:42px}.active-filters{display:flex;justify-content:space-between;margin-top:.8rem;padding-top:.75rem;border-top:1px dashed var(--border);color:var(--text-muted);font-size:.72rem}.active-filters strong{color:var(--gold)}
.cake-list-heading{display:flex;align-items:center;justify-content:space-between;margin-top:.2rem}.cake-list-heading h2{display:inline;margin:0 .3rem 0 0;font-size:1rem}.cake-list-heading span{color:var(--text-muted);font-size:.72rem}.cake-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.cake-order-card{display:grid;grid-template-columns:124px minmax(0,1fr);min-width:0;background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 3px 12px rgba(15,23,42,.045);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}.cake-order-card:hover{transform:translateY(-2px);border-color:rgba(212,160,23,.38);box-shadow:0 10px 24px rgba(15,23,42,.09)}.cake-visual{display:block;min-height:100%;overflow:hidden;background:#faf7ee}.cake-visual img{width:100%;height:100%;display:block;object-fit:cover;transition:transform .25s ease}.cake-order-card:hover .cake-visual img{transform:scale(1.035)}.cake-visual-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4rem;color:#9b7a22;background:linear-gradient(145deg,#fffdf7,#f4ecd9);font-size:.7rem;text-align:center}.placeholder-cake{display:grid;place-items:center;width:48px;height:48px;border-radius:15px;color:#b77900;background:rgba(255,255,255,.8);font-size:1.6rem;box-shadow:0 6px 18px rgba(146,105,16,.1)}
.cake-card-content{display:grid;gap:.7rem;padding:.85rem;min-width:0}.cake-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}.cake-card-head>div{display:grid;min-width:0}.order-number{color:var(--gold);font-size:.84rem;font-weight:900;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.order-number:hover{text-decoration:underline}.created-time{margin-top:.08rem;color:var(--text-muted);font-size:.61rem}.status-pill{display:inline-flex;align-items:center;max-width:48%;padding:.28rem .5rem;border-radius:999px;font-size:.62rem;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.status-pill.neutral{color:#64748b;background:#f1f5f9}.status-pill.warning{color:#9a6700;background:#fff3c4}.status-pill.info{color:#1d4ed8;background:#dbeafe}.status-pill.purple{color:#7c3aed;background:#ede9fe}.status-pill.progress{color:#c2410c;background:#ffedd5}.status-pill.pink{color:#be185d;background:#fce7f3}.status-pill.quality{color:#0e7490;background:#cffafe}.status-pill.ready,.status-pill.success{color:#15803d;background:#dcfce7}.status-pill.delivery{color:#1d4ed8;background:#dbeafe}.status-pill.received{color:#0f766e;background:#ccfbf1}.status-pill.danger{color:#b91c1c;background:#fee2e2}
.customer-line{display:flex;align-items:center;gap:.55rem}.customer-avatar{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:11px;color:#a16207;background:#fef3c7;font-weight:900}.customer-line>div{display:grid;min-width:0}.customer-line strong{font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.customer-line a{margin-top:.05rem;color:var(--text-muted);font-size:.65rem;text-decoration:none;direction:ltr;text-align:right}.cake-specs{display:flex;flex-wrap:wrap;gap:.3rem}.cake-specs span{padding:.22rem .45rem;border:1px solid var(--border);border-radius:7px;color:var(--text-muted);background:var(--off-white);font-size:.62rem}.cake-specs .muted-spec{border-style:dashed;background:transparent}
.delivery-box{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .65rem;border:1px solid;border-radius:10px}.delivery-box>div{display:grid;gap:.15rem}.delivery-box small{font-size:.62rem;font-weight:800}.delivery-box strong{font-size:.73rem}.delivery-box strong span{font-weight:600}.delivery-box.muted{color:#475569;background:#f8fafc;border-color:#e2e8f0}.delivery-box.soon{color:#9a6700;background:#fffbeb;border-color:#fde68a}.delivery-box.today{color:#0369a1;background:#f0f9ff;border-color:#bae6fd}.delivery-box.late{color:#b91c1c;background:#fef2f2;border-color:#fecaca}.delivery-icon{font-size:1.15rem}
.branch-price-row{display:flex;align-items:flex-end;justify-content:space-between;gap:.75rem;padding-top:.55rem;border-top:1px dashed var(--border)}.branch-flow,.cake-price{display:grid;gap:.1rem;min-width:0}.branch-flow small,.cake-price small{color:var(--text-muted);font-size:.6rem}.branch-flow strong{font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.branch-flow strong span{color:var(--gold)}.cake-price{text-align:left;white-space:nowrap}.cake-price strong{font-size:.9rem}.cake-card-actions{display:flex;gap:.45rem}.cake-card-actions .btn:first-child{flex:1}.cake-card-actions .btn{min-height:34px}
.cake-empty{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:280px;padding:2rem;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:16px}.cake-empty>span{display:grid;place-items:center;width:56px;height:56px;margin-bottom:.7rem;border-radius:18px;color:var(--gold);background:#fff7db;font-size:1.8rem}.cake-empty strong{font-size:1rem}.cake-empty p{margin:.3rem 0 1rem;color:var(--text-muted);font-size:.76rem}.cake-pagination{margin-top:.25rem}
@media(max-width:1250px){.cake-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cake-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.search-field{grid-column:span 2}.filter-buttons{grid-column:span 2}.filter-buttons .btn:first-child{flex:1}}
@media(max-width:800px){.cake-hero{align-items:flex-start;flex-direction:column}.cake-hero-actions{width:100%}.cake-create-btn,.cake-report-btn{flex:1;justify-content:center}.cake-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.cake-grid{grid-template-columns:1fr}.cake-order-card{grid-template-columns:108px minmax(0,1fr)}}
@media(max-width:560px){.cake-summary{grid-template-columns:1fr 1fr}.summary-card{min-height:76px;padding:.65rem}.summary-card .summary-icon{width:32px;height:32px;flex-basis:32px}.summary-card small{font-size:.61rem}.cake-filter-grid{grid-template-columns:1fr}.search-field,.filter-buttons{grid-column:auto}.cake-order-card{grid-template-columns:1fr}.cake-visual{height:150px;min-height:0}.cake-visual-placeholder{height:92px}.cake-card-actions .btn{flex:1}.cake-hero h1{font-size:1.18rem}}
</style>
@endsection
