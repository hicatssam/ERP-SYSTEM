<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerGrowthAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerInteractionController extends Controller
{
    public function __construct(
        private readonly CustomerGrowthAccessService $access
    ) {}

    public function store(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user, 'crm.view_all');

        $data = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'note',
                    'call',
                    'whatsapp',
                    'email',
                    'visit',
                    'follow_up',
                    'complaint',
                ]),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $row = CustomerInteraction::query()->create(array_merge($data, [
            'customer_id' => $customer->id,
            'location_id' => $user->primaryLocation()?->id,
            'user_id' => $user->id,
        ]));

        // Avoid depending on Customer::$fillable for Sprint 08 columns.
        Customer::query()
            ->whereKey($customer->id)
            ->update(['last_contacted_at' => now()]);

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.interaction.created',
            module: 'crm',
            recordType: 'customer_interactions',
            recordId: $row->id,
            oldValues: null,
            newValues: [
                'customer_id' => $customer->id,
                'location_id' => $row->location_id,
                'type' => $row->type,
                'next_follow_up_at' => $row->next_follow_up_at,
            ],
            metadata: null,
        );

        return back()->with('success', 'تم تسجيل التفاعل.');
    }

    public function complete(
        Request $request,
        Customer $customer,
        CustomerInteraction $interaction
    ) {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user, 'crm.view_all');

        abort_unless(
            (int) $interaction->customer_id === (int) $customer->id,
            404
        );

        if (! $this->access->canViewAllCustomers($user, 'crm.view_all')) {
            abort_unless(
                $interaction->location_id
                && (int) $interaction->location_id === (int) $user->primaryLocation()?->id,
                403,
                'لا يمكنك إغلاق متابعة تخص فرعاً آخر.'
            );
        }

        if (! $interaction->completed_at) {
            $interaction->update(['completed_at' => now()]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'crm.interaction.completed',
                module: 'crm',
                recordType: 'customer_interactions',
                recordId: $interaction->id,
                oldValues: ['completed_at' => null],
                newValues: ['completed_at' => $interaction->completed_at],
                metadata: [
                    'customer_id' => $customer->id,
                    'location_id' => $interaction->location_id,
                ],
            );
        }

        return back()->with('success', 'تم إغلاق المتابعة.');
    }
}
