<?php

namespace App\Http\Controllers\Finance;

use App\Enums\IncomingTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreIncomingBankTransferRequest;
use App\Models\IncomingBankTransfer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomingBankTransferController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
            || $user->canAny([
                'payments.record',
                'payments.verify',
                'financial.branch.view',
                'financial.global.view',
                'financial.collections.view',
            ]),
            403
        );

        $canViewAll =
            $user->isAdmin()
            || $user->can('financial.global.view');

        $locationIds = $canViewAll
            ? Location::query()->branches()->active()->pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        abort_if($locationIds->isEmpty(), 403, 'لا يوجد فرع متاح لهذا المستخدم.');

        $query = IncomingBankTransfer::query()
            ->with([
                'location',
                'paymentMethod',
                'locationPaymentAccount',
                'createdBy.employee',
                'verifiedBy.employee',
            ])
            ->whereIn('location_id', $locationIds);

        if ($request->filled('location_id')) {
            $requestedLocationId = $request->integer('location_id');

            if ($locationIds->contains($requestedLocationId)) {
                $query->where('location_id', $requestedLocationId);
            }
        }

        if ($request->filled('payment_method_id')) {
            $query->where(
                'payment_method_id',
                $request->integer('payment_method_id')
            );
        }

        if ($request->filled('location_payment_account_id')) {
            $query->where(
                'location_payment_account_id',
                $request->integer('location_payment_account_id')
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'received_at',
                '>=',
                $request->date('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'received_at',
                '<=',
                $request->date('date_to')
            );
        }

        $search = trim($request->string('search')->toString());

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($searchQuery) use ($like): void {
                $searchQuery
                    ->where('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    ->orWhere('sender_account_number', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhereHas('locationPaymentAccount', function ($accountQuery) use ($like): void {
                        $accountQuery
                            ->where('name', 'like', $like)
                            ->orWhere('provider_name', 'like', $like)
                            ->orWhere('account_holder_name', 'like', $like)
                            ->orWhere('account_number', 'like', $like)
                            ->orWhere('iban', 'like', $like)
                            ->orWhere('phone_number', 'like', $like);
                    });
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as transfers_count')
            ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as confirmed_total")
            ->selectRaw("SUM(CASE WHEN status = 'pending_verification' THEN amount ELSE 0 END) as pending_total")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_total")
            ->first();

        $methodBreakdown = (clone $query)
            ->selectRaw('payment_method_id, COUNT(*) as transfers_count, SUM(amount) as total_amount')
            ->groupBy('payment_method_id')
            ->orderByDesc('total_amount')
            ->get()
            ->keyBy('payment_method_id');

        $transfers = $query
            ->latest('received_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->where('type', '!=', 'cash')
            ->with([
                'locationPaymentMethods' => fn ($assignment) =>
                    $assignment->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if (! $canViewAll) {
            $locationId = (int) $locationIds->first();

            $paymentMethods = $paymentMethods
                ->filter(function (PaymentMethod $method) use ($locationId): bool {
                    $assignments = $method->locationPaymentMethods;

                    return $assignments->isEmpty()
                        || $assignments->contains(
                            fn ($assignment): bool =>
                                (int) $assignment->location_id === $locationId
                                && (bool) $assignment->is_active
                        );
                })
                ->values();
        }

        $paymentAccounts = LocationPaymentAccount::query()
            ->with('paymentMethod')
            ->whereIn('location_id', $locationIds)
            ->where('is_active', true)
            ->whereHas(
                'paymentMethod',
                fn ($method) => $method
                    ->active()
                    ->where('type', '!=', 'cash')
            )
            ->orderBy('location_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $locations = $canViewAll
            ? Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get()
            : collect();

        return view('finance.incoming-bank-transfers.index', [
            'transfers' => $transfers,
            'summary' => $summary,
            'methodBreakdown' => $methodBreakdown,
            'paymentMethods' => $paymentMethods,
            'paymentAccounts' => $paymentAccounts,
            'locations' => $locations,
            'statusOptions' => IncomingTransferStatus::cases(),
            'canViewAll' => $canViewAll,
            'currentLocationId' => $canViewAll
                ? (int) $request->integer('location_id')
                : (int) $locationIds->first(),
        ]);
    }

    public function store(StoreIncomingBankTransferRequest $request)
    {
        $validated = $request->validated();

        $proof = $request->file('payment_proof');

        $transfer = IncomingBankTransfer::query()->create([
            'location_id' => $validated['location_id'],
            'payment_method_id' => $validated['payment_method_id'],
            'location_payment_account_id' =>
                $validated['location_payment_account_id'] ?? null,
            'sender_name' => trim($validated['sender_name']),
            'sender_phone' => filled($validated['sender_phone'] ?? null)
                ? trim((string) $validated['sender_phone'])
                : null,
            'sender_account_number' =>
                filled($validated['sender_account_number'] ?? null)
                    ? trim((string) $validated['sender_account_number'])
                    : null,
            'reference_number' => trim($validated['reference_number']),
            'amount' => round((float) $validated['amount'], 2),
            'currency_code' => strtoupper(
                (string) ($validated['currency_code'] ?? 'ILS')
            ),
            'status' => IncomingTransferStatus::PendingVerification,
            'payment_proof' => $proof
                ? $proof->store('incoming-transfer-proofs', 'public')
                : null,
            'notes' => filled($validated['notes'] ?? null)
                ? trim((string) $validated['notes'])
                : null,
            'received_at' => $validated['received_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'incoming_bank_transfer.recorded',
            module: 'payments',
            recordType: 'incoming_bank_transfers',
            recordId: $transfer->id,
            newValues: $transfer->only([
                'location_id',
                'payment_method_id',
                'location_payment_account_id',
                'sender_name',
                'reference_number',
                'amount',
                'status',
            ]),
            metadata: [
                'source' => 'finance_incoming_transfer_panel',
            ],
        );

        return back()->with(
            'success',
            'تم تسجيل الحوالة الواردة وهي بانتظار التحقق.'
        );
    }

    public function verify(
        Request $request,
        IncomingBankTransfer $incomingBankTransfer
    ) {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
            || $user->can('payments.verify'),
            403
        );

        $this->assertLocationAccess(
            $incomingBankTransfer,
            $user
        );

        $validated = $request->validate([
            'action' => ['required', 'in:verify,reject'],
            'rejection_reason' => [
                'required_if:action,reject',
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if (
            $incomingBankTransfer->statusValue()
            !== IncomingTransferStatus::PendingVerification->value
        ) {
            throw ValidationException::withMessages([
                'transfer' => 'تمت معالجة هذه الحوالة مسبقًا.',
            ]);
        }

        $approved = $validated['action'] === 'verify';

        $incomingBankTransfer->update([
            'status' => $approved
                ? IncomingTransferStatus::Confirmed
                : IncomingTransferStatus::Rejected,
            'verified_by' => $user->id,
            'verified_at' => now(),
            'rejection_reason' => $approved
                ? null
                : trim((string) $validated['rejection_reason']),
        ]);

        ActivityLogger::log(
            userId: $user->id,
            action: $approved
                ? 'incoming_bank_transfer.verified'
                : 'incoming_bank_transfer.rejected',
            module: 'payments',
            recordType: 'incoming_bank_transfers',
            recordId: $incomingBankTransfer->id,
            oldValues: ['status' => 'pending_verification'],
            newValues: [
                'status' => $incomingBankTransfer->fresh()->statusValue(),
            ],
            metadata: [
                'location_id' => $incomingBankTransfer->location_id,
                'reason' => $validated['rejection_reason'] ?? null,
            ],
        );

        return back()->with(
            'success',
            $approved
                ? 'تم اعتماد الحوالة الواردة.'
                : 'تم رفض الحوالة الواردة.'
        );
    }

    public function proof(
        Request $request,
        IncomingBankTransfer $incomingBankTransfer
    ): StreamedResponse {
        $this->assertLocationAccess(
            $incomingBankTransfer,
            $request->user()
        );

        $path = ltrim(
            str_replace(
                '\\',
                '/',
                (string) $incomingBankTransfer->payment_proof
            ),
            '/'
        );

        abort_if(
            $path === ''
            || str_contains($path, '..'),
            404,
            'إثبات الحوالة غير موجود.'
        );

        $disk = Storage::disk('public');

        abort_unless(
            $disk->exists($path),
            404,
            'ملف إثبات الحوالة غير موجود.'
        );

        return $disk->response(
            $path,
            'incoming-transfer-proof-' . $incomingBankTransfer->id
                . '.' . pathinfo($path, PATHINFO_EXTENSION),
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function assertLocationAccess(
        IncomingBankTransfer $transfer,
        $user
    ): void {
        abort_unless(
            $user->isAdmin()
            || $user->can('financial.global.view')
            || (
                (int) ($user->primaryLocation()?->id ?? 0)
                === (int) $transfer->location_id
            ),
            403,
            'لا يمكنك الوصول إلى حوالة تابعة لفرع آخر.'
        );
    }
}
