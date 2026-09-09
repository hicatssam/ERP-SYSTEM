<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\KitchenTicket;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\SpecialCakeOrder;
use App\Models\StockRequest;
use App\Services\ModuleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $now = now();
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth();

        /** @var ModuleService $modules */
        $modules = app(ModuleService::class);

        /*
        |--------------------------------------------------------------------------
        | Scope
        |--------------------------------------------------------------------------
        |
        | المؤشرات العامة تبقى على نفس منطق النظام الحالي:
        | Admin = جميع المواقع
        | باقي المستخدمين = الموقع الرئيسي
        |
        | الوحدات التي تملك view_all_locations لها Scope مستقل أدناه.
        |
        */

        $baseLocationIds = $user->isAdmin()
            ? Location::query()->active()->pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        $baseLocationIds = $baseLocationIds
            ->map(fn ($id) => (int) $id)
            ->values();

        $allActiveLocationIds = null;

        $allLocations = static function () use (&$allActiveLocationIds): Collection {
            if ($allActiveLocationIds === null) {
                $allActiveLocationIds = Location::query()
                    ->active()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();
            }

            return $allActiveLocationIds;
        };

        $scopeFor = static function (
            bool $canViewAll
        ) use (
            $user,
            $baseLocationIds,
            $allLocations
        ): Collection {
            return ($user->isAdmin() || $canViewAll)
                ? $allLocations()
                : $baseLocationIds;
        };

        $stats = [
            /*
             * Core
             */
            'orders_today' => 0,
            'sales_today' => 0,
            'cake_orders_pending' => 0,
            'stock_requests_pending' => 0,
            'net_sales_month' => 0,
            'collections_month' => 0,
            'outstanding_month' => 0,
            'invoices_month' => 0,

            /*
             * هذا ليس "صافي ربح" محاسبي.
             * هو المبيعات بعد خصم المرتجعات فقط.
             */
            'net_sales_after_refunds' => 0,
            'cash_balance' => 0,

            'factory_production' => 0,
            'low_stock_count' => 0,
            'cake_pipeline' => [],
            'branch_sales' => [],
            'top_products' => collect(),
            'revenue_labels' => [],
            'revenue_data' => [],
            'recent_notifications' => $modules->isEnabled('notifications')
                ? $user->notifications()->latest()->limit(5)->get()
                : collect(),
            'payment_breakdown' => [],

            /*
             * Restaurant / Kitchen / KDS
             */
            'restaurant' => [
                'orders_today' => 0,
                'open_orders' => 0,
                'tables_total' => 0,
                'tables_occupied' => 0,
            ],

            'kitchen' => [
                'queued' => 0,
                'preparing' => 0,
                'ready' => 0,
                'urgent' => 0,
            ],

            /*
             * Sprint 06 — Recipes / Production / QC
             */
            'production' => [
                'draft' => 0,
                'released' => 0,
                'in_progress' => 0,
                'awaiting_quality' => 0,
                'completed_today' => 0,
                'active_recipes' => 0,
            ],

            /*
             * Operational task center
             */
            'task_center' => [],
        ];

        /*
        |--------------------------------------------------------------------------
        | Core sales
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('sales')
            && $user->can('orders.view')
            && $baseLocationIds->isNotEmpty()
        ) {
            $stats['orders_today'] = Order::query()
                ->whereIn('location_id', $baseLocationIds->all())
                ->whereDate('created_at', $today)
                ->where('status', '!=', 'cancelled')
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Finance
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('finance')
            && $user->can('financial.dashboard.view')
            && $baseLocationIds->isNotEmpty()
        ) {
            $invoiceQuery = Invoice::query()
                ->whereIn('location_id', $baseLocationIds->all())
                ->where('status', 'active');

            $stats['sales_today'] = (float) (clone $invoiceQuery)
                ->whereDate('issued_at', $today)
                ->sum('total_amount');

            $stats['net_sales_month'] = (float) (clone $invoiceQuery)
                ->whereBetween('issued_at', [$monthStart, $now])
                ->sum('total_amount');

            /*
             * الذمم الحالية وليست فقط ذمم هذا الشهر.
             * الاسم القديم outstanding_month كان مضللًا.
             */
            $stats['outstanding_month'] = (float) (clone $invoiceQuery)
                ->sum('remaining_amount');

            $stats['invoices_month'] = (clone $invoiceQuery)
                ->whereBetween('issued_at', [$monthStart, $now])
                ->count();

            /*
             * refunds لا يضمن location_id في النسخة الحالية.
             * لذلك لا نخصم مرتجعات مواقع أخرى من مستخدم الفرع.
             */
            $refunds = $user->isAdmin() && Schema::hasTable('refunds')
                ? (float) DB::table('refunds')
                    ->whereBetween('created_at', [$monthStart, $now])
                    ->sum('amount')
                : 0.0;

            $stats['net_sales_after_refunds'] = max(
                0,
                (float) $stats['net_sales_month'] - $refunds
            );

            $dailyRevenue = (clone $invoiceQuery)
                ->whereBetween(
                    'issued_at',
                    [
                        $now->copy()->subDays(29)->startOfDay(),
                        $now,
                    ]
                )
                ->selectRaw(
                    'DATE(issued_at) as rev_date, SUM(total_amount) as total'
                )
                ->groupBy('rev_date')
                ->orderBy('rev_date')
                ->get()
                ->keyBy('rev_date');

            for ($i = 29; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);

                $stats['revenue_labels'][] =
                    $date->format('d/m');

                $stats['revenue_data'][] =
                    (float) (
                        $dailyRevenue[
                            $date->toDateString()
                        ]->total
                        ?? 0
                    );
            }

            if ($user->isAdmin()) {
                $stats['branch_sales'] = Location::query()
                    ->branches()
                    ->active()
                    ->get()
                    ->map(
                        fn (Location $branch) => [
                            'name' => $branch->name,
                            'amount' => (float) Invoice::query()
                                ->where(
                                    'location_id',
                                    $branch->id
                                )
                                ->where(
                                    'status',
                                    'active'
                                )
                                ->whereBetween(
                                    'issued_at',
                                    [$monthStart, $now]
                                )
                                ->sum(
                                    'total_amount'
                                ),
                        ]
                    )
                    ->sortByDesc('amount')
                    ->take(5)
                    ->values()
                    ->all();
            }
        }

        if (
            $modules->isEnabled('payments')
            && $user->can('payments.record')
            && $baseLocationIds->isNotEmpty()
        ) {
            $stats['collections_month'] = (float) Payment::query()
                ->whereIn(
                    'location_id',
                    $baseLocationIds->all()
                )
                ->where(
                    'status',
                    'confirmed'
                )
                ->whereBetween(
                    'paid_at',
                    [$monthStart, $now]
                )
                ->sum('amount');
        }

        if (
            $modules->isEnabled('finance')
            && $user->can('cash_sessions.manage')
            && $baseLocationIds->isNotEmpty()
        ) {
            $stats['cash_balance'] = CashSession::query()
                ->whereIn(
                    'location_id',
                    $baseLocationIds->all()
                )
                ->where(
                    'status',
                    'open'
                )
                ->get()
                ->sum(
                    fn (CashSession $session) =>
                        (float) $session->opening_balance
                        + (float) $session->cash_received
                        - (float) $session->cash_refunds
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Cake workflow
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('cake_orders')
            && $user->can('cake_orders.view')
            && $baseLocationIds->isNotEmpty()
        ) {
            $cakeQuery = SpecialCakeOrder::query()
                ->whereIn(
                    'origin_branch_id',
                    $baseLocationIds->all()
                );

            $stats['cake_orders_pending'] =
                (clone $cakeQuery)
                    ->where(
                        'status',
                        'pending_factory_review'
                    )
                    ->count();

            $stats['factory_production'] =
                (clone $cakeQuery)
                    ->whereIn(
                        'status',
                        [
                            'accepted',
                            'scheduled',
                            'in_preparation',
                            'decorating',
                            'quality_check',
                        ]
                    )
                    ->count();

            foreach (
                [
                    'pending_factory_review',
                    'accepted',
                    'scheduled',
                    'in_preparation',
                    'decorating',
                    'quality_check',
                    'ready',
                    'sent_to_branch',
                ] as $stage
            ) {
                $stats['cake_pipeline'][$stage] =
                    (clone $cakeQuery)
                        ->where(
                            'status',
                            $stage
                        )
                        ->count();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Inventory
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('inventory')
            && $user->can('stock_requests.review')
            && $baseLocationIds->isNotEmpty()
        ) {
            $stats['stock_requests_pending'] = StockRequest::query()
                ->whereIn(
                    'branch_location_id',
                    $baseLocationIds->all()
                )
                ->where(
                    'status',
                    'pending_factory_review'
                )
                ->count();
        }

        if (
            $modules->isEnabled('inventory')
            && $user->can('inventory.view')
            && $baseLocationIds->isNotEmpty()
        ) {
            $stats['low_stock_count'] = Inventory::query()
                ->whereIn(
                    'location_id',
                    $baseLocationIds->all()
                )
                ->whereRaw(
                    'quantity <= (
                        SELECT minimum_stock_level
                        FROM location_products
                        WHERE location_products.location_id = inventories.location_id
                          AND location_products.product_id = inventories.product_id
                        LIMIT 1
                    )'
                )
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Top products
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('products')
            && $user->can('products.view')
            && $baseLocationIds->isNotEmpty()
            && Schema::hasTable('order_items')
        ) {
            $stats['top_products'] = DB::table('order_items')
                ->join(
                    'orders',
                    'orders.id',
                    '=',
                    'order_items.order_id'
                )
                ->whereIn(
                    'orders.location_id',
                    $baseLocationIds->all()
                )
                ->whereBetween(
                    'orders.created_at',
                    [$monthStart, $now]
                )
                ->where(
                    'orders.status',
                    '!=',
                    'cancelled'
                )
                ->groupBy(
                    'order_items.product_id',
                    'order_items.product_name'
                )
                ->select(
                    'order_items.product_id',
                    'order_items.product_name',
                    DB::raw(
                        'SUM(order_items.quantity) as total_qty'
                    )
                )
                ->orderByDesc('total_qty')
                ->limit(5)
                ->get();
        }

        /*
        |--------------------------------------------------------------------------
        | Restaurant
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('restaurant')
            && $user->can('restaurant.view')
            && Schema::hasColumn(
                'orders',
                'restaurant_service_type'
            )
        ) {
            $restaurantLocationIds = $scopeFor(
                $user->can(
                    'restaurant.view_all_locations'
                )
            );

            if ($restaurantLocationIds->isNotEmpty()) {
                $restaurantOrders = Order::query()
                    ->whereIn(
                        'location_id',
                        $restaurantLocationIds->all()
                    )
                    ->whereNotNull(
                        'restaurant_service_type'
                    );

                $stats['restaurant']['orders_today'] =
                    (clone $restaurantOrders)
                        ->whereDate(
                            'created_at',
                            $today
                        )
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->count();

                $stats['restaurant']['open_orders'] =
                    (clone $restaurantOrders)
                        ->whereIn(
                            'status',
                            [
                                'draft',
                                'confirmed',
                            ]
                        )
                        ->count();

                if (
                    $modules->isEnabled(
                        'restaurant_tables'
                    )
                    && Schema::hasTable(
                        'restaurant_tables'
                    )
                    && $user->can(
                        'restaurant_tables.view'
                    )
                ) {
                    $tables = RestaurantTable::query()
                        ->whereIn(
                            'location_id',
                            $restaurantLocationIds->all()
                        )
                        ->active();

                    $stats['restaurant']['tables_total'] =
                        (clone $tables)->count();

                    $stats['restaurant']['tables_occupied'] =
                        (clone $tables)
                            ->whereHas(
                                'activeSession'
                            )
                            ->count();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Kitchen / KDS
        |--------------------------------------------------------------------------
        */

        if (
            $modules->isEnabled('kitchen')
            && $user->can('kitchen.view')
            && Schema::hasTable('kitchen_tickets')
        ) {
            $kitchenLocationIds = $scopeFor(
                $user->can(
                    'kitchen.view_all_locations'
                )
            );

            if ($kitchenLocationIds->isNotEmpty()) {
                $kitchen = KitchenTicket::query()
                    ->whereIn(
                        'location_id',
                        $kitchenLocationIds->all()
                    );

                foreach (
                    [
                        'queued',
                        'preparing',
                        'ready',
                    ] as $status
                ) {
                    $stats['kitchen'][$status] =
                        (clone $kitchen)
                            ->where(
                                'status',
                                $status
                            )
                            ->count();
                }

                $stats['kitchen']['urgent'] =
                    (clone $kitchen)
                        ->whereIn(
                            'status',
                            [
                                'queued',
                                'preparing',
                                'ready',
                            ]
                        )
                        ->where(
                            'priority',
                            '>=',
                            10
                        )
                        ->count();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sprint 06 — Recipes / Production / Quality
        |--------------------------------------------------------------------------
        |
        | Schema guards تجعل لوحة التحكم لا تنهار حتى أثناء تركيب Sprint 06
        | وقبل اكتمال الـ migration.
        |
        */

        if (
            $modules->isEnabled('recipes')
            && $user->can('recipes.view')
            && Schema::hasTable('recipes')
        ) {
            $stats['production']['active_recipes'] =
                Recipe::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->count();
        }

        if (
            $modules->isEnabled('production')
            && $user->can('production.view')
            && Schema::hasTable('production_orders')
        ) {
            $productionLocationIds = $scopeFor(
                $user->can(
                    'production.view_all_locations'
                )
            );

            if ($productionLocationIds->isNotEmpty()) {
                $production = ProductionOrder::query()
                    ->whereIn(
                        'location_id',
                        $productionLocationIds->all()
                    );

                foreach (
                    [
                        'draft',
                        'released',
                        'in_progress',
                        'awaiting_quality',
                    ] as $status
                ) {
                    $stats['production'][$status] =
                        (clone $production)
                            ->where(
                                'status',
                                $status
                            )
                            ->count();
                }

                $stats['production']['completed_today'] =
                    (clone $production)
                        ->where(
                            'status',
                            'completed'
                        )
                        ->whereDate(
                            'completed_at',
                            $today
                        )
                        ->count();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Task center
        |--------------------------------------------------------------------------
        */

        $tasks = collect();

        $pushTask = static function (
            string $type,
            string $title,
            string $description,
            string $action,
            int $count,
            string $url,
            string $tone = 'gold'
        ) use ($tasks): void {
            if ($count <= 0) {
                return;
            }

            $tasks->push([
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'action' => $action,
                'count' => $count,
                'url' => $url,
                'tone' => $tone,
            ]);
        };

        /*
         * Cake
         */
        if (
            $modules->isEnabled('cake_orders')
            && Route::has('cake-orders.index')
            && (
                $user->isAdmin()
                || $user->can('cake_orders.review')
                || $user->can('cake_orders.accept')
                || $user->can('cake_orders.reject')
                || $user->can(
                    'cake_orders.request_modification'
                )
            )
        ) {
            $pushTask(
                'cake',
                'طلبات كيك بانتظار مراجعة المصنع',
                'طلبات تحتاج مراجعة واعتماد أو رفض من المصنع.',
                'فتح طلبات الكيك',
                (int) (
                    $stats['cake_pipeline'][
                        'pending_factory_review'
                    ]
                    ?? 0
                ),
                route(
                    'cake-orders.index',
                    [
                        'status' =>
                            'pending_factory_review',
                    ]
                ),
                'gold'
            );
        }

        /*
         * Inventory
         */
        if (
            $modules->isEnabled('inventory')
            && Route::has('stock-requests.index')
            && $user->can('stock_requests.review')
        ) {
            $pushTask(
                'inventory',
                'طلبات مخزون بانتظار المراجعة',
                'طلبات فروع تحتاج مراجعة قبل بدء التجهيز.',
                'مراجعة طلبات المخزون',
                (int) $stats[
                    'stock_requests_pending'
                ],
                route('stock-requests.index'),
                'blue'
            );
        }

        /*
         * Kitchen
         */
        if (
            $modules->isEnabled('kitchen')
            && Route::has('kitchen.tickets.index')
        ) {
            if (
                $user->can(
                    'kitchen.ticket.start'
                )
            ) {
                $pushTask(
                    'kitchen',
                    'تذاكر مطبخ بانتظار التحضير',
                    'طلبات وصلت للمطبخ ولم يبدأ تجهيزها بعد.',
                    'بدء التحضير',
                    (int) $stats[
                        'kitchen'
                    ]['queued'],
                    route(
                        'kitchen.tickets.index',
                        [
                            'status' =>
                                'queued',
                        ]
                    ),
                    'gold'
                );
            }

            if (
                $user->can(
                    'kitchen.ticket.ready'
                )
            ) {
                $pushTask(
                    'kitchen',
                    'تذاكر قيد التحضير',
                    'تذاكر مفتوحة تحتاج اعتماد الجاهزية بعد انتهاء التحضير.',
                    'متابعة التحضير',
                    (int) $stats[
                        'kitchen'
                    ]['preparing'],
                    route(
                        'kitchen.tickets.index',
                        [
                            'status' =>
                                'preparing',
                        ]
                    ),
                    'blue'
                );
            }

            if (
                $user->can(
                    'kitchen.ticket.serve'
                )
            ) {
                $pushTask(
                    'kitchen',
                    'طلبات جاهزة للتسليم',
                    'تذاكر جاهزة وتحتاج تسجيل التسليم للعميل أو الصالة.',
                    'تسجيل التسليم',
                    (int) $stats[
                        'kitchen'
                    ]['ready'],
                    route(
                        'kitchen.tickets.index',
                        [
                            'status' =>
                                'ready',
                        ]
                    ),
                    'green'
                );
            }
        }

        /*
         * KDS urgent
         */
        if (
            $modules->isEnabled('kds')
            && Route::has('kds.index')
            && $user->can('kds.view')
        ) {
            $pushTask(
                'kds',
                'تذاكر مطبخ عاجلة',
                'تذاكر ذات أولوية مرتفعة تحتاج متابعة مباشرة على شاشة المطبخ.',
                'فتح شاشة KDS',
                (int) $stats[
                    'kitchen'
                ]['urgent'],
                route('kds.index'),
                'red'
            );
        }

        /*
         * Production
         */
        if (
            $modules->isEnabled('production')
            && Route::has(
                'production.orders.index'
            )
        ) {
            if (
                $user->can(
                    'production.release'
                )
            ) {
                $pushTask(
                    'production',
                    'أوامر إنتاج مسودة',
                    'أوامر تحتاج مراجعة واعتماد قبل بدء التنفيذ.',
                    'مراجعة أوامر الإنتاج',
                    (int) $stats[
                        'production'
                    ]['draft'],
                    route(
                        'production.orders.index',
                        [
                            'status' => 'draft',
                        ]
                    ),
                    'gold'
                );
            }

            if (
                $user->can(
                    'production.start'
                )
            ) {
                $pushTask(
                    'production',
                    'أوامر معتمدة بانتظار البدء',
                    'تم اعتمادها وتحتاج بدء الإنتاج وحجز المواد.',
                    'بدء الإنتاج',
                    (int) $stats[
                        'production'
                    ]['released'],
                    route(
                        'production.orders.index',
                        [
                            'status' => 'released',
                        ]
                    ),
                    'blue'
                );
            }

            if (
                $user->can(
                    'production.complete'
                )
            ) {
                $pushTask(
                    'production',
                    'أوامر قيد الإنتاج',
                    'أوامر مفتوحة تحتاج إكمال الكميات وترحيل الاستهلاك.',
                    'متابعة الإنتاج',
                    (int) $stats[
                        'production'
                    ]['in_progress'],
                    route(
                        'production.orders.index',
                        [
                            'status' =>
                                'in_progress',
                        ]
                    ),
                    'green'
                );
            }
        }

        /*
         * Quality control
         */
        if (
            $modules->isEnabled(
                'quality_control'
            )
            && Route::has(
                'quality-control.index'
            )
            && $user->can(
                'quality_control.inspect'
            )
        ) {
            $pushTask(
                'quality',
                'إنتاج بانتظار فحص الجودة',
                'ناتج إنتاج لا يتم ترحيله نهائيًا قبل قرار الجودة.',
                'فتح مراقبة الجودة',
                (int) $stats[
                    'production'
                ]['awaiting_quality'],
                route(
                    'quality-control.index'
                ),
                'red'
            );
        }

        $stats['task_center'] = $tasks
            ->sortByDesc('count')
            ->values()
            ->all();

        return view(
            'dashboard',
            compact('stats')
        );
    }
}