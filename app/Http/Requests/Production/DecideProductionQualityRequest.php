<?php

namespace App\Http\Requests\Production;

use App\Enums\QualityCheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideProductionQualityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('quality_control.decide') ?? false;
    }

    public function rules(): array
    {
        return [
            'accepted_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
            'rejected_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'criteria' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'criteria.*.criterion' => [
                'required',
                'string',
                'max:180',
            ],
            'criteria.*.result' => [
                'required',
                Rule::enum(QualityCheckResult::class),
            ],
            'criteria.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
