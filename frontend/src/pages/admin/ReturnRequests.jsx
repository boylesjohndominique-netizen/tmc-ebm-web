import { useCallback, useEffect, useState } from 'react';
import { Inbox, CheckCircle2, XCircle, PackageCheck } from 'lucide-react';
import api from '../../services/api';
import StatusBadge from '../../components/StatusBadge';
import Modal from '../../components/Modal';
import SearchBar from '../../components/SearchBar';
import { useAuth } from '../../context/AuthContext';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import useDebouncedValue from '../../hooks/useDebouncedValue';
import useRealtimeResource from '../../hooks/useRealtimeResource';
import { fmtCurrency } from '../../utils/datetime';
import ReturnInspectionDesk from '../../components/ReturnInspectionDesk';

export default function ReturnRequests() {
  const { can } = useAuth();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('All');

  // Confirmation dialog state: { request, action } — action 'Confirmed' | 'Rejected'
  const [selected, setSelected] = useState(null);
  const [action, setAction] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [modalError, setModalError] = useState('');
  const [handoverStart, setHandoverStart] = useState('');
  const [handoverEnd, setHandoverEnd] = useState('');

  const load = useCallback(() => {
    return api.get('/admin/returns')
      .then(r => setItems(r.data))
      .catch(() => setError('Unable to load return requests. Please refresh the page.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  // Live queue: student return submissions and confirmations from other
  // staff appear instantly, without losing the current filters.
  useRealtimeResource(['returns', 'equipment'], load);

  /* ---- Search & filters ---- */
  const query = useDebouncedValue(search.trim(), 300).toLowerCase();
  const filtered = items.filter(x => {
    const fullName = [x.user?.first_name, x.user?.middle_name, x.user?.last_name].filter(Boolean).join(' ').toLowerCase();
    const textMatch = !query || fullName.includes(query) || `#${x.id}`.includes(query) || `#${x.borrowing_request_id}`.includes(query) || x.transaction_code?.includes(query);
    const statusMatch = statusFilter === 'All' || x.status === statusFilter;
    return textMatch && statusMatch;
  });
  const hasFilters = query !== '' || statusFilter !== 'All';
  const clearFilters = () => { setSearch(''); setStatusFilter('All'); };
  const statuses = [...new Set(items.map(x => x.status))]; // statuses actually present

  const open = (x, mode) => { setSelected(x); setAction(mode); setRemarks(x.status === 'Awaiting Verification' && mode === 'Rejected' ? x.handover_remarks || '' : ''); setModalError(''); setHandoverStart(''); setHandoverEnd(''); };
  const close = () => { if (!busy) { setSelected(null); setAction(null); setRemarks(''); setModalError(''); } };

  const submit = async () => {
    setBusy(true);
    setModalError('');
    try {
      await api.put(`/admin/returns/${selected.id}/process`, {
        status: action, remarks: remarks || undefined,
        ...(action === 'Approved' ? { handover_starts_at: new Date(handoverStart).toISOString(), handover_ends_at: new Date(handoverEnd).toISOString() } : {}),
      });
      close();
      setSelected(null);
      await load();
    } catch (err) {
      setModalError(err.response?.data?.message || 'Unable to process the return request.');
    } finally {
      setBusy(false);
    }
  };

  const isReject = action === 'Rejected';
  const isVerification = selected?.status === 'Awaiting Verification';
  const borrowing = selected?.borrowing;
  const itemCount = borrowing?.items?.length ?? 0;

  return (
    <div className="panel">
      <h1>Return Requests</h1>
      {can('returns', 'handover') && <ReturnInspectionDesk onProcessed={load} />}
      {error && <div className="alert error">{error}</div>}
      {loading && !error && <Skeleton rows={4} />}
      {!loading && !error && (items.length
        ? <>
        {/* ---- Search & filters ---- */}
        <div className="toolbar">
          <SearchBar value={search} onChange={setSearch} placeholder="Search by user, #request, or #borrowing..." />
          <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} aria-label="Filter by status">
            <option value="All">All Statuses</option>
            {statuses.map(s => <option key={s} value={s}>{s}</option>)}
          </select>
          {hasFilters && <button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
        </div>
        <div className="table-wrap"><table>
          <thead><tr><th>ID</th><th>User</th><th>Borrowing</th><th>Condition / Problem</th><th>Overdue Fee</th><th>Payment</th><th>Staff Decision / Reason</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            {filtered.map(x => (
              <tr key={x.id}>
                <td>#{x.id}<br/><small>{x.transaction_code}</small></td>
                <td>{x.user?.first_name} {x.user?.last_name}</td>
                <td>#{x.borrowing_request_id}</td>
                <td>{x.condition_on_return || 'Not assessed'}{x.condition_notes && <div>{x.condition_notes}</div>}</td>
                <td>{x.overdue_fee > 0 ? `${fmtCurrency(x.overdue_fee)} · ${x.overdue_days}d` : '—'}</td>
                <td>{x.payment_status}</td>
                <td>{x.handover_status || 'Awaiting inspection'}{x.handover_remarks && <div>{x.handover_remarks}</div>}{x.handover_processor && <small>{x.handover_processor.first_name} {x.handover_processor.last_name}</small>}</td>
                <td><StatusBadge status={x.status} />{x.admin_verified_at && <div className="muted">Admin verified</div>}</td>
                <td>
                  {x.status === 'Pending' && can('returns', 'process') && <>
                    <button className="btn btn-primary small" disabled={busy} onClick={() => open(x, 'Approved')}>Approve receipt</button>{' '}
                    <button className="btn btn-danger small" disabled={busy} onClick={() => open(x, 'Rejected')}>Reject</button>
                  </>}
                  {x.status === 'Pending' && !can('returns', 'process') && <span className="muted">Awaiting review</span>}
                  {x.status === 'Awaiting Verification' && (can('returns', 'process') ? <>
                    <button className="btn btn-primary small" disabled={busy} onClick={() => open(x, x.handover_status === 'Accepted' ? 'Confirmed' : 'Rejected')}>Verify staff decision</button>
                    {x.handover_status === 'Accepted' && <button className="btn btn-danger small" disabled={busy} onClick={() => open(x, 'Rejected')}>Reject verification</button>}
                  </> : <span className="muted">Awaiting admin verification</span>)}
                </td>
              </tr>
            ))}
            {!filtered.length && <tr className="empty"><td colSpan="9">No return requests match your filters.</td></tr>}
          </tbody>
        </table></div>
        </>
        : <EmptyState icon={Inbox} title="No return requests yet" message="Student return submissions awaiting your review will appear here." />)}

      {/* ---- Confirm / Reject dialog ---- */}
      <Modal
        open={!!selected}
        onClose={close}
        tone={isReject ? 'danger' : 'primary'}
        icon={isReject ? XCircle : CheckCircle2}
        title={`Return #${selected?.id} — ${isVerification ? 'Verify staff decision' : isReject ? 'Reject' : 'Approve receipt'}`}
        submitLabel={isVerification ? (isReject ? 'Verify rejection' : 'Verify and complete return') : isReject ? 'Reject Return' : 'Approve Return Receipt'}
        busyLabel={isReject ? 'Rejecting…' : 'Confirming…'}
        onSubmit={submit}
        busy={busy}
        disableSubmit={isReject ? !remarks.trim() : !isVerification && (!handoverStart || !handoverEnd || new Date(handoverEnd) <= new Date(handoverStart))}
      >
        {selected && (
          <div className="modal-body">
            <p>
              <b>{[selected.user?.first_name, selected.user?.last_name].filter(Boolean).join(' ')}</b>
              {' '}is returning items from borrowing request <b>#{selected.borrowing_request_id}</b>.
            </p>

            {borrowing?.items?.length > 0 && (
              <>
                <p><b>Equipment staff must inspect:</b></p>
                <ul className="equipment-list">
                  {borrowing.items.map(i => (
                    <li key={i.id}>
                      <PackageCheck size={14} style={{ verticalAlign: '-2px', marginRight: 4 }} />
                      {i.equipment?.name} — <b>{i.quantity}</b>
                    </li>
                  ))}
                </ul>
              </>
            )}

            {selected.condition_on_return && (
              <p><b>Condition reported:</b> {selected.condition_on_return}</p>
            )}
            {isVerification && <>
              {selected.condition_notes && <p><b>Equipment problem:</b> {selected.condition_notes}</p>}
              <p><b>Overdue fee:</b> {fmtCurrency(selected.overdue_fee)} · {selected.payment_status}</p>
              <p><b>Staff decision:</b> {selected.handover_status}</p>
              <p><b>Staff reason:</b> {selected.handover_remarks || '—'}</p>
              <p>{isReject ? 'Verifying this rejection leaves the loan open and stock reserved.' : 'Verification restores stock and completes the loan. The overdue fee remains the amount assessed by staff.'}</p>
            </>}

            {selected.returned_at && (
              <p><b>Returned at:</b> {new Date(selected.returned_at).toLocaleString()}</p>
            )}

            {action === 'Approved' && <>
              <label>Return window starts <input type="datetime-local" value={handoverStart} onChange={event => setHandoverStart(event.target.value)} required /></label>
              <label>Return window ends <input type="datetime-local" value={handoverEnd} min={handoverStart} onChange={event => setHandoverEnd(event.target.value)} required /></label>
            </>}
            {(
              <label>
                {isReject ? 'Reason (required)' : isVerification ? 'Verification notes (optional)' : 'Return instructions (optional)'} <span className="muted">(shown to the borrower)</span>
                <textarea
                  value={remarks}
                  onChange={e => setRemarks(e.target.value)}
                  placeholder="e.g. items incomplete — tripod plate missing"
                  rows={2}
                />
              </label>
            )}

            {action === 'Approved' && (
              <div className="alert success" style={{ marginTop: 10 }}>
                Approval issues a receipt for {itemCount || 'the'} item{itemCount === 1 ? '' : 's'}. Staff must verify the student and inspect the equipment before restocking or closing the loan.
              </div>
            )}

            {modalError && <div className="alert error">{modalError}</div>}
          </div>
        )}
      </Modal>
    </div>
  );
}
