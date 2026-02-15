<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalOccurrenceRequest extends FormRequest
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
            'externalId' => 'required|string',
            'type' => 'required|string',
            'description' => 'required|string',
            'reportedAt' => 'required|date',
        ];
    }
}
