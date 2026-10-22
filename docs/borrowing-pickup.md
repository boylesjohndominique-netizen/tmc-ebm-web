# Borrowing approval and equipment pickup

1. The student submits a borrowing request. The API assigns a unique random 12-digit transaction number and a separate random QR token.
2. An admin approves or rejects the request. Approval requires a future pickup window within the borrowing and return dates, reserves stock, and notifies the student. The mobile app displays the approval receipt under **Requests → Receipt**.
3. Equipment staff open **Borrowing Requests → Equipment handover**. Searching the 12-digit number or scanning the QR code opens the receipt for review, including the student's name, ID, and equipment details. Staff then confirm or reject pickup without entering the student ID again. Scanning alone does not confirm pickup. Confirmation is available during the approved pickup window.
4. Staff reject pickups with a reason after either number lookup or QR scanning. Rejection releases reserved stock and notifies both the student and administrators. A receipt can be processed once; repeated or expired scans cannot confirm pickup.
5. Students can request returns only after staff confirm receipt.

## Equipment pickup location and loan duration

In **Equipment → Add Equipment / Edit**, set the school pickup location (for example, AV Office, Building A, Room 102). Students see it in equipment details and the request form. Approval saves that location in the receipt, including PNG/PDF exports, so later equipment edits do not change an issued receipt.

Set the minimum and maximum borrowing days to limit the loan duration. Both limits are inclusive: 10–15 days permits a return date 10 through 15 calendar days after the borrowing date. For example, borrowing on October 24 permits returning November 3–8. Setting both limits to 10 allows exactly ten days. Leave both fields empty to remove duration restrictions. Existing equipment has no duration restriction until an admin configures it.

Students choose a borrowing date starting today (Philippine time); the return-date picker then allows only dates within the duration limits. Changing the borrowing date clears an incompatible return date. The API checks the duration at submission and again at approval; changed limits may make a pending request ineligible for approval. Existing approved loans retain their issued receipts and return deadlines. The earlier calendar-date columns remain stored for migration compatibility and no longer restrict requests; they are not converted into day counts.

## Return receipts and inspection

Students submit return requests; submission cannot confirm a return. An admin approves the request with a return window, issuing a separate random 12-digit receipt number and a `TMCEBMS:RETURN:` QR token. Approval does not restock equipment or close the loan.

Staff open **Return Requests → Equipment return inspection** and search or scan the return receipt. Scanning opens the inspection form; it does not skip the condition or payment prompts. The receipt already shows the student's ID, so staff never retype it: they confirm the student shown on the receipt, inspect all equipment and accessories, and answer whether the equipment is in good condition. A No answer requires a problem category and description.

The server calculates overdue days using Philippine calendar dates and the configured daily rate. If a fee is due, staff must answer whether the displayed amount is paid. Staff can accept only complete equipment in good condition, with the student identified from the receipt and any fee paid, during the return window. Damaged, incomplete, unpresented or unpaid returns are rejected with a reason. If the fee changes since lookup, staff must refresh the form and check payment against the new amount.

Both staff decisions produce **Awaiting Verification** and notify administrators and the student. The table displays the condition/problem, fee, Paid/Not paid status, staff decision and reason. No stock is restored and the loan stays open at this stage. The admin uses **Verify staff decision** in the Action column to review the findings. Verifying an accepted, paid return completes the loan and restores stock once. Verifying a rejection keeps the loan open; the student can then submit a new request with new codes.

Fees and the actual return time are fixed at staff acceptance, so a later admin review does not increase the bill. Existing finalized records are preserved, and missing historical payment records are shown as Not recorded rather than assumed paid. Zero fees show Not required. Return receipts and PNG/PDF exports include condition problems, payment status, staff decision and final admin verification details. Staff remain responsible for physical inspection and checking payment.

Mobile receipts include borrower details, equipment and quantities, purpose, admin instructions, pickup window, return deadline, approval details, and current pickup status. **Save image** and **Export PDF** open the device save/share menu. Saved copies reflect the status when exported; the server remains authoritative.

## Setup and permissions

Run `php artisan migrate` from `backend/`, `npm ci` from `frontend/`, and `flutter pub get` from `tmcebms/`. The migration adds receipt fields and backfills identifiers. Existing approved/completed records retain their previous received-equipment behavior; new approvals require handover. Legacy approvals do not gain a fabricated approval receipt.

The `borrowings.process` and `returns.process` permissions control approvals. Separate `borrowings.handover` and `returns.handover` permissions control staff receipt lookup and handover. Staff receive handover permissions by default; existing explicit permission overrides remain effective and can be changed in the Permissions page. Students cannot access these staff endpoints.

Camera scanning requires HTTPS or localhost and camera permission. Manual number lookup remains available. Pickup timestamps are transmitted as ISO 8601 instants and displayed in the viewer's time zone; borrowing/return date bounds use Philippine time.

## Verification

- API: `php artisan test` (pickup and return suites use isolated in-memory SQLite).
- Web: `npm run build`.
- Mobile: `flutter analyze`, `flutter test`, `flutter build apk --debug`.
