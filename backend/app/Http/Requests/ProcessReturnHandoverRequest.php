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
            'status' => ['required', Rule::in(['Accepted', 'Rejected'])],
            'method' => ['required', Rule::in(['manual', 'qr'])],
            'remarks' => ['required_if:status,Rejected', 'nullable', 'string', 'max:500'],
            'equipment_checked' => ['required_if:status,Accepted', 'accepted_if:status,Accepted'],
            'condition_on_return' => ['required', Rule::in(['Good', 'Damaged', 'Incomplete', 'NotPresented', 'Other'])],
            'condition_notes' => ['required_unless:condition_on_return,Good', 'nullable', 'string', 'max:1000'],
            'overdue_fee_paid' => ['required', 'boolean'],
            'quoted_overdue_fee' => ['required', 'numeric', 'min:0'],
        ];
    }
}
