<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) نوسّع جدول currencies الموجود فقط
        |--------------------------------------------------------------------------
        |
        | لا نغيّر أي عمود مالي موجود:
        | code, name, name_ar, symbol, decimal_places, is_base, is_active
        |
        | نضيف فقط بيانات العرض والإدارة الجديدة.
        */

        if (! Schema::hasColumn('currencies', 'icon')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->string('icon', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('currencies', 'image')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->string('image')->nullable();
            });
        }

        if (! Schema::hasColumn('currencies', 'sort_order')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        if (! Schema::hasColumn('currencies', 'notes')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->text('notes')->nullable();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2) ننقل أي عملات أضيفت في system_currencies إلى currencies
        |--------------------------------------------------------------------------
        |
        | الدمج يعتمد على code.
        |
        | - العملة الموجودة أصلاً في currencies لا نغيّر بياناتها المالية.
        | - ننقل لها فقط icon/image/sort_order/notes.
        | - العملة غير الموجودة (مثل SAR/JOD إن أضيفت هناك) ننشئها في currencies.
        | - لا نغيّر العملة الأساسية الحالية is_base.
        */

        if (Schema::hasTable('system_currencies')) {
            $legacyCurrencies = DB::table('system_currencies')
                ->orderBy('id')
                ->get();

            foreach ($legacyCurrencies as $legacy) {
                $code = strtoupper(trim((string) ($legacy->code ?? '')));

                if ($code === '') {
                    continue;
                }

                $existing = DB::table('currencies')
                    ->where('code', $code)
                    ->first();

                if ($existing) {
                    $update = [
                        'updated_at' => now(),
                    ];

                    if (
                        Schema::hasColumn('system_currencies', 'icon')
                        && ! empty($legacy->icon)
                    ) {
                        $update['icon'] = $legacy->icon;
                    }

                    if (
                        Schema::hasColumn('system_currencies', 'image')
                        && ! empty($legacy->image)
                    ) {
                        $update['image'] = $legacy->image;
                    }

                    if (Schema::hasColumn('system_currencies', 'sort_order')) {
                        $update['sort_order'] = (int) ($legacy->sort_order ?? 0);
                    }

                    if (
                        Schema::hasColumn('system_currencies', 'notes')
                        && ! empty($legacy->notes)
                    ) {
                        $update['notes'] = $legacy->notes;
                    }

                    DB::table('currencies')
                        ->where('id', $existing->id)
                        ->update($update);

                    continue;
                }

                $nameEn = Schema::hasColumn('system_currencies', 'name_en')
                    ? trim((string) ($legacy->name_en ?? ''))
                    : '';

                $nameAr = trim((string) ($legacy->name_ar ?? ''));

                DB::table('currencies')->insert([
                    'code' => $code,
                    'name' => $nameEn !== ''
                        ? $nameEn
                        : ($nameAr !== '' ? $nameAr : $code),
                    'name_ar' => $nameAr !== '' ? $nameAr : null,
                    'symbol' => (string) ($legacy->symbol ?? ''),
                    'decimal_places' => (int) ($legacy->decimal_places ?? 2),

                    // مهم: لا نجعل العملة المنقولة Base تلقائياً.
                    'is_base' => false,
                    'is_active' => (bool) ($legacy->is_active ?? true),

                    'icon' => Schema::hasColumn('system_currencies', 'icon')
                        ? ($legacy->icon ?? null)
                        : null,
                    'image' => Schema::hasColumn('system_currencies', 'image')
                        ? ($legacy->image ?? null)
                        : null,
                    'sort_order' => Schema::hasColumn('system_currencies', 'sort_order')
                        ? (int) ($legacy->sort_order ?? 0)
                        : 0,
                    'notes' => Schema::hasColumn('system_currencies', 'notes')
                        ? ($legacy->notes ?? null)
                        : null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3) حماية فقط إذا لم توجد عملة أساسية أصلاً
        |--------------------------------------------------------------------------
        |
        | النظام الحالي للمشتريات يعتمد على is_base.
        | نحن لا نغيّر Base موجودة.
        */

        $hasBase = DB::table('currencies')
            ->where('is_base', true)
            ->exists();

        if (! $hasBase) {
            $base = DB::table('currencies')
                ->where('code', 'ILS')
                ->first()
                ?? DB::table('currencies')
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first()
                ?? DB::table('currencies')
                    ->orderBy('id')
                    ->first();

            if ($base) {
                DB::table('currencies')
                    ->where('id', $base->id)
                    ->update([
                        'is_base' => true,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        /*
         * Rollback غير هدّام:
         * لا نحذف العملات التي تم دمجها لأن بعضها قد يكون استُخدم بعد الدمج.
         * فقط نزيل حقول الواجهة الجديدة.
         */

        if (! Schema::hasTable('currencies')) {
            return;
        }

        $columns = array_values(array_filter(
            ['icon', 'image', 'sort_order', 'notes'],
            fn (string $column): bool => Schema::hasColumn('currencies', $column)
        ));

        if ($columns !== []) {
            Schema::table('currencies', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
