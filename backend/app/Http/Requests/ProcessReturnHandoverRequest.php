<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessReturnHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccess('returns', 'handover') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^(\d{12}|TMCEBMS:RETURN:[a-f0-9]{64})$/'],
            'status' => ['required', Rule::in(['Confirmed', 'Rejected'])],
            'method' => ['required', Rule::in(['manual', 'qr'])],
            'remarks' => ['required_if:status,Rejected', 'nullable', 'string', 'max:500'],
            'student_id_number' => ['required_if:status,Confirmed', 'nullable', 'string', 'max:20'],
            'equipment_checked' => ['required_if:status,Confirmed', 'accepted_if:status,Confirmed'],
            'condition_on_return' => ['required', Rule::in($this->input('status') === 'Confirmed'
                ? ['Good'] : ['Damaged', 'Incomplete', 'NotPresented', 'Other'])],
        ];
    }
}
