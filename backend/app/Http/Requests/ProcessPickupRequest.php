<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccess('borrowings', 'handover') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^(\d{12}|TMCEBMS:[a-f0-9]{64})$/'],
            'status' => ['required', Rule::in(['Received', 'Rejected'])],
            'remarks' => ['required_if:status,Rejected', 'nullable', 'string', 'max:500'],
            'method' => ['required', Rule::in(['manual', 'qr'])],
        ];
    }
}
