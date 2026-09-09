<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesChannel\StoreSalesChannelRequest;
use App\Http\Requests\SalesChannel\UpdateSalesChannelRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\SalesChannelChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SalesChannelController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesChannel::class);

        $channels = SalesChannel::query()
            ->withCount([
                'orders as orders_count' => fn ($query) => $query->whereNotIn('status', ['cancelled']),
            ])
            ->withSum([
                'orders as gross_sales' => fn ($query) => $query->whereNotIn('status', ['cancelled']),
            ], 'subtotal')
            ->withSum([
                'orders as total_channel_discounts' => fn ($query) => $query->whereNotIn('status', ['cancelled']),
            ], 'channel_discount_amount')
            ->withSum([
                'orders as net_revenue' => fn ($query) => $query->whereNotIn('status', ['cancelled']),
            ], 'channel_net_revenue')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query
                ->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.settings.sales-channels.index', compact('channels'));
    }

    public function create(): View
    {
        $this->authorize('create', SalesChannel::class);

        return view('admin.settings.sales-channels.create');
    }

    public function store(StoreSalesChannelRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('sales-channels', 'public');
        }

        $channel = SalesChannel::create($data);
        $this->logActivity('sales_channel.created', $channel, null, $channel->toArray());
        $this->notifyAdmins($channel, 'created');

        return redirect()
            ->route('settings.sales-channels.show', $channel)
            ->with('success', 'تم إنشاء قناة البيع بنجاح.');
    }

    public function show(SalesChannel $salesChannel): View
    {
        $this->authorize('view', $salesChannel);

        $baseOrders = $salesChannel->orders()->whereNotIn('status', ['cancelled']);
        $stats = [
            'orders_count' => (clone $baseOrders)->count(),
            'gross_sales' => (float) (clone $baseOrders)->sum('subtotal'),
            'channel_discounts' => (float) (clone $baseOrders)->sum('channel_discount_amount'),
            'commissions' => (float) (clone $baseOrders)->sum('channel_commission_amount'),
            'net_revenue' => (float) (clone $baseOrders)->sum('channel_net_revenue'),
        ];

        $monthlySales = (clone $baseOrders)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(channel_net_revenue), 0) as net_revenue')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $recentOrders = $salesChannel->orders()
            ->with(['customer', 'location'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.settings.sales-channels.show', compact(
            'salesChannel',
            'stats',
            'monthlySales',
            'recentOrders'
        ));
    }

    public function edit(SalesChannel $salesChannel): View
    {
        $this->authorize('update', $salesChannel);

        return view('admin.settings.sales-channels.edit', [
            'channel' => $salesChannel,
        ]);
    }

    public function update(
        UpdateSalesChannelRequest $request,
        SalesChannel $salesChannel
    ): RedirectResponse {
        $old = $salesChannel->toArray();
        $data = $request->safe()->except(['logo', 'remove_logo', 'remove_api_key']);

        if (! $request->filled('api_key')) {
            unset($data['api_key']);
        }

        if ($request->boolean('remove_api_key')) {
            $data['api_key'] = null;
        }

        if ($request->boolean('remove_logo') && $salesChannel->logo) {
            Storage::disk('public')->delete($salesChannel->logo);
            $data['logo'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($salesChannel->logo) {
                Storage::disk('public')->delete($salesChannel->logo);
            }
            $data['logo'] = $request->file('logo')->store('sales-channels', 'public');
        }

        $salesChannel->update($data);
        $new = $salesChannel->fresh()->toArray();
        $commercialChanged = collect([
            'discount_type',
            'discount_value',
            'discount_funded_by_channel',
            'commission_type',
            'commission_value',
            'commission_base',
        ])->contains(fn ($field) => (string) ($old[$field] ?? '') !== (string) ($new[$field] ?? ''));

        $this->logActivity(
            $commercialChanged ? 'sales_channel.commercial_terms_changed' : 'sales_channel.updated',
            $salesChannel,
            $old,
            $new
        );
        $this->notifyAdmins($salesChannel, $commercialChanged ? 'commercial_terms_changed' : 'updated');

        return redirect()
            ->route('settings.sales-channels.show', $salesChannel)
            ->with('success', 'تم تحديث قناة البيع بنجاح.');
    }

    public function destroy(SalesChannel $salesChannel): RedirectResponse
    {
        $this->authorize('delete', $salesChannel);

        if ($salesChannel->orders()->exists()) {
            return back()->with('error', 'لا يمكن حذف قناة مرتبطة بطلبات سابقة. عطّلها للحفاظ على السجل المالي.');
        }

        $old = $salesChannel->toArray();
        $salesChannel->delete();
        $this->logActivity('sales_channel.deleted', $salesChannel, $old, null);

        return redirect()
            ->route('settings.sales-channels.index')
            ->with('success', 'تم حذف قناة البيع بنجاح.');
    }

    public function toggleStatus(SalesChannel $salesChannel): RedirectResponse
    {
        $this->authorize('toggleStatus', $salesChannel);

        $wasActive = $salesChannel->is_active;
        $salesChannel->update(['is_active' => ! $wasActive]);
        $event = $wasActive ? 'disabled' : 'enabled';

        $this->logActivity(
            "sales_channel.{$event}",
            $salesChannel,
            ['is_active' => $wasActive],
            ['is_active' => ! $wasActive]
        );
        $this->notifyAdmins($salesChannel, $event);

        return back()->with('success', $wasActive ? 'تم تعطيل قناة البيع.' : 'تم تفعيل قناة البيع.');
    }

    public function report(Request $request): View
    {
        $this->authorize('reports', SalesChannel::class);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', 'in:daily,monthly,yearly'],
            'sales_channel_id' => ['nullable', 'integer', 'exists:sales_channels,id'],
        ]);

        $groupBy = $filters['group_by'] ?? 'monthly';
        $periodExpression = match ($groupBy) {
            'daily' => "DATE(orders.created_at)",
            'yearly' => "DATE_FORMAT(orders.created_at, '%Y')",
            default => "DATE_FORMAT(orders.created_at, '%Y-%m')",
        };

        $rows = Order::query()
            ->join('sales_channels', 'sales_channels.id', '=', 'orders.sales_channel_id')
            ->whereNotIn('orders.status', ['cancelled'])
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '<=', $date))
            ->when($filters['sales_channel_id'] ?? null, fn ($q, $id) => $q->where('orders.sales_channel_id', $id))
            ->select([
                'sales_channels.id as sales_channel_id',
                'sales_channels.name as sales_channel_name',
            ])
            ->selectRaw("{$periodExpression} as period")
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(orders.channel_discount_amount), 0) as channel_discounts')
            ->selectRaw('COALESCE(SUM(orders.channel_commission_amount), 0) as commissions')
            ->selectRaw('COALESCE(SUM(orders.channel_net_revenue), 0) as net_revenue')
            ->groupBy('sales_channels.id', 'sales_channels.name', DB::raw($periodExpression))
            ->orderByDesc('period')
            ->orderBy('sales_channels.name')
            ->paginate(40)
            ->withQueryString();

        $channels = SalesChannel::withTrashed()->orderBy('name')->get(['id', 'name']);

        return view('admin.reports.sales-channels', compact('rows', 'channels', 'filters', 'groupBy'));
    }

    private function notifyAdmins(SalesChannel $channel, string $event): void
    {
        NotificationDispatcher::notifyByPermissions(
            new SalesChannelChanged($channel, $event, auth()->id()),
            ['sales_channels.view', 'sales_channels.update', 'sales_channels.reports'],
            null,
            ['sales_channels.view'],
            auth()->id(),
        );
    }

    private function logActivity(
        string $action,
        SalesChannel $channel,
        ?array $old,
        ?array $new
    ): void {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'sales_channels',
            'record_type' => SalesChannel::class,
            'record_id' => $channel->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
