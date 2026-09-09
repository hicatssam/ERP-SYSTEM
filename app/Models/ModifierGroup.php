<?php

namespace App\Models;

use App\Enums\ModifierSelectionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'name_ar', 'selection_type',
        'min_selections', 'max_selections', 'is_required',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'selection_type' => ModifierSelectionType::class,
            'min_selections' => 'integer',
            'max_selections' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function productLinks(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class);
    }
}
