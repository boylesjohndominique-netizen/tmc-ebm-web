<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class HandoverVerificationService
{
    /**
     * Verify a handover using the student ID already recorded on the receipt.
     *
     * Staff identify the student against the receipt shown in the system, so
     * the ID is never retyped and cannot drift from the issued receipt.
     */
    public function verify(?string $receiptStudentId, array $data): array
    {
        $studentId = strtoupper(trim($receiptStudentId ?? ''));
        if ($studentId === '') {
            throw ValidationException::withMessages([
                'code' => 'This receipt has no student ID on record. An administrator must add the student ID before handover.',
            ]);
        }
        if (! filter_var($data['equipment_checked'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['equipment_checked' => 'Staff must inspect all equipment and accessories before confirming handover.']);
        }

        return ['verified_student_id' => $studentId, 'equipment_checked' => true];
    }
}
