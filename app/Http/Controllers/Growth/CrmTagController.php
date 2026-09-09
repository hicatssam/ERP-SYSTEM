<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\CrmTag;
use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerGrowthAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CrmTagController extends Controller
{
    public function __construct(
        private readonly CustomerGrowthAccessService $access
    ) {}

    public function index()
    {
        return view('growth.crm.tags', [
            'tags' => CrmTag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:crm_tags,name'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $tag = CrmTag::query()->create($data + ['is_active' => true]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'crm.tag.created',
            module: 'crm',
            recordType: 'crm_tags',
            recordId: $tag->id,
            oldValues: null,
            newValues: $tag->only(['name', 'color', 'is_active']),
            metadata: null,
        );

        return back()->with('success', 'تمت إضافة الوسم.');
    }

    public function update(Request $request, CrmTag $tag)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('crm_tags', 'name')->ignore($tag->id),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $tag->only(['name', 'color', 'is_active']);
        $data['is_active'] = $request->boolean('is_active');
        $tag->update($data);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'crm.tag.updated',
            module: 'crm',
            recordType: 'crm_tags',
            recordId: $tag->id,
            oldValues: $old,
            newValues: $tag->fresh()->only(['name', 'color', 'is_active']),
            metadata: null,
        );

        return back()->with('success', 'تم تحديث الوسم.');
    }

    public function sync(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user);

        $data = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:crm_tags,id'],
        ]);

        $ids = CrmTag::query()
            ->whereIn('id', $data['tag_ids'] ?? [])
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $oldIds = DB::table('crm_customer_tag')
            ->where('customer_id', $customer->id)
            ->pluck('crm_tag_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        DB::transaction(function () use ($customer, $ids, $user): void {
            DB::table('crm_customer_tag')
                ->where('customer_id', $customer->id)
                ->delete();

            foreach ($ids as $id) {
                DB::table('crm_customer_tag')->insert([
                    'customer_id' => $customer->id,
                    'crm_tag_id' => $id,
                    'assigned_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_tags.synced',
            module: 'crm',
            recordType: 'customers',
            recordId: $customer->id,
            oldValues: ['tag_ids' => $oldIds],
            newValues: ['tag_ids' => $ids],
            metadata: null,
        );

        return back()->with('success', 'تم تحديث وسوم العميل.');
    }
}
