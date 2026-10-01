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
        Schema::create('employee_hr_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('emergency_name', 190)->nullable();
            $table->string('emergency_phone', 40)->nullable();
            $table->string('emergency_relationship', 100)->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->date('onboarded_on')->nullable();
            $table->date('offboarded_on')->nullable();
            $table->text('lifecycle_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title', 190);
            $table->string('kind', 50);
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['expires_on', 'employee_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['hr.dashboard.view', 'hr.documents.view', 'hr.documents.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::query()->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'super-admin', 'General Manager'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo([
                'hr.dashboard.view', 'hr.documents.view', 'hr.documents.manage',
            ]));
        Role::query()->where('guard_name', 'web')->where('name', 'Branch Manager')
            ->get()->each(fn (Role $role) => $role->givePermissionTo([
                'hr.dashboard.view', 'hr.documents.view',
            ]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_hr_profiles');
    }
};
