<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);
            if (! is_array($settings)) {
                return;
            }
            foreach (array_keys($settings) as $key) {
                if (! in_array($key, AdminSettingsService::ALLOWED_KEYS, true)) {
                    $validator->errors()->add('settings.'.$key, 'Setting key is not allowed.');
                }
            }
        });
    }
}
