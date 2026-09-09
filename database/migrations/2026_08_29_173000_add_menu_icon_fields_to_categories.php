<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'icon_key')) {
                $table->string('icon_key', 40)
                    ->nullable()
                    ->after('image');
            }

            if (! Schema::hasColumn('categories', 'icon_color')) {
                $table->string('icon_color', 7)
                    ->nullable()
                    ->after('icon_key');
            }
        });

        // Safe automatic defaults for existing categories.
        DB::table('categories')
            ->select(['id', 'slug', 'name', 'name_ar', 'icon_key'])
            ->orderBy('id')
            ->get()
            ->each(function ($category): void {
                if (filled($category->icon_key)) {
                    return;
                }

                $haystack = Str::lower(
                    implode(' ', [
                        (string) $category->slug,
                        (string) $category->name,
                        (string) $category->name_ar,
                    ])
                );

                $icon = match (true) {
                    Str::contains($haystack, ['burger', 'برغر', 'برجر']) => 'burger',
                    Str::contains($haystack, ['pizza', 'بيتزا']) => 'pizza',
                    Str::contains($haystack, ['coffee', 'قهوة', 'كافيه']) => 'coffee',
                    Str::contains($haystack, ['cake', 'كيك', 'جاتوه', 'غاتوه']) => 'cake',
                    Str::contains($haystack, ['donut', 'دونات']) => 'donut',
                    Str::contains($haystack, ['ice', 'آيس', 'ايس كريم']) => 'icecream',
                    Str::contains($haystack, ['drink', 'مشروب', 'مشروبات']) => 'drink',
                    Str::contains($haystack, ['juice', 'عصير']) => 'juice',
                    Str::contains($haystack, ['fries', 'بطاط']) => 'fries',
                    Str::contains($haystack, ['chicken', 'دجاج']) => 'chicken',
                    Str::contains($haystack, ['salad', 'سلط']) => 'salad',
                    Str::contains($haystack, ['sandwich', 'ساند']) => 'sandwich',
                    Str::contains($haystack, ['chocolate', 'شوكولات']) => 'chocolate',
                    Str::contains($haystack, ['gift', 'هدايا', 'هدية']) => 'gift',
                    Str::contains($haystack, ['bakery', 'مخبز', 'مخبوز']) => 'croissant',
                    Str::contains($haystack, ['oriental', 'شرقي']) => 'dessert',
                    default => 'sparkles',
                };

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update([
                        'icon_key' => $icon,
                        'icon_color' => '#111111',
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $drop = [];

        if (Schema::hasColumn('categories', 'icon_color')) {
            $drop[] = 'icon_color';
        }

        if (Schema::hasColumn('categories', 'icon_key')) {
            $drop[] = 'icon_key';
        }

        if ($drop !== []) {
            Schema::table('categories', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }
};
