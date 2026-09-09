<?php

namespace App\Http\Requests\Procurement;

use App\Models\SupplierProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Purchase Order
            |--------------------------------------------------------------------------
            */

            'supplier_id' => [
                'required',
                'integer',

                Rule::exists(
                    'suppliers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'status',
                                'active'
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                ),
            ],

            'location_id' => [
                'required',
                'integer',
                Rule::exists(
                    'locations',
                    'id'
                ),
            ],

            'currency_id' => [
                'required',
                'integer',

                Rule::exists(
                    'currencies',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            'exchange_rate' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'order_date' => [
                'required',
                'date',
            ],

            'expected_delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:order_date',
            ],

            'discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'tax_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'shipping_cost' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
            ],


            /*
            |--------------------------------------------------------------------------
            | Items
            |--------------------------------------------------------------------------
            */

            'items' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',

                Rule::exists(
                    'products',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                ),
            ],

            'items.*.description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.ordered_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.tax_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * تحقق إضافي:
     *
     * 1. المنتج تابع فعلاً لهذا المورد.
     * 2. علاقة المنتج بالمورد فعالة.
     * 3. احترام MOQ.
     * 4. جميع منتجات أمر الشراء بنفس العملة.
     */
    public function withValidator(
        Validator $validator
    ): void {

        $validator->after(
            function (Validator $validator): void {

                $supplierId =
                    (int) $this->input(
                        'supplier_id'
                    );

                $currencyId =
                    (int) $this->input(
                        'currency_id'
                    );

                $items =
                    $this->input(
                        'items',
                        []
                    );

                if (
                    $supplierId <= 0
                    ||
                    ! is_array($items)
                ) {
                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Load Supplier Products Once
                |--------------------------------------------------------------------------
                */

                $productIds =
                    collect($items)
                        ->pluck('product_id')
                        ->filter()
                        ->map(
                            fn ($id) => (int) $id
                        )
                        ->unique()
                        ->values();


                if ($productIds->isEmpty()) {
                    return;
                }


                $supplierProducts =
                    SupplierProduct::query()

                        ->where(
                            'supplier_id',
                            $supplierId
                        )

                        ->whereIn(
                            'product_id',
                            $productIds
                        )

                        ->where(
                            'is_active',
                            true
                        )

                        ->get()

                        ->keyBy('product_id');


                foreach (
                    $items
                    as
                    $index => $item
                ) {

                    $productId =
                        (int) (
                            $item['product_id']
                            ?? 0
                        );

                    if ($productId <= 0) {
                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Product Must Belong To Supplier
                    |--------------------------------------------------------------------------
                    */

                    $supplierProduct =
                        $supplierProducts->get(
                            $productId
                        );


                    if (! $supplierProduct) {

                        $validator
                            ->errors()
                            ->add(
                                "items.$index.product_id",
                                'هذا الصنف غير مرتبط بالمورد المحدد أو تم إيقافه لهذا المورد.'
                            );

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Currency
                    |--------------------------------------------------------------------------
                    |
                    | أمر الشراء عندنا لديه عملة واحدة.
                    | لذلك لا نسمح بإضافة منتج مسعر بعملة أخرى.
                    |
                    */

                    if (
                        $currencyId > 0
                        &&
                        (int) $supplierProduct
                            ->currency_id
                            !==
                        $currencyId
                    ) {

                        $validator
                            ->errors()
                            ->add(
                                "items.$index.product_id",
                                'عملة هذا الصنف لدى المورد تختلف عن عملة أمر الشراء. أنشئ أمر شراء منفصل للعملة الأخرى.'
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Minimum Order Quantity
                    |--------------------------------------------------------------------------
                    */

                    $orderedQuantity =
                        (float) (
                            $item[
                                'ordered_quantity'
                            ]
                            ?? 0
                        );

                    $minimumQuantity =
                        (float) $supplierProduct
                            ->minimum_order_quantity;


                    if (
                        $minimumQuantity > 0
                        &&
                        $orderedQuantity
                            < $minimumQuantity
                    ) {

                        $validator
                            ->errors()
                            ->add(
                                "items.$index.ordered_quantity",
                                'الحد الأدنى للطلب من هذا المورد هو '
                                .
                                number_format(
                                    $minimumQuantity,
                                    3,
                                    '.',
                                    ''
                                )
                                .
                                '.'
                            );
                    }
                }
            }
        );
    }

    public function messages(): array
    {
        return [
            'items.required' =>
                'يجب إضافة صنف واحد على الأقل.',

            'items.min' =>
                'يجب إضافة صنف واحد على الأقل.',

            'items.*.product_id.required' =>
                'اختر المنتج.',

            'items.*.product_id.distinct' =>
                'لا يمكن إضافة نفس المنتج أكثر من مرة.',

            'items.*.ordered_quantity.required' =>
                'أدخل الكمية المطلوبة.',

            'items.*.ordered_quantity.gt' =>
                'يجب أن تكون الكمية أكبر من صفر.',

            'items.*.unit_price.required' =>
                'أدخل سعر الشراء.',
        ];
    }
}