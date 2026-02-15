<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key'),]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string',
            'resourceCode' => 'required|string|max:25',
        ];
    }
}
