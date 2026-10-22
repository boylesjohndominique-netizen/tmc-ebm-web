<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccess('borrowings', 'process') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
            'remarks' => ['nullable', 'string', 'max:500'],
            'pickup_starts_at' => ['required_if:status,Approved', 'nullable', 'date', 'after_or_equal:now'],
            'pickup_ends_at' => ['required_if:status,Approved', 'nullable', 'date', 'after:pickup_starts_at'],
        ];
    }
}
