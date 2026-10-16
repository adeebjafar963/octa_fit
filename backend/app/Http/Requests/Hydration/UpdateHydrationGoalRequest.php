<?php

namespace App\Http\Requests\Hydration;

use App\Services\HydrationGoalService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHydrationGoalRequest extends FormRequest
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
            'goal_ml' => [
                'required',
                'integer',
                'min:'.HydrationGoalService::MIN_GOAL_ML,
                'max:'.HydrationGoalService::MAX_GOAL_ML,
            ],
            'reset_to_recommended' => ['sometimes', 'boolean'],
        ];
    }
}
