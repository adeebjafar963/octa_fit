<?php

namespace App\Http\Requests\Hydration;

use App\Services\HydrationGoalService;
use Illuminate\Foundation\Http\FormRequest;

class StoreWaterIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_ml' => ['required', 'integer', 'min:1', 'max:5000'],
            'consumed_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
