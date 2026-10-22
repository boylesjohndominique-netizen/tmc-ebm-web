<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class HandoverVerificationService
{
    public function verify(?string $expectedStudentId, array $data): array
    {
        $expected = strtoupper(trim($expectedStudentId ?? ''));
        $provided = strtoupper(trim($data['student_id_number'] ?? ''));
        if ($expected === '' || $provided !== $expected) {
            throw ValidationException::withMessages([
                'student_id_number' => 'The ID presented must match the student on this receipt. An administrator must correct a missing or incorrect student ID before handover.',
            ]);
        }
        if (! filter_var($data['equipment_checked'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['equipment_checked' => 'Staff must inspect all equipment and accessories before confirming handover.']);
        }

        return ['verified_student_id' => $expected, 'equipment_checked' => true];
    }
}
