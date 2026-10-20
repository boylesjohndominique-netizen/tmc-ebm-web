<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\XlsxWriter;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Data source for the printable reports page. Each key feeds one
     * selectable report (summary, users, borrowings, returns); the audit
     * report is served separately by /admin/transactions.
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->filters($request);

        return response()->json($this->payload($from, $to));
    }

    /**
     * Export a general report of every section — summary, users,
     * borrowings, returns and the transaction audit — as a single Excel
     * (.xlsx) workbook, one worksheet per section.
     */
    public function export(Request $request)
    {
        [$from, $to] = $this->filters($request);

        $data = $this->payload($from, $to);
        $audit = $this->audit($from, $to);

        $sheets = [
            ['name' => 'Summary', 'rows' => $this->summaryRows($data, $from, $to)],
            ['name' => 'Users', 'rows' => $this->userRows($data['users'])],
            ['name' => 'Borrowing Requests', 'rows' => $this->borrowingRows($data['borrowings'])],
            ['name' => 'Return Requests', 'rows' => $this->returnRows($data['returns'])],
            ['name' => 'Audit - Borrowings', 'rows' => $this->auditBorrowingRows($audit['borrowings'])],
            ['name' => 'Audit - Returns', 'rows' => $this->auditReturnRows($audit['returns'])],
        ];

        $path = tempnam(sys_get_temp_dir(), 'tmc_report_');
        (new XlsxWriter($sheets))->save($path);

        return response()->download(
            $path,
            'TMC-General-Report-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /** Validate and return the [from, to] date filter pair. */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [$data['from'] ?? null, $data['to'] ?? null];
    }

    /** The reports payload shared by the JSON endpoint and the export. */
    private function payload(?string $from, ?string $to): array
    {
        $borrowings = BorrowingRequest::with(['user', 'items.equipment'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()->get();

        $returns = ReturnRequest::with(['user', 'borrowing.items.equipment'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()->get();

        $users = User::with('role')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return [
            'summary' => [
                'users' => $users->count(),
                'equipment' => Equipment::count(),
                'borrowings' => $borrowings->count(),
                'returns' => $returns->count(),
                'overdue' => BorrowingRequest::where('status', 'Approved')
                    ->whereDate('expected_return_date', '<', now()->toDateString())->count(),
            ],
            'users' => $users,
            'borrowings' => $borrowings,
            'returns' => $returns,
        ];
    }

    /** Processed (confirmed/declined) borrowing and return rows. */
    private function audit(?string $from, ?string $to): array
    {
        $borrowings = BorrowingRequest::with(['user', 'items.equipment', 'processor'])
            ->whereIn('status', ['Approved', 'Rejected'])
            ->when($from, fn ($q) => $q->whereDate('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('updated_at', '<=', $to))
            ->latest('updated_at')
            ->get();

        $returns = ReturnRequest::with(['user', 'borrowing.items.equipment', 'processor'])
            ->whereIn('status', ['Confirmed', 'Rejected'])
            ->when($from, fn ($q) => $q->whereDate('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('updated_at', '<=', $to))
            ->latest('updated_at')
            ->get();

        return ['borrowings' => $borrowings, 'returns' => $returns];
    }

    private function summaryRows(array $data, ?string $from, ?string $to): array
    {
        $range = ($from || $to)
            ? (($from ?: '…').' to '.($to ?: 'present'))
            : 'All time';

        return [
            ['Metric', 'Value'],
            ['Date range', $range],
            ['Total users', $data['summary']['users']],
            ['Total equipment', $data['summary']['equipment']],
            ['Borrowing requests', $data['summary']['borrowings']],
            ['Return requests', $data['summary']['returns']],
            ['Overdue borrowings', $data['summary']['overdue']],
        ];
    }

    private function userRows($users): array
    {
        $rows = [['ID', 'Name', 'Username', 'Email', 'Contact', 'Role', 'Registered']];

        foreach ($users as $u) {
            $rows[] = [
                $u->id,
                $this->fullName($u),
                $u->username,
                $u->email,
                $u->contact_number ?: '—',
                $u->role->name ?? '—',
                $this->date($u->created_at),
            ];
        }

        return $rows;
    }

    private function borrowingRows($borrowings): array
    {
        $rows = [['ID', 'Borrower', 'Equipment', 'Borrowing Date', 'Expected Return', 'Quantity', 'Remarks', 'Status']];

        foreach ($borrowings as $x) {
            $rows[] = [
                $x->id,
                $this->fullName($x->user),
                $this->equipmentNames($x),
                $this->date($x->borrowing_date),
                $this->date($x->expected_return_date),
                $x->items->pluck('quantity')->implode(', '),
                $x->remarks ?: '—',
                $x->status,
            ];
        }

        return $rows;
    }

    private function returnRows($returns): array
    {
        $rows = [['ID', 'User', 'Borrowing', 'Equipment', 'Condition', 'Remarks', 'Status']];

        foreach ($returns as $x) {
            $rows[] = [
                $x->id,
                $this->fullName($x->user),
                '#'.$x->borrowing_request_id,
                $this->equipmentNames($x->borrowing) ?: '—',
                $x->condition_on_return ?: '—',
                $x->remarks ?: '—',
                $x->status,
            ];
        }

        return $rows;
    }

    private function auditBorrowingRows($borrowings): array
    {
        $rows = [['ID', 'Borrower', 'Equipment', 'Borrowing Date', 'Expected Return', 'Outcome', 'Processed By', 'Date Processed']];

        foreach ($borrowings as $x) {
            $rows[] = [
                $x->id,
                $this->fullName($x->user),
                $this->equipmentNames($x),
                $this->date($x->borrowing_date),
                $this->date($x->expected_return_date),
                $x->status,
                $x->processor ? $this->fullName($x->processor) : '—',
                $this->dateTime($x->updated_at),
            ];
        }

        return $rows;
    }

    private function auditReturnRows($returns): array
    {
        $rows = [['ID', 'User', 'Borrowing', 'Condition', 'Outcome', 'Processed By', 'Date Processed']];

        foreach ($returns as $x) {
            $rows[] = [
                $x->id,
                $this->fullName($x->user),
                '#'.$x->borrowing_request_id,
                $x->condition_on_return ?: '—',
                $x->status,
                $x->processor ? $this->fullName($x->processor) : '—',
                $this->dateTime($x->updated_at),
            ];
        }

        return $rows;
    }

    /** Comma-separated equipment names for a borrowing-like record. */
    private function equipmentNames($record): string
    {
        if (! $record) {
            return '';
        }

        return $record->items
            ->map(fn ($item) => $item->equipment?->name)
            ->filter()
            ->implode(', ');
    }

    private function fullName($user): string
    {
        if (! $user) {
            return '—';
        }

        return trim(implode(' ', array_filter([
            $user->first_name, $user->middle_name, $user->last_name,
        ]))) ?: '—';
    }

    private function date($value): string
    {
        if (! $value) {
            return '';
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    private function dateTime($value): string
    {
        if (! $value) {
            return '';
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
