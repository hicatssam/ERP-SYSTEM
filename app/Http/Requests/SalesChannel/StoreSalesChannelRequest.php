<?php

namespace App\Http\Requests\SalesChannel;

use App\Enums\CommissionBase;
use App\Enums\DeliveryFeeRecipient;
use App\Enums\DiscountType;
use App\Enums\SalesChannelType;
use App\Enums\SettlementCycle;
use App\Models\SalesChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSalesChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', SalesChannel::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:sales_channels,name'],
            'slug' => ['required', 'string', 'max:160', 'unique:sales_channels,slug'],
            'type' => ['required', Rule::enum(SalesChannelType::class)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'discount_funded_by_channel' => ['required', 'numeric', 'between:0,100'],
            'commission_type' => ['required', Rule::enum(DiscountType::class)],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'commission_base' => ['required', Rule::enum(CommissionBase::class)],
            'delivery_fee_recipient' => ['required', Rule::enum(DeliveryFeeRecipient::class)],
            'settlement_cycle' => ['required', Rule::enum(SettlementCycle::class)],
            'settlement_days' => ['required', 'integer', 'between:0,365'],
            'is_active' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('discount_type') === DiscountType::Percentage->value
                && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'نسبة خصم القناة لا يمكن أن تتجاوز 100%.');
            }

            if ($this->input('commission_type') === DiscountType::Percentage->value
                && (float) $this->input('commission_value', 0) > 100) {
                $validator->errors()->add('commission_value', 'نسبة عمولة القناة لا يمكن أن تتجاوز 100%.');
            }
        });
    }
}
