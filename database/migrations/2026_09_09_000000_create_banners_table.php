<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            // location_id = null  =>  البانر يظهر بكل الفروع (بانر عام)
            // location_id = رقم   =>  البانر يظهر بفرع محدد فقط
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations')
                ->cascadeOnDelete();

            $table->string('image')->nullable();
            $table->string('kicker')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_link')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // جدولة اختيارية: لو بدك بانر يشتغل بفترة معينة بس (عرض عيد مثلاً)
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
