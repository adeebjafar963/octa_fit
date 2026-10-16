<?php

namespace App\Http\Requests\Store;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => [
                'required',
                'string',
                Rule::in(Order::selectablePaymentMethods()),
            ],
            'payment_phone' => [
                Rule::excludeIf(fn () => $this->input('payment_method') !== Order::PAYMENT_SYRIATEL_CASH),
                'required',
                'string',
                'regex:'.Order::SYRIATEL_PHONE_PATTERN,
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_phone.required' => 'Mobile number is required.',
            'payment_phone.regex' => 'Invalid mobile number.',
        ];
    }
}
