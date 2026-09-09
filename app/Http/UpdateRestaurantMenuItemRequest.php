<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant_menu.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],

            'display_name' => ['nullable', 'string', 'max:255'],
            'display_name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:png,webp,jpg,jpeg', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'is_active' => ['nullable', 'boolean'],
            'show_in_pos' => ['nullable', 'boolean'],
            'show_in_qr' => ['nullable', 'boolean'],
            'show_in_delivery' => ['nullable', 'boolean'],

            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
