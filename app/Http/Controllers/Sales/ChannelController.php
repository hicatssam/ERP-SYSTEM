<?php

namespace App\Http\Controllers\Sales;


use App\Http\Controllers\Controller;

use App\Http\Requests\SalesChannel\UpdateSalesChannelRequest;
use App\Http\Requests\SalesChannel\StoreSalesChannelRequest;

use App\Models\ActivityLog;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\SalesChannelDisabled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChannelController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesChannel::class);

        $channels = SalesChannel::query()
            ->withCount('orders')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('settings.sales-channels.index', compact('channels'));
    }

    public function create(): View
    {
        $this->authorize('create', SalesChannel::class);

        return view('admin.settings.sales-channels.create');
    }

    public function store(StoreSalesChannelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('sales-channels', 'public');
        }

        $channel = SalesChannel::create($data);

        $this->logActivity('create', $channel, null, $channel->toArray());

        return redirect()
            ->route('settings.sales-channels.index')
            ->with('success', 'تم إنشاء قناة البيع بنجاح.');
    }

  public function edit(SalesChannel $salesChannel): View
{
    $this->authorize('update', $salesChannel);

    return view('admin.settings.sales-channels.edit', [
        'channel' => $salesChannel,
    ]);
}
    public function update(UpdateSalesChannelRequest $request, SalesChannel $salesChannel): RedirectResponse
    {
        $old  = $salesChannel->toArray();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($salesChannel->logo) {
                Storage::disk('public')->delete($salesChannel->logo);
            }
            $data['logo'] = $request->file('logo')->store('sales-channels', 'public');
        }

        $salesChannel->update($data);

        $this->logActivity('update', $salesChannel, $old, $salesChannel->toArray());

        return redirect()
            ->route('settings.sales-channels.index')
            ->with('success', 'تم تحديث قناة البيع بنجاح.');
    }

    public function destroy(SalesChannel $salesChannel): RedirectResponse
    {
        $this->authorize('delete', $salesChannel);

        if ($salesChannel->orders()->exists()) {
            return back()->with('error', 'لا يمكن حذف قناة بيع مرتبطة بطلبات سابقة. قم بتعطيلها بدلاً من ذلك.');
        }

        $old = $salesChannel->toArray();
        $salesChannel->delete();

        $this->logActivity('delete', $salesChannel, $old, null);

        return redirect()
            ->route('settings.sales-channels.index')
            ->with('success', 'تم حذف قناة البيع بنجاح.');
    }

    public function toggleStatus(SalesChannel $salesChannel): RedirectResponse
    {
        $this->authorize('toggleStatus', $salesChannel);

        $wasActive = $salesChannel->is_active;
        $salesChannel->update(['is_active' => ! $wasActive]);

        $this->logActivity($wasActive ? 'disable' : 'enable', $salesChannel, ['is_active' => $wasActive], ['is_active' => ! $wasActive]);

        if ($wasActive) {
            Notification::send(
                User::role('Admin')->get(),
                new SalesChannelDisabled($salesChannel)
            );
        }

        return back()->with('success', $wasActive ? 'تم تعطيل قناة البيع.' : 'تم تفعيل قناة البيع.');
    }

    private function logActivity(string $action, SalesChannel $channel, ?array $old, ?array $new): void
    {
        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'module'      => 'sales_channels',
            'record_type' => SalesChannel::class,
            'record_id'   => $channel->id,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
    }
}
