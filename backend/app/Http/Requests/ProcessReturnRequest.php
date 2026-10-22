<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccess('returns', 'process') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
            'remarks' => ['required_if:status,Rejected', 'nullable', 'string', 'max:500'],
            'handover_starts_at' => ['required_if:status,Approved', 'nullable', 'date', 'after_or_equal:now'],
            'handover_ends_at' => ['required_if:status,Approved', 'nullable', 'date', 'after:handover_starts_at'],
        ];
    }
}
