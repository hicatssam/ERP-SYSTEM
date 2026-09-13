<?php

namespace App\Services\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\PaymentStatus;
use App\Enums\RestaurantServiceType;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\CustomerMenuOrderCreatedNotification;
use App\Services\Orders\OrderService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderingService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly RestaurantTableService $tables
    ) {
    }

    public function create(
        Location $location,
        array $data
    ): Order {
        if (! $location->is_active || ! $location->isBranch()) {
            throw ValidationException::withMessages([
                'location' => 'الفرع غير متاح للطلب حالياً.',
            ]);
        }

        if (! (bool) SystemSetting::get('customer_menu_enabled', true)) {
            throw ValidationException::withMessages([
                'menu' => 'الطلب من المنيو متوقف مؤقتاً.',
            ]);
        }

        $existing = Order::query()
            ->where('order_source', 'customer_menu')
            ->where('public_request_id', (string) $data['request_token'])
            ->first();

        if ($existing) {
            $this->syncPublicPayment(
                $existing,
                $location,
                $data,
                $this->resolveInternalActor()
            );

            return $existing->fresh([
                'items',
                'customer',
                'location',
                'restaurantTable',
                'payments.paymentMethod',
            ]);
        }

        $paymentContext = $this->resolvePaymentContext($location, $data);

        $serviceType = RestaurantServiceType::from(
            (string) $data['service_type']
        );

        $this->assertServiceTypeEnabled($serviceType);

        $table = $this->resolveTable(
            $location,
            $serviceType,
            $data['restaurant_table_id'] ?? null
        );

        $items = $this->validatedMenuItems(
            $location,
            $serviceType,
            $data['items'] ?? []
        );

        $channel = $this->resolveSalesChannel();
        $actor = $this->resolveInternalActor();

        $openedSession = null;

        $order = DB::transaction(function () use (
            $location,
            $data,
            $serviceType,
            $table,
            $items,
            $channel,
            $actor,
            $paymentContext,
            &$openedSession
        ): Order {
            $customer = $this->upsertCustomer(
                $location,
                (string) $data['name'],
                (string) $data['phone'],
                $data['address'] ?? null
            );

            $publicToken = Str::random(48);

            $notes = collect([
                filled($data['notes'] ?? null)
                    ? 'ملاحظة العميل: ' . trim((string) $data['notes'])
                    : null,

                $serviceType === RestaurantServiceType::Delivery
                    && filled($data['address'] ?? null)
                        ? 'عنوان التوصيل: ' . trim((string) $data['address'])
                        : null,
            ])
                ->filter()
                ->implode(PHP_EOL);

            if (
                $serviceType === RestaurantServiceType::DineIn
                && $table
            ) {
                /*
                 * Public customer ordering reserves only a currently available
                 * table. RestaurantTableService::open() locks the table/session,
                 * so two customers cannot reserve the same table concurrently.
                 */
                $openedSession = $this->tables->open(
                    $table,
                    $actor,
                    1,
                    'حجز تلقائي من منيو العميل'
                );
            }

            $payload = [
                'location_id' => (int) $location->id,
                'customer_id' => (int) $customer->id,
                'payment_arrangement' => $paymentContext['arrangement']->value,
                'sales_channel_id' => (int) $channel->id,
                'discount_type' => 'none',
                'discount_value' => 0,
                'notes' => $notes !== '' ? $notes : null,
                'items' => $items,
                'restaurant_service_type' => $serviceType->value,
                'restaurant_table_id' => $table?->id,
                'restaurant_table_session_id' => $openedSession?->id,
                'waiter_id' => null,
                'guest_count' => $serviceType === RestaurantServiceType::DineIn
                    ? 1
                    : null,

                /*
                 * Persist public source data in OrderService::createOrder itself.
                 * This must exist before OrderCreated is dispatched so the normal
                 * staff listener can distinguish customer-menu orders and avoid
                 * duplicate notifications.
                 */
                'order_source' => 'customer_menu',
                'public_token' => $publicToken,
                'public_request_id' => (string) $data['request_token'],
                'public_order_meta' => [
                    'customer_name' => (string) $data['name'],
                    'customer_phone' => (string) $data['phone'],
                    'delivery_address' => $data['address'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                    'source' => 'customer_menu',
                    'restaurant_table_id' => $table?->id,
                    'restaurant_table_session_id' => $openedSession?->id,
                ],
            ];

            /*
             * Important:
             * Public orders are ALWAYS created as draft requests.
             * Existing staff confirmation then performs costing, inventory
             * consumption, invoice creation and KDS dispatch.
             */
            $order = $this->orders->createOrder(
                $payload,
                $actor,
                false
            );

            $order->forceFill([
                'order_source' => 'customer_menu',
                'public_token' => $publicToken,
                'public_request_id' => (string) $data['request_token'],
                'public_order_meta' => [
                    'customer_name' => (string) $data['name'],
                    'customer_phone' => (string) $data['phone'],
                    'delivery_address' => $data['address'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                    'source' => 'customer_menu',
                    'restaurant_table_id' => $table?->id,
                    'restaurant_table_session_id' => $openedSession?->id,
                ],
            ])->save();

            return $order->fresh([
                'items',
                'customer',
                'location',
                'restaurantTable',
                'restaurantTableSession',
            ]);
        });

        $this->syncPublicPayment($order, $location, $data, $actor, $paymentContext);

        if ($openedSession) {
            $this->tables->notifyOpened(
                $openedSession->fresh([
                    'table.area',
                    'location',
                    'openedBy.employee',
                ])
            );
        }

        /*
         * Public customer order alert:
         * - Admin always receives it.
         * - Same-branch users receive it when they have any order/POS permission.
         * - Global restaurant viewers receive it across branches.
         *
         * This is deliberately synchronous database notification so the cashier
         * sees it immediately without depending on a queue worker.
         */
        NotificationDispatcher::notifyLocationAndAdmins(
            locationId: (int) $order->location_id,
            notification: new CustomerMenuOrderCreatedNotification(
                $order->fresh([
                    'customer',
                    'location',
                    'restaurantTable.area',
                    'items',
                ])
            ),
            permissions: [
                'orders.view',
                'orders.create',
                'orders.edit',
                'orders.confirm',
                'restaurant_pos.use',
            ],
            globalPermissions: [
                'restaurant.view_all_locations',
            ],
        );

        return $order;
    }

    private function resolvePaymentContext(Location $location, array $data): array
    {
        $method = PaymentMethod::query()
            ->whereKey((int) ($data['payment_method_id'] ?? 0))
            ->where('is_active', true)
            ->whereHas('locationPaymentMethods', function ($query) use ($location): void {
                $query
                    ->where('location_id', $location->id)
                    ->where('is_active', true);
            })
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'طريقة الدفع المحددة غير متاحة في هذا الفرع.',
            ]);
        }

        $isCash = (string) $method->type === 'cash';
        $account = null;

        if (! $isCash) {
            $activeAccounts = LocationPaymentAccount::query()
                ->where('location_id', $location->id)
                ->where('payment_method_id', $method->id)
                ->where('is_active', true);

            if ($activeAccounts->exists()) {
                $accountId = (int) ($data['payment_account_id'] ?? 0);
                $account = (clone $activeAccounts)->find($accountId);

                if (! $account) {
                    throw ValidationException::withMessages([
                        'payment_account_id' => 'اختر حساب التحويل الصحيح لهذا الفرع.',
                    ]);
                }
            }

            if ((bool) $method->requires_reference && blank($data['payment_reference'] ?? null)) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'أدخل رقم مرجع عملية الدفع.',
                ]);
            }

            if ((bool) $method->requires_verification && ! (($data['payment_proof'] ?? null) instanceof UploadedFile)) {
                throw ValidationException::withMessages([
                    'payment_proof' => 'ارفع صورة أو ملف إثبات التحويل قبل تأكيد الطلب.',
                ]);
            }
        }

        return [
            'method' => $method,
            'account' => $account,
            'is_cash' => $isCash,
            'arrangement' => $isCash
                ? PaymentArrangement::PayOnPickup
                : ((bool) $method->requires_verification
                    ? PaymentArrangement::PendingVerification
                    : PaymentArrangement::PayNow),
        ];
    }

    private function syncPublicPayment(
        Order $order,
        Location $location,
        array $data,
        User $actor,
        ?array $context = null
    ): void {
        $context ??= $this->resolvePaymentContext($location, $data);

        if ($context['is_cash']) {
            $order->forceFill([
                'payment_arrangement' => PaymentArrangement::PayOnPickup->value,
                'payment_status' => 'payment_pending',
            ])->save();

            return;
        }

        $method = $context['method'];
        $account = $context['account'];
        $requiresVerification = (bool) $method->requires_verification;
        $proofPath = null;

        if (($data['payment_proof'] ?? null) instanceof UploadedFile) {
            $file = $data['payment_proof'];
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $proofPath = $file->storeAs(
                'payment-proofs/customer-menu/' . $order->id,
                'proof-' . $order->public_request_id . '.' . $extension,
                'public'
            );
        }

        DB::transaction(function () use (
            $order,
            $location,
            $data,
            $actor,
            $method,
            $account,
            $requiresVerification,
            $proofPath,
            $context
        ): void {
            $payment = Payment::query()
                ->where('order_type', 'order')
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            $referenceParts = collect([
                filled($data['payment_reference'] ?? null)
                    ? trim((string) $data['payment_reference'])
                    : null,
            ])->filter()->implode(' | ');

            $attributes = [
                'payment_method_id' => (int) $method->id,
                'location_payment_account_id' => $account?->id,
                'location_id' => (int) $location->id,
                'amount' => (float) $order->total_amount,
                'status' => $requiresVerification
                    ? PaymentStatus::PendingVerification->value
                    : PaymentStatus::Confirmed->value,
                'reference_number' => $referenceParts !== '' ? $referenceParts : null,
                'received_by' => (int) $actor->id,
                'paid_at' => now(),
            ];

            if ($proofPath) {
                $attributes['payment_proof'] = $proofPath;
            }

            if ($payment) {
                // Idempotent retry: do not create a second payment for the same public order.
                $payment->forceFill($attributes)->save();
            } else {
                Payment::query()->create(array_merge($attributes, [
                    'order_type' => 'order',
                    'order_id' => (int) $order->id,
                ]));
            }

            $order->forceFill([
                'payment_arrangement' => $context['arrangement']->value,
                'payment_status' => $requiresVerification
                    ? 'pending_payment_verification'
                    : 'paid',
            ])->save();
        });
    }

    private function validatedMenuItems(
        Location $location,
        RestaurantServiceType $serviceType,
        array $requestedItems
    ): array {
        $requested = collect($requestedItems)
            ->map(function (array $item): array {
                $modifiers = collect($item['modifiers'] ?? [])
                    ->filter(fn ($row) => is_array($row) && filled($row['modifier_id'] ?? null))
                    ->map(fn (array $row): array => [
                        'modifier_id' => (int) $row['modifier_id'],
                        'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                    ])
                    ->sortBy('modifier_id')
                    ->values()
                    ->all();

                $variantId = filled($item['product_variant_id'] ?? null)
                    ? (int) $item['product_variant_id']
                    : null;

                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'kitchen_notes' => filled($item['kitchen_notes'] ?? null)
                        ? trim((string) $item['kitchen_notes'])
                        : null,
                    'product_variant_id' => $variantId,
                    'modifiers' => $modifiers,
                    // Two lines are "the same line" only if product, variant,
                    // and the exact set of modifiers all match — otherwise a
                    // small cake and a large cake (or a cake with vs without
                    // an add-on) would incorrectly merge into one line and
                    // lose the customer's choice.
                    'combo_key' => implode('|', [
                        $item['product_id'],
                        $variantId ?? 0,
                        collect($modifiers)
                            ->map(fn (array $m): string => $m['modifier_id'] . ':' . $m['quantity'])
                            ->implode(','),
                    ]),
                ];
            })
            ->groupBy('combo_key')
            ->map(function ($rows): array {
                $first = $rows->first();

                return [
                    'product_id' => $first['product_id'],
                    'quantity' => min(50, (int) $rows->sum('quantity')),
                    'kitchen_notes' => $rows
                        ->pluck('kitchen_notes')
                        ->filter()
                        ->unique()
                        ->implode(' | ') ?: null,
                    'product_variant_id' => $first['product_variant_id'],
                    'modifiers' => $first['modifiers'],
                ];
            })
            ->values();

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'أضف صنفاً واحداً على الأقل إلى السلة.',
            ]);
        }

        $ids = $requested->pluck('product_id');

        $allowed = Product::query()
            ->active()
            ->whereIn('id', $ids)
            ->whereHas('restaurantMenuItems', function ($query) use ($location, $serviceType): void {
                $query
                    ->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->where('show_in_qr', true);

                if ($serviceType === RestaurantServiceType::Delivery) {
                    $query->where('show_in_delivery', true);
                }
            })
            ->whereHas('locationProducts', function ($query) use ($location): void {
                $query
                    ->where('location_id', $location->id)
                    ->where('is_available', true);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $invalid = $ids
            ->map(fn ($id): int => (int) $id)
            ->diff($allowed);

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'أحد الأصناف لم يعد متاحاً في منيو هذا الفرع. حدّث الصفحة وحاول مرة أخرى.',
            ]);
        }

        return $requested->all();
    }

    private function resolveTable(
        Location $location,
        RestaurantServiceType $serviceType,
        mixed $tableId
    ): ?RestaurantTable {
        if ($serviceType !== RestaurantServiceType::DineIn) {
            return null;
        }

        $table = RestaurantTable::query()
            ->forLocation((int) $location->id)
            ->active()
            ->with('activeSession')
            ->find((int) $tableId);

        if (! $table) {
            throw ValidationException::withMessages([
                'restaurant_table_id' => 'الطاولة المحددة غير متاحة في هذا الفرع.',
            ]);
        }

        if ($table->isOccupied()) {
            throw ValidationException::withMessages([
                'restaurant_table_id' =>
                    'هذه الطاولة أصبحت مشغولة. اختر طاولة متاحة أخرى.',
            ]);
        }

        return $table;
    }

    private function assertServiceTypeEnabled(
        RestaurantServiceType $serviceType
    ): void {
        $setting = match ($serviceType) {
            RestaurantServiceType::DineIn => 'customer_menu_allow_dine_in',
            RestaurantServiceType::Takeaway => 'customer_menu_allow_takeaway',
            RestaurantServiceType::Delivery => 'customer_menu_allow_delivery',
            default => null,
        };

        if (! $setting || ! (bool) SystemSetting::get($setting, false)) {
            throw ValidationException::withMessages([
                'service_type' => 'نوع الطلب المحدد غير متاح حالياً.',
            ]);
        }
    }

    private function resolveSalesChannel(): SalesChannel
    {
        $configuredId = (int) SystemSetting::get(
            'customer_menu_sales_channel_id',
            0
        );

        if ($configuredId > 0) {
            $configured = SalesChannel::query()
                ->active()
                ->find($configuredId);

            if ($configured) {
                return $configured;
            }
        }

        $website = SalesChannel::query()
            ->active()
            ->where('slug', 'website')
            ->first();

        if ($website) {
            return $website;
        }

        $fallback = SalesChannel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $fallback) {
            throw ValidationException::withMessages([
                'sales_channel_id' =>
                    'لا توجد قناة بيع فعالة لاستقبال طلبات المنيو العام. فعّل قناة Website من إعدادات قنوات البيع.',
            ]);
        }

        return $fallback;
    }

    private function resolveInternalActor(): User
    {
        $admin = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', [
                    'Admin',
                    'super-admin',
                    'General Manager',
                ]);
            })
            ->orderBy('id')
            ->first();

        if ($admin) {
            return $admin;
        }

        $fallback = User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $fallback) {
            throw ValidationException::withMessages([
                'system' =>
                    'لا يوجد مستخدم إداري فعال لربط طلب العميل بسجل التدقيق.',
            ]);
        }

        return $fallback;
    }

    private function upsertCustomer(
        Location $location,
        string $name,
        string $phone,
        ?string $address
    ): Customer {
        $customer = Customer::query()
            ->where('location_id', $location->id)
            ->where('phone', $phone)
            ->first();

        if (! $customer) {
            return Customer::query()->create([
                'location_id' => $location->id,
                'customer_type' => Customer::TYPE_INDIVIDUAL,
                'scope' => Customer::SCOPE_BRANCH,
                'name' => $name,
                'phone' => $phone,
                'allow_credit' => false,
                'billing_cycle' => 'immediate',
                'payment_terms_days' => 0,
                'address' => filled($address) ? trim((string) $address) : null,
                'notes' => 'تم إنشاؤه تلقائياً من منيو الطلب العام.',
            ]);
        }

        $changes = [];

        if (trim((string) $customer->name) === '' && $name !== '') {
            $changes['name'] = $name;
        }

        if (
            filled($address)
            && trim((string) ($customer->address ?? '')) === ''
        ) {
            $changes['address'] = trim((string) $address);
        }

        if ($changes !== []) {
            $customer->forceFill($changes)->save();
        }

        return $customer;
    }
}
