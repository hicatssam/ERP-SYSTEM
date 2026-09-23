<?php

namespace Tests\Feature\Payroll;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Location;
use App\Models\EmployeeAdvanceRepayment;
use App\Models\EmployeeCompensationProfile;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\EmployeeAdvanceRepaymentService;
use App\Services\EmployeeLedgerService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeAdvanceRepaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cash_repayment_reduces_advance_and_appears_as_credit_in_employee_statement(): void
    {
        [$employee, $actor] = $this->employeeWithCurrency();
        $cash = $this->paymentMethod(
            'cash',
            requiresVerification: false,
            requiresReference: false
        );

        $advance = app(PayrollService::class)->issueAdvance(
            $employee,
            [
                'amount' => 100,
                'issued_at' => now()->toDateString(),
                'payment_method_id' => $cash->id,
                'reference' => 'ADV-100',
                'notes' => 'سلفة اختبار',
            ],
            $actor
        );

        $ledger = app(EmployeeLedgerService::class);

        $this->assertSame(-100.0, $ledger->balance($employee));

        $repayment = app(EmployeeAdvanceRepaymentService::class)->create(
            $advance,
            [
                'amount' => 30,
                'payment_method_id' => $cash->id,
                'paid_at' => now(),
                'reference' => 'RPY-30',
                'notes' => 'سداد نقدي',
            ],
            null,
            $actor
        );

        $advance->refresh();

        $this->assertSame('posted', $repayment->status);
        $this->assertSame(30.0, (float) $advance->recovered_amount);
        $this->assertSame(70.0, (float) $advance->outstanding_amount);
        $this->assertSame('open', $advance->status);
        $this->assertSame(-70.0, $ledger->balance($employee));

        $this->assertDatabaseHas('employee_ledger_entries', [
            'employee_id' => $employee->id,
            'direction' => 'credit',
            'entry_type' => 'advance_repayment',
            'source_type' => 'employee_advance_repayment',
            'source_id' => $repayment->id,
            'reference' => 'RPY-30',
        ]);
    }

    #[Test]
    public function employee_cannot_repay_more_than_remaining_advance(): void
    {
        [$employee, $actor] = $this->employeeWithCurrency();
        $cash = $this->paymentMethod(
            'cash',
            requiresVerification: false,
            requiresReference: false
        );

        $advance = app(PayrollService::class)->issueAdvance(
            $employee,
            [
                'amount' => 100,
                'issued_at' => now()->toDateString(),
                'payment_method_id' => $cash->id,
            ],
            $actor
        );

        $this->expectException(ValidationException::class);

        app(EmployeeAdvanceRepaymentService::class)->create(
            $advance,
            [
                'amount' => 100.01,
                'payment_method_id' => $cash->id,
                'paid_at' => now(),
            ],
            null,
            $actor
        );
    }

    #[Test]
    public function pending_bank_repayment_does_not_reduce_advance_until_verified(): void
    {
        Storage::fake('public');

        [$employee, $actor] = $this->employeeWithCurrency();
        $cash = $this->paymentMethod(
            'cash',
            requiresVerification: false,
            requiresReference: false
        );
        $bank = $this->paymentMethod(
            'bank_transfer',
            requiresVerification: true,
            requiresReference: true
        );

        $advance = app(PayrollService::class)->issueAdvance(
            $employee,
            [
                'amount' => 100,
                'issued_at' => now()->toDateString(),
                'payment_method_id' => $cash->id,
            ],
            $actor
        );

        $repayment = app(EmployeeAdvanceRepaymentService::class)->create(
            $advance,
            [
                'amount' => 40,
                'payment_method_id' => $bank->id,
                'paid_at' => now(),
                'reference' => 'BANK-RPY-40',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $actor
        );

        $advance->refresh();

        $this->assertSame(
            'pending_verification',
            $repayment->status
        );
        $this->assertSame(
            100.0,
            (float) $advance->outstanding_amount
        );
        $this->assertSame(
            -100.0,
            app(EmployeeLedgerService::class)->balance($employee)
        );

        app(EmployeeAdvanceRepaymentService::class)->verify(
            $repayment,
            $actor
        );

        $repayment->refresh();
        $advance->refresh();

        $this->assertSame('posted', $repayment->status);
        $this->assertSame(40.0, (float) $advance->recovered_amount);
        $this->assertSame(60.0, (float) $advance->outstanding_amount);
        $this->assertSame(
            -60.0,
            app(EmployeeLedgerService::class)->balance($employee)
        );
    }

    #[Test]
    public function pending_repayments_reserve_the_remaining_advance_balance(): void
    {
        Storage::fake('public');

        [$employee, $actor] = $this->employeeWithCurrency();
        $cash = $this->paymentMethod(
            'cash',
            requiresVerification: false,
            requiresReference: false
        );
        $bank = $this->paymentMethod(
            'bank_transfer',
            requiresVerification: true,
            requiresReference: true
        );

        $advance = app(PayrollService::class)->issueAdvance(
            $employee,
            [
                'amount' => 100,
                'issued_at' => now()->toDateString(),
                'payment_method_id' => $cash->id,
            ],
            $actor
        );

        app(EmployeeAdvanceRepaymentService::class)->create(
            $advance,
            [
                'amount' => 80,
                'payment_method_id' => $bank->id,
                'paid_at' => now(),
                'reference' => 'BANK-PENDING-80',
            ],
            UploadedFile::fake()->image('proof-80.jpg'),
            $actor
        );

        try {
            app(EmployeeAdvanceRepaymentService::class)->create(
                $advance,
                [
                    'amount' => 21,
                    'payment_method_id' => $cash->id,
                    'paid_at' => now(),
                ],
                null,
                $actor
            );

            $this->fail('Expected over-reserved advance repayment to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $this->assertSame(
            1,
            EmployeeAdvanceRepayment::query()->count()
        );
    }


    #[Test]
    public function payroll_period_cannot_be_approved_while_advance_repayment_is_pending(): void
    {
        Storage::fake('public');

        [$employee, $actor] = $this->employeeWithCurrency();

        $cash = $this->paymentMethod(
            'cash',
            requiresVerification: false,
            requiresReference: false
        );

        $bank = $this->paymentMethod(
            'bank_transfer',
            requiresVerification: true,
            requiresReference: true
        );

        $advance = app(PayrollService::class)->issueAdvance(
            $employee,
            [
                'amount' => 100,
                'issued_at' => now()->toDateString(),
                'payment_method_id' => $cash->id,
            ],
            $actor
        );

        app(EmployeeAdvanceRepaymentService::class)->create(
            $advance,
            [
                'amount' => 80,
                'payment_method_id' => $bank->id,
                'paid_at' => now(),
                'reference' => 'PENDING-BEFORE-PAYROLL',
            ],
            UploadedFile::fake()->image('pending-proof.jpg'),
            $actor
        );

        $currencyId = Currency::query()
            ->where('is_base', true)
            ->value('id');

        $period = PayrollPeriod::query()->create([
            'code' => 'PAY-PENDING-' . uniqid(),
            'name' => 'دورة اختبار سداد معلق',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'calculated',
            'currency_id' => $currencyId,
            'created_by' => $actor->id,
        ]);

        PayrollItem::query()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'base_salary' => 1000,
            'allowances_total' => 0,
            'bonuses_total' => 0,
            'deductions_total' => 0,
            'gross_salary' => 1000,
            'net_salary' => 1000,
            'payable_amount' => 900,
            'status' => 'calculated',
        ]);

        try {
            app(PayrollService::class)->approvePeriod(
                $period,
                $actor
            );

            $this->fail(
                'Expected payroll approval to be blocked by pending advance repayment.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payroll',
                $exception->errors()
            );
        }

        $this->assertSame(
            'calculated',
            $period->fresh()->status
        );

        $this->assertSame(
            100.0,
            (float) $advance->fresh()->outstanding_amount
        );
    }


    #[Test]
    public function branch_user_cannot_repay_advance_for_employee_at_another_location(): void
    {
        $branchA = Location::query()->create([
            'name' => 'فرع الرواتب A',
            'code' => 'PAY-A-' . uniqid(),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $branchB = Location::query()->create([
            'name' => 'فرع الرواتب B',
            'code' => 'PAY-B-' . uniqid(),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $user->employee->locations()->attach(
            $branchA->id,
            ['is_primary' => true]
        );

        $permission = Permission::findOrCreate(
            'payroll.advances.manage',
            'web'
        );

        $user->givePermissionTo($permission);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $otherEmployee = Employee::query()->create([
            'employee_number' => 'EMP-OTHER-' . uniqid(),
            'full_name' => 'موظف فرع آخر',
            'employment_status' => 'active',
        ]);

        $otherEmployee->locations()->attach(
            $branchB->id,
            ['is_primary' => true]
        );

        $advance = EmployeeAdvance::query()->create([
            'employee_id' => $otherEmployee->id,
            'amount' => 100,
            'recovered_amount' => 0,
            'outstanding_amount' => 100,
            'issued_at' => now()->toDateString(),
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                route(
                    'payroll.employees.advances.repayments.store',
                    [
                        'employee' => $otherEmployee,
                        'advance' => $advance,
                    ]
                ),
                []
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'employee_advance_repayments',
            0
        );
    }

    private function employeeWithCurrency(): array
    {
        $actor = User::factory()->create();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-RPY-' . uniqid(),
            'full_name' => 'موظف اختبار السلفة',
            'employment_status' => 'active',
        ]);

        $currency = Currency::query()->create([
            'code' => 'ILS',
            'name' => 'Israeli New Shekel',
            'name_ar' => 'شيكل',
            'symbol' => '₪',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
        ]);

        EmployeeCompensationProfile::query()->create([
            'employee_id' => $employee->id,
            'salary_basis' => 'monthly',
            'base_salary' => 1000,
            'currency_id' => $currency->id,
            'effective_from' => now()->subMonth()->toDateString(),
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        return [$employee, $actor];
    }

    private function paymentMethod(
        string $type,
        bool $requiresVerification,
        bool $requiresReference
    ): PaymentMethod {
        return PaymentMethod::query()->create([
            'name' => 'Test ' . $type . ' ' . uniqid(),
            'name_ar' => 'طريقة اختبار',
            'code' => 'test-' . $type . '-' . uniqid(),
            'type' => $type,
            'requires_verification' => $requiresVerification,
            'requires_reference' => $requiresReference,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
