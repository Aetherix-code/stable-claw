<?php

namespace App\Http\Requests\Secretary;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:10000'],
            'type' => ['required', 'string', 'in:fact,credential,preference'],
            'is_sensitive' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
