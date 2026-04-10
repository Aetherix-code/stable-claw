<?php

namespace App\Http\Requests\Secretary;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_keywords' => ['nullable', 'array'],
            'trigger_keywords.*' => ['string', 'max:100'],
            'steps' => ['nullable', 'array'],
            'memory_keys' => ['nullable', 'array'],
        ];
    }
}
