import { useCallback, useEffect, useState } from 'react';
import {
  BarChart3, ClipboardList, FileSpreadsheet, FileText, History, Printer, RotateCcw, Users as UsersIcon,
} from 'lucide-react';
import api from '../../services/api';
import StatusBadge from '../../components/StatusBadge';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import { fmtDate, fmtDateTime } from '../../utils/datetime';
import useRealtimeResource from '../../hooks/useRealtimeResource';

const fullName = u => [u?.first_name, u?.middle_name, u?.last_name].filter(Boolean).join(' ');

// Report catalog: id, label and icon. Adding a report here is all it takes.
const REPORTS = [
  { id: 'summary', label: 'Summary Report', icon: BarChart3 },
  { id: 'users', label: 'List of Users', icon: UsersIcon },
  { id: 'borrowings', label: 'List of Borrowing Requests', icon: ClipboardList },
  { id: 'returns', label: 'List of Return Requests', icon: RotateCcw },
  { id: 'audit', label: 'Transaction Audit (Confirmed & Declined)', icon: History },
];

export default function Reports() {
  const [report, setReport] = useState('summary');
  const [d, setD] = useState(null);          // /admin/reports payload
  const [audit, setAudit] = useState(null);  // /admin/transactions payload
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [error, setError] = useState('');
  const [exporting, setExporting] = useState(false);

  const load = useCallback(() => {
    const request = report === 'audit'
      ? api.get('/admin/transactions', { params: { from, to } }).then(r => setAudit(r.data))
      : api.get('/admin/reports', { params: { from, to } }).then(r => setD(r.data));
    return request.catch(() => setError('Unable to load the report data. Please refresh the page.'));
  }, [report, from, to]);

  // Null the previous data only when report/filters change — realtime
  // refreshes call `load` directly so the visible report never flickers.
  useEffect(() => {
    setError('');
    if (report === 'audit') setAudit(null); else setD(null);
    load();
  }, [load]);

  // Reports reflect borrowings, returns, users and stock — keep them live.
  useRealtimeResource(['borrowings', 'returns', 'users', 'equipment'], load);

  const borrowings = d?.borrowings ?? [];
  const users = d?.users ?? [];
  const returns = d?.returns ?? [];
  const auditBorrowings = audit?.borrowings ?? [];
  const auditReturns = audit?.returns ?? [];

  const reportLabel = REPORTS.find(r => r.id === report)?.label;
  const today = fmtDate(new Date());
  const showDateFilter = report !== 'summary';
  const loading = report === 'audit' ? !audit : !d;

  const clearFilters = () => { setFrom(''); setTo(''); };

  const exportExcel = async () => {
    setExporting(true); setError('');
    try {
      const res = await api.get('/admin/reports/export', {
        params: { from, to },
        responseType: 'blob',
      });
      const url = URL.createObjectURL(new Blob([res.data], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      }));
      const link = document.createElement('a');
      link.href = url;
      link.download = `TMC-General-Report-${new Date().toISOString().slice(0, 10)}.xlsx`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch {
      setError('Unable to export the report. Please try again.');
    } finally { setExporting(false); }
  };

  return (
    <>
      <div className="page-heading">
        <div>
          <h1>Reports</h1>
          <p>Generate, export or print system reports — choose a report below.</p>
        </div>
        <div className="page-heading-actions">
          <button className="btn btn-primary print-hide" onClick={exportExcel} disabled={exporting || loading}>
            <FileSpreadsheet size={16} /> {exporting ? 'Exporting…' : 'Export to Excel'}
          </button>
          <button className="btn btn-secondary print-hide" onClick={() => window.print()} disabled={loading}>
            <Printer size={16} /> Print / Save PDF
          </button>
        </div>
      </div>

      {/* Only visible on the printed report */}
      <div className="print-header print-only">
        <h2>TMC — Equipment Borrowing &amp; Tracking Management System</h2>
        <p>{reportLabel}{from || to ? ` — ${from || '...'} to ${to || 'present'}` : ''}</p>
        <p>Generated on {today}</p>
      </div>

      {/* Report picker */}
      <div className="panel print-hide">
        <h2>Choose a Report</h2>
        <div className="report-picker">
          {REPORTS.map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              className={`report-option ${report === id ? 'active' : ''}`}
              onClick={() => setReport(id)}
            >
              <Icon size={18} />
              <span>{label}</span>
            </button>
          ))}
        </div>
        {showDateFilter && (
          <div className="toolbar report-toolbar">
            <label className="inline-label">From <input type="date" value={from} onChange={e => setFrom(e.target.value)} /></label>
            <label className="inline-label">To <input type="date" value={to} onChange={e => setTo(e.target.value)} /></label>
            {(from || to) && <button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
          </div>
        )}
      </div>

      {error && <div className="alert error">{error}</div>}
      {loading && !error && <div className="panel"><Skeleton rows={6} /></div>}

      {/* ============ SUMMARY REPORT ============ */}
      {report === 'summary' && d && (
        <>
          <div className="dashboard-grid">
            {Object.entries(d.summary).map(([k, v]) => (
              <div className="dashboard-card" key={k}>
                <div><div className="card-title">{k.replaceAll('_', ' ')}</div><div className="card-value">{v}</div></div>
              </div>
            ))}
          </div>

          <div className="panel">
            <h2>Borrowing Overview</h2>
            {borrowings.length
              ? <div className="table-wrap"><table>
                  <thead><tr><th>ID</th><th>User</th><th>Equipment</th><th>Borrowed</th><th>Return Due</th><th>Status</th></tr></thead>
                  <tbody>{borrowings.map(x => (
                    <tr key={x.id}>
                      <td>#{x.id}</td>
                      <td>{fullName(x.user)}</td>
                      <td>{x.items.map(i => `${i.equipment?.name} × ${i.quantity}`).join(', ')}</td>
                      <td>{fmtDate(x.borrowing_date)}</td>
                      <td>{fmtDate(x.expected_return_date)}</td>
                      <td><StatusBadge status={x.status} /></td>
                    </tr>
                  ))}</tbody>
                </table></div>
              : <EmptyState icon={ClipboardList} title="No borrowings in this range" message="Borrowing requests will appear here once students start submitting." />}
          </div>
        </>
      )}

      {/* ============ USERS REPORT ============ */}
      {report === 'users' && d && (
        <div className="panel">
          <h2>List of Users</h2>
          {users.length
            ? <div className="table-wrap"><table>
                <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Contact</th><th>Role</th><th>Registered</th></tr></thead>
                <tbody>{users.map(u => (
                  <tr key={u.id}>
                    <td>#{u.id}</td>
                    <td>{fullName(u)}</td>
                    <td>{u.username}</td>
                    <td>{u.email}</td>
                    <td>{u.contact_number || '—'}</td>
                    <td>{u.role?.name || '—'}</td>
                    <td>{fmtDate(u.created_at)}</td>
                  </tr>
                ))}</tbody>
              </table></div>
            : <EmptyState icon={UsersIcon} title="No users found" message={from || to ? 'No users were registered in this date range.' : 'User accounts will appear here.'} />}
        </div>
      )}

      {/* ============ BORROWING REQUESTS REPORT ============ */}
      {report === 'borrowings' && d && (
        <div className="panel">
          <h2>List of Borrowing Requests</h2>
          {borrowings.length
            ? <div className="table-wrap"><table>
                <thead><tr><th>ID</th><th>Borrower</th><th>Equipment</th><th>Borrowing Date</th><th>Expected Return</th><th>Quantity</th><th>Remarks</th><th>Status</th></tr></thead>
                <tbody>{borrowings.map(x => (
                  <tr key={x.id}>
                    <td>#{x.id}</td>
                    <td>{fullName(x.user)}</td>
                    <td>{x.items.map(i => i.equipment?.name).join(', ')}</td>
                    <td>{x.borrowing_date}</td>
                    <td>{x.expected_return_date}</td>
                    <td>{x.items.map(i => i.quantity).join(', ')}</td>
                    <td className="remarks-cell">{x.remarks || '—'}</td>
                    <td><StatusBadge status={x.status} /></td>
                  </tr>
                ))}</tbody>
              </table></div>
            : <EmptyState icon={ClipboardList} title="No borrowing requests in this range" message="Try widening the date range or clearing the filters." />}
        </div>
      )}

      {/* ============ RETURN REQUESTS REPORT ============ */}
      {report === 'returns' && d && (
        <div className="panel">
          <h2>List of Return Requests</h2>
          {returns.length
            ? <div className="table-wrap"><table>
                <thead><tr><th>ID</th><th>User</th><th>Borrowing</th><th>Equipment</th><th>Condition</th><th>Remarks</th><th>Status</th></tr></thead>
                <tbody>{returns.map(x => (
                  <tr key={x.id}>
                    <td>#{x.id}</td>
                    <td>{fullName(x.user)}</td>
                    <td>#{x.borrowing_request_id}</td>
                    <td>{x.borrowing?.items?.map(i => i.equipment?.name).join(', ') || '—'}</td>
                    <td>{x.condition_on_return || '—'}</td>
                    <td className="remarks-cell">{x.remarks || '—'}</td>
                    <td><StatusBadge status={x.status} /></td>
                  </tr>
                ))}</tbody>
              </table></div>
            : <EmptyState icon={RotateCcw} title="No return requests in this range" message="Try widening the date range or clearing the filters." />}
        </div>
      )}

      {/* ============ AUDIT REPORT ============ */}
      {report === 'audit' && audit && (
        <>
          <div className="panel">
            <h2>Confirmed &amp; Declined Borrowing Requests</h2>
            {auditBorrowings.length
              ? <div className="table-wrap"><table>
                  <thead><tr><th>ID</th><th>Borrower</th><th>Equipment</th><th>Borrowing Date</th><th>Expected Return</th><th>Outcome</th><th>Processed By</th><th>Date Processed</th></tr></thead>
                  <tbody>{auditBorrowings.map(x => (
                    <tr key={x.id}>
                      <td>#{x.id}</td>
                      <td>{fullName(x.user)}</td>
                      <td>{x.items.map(i => i.equipment?.name).join(', ')}</td>
                      <td>{fmtDate(x.borrowing_date)}</td>
                      <td>{fmtDate(x.expected_return_date)}</td>
                      <td><StatusBadge status={x.status} /></td>
                      <td>{x.processor ? fullName(x.processor) : '—'}</td>
                      <td>{fmtDateTime(x.updated_at)}</td>
                    </tr>
                  ))}</tbody>
                </table></div>
              : <EmptyState icon={History} title="No processed borrowings" message="Approved and declined borrowing requests will be listed here." />}
          </div>

          <div className="panel">
            <h2>Confirmed &amp; Declined Return Requests</h2>
            {auditReturns.length
              ? <div className="table-wrap"><table>
                  <thead><tr><th>ID</th><th>User</th><th>Borrowing</th><th>Condition</th><th>Outcome</th><th>Processed By</th><th>Date Processed</th></tr></thead>
                  <tbody>{auditReturns.map(x => (
                    <tr key={x.id}>
                      <td>#{x.id}</td>
                      <td>{fullName(x.user)}</td>
                      <td>#{x.borrowing_request_id}<br/><small>{x.transaction_code}</small></td>
                      <td>{x.condition_on_return || '—'}</td>
                      <td><StatusBadge status={x.status} />{x.handover_remarks && <div>{x.handover_remarks}</div>}</td>
                      <td>{(x.handover_processor || x.processor) ? fullName(x.handover_processor || x.processor) : '—'}</td>
                      <td>{fmtDateTime(x.handover_processed_at || x.updated_at)}</td>
                    </tr>
                  ))}</tbody>
                </table></div>
              : <EmptyState icon={History} title="No processed returns" message="Confirmed and declined return requests will be listed here." />}
          </div>
        </>
      )}
    </>
  );
}
