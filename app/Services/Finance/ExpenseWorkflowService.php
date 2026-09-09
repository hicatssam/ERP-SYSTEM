<?php

namespace App\Services\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\LedgerEntryType;
use App\Models\Expense;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use App\Notifications\ExpenseWorkflowNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseWorkflowService
{
    public function __construct(
        private FinancialPeriodResolver $periods,
    ) {}

    public function createDraft(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user): Expense {
            $expense = Expense::create($data + [
                'expense_number' => null,
                'status' => ExpenseStatus::Draft,
                'created_by' => $user->id,
            ]);

            $expense->update([
                'expense_number' => sprintf(
                    'EXP-%s-%06d',
                    $expense->expense_date?->format('Y') ?? now()->format('Y'),
                    $expense->id
                ),
            ]);

            $this->log($expense, $user, 'expense.created', null, ['status' => 'draft']);

            return $expense->fresh(['category', 'location']);
        });
    }

    public function updateDraft(Expense $expense, array $data, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $data, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'expense' => 'لا يمكن تعديل المصروف بعد إرساله للاعتماد.',
                ]);
            }

            $old = $locked->only([
                'expense_category_id', 'location_id', 'amount', 'expense_date',
                'payee', 'reference_number', 'description', 'status',
            ]);

            $locked->update($data + [
                // Editing a rejected expense explicitly creates a new Draft.
                // Therefore it cannot be re-submitted without an actual edit.
                'status' => ExpenseStatus::Draft,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $fresh = $locked->fresh();
            $this->log($fresh, $user, 'expense.updated', $old, $fresh->toArray());

            return $fresh->load(['category', 'location']);
        });
    }

    public function submit(Expense $expense, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A rejected item MUST be edited first. updateDraft() turns it back
            // into Draft; direct rejected -> submitted is intentionally forbidden.
            if ($locked->statusValue() !== ExpenseStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'expense' => $locked->statusValue() === ExpenseStatus::Rejected->value
                        ? 'المصروف المرفوض يجب تعديله أولًا ثم إرساله من جديد.'
                        : 'يمكن إرسال مسودة المصروف فقط للاعتماد.',
                ]);
            }

            $locked->update([
                'status' => ExpenseStatus::Submitted,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->log($locked, $user, 'expense.submitted', ['status' => 'draft'], ['status' => 'submitted']);
            $this->notifyReviewers($locked, $user, 'submitted');

            return $locked->fresh();
        });
    }

    public function approve(Expense $expense, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->statusValue() !== ExpenseStatus::Submitted->value) {
                throw ValidationException::withMessages(['expense' => 'المصروف ليس بانتظار الاعتماد.']);
            }

            $this->assertIndependentReviewer($locked, $user);

            $locked->update([
                'status' => ExpenseStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->log($locked, $user, 'expense.approved', ['status' => 'submitted'], ['status' => 'approved']);
            $this->notifyCreator($locked, $user, 'approved');

            return $locked->fresh();
        });
    }

    public function reject(Expense $expense, string $reason, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $reason, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->statusValue() !== ExpenseStatus::Submitted->value) {
                throw ValidationException::withMessages(['expense' => 'المصروف ليس بانتظار الاعتماد.']);
            }

            // Rejection is also an approval-control decision and therefore uses
            // the same segregation-of-duties rule as approval.
            $this->assertIndependentReviewer($locked, $user);

            $locked->update([
                'status' => ExpenseStatus::Rejected,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->log(
                $locked,
                $user,
                'expense.rejected',
                ['status' => 'submitted'],
                ['status' => 'rejected'],
                ['reason' => trim($reason)]
            );
            $this->notifyCreator($locked, $user, 'rejected');

            return $locked->fresh();
        });
    }

    public function post(Expense $expense, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($locked->statusValue() === ExpenseStatus::Posted->value) {
                return $locked;
            }

            if ($locked->statusValue() !== ExpenseStatus::Approved->value) {
                throw ValidationException::withMessages([
                    'expense' => 'يجب اعتماد المصروف قبل ترحيله محاسبيًا.',
                ]);
            }

            $period = $this->periods->openForDate($locked->expense_date, true);
            $key = 'expense:' . $locked->id . ':post';

            SalesLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'location_id' => $locked->location_id,
                    'financial_period_id' => $period->id,
                    'entry_date' => $locked->expense_date,
                    'entry_type' => LedgerEntryType::Expense,
                    'amount' => $locked->amount,
                    'reference_type' => 'expenses',
                    'reference_id' => $locked->id,
                    'description' => 'مصروف ' . $locked->expense_number . ($locked->payee ? ' — ' . $locked->payee : ''),
                    'currency_code' => 'ILS',
                    'created_by' => $user->id,
                ]
            );

            $locked->update([
                'financial_period_id' => $period->id,
                'status' => ExpenseStatus::Posted,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            $this->log($locked, $user, 'expense.posted', ['status' => 'approved'], ['status' => 'posted'], ['financial_period_id' => $period->id]);
            $this->notifyCreator($locked, $user, 'posted');

            return $locked->fresh();
        });
    }

    public function void(Expense $expense, string $reason, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $reason, $user): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($locked->statusValue() === ExpenseStatus::Void->value) {
                return $locked;
            }

            if ($locked->statusValue() !== ExpenseStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'expense' => 'لا يمكن الإلغاء المحاسبي إلا لمصروف مرحّل.',
                ]);
            }

            // Reversals are posted in the currently open period. This preserves
            // closed historical periods instead of silently rewriting them.
            $period = $this->periods->openForDate(now(), true);
            $key = 'expense:' . $locked->id . ':void';

            SalesLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'location_id' => $locked->location_id,
                    'financial_period_id' => $period->id,
                    'entry_date' => now()->toDateString(),
                    'entry_type' => LedgerEntryType::ExpenseReversal,
                    'amount' => $locked->amount,
                    'reference_type' => 'expenses',
                    'reference_id' => $locked->id,
                    'description' => 'عكس المصروف ' . $locked->expense_number . ' — ' . trim($reason),
                    'currency_code' => 'ILS',
                    'created_by' => $user->id,
                ]
            );

            $locked->update([
                'status' => ExpenseStatus::Void,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => trim($reason),
            ]);

            $this->log($locked, $user, 'expense.voided', ['status' => 'posted'], ['status' => 'void'], [
                'reason' => trim($reason),
                'reversal_period_id' => $period->id,
            ]);

            $this->notifyCreator($locked, $user, 'voided');

            return $locked->fresh();
        });
    }

    private function assertIndependentReviewer(Expense $expense, User $user): void
    {
        if ((int) $expense->created_by === (int) $user->id && ! $user->can('expenses.approve_own')) {
            throw ValidationException::withMessages([
                'expense' => 'لا يجوز لمن أنشأ المصروف مراجعة نفس العملية. يلزم اعتماد مستقل.',
            ]);
        }
    }

    private function notifyReviewers(Expense $expense, User $actor, string $event): void
    {
        User::query()
            ->where('is_active', true)
            ->permission('expenses.approve')
            ->with('employee.locations')
            ->get()
            ->filter(function (User $user) use ($expense): bool {
                if (
                    (int) $user->id === (int) $expense->created_by
                    && ! $user->can('expenses.approve_own')
                ) {
                    return false;
                }

                if ($user->isAdmin() || $user->can('expenses.view_all_locations')) {
                    return true;
                }

                return (int) ($user->primaryLocation()?->id ?? 0) === (int) $expense->location_id;
            })
            ->each(fn (User $user) => $user->notify(new ExpenseWorkflowNotification($expense, $event, $actor)));
    }

    private function notifyCreator(Expense $expense, User $actor, string $event): void
    {
        $creator = User::query()->find($expense->created_by);

        if ($creator && $creator->is_active && (int) $creator->id !== (int) $actor->id) {
            $creator->notify(new ExpenseWorkflowNotification($expense, $event, $actor));
        }
    }

    private function log(Expense $expense, User $user, string $action, ?array $old, ?array $new, array $metadata = []): void
    {
        ActivityLogger::log(
            userId: $user->id,
            action: $action,
            module: 'costing',
            recordType: 'expenses',
            recordId: $expense->id,
            oldValues: $old,
            newValues: $new,
            metadata: array_merge(['location_id' => $expense->location_id], $metadata),
        );
    }
}
