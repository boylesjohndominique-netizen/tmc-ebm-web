# Borrowing approval and equipment pickup

1. The student submits a borrowing request. The API assigns a unique random 12-digit transaction number and a separate random QR token.
2. An admin approves or rejects the request. Approval requires a future pickup window within the borrowing and return dates, reserves stock, and notifies the student. The mobile app displays the approval receipt under **Requests → Receipt**.
3. Equipment staff open **Borrowing Requests → Equipment handover**. Staff enter the student ID from the physical card and attest that they checked identity, equipment, quantities and accessories. Searching the number displays the receipt for manual confirmation or rejection. After these checks, scanning the receipt QR confirms pickup only when the ID matches and the pickup window is valid.
4. Staff reject missed pickups manually with a reason. Rejection releases reserved stock and notifies both the student and administrators. A receipt can be processed once; repeated or expired scans cannot confirm pickup.
5. Students can request returns only after staff confirm receipt.

## Return receipts and inspection

Students submit return requests; submission cannot confirm a return. An admin approves the request with a return window, issuing a separate random 12-digit receipt number and a `TMCEBMS:RETURN:` QR token. Approval does not restock equipment or close the loan.

Staff open **Return Requests → Equipment return inspection**, search the return number or scan the return QR, match the student's physical ID, and inspect every item and accessory. Confirmation requires complete, undamaged equipment and a valid return window. Only then does the server restock equipment, close the loan, calculate any overdue fee from the actual receipt time, and notify administrators and the student.

Staff reject damaged, incomplete or unpresented returns with an inspection category and reason. The loan remains open and stock stays reserved; the admin and student are notified. This rejection records that staff did not accept the equipment. The student may submit a new request with new codes, while previous receipts remain in transaction details. Damaged equipment is never automatically added to available stock.

Return receipts reuse PNG/PDF export and include the original borrowing code, return window, staff inspection result, verified ID, confirmation time and overdue fee when applicable. QR codes alone do not prove a physical handover; staff remain responsible for inspecting identity and equipment.

Mobile receipts include borrower details, equipment and quantities, purpose, admin instructions, pickup window, return deadline, approval details, and current pickup status. **Save image** and **Export PDF** open the device save/share menu. Saved copies reflect the status when exported; the server remains authoritative.

## Setup and permissions

Run `php artisan migrate` from `backend/`, `npm ci` from `frontend/`, and `flutter pub get` from `tmcebms/`. The migration adds receipt fields and backfills identifiers. Existing approved/completed records retain their previous received-equipment behavior; new approvals require handover. Legacy approvals do not gain a fabricated approval receipt.

The `borrowings.process` and `returns.process` permissions control approvals. Separate `borrowings.handover` and `returns.handover` permissions control staff receipt lookup and handover. Staff receive handover permissions by default; existing explicit permission overrides remain effective and can be changed in the Permissions page. Students cannot access these staff endpoints.

Camera scanning requires HTTPS or localhost and camera permission. Manual number lookup remains available. Pickup timestamps are transmitted as ISO 8601 instants and displayed in the viewer's time zone; borrowing/return date bounds use Philippine time.

## Verification

- API: `php artisan test` (pickup and return suites use isolated in-memory SQLite).
- Web: `npm run build`.
- Mobile: `flutter analyze`, `flutter test`, `flutter build apk --debug`.
