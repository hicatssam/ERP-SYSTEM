<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 190);
            $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['location_id', 'is_active']);
        });

        Schema::create('hr_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 190);
            $table->foreignId('department_id')->constrained('hr_departments')->restrictOnDelete();
            $table->string('grade', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['department_id', 'is_active']);
        });

        Schema::create('hr_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 190);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['location_id', 'is_active']);
        });

        Schema::create('employee_org_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('hr_cost_centers')->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from'], 'org_employee_start_unique');
            $table->index(['department_id', 'effective_from', 'effective_to'], 'org_department_dates_idx');
            $table->index(['cost_center_id', 'effective_from', 'effective_to'], 'org_center_dates_idx');
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->foreignId('org_assignment_id')->nullable()
                ->constrained('employee_org_assignments')->nullOnDelete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['hr.organization.view', 'hr.organization.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::query()->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'super-admin', 'General Manager'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo([
                'hr.organization.view', 'hr.organization.manage',
            ]));
        Role::query()->where('guard_name', 'web')->where('name', 'Branch Manager')
            ->get()->each(fn (Role $role) => $role->givePermissionTo('hr.organization.view'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('org_assignment_id');
        });
        Schema::dropIfExists('employee_org_assignments');
        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_cost_centers');
        Schema::dropIfExists('hr_departments');
    }
};
