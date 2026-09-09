<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentArrangement;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quick_sale' => [
                'nullable',
                'boolean',
            ],

            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],

            'payment_arrangement' => [
                'required',
                Rule::in(
                    array_map(
                        fn (PaymentArrangement $arrangement): string =>
                            $arrangement->value,
                        PaymentArrangement::cases()
                    )
                ),
            ],

            'payment_method_id' => [
                'nullable',
                'integer',
                'exists:payment_methods,id',
            ],

            'paid_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.001',
            ],

            'sales_channel_id' => [
                'required',
                'integer',
                'exists:sales_channels,id',
            ],

            'discount_type' => [
                'nullable',
                Rule::in([
                    'none',
                    'percentage',
                    'fixed',
                ]),
            ],

            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'customer_id.required' =>
                'يجب اختيار عميل مسجل للبيع على الحساب.',

            'payment_method_id.required' =>
                'طريقة الدفع مطلوبة.',

            'reference_number.required' =>
                'رقم عملية الدفع أو الحوالة مطلوب لطريقة الدفع المحددة.',

            'payment_proof.required' =>
                'صورة إثبات الدفع مطلوبة لطريقة الدفع المحددة.',

            'payment_proof.file' =>
                'يجب رفع ملف صالح كإثبات للدفع.',

            'payment_proof.mimes' =>
                'صورة إثبات الدفع يجب أن تكون بصيغة JPG أو JPEG أو PNG أو WEBP.',

            'payment_proof.max' =>
                'حجم صورة إثبات الدفع يجب ألا يتجاوز 10 ميجابايت.',

            'items.required' =>
                'يجب إضافة منتج واحد على الأقل.',

            'items.min' =>
                'يجب إضافة منتج واحد على الأقل.',

            'items.*.product_id.required' =>
                'يجب اختيار المنتج.',

            'items.*.quantity.required' =>
                'يجب إدخال الكمية.',

            'items.*.quantity.min' =>
                'الكمية يجب أن تكون أكبر من صفر.',

            'sales_channel_id.required' =>
                'يجب اختيار قناة البيع.',
        ];
    }


    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {

            $arrangement =
                (string) $this->input(
                    'payment_arrangement'
                );

            $quickSale =
                $this->boolean(
                    'quick_sale'
                );


            /*
            |--------------------------------------------------------------------------
            | البيع السريع
            |--------------------------------------------------------------------------
            |
            | البيع السريع:
            |
            | - بدون عميل مسجل
            | - دفع فوري
            | - يسمح بالنقدي
            | - يسمح بالتحويل البنكي
            | - يسمح بالمحافظ الإلكترونية
            | - يسمح بأي طريقة دفع مفعلة
            |
            | وإذا كانت الطريقة تحتاج تحقق:
            | نطلب رقم العملية + إثبات الدفع،
            | لكن لا نمنع البيع السريع.
            |
            */

            if ($quickSale) {

                if ($this->filled('customer_id')) {

                    $validator
                        ->errors()
                        ->add(
                            'customer_id',
                            'البيع السريع مخصص للعميل النقدي بدون بيانات.'
                        );
                }


                if (
                    $arrangement
                    !==
                    PaymentArrangement::PayNow->value
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'payment_arrangement',
                            'البيع السريع يجب أن يكون دفعًا فوريًا.'
                        );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | العربون / الدفع الجزئي
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $arrangement,
                    [
                        PaymentArrangement::Deposit->value,
                        PaymentArrangement::PartialPayment->value,
                    ],
                    true
                )
            ) {

                if (
                    ! $this->filled('paid_amount')
                    ||
                    (float) $this->input('paid_amount')
                    <= 0
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'paid_amount',
                            'يجب إدخال المبلغ المدفوع للعربون أو الدفع الجزئي.'
                        );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | البيع على الحساب
            |--------------------------------------------------------------------------
            */

            if (
                $arrangement
                ===
                PaymentArrangement::OnAccount->value
            ) {

                if (
                    ! $this->filled(
                        'customer_id'
                    )
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'customer_id',
                            'يجب اختيار عميل مسجل للبيع على الحساب.'
                        );
                }

            } elseif (
                $arrangement
                !==
                PaymentArrangement::PayOnPickup->value
                &&
                ! $this->filled(
                    'payment_method_id'
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | طريقة الدفع مطلوبة لأي دفع فوري
                |--------------------------------------------------------------------------
                */

                $validator
                    ->errors()
                    ->add(
                        'payment_method_id',
                        'طريقة الدفع مطلوبة.'
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | ترتيب دفع: بانتظار التحقق
            |--------------------------------------------------------------------------
            */

            if (
                $arrangement
                ===
                PaymentArrangement::PendingVerification->value
            ) {

                if (
                    ! $this->filled(
                        'reference_number'
                    )
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'reference_number',
                            'رقم العملية / الحوالة مطلوب للدفعة بانتظار التحقق.'
                        );
                }


                if (
                    ! $this->hasFile(
                        'payment_proof'
                    )
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'payment_proof',
                            'إثبات الدفع مطلوب للدفعة بانتظار التحقق.'
                        );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | هل يوجد دفع الآن؟
            |--------------------------------------------------------------------------
            */

            $noImmediatePayment =
                in_array(
                    $arrangement,
                    [
                        PaymentArrangement::PayOnPickup->value,
                        PaymentArrangement::OnAccount->value,
                    ],
                    true
                );


            /*
            |--------------------------------------------------------------------------
            | التحقق من طريقة الدفع
            |--------------------------------------------------------------------------
            */

            if (
                $this->filled(
                    'payment_method_id'
                )
                &&
                ! $noImmediatePayment
            ) {

                $method =
                    PaymentMethod::query()
                        ->active()
                        ->find(
                            $this->integer(
                                'payment_method_id'
                            )
                        );


                /*
                |--------------------------------------------------------------------------
                | الطريقة غير موجودة أو غير مفعلة
                |--------------------------------------------------------------------------
                */

                if (! $method) {

                    $validator
                        ->errors()
                        ->add(
                            'payment_method_id',
                            'طريقة الدفع المحددة غير مفعّلة أو غير موجودة.'
                        );

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | رقم العملية / الحوالة
                    |--------------------------------------------------------------------------
                    |
                    | مطلوب إذا كانت طريقة الدفع:
                    |
                    | requires_reference
                    | أو
                    | requires_verification
                    |
                    */

                    if (
                        (
                            (bool) $method->requires_reference
                            ||
                            (bool) $method->requires_verification
                        )
                        &&
                        ! $this->filled(
                            'reference_number'
                        )
                    ) {

                        $validator
                            ->errors()
                            ->add(
                                'reference_number',
                                'رقم العملية / الحوالة مطلوب لطريقة الدفع المحددة.'
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | إثبات الدفع
                    |--------------------------------------------------------------------------
                    |
                    | إذا كانت الطريقة تحتاج تحقق،
                    | يجب رفع صورة إثبات الدفع.
                    |
                    | وهذا مسموح أيضًا في البيع السريع.
                    |
                    */

                    if (
                        (bool) $method->requires_verification
                        &&
                        ! $this->hasFile(
                            'payment_proof'
                        )
                    ) {

                        $validator
                            ->errors()
                            ->add(
                                'payment_proof',
                                'إثبات الدفع مطلوب لطريقة الدفع المحددة.'
                            );
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | قناة البيع
            |--------------------------------------------------------------------------
            */

            if (
                $this->filled(
                    'sales_channel_id'
                )
                &&
                ! SalesChannel::query()
                    ->active()
                    ->whereKey(
                        $this->input(
                            'sales_channel_id'
                        )
                    )
                    ->exists()
            ) {

                $validator
                    ->errors()
                    ->add(
                        'sales_channel_id',
                        'قناة البيع المحددة غير مفعّلة أو غير موجودة.'
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | نسبة الخصم
            |--------------------------------------------------------------------------
            */

            if (
                $this->input(
                    'discount_type'
                )
                ===
                'percentage'
                &&
                (float) $this->input(
                    'discount_value',
                    0
                )
                > 100
            ) {

                $validator
                    ->errors()
                    ->add(
                        'discount_value',
                        'نسبة الخصم لا يمكن أن تتجاوز 100%.'
                    );
            }
        });
    }
}