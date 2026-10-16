<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(AdminOrderService::ALLOWED_STATUSES)],
        ];
    }
}
