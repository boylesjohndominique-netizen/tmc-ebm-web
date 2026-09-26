import { useCallback, useEffect, useMemo, useState } from 'react';
import { ClipboardList, FileText, History, Inbox, RotateCcw } from 'lucide-react';
import api from '../../services/api';
import StatusBadge from '../../components/StatusBadge';
import SearchBar from '../../components/SearchBar';
import { fmtDate, fmtDateTime } from '../../utils/datetime';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import useRealtimeResource from '../../hooks/useRealtimeResource';

const fullName = u => [u?.first_name, u?.middle_name, u?.last_name].filter(Boolean).join(' ');

/**
 * Processed (confirmed/declined) borrowing and return requests, listed
 * like an audit trail. Requests move here automatically once an admin
 * approves, rejects or confirms them, and the list can be printed.
 */
export default function TransactionHistory() {
  const [data, setData] = useState({ borrowings: [], returns: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [tab, setTab] = useState('all'); // 'all' | 'borrowings' | 'returns'
  const [status, setStatus] = useState('all'); // 'all' | 'Approved' | 'Rejected' | 'Confirmed'
  const [search, setSearch] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const load = useCallback(() => {
    return api.get('/admin/transactions', { params: { from, to } })
      .then(r => setData({ borrowings: r.data.borrowings, returns: r.data.returns }))
      .catch(() => setError('Unable to load the transaction history. Please refresh the page.'))
      .finally(() => setLoading(false));
  }, [from, to]);

  useEffect(() => { load(); }, [load]);

  // Audit trail stays current as other staff process requests.
  useRealtimeResource(['borrowings', 'returns'], load);

  const borrowingRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return data.borrowings.filter(x =>
      (status === 'all' || tab === 'returns' || status === x.status) &&
      (!q || `#${x.id} ${fullName(x.user)}`.toLowerCase().includes(q))
    );
  }, [data.borrowings, status, tab, search]);

  const returnRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return data.returns.filter(x =>
      (status === 'all' || tab === 'borrowings' || status === x.status) &&
      (!q || `#${x.id} ${fullName(x.user)}`.toLowerCase().includes(q))
    );
  }, [data.returns, status, tab, search]);

  const showBorrowings = tab !== 'returns';
  const showReturns = tab !== 'borrowings';
  const totalRows = (showBorrowings ? borrowingRows.length : 0) + (showReturns ? returnRows.length : 0);

  const clearFilters = () => { setStatus('all'); setSearch(''); setFrom(''); setTo(''); };
  const hasFilters = status !== 'all' || search || from || to;

  return (
    <>
      <div className="page-heading">
        <div>
          <h1><History size={24} className="heading-icon" /> Transaction History</h1>
          <p>Confirmed and declined borrowing &amp; return requests, recorded for audit.</p>
        </div>
      </div>

      {/* Filters */}
      <div className="panel print-hide">
        <div className="transactions-toolbar">
          <div className="segmented-tabs" role="tablist" aria-label="Transaction type">
            <button role="tab" aria-selected={tab === 'all'} className={tab === 'all' ? 'active' : ''} onClick={() => setTab('all')}>
              <FileText size={15} /> All
            </button>
            <button role="tab" aria-selected={tab === 'borrowings'} className={tab === 'borrowings' ? 'active' : ''} onClick={() => setTab('borrowings')}>
              <ClipboardList size={15} /> Borrowings
            </button>
            <button role="tab" aria-selected={tab === 'returns'} className={tab === 'returns' ? 'active' : ''} onClick={() => setTab('returns')}>
              <RotateCcw size={15} /> Returns
            </button>
          </div>
          <div className="transactions-filters">
            <label className="inline-label">From <input type="date" value={from} onChange={e => setFrom(e.target.value)} /></label>
            <label className="inline-label">To <input type="date" value={to} onChange={e => setTo(e.target.value)} /></label>
            <select value={status} onChange={e => setStatus(e.target.value)} aria-label="Filter by outcome">
              <option value="all">All outcomes</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
              <option value="Confirmed">Confirmed</option>
            </select>
            <SearchBar value={search} onChange={setSearch} placeholder="Search borrower or #id..." />
            {hasFilters && <button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
          </div>
        </div>
      </div>

      {error && <div className="alert error">{error}</div>}
      {loading && <div className="panel"><Skeleton rows={6} /></div>}

      {!loading && (
        <>
          {/* Summary */}
          <div className="dashboard-grid print-keep">
            <div className="dashboard-card">
              <div className="card-icon"><FileText size={22} /></div>
              <div>
                <div className="card-title">Total Processed</div>
                <div className="card-value">{borrowingRows.length + returnRows.length}</div>
                <div className="card-subtitle">Matching the current filters</div>
              </div>
            </div>
            <div className="dashboard-card">
              <div className="card-icon"><ClipboardList size={22} /></div>
              <div>
                <div className="card-title">Borrowing Requests</div>
                <div className="card-value">{borrowingRows.length}</div>
                <div className="card-subtitle">
                  {borrowingRows.filter(x => x.status === 'Approved').length} approved · {borrowingRows.filter(x => x.status === 'Rejected').length} rejected
                </div>
              </div>
            </div>
            <div className="dashboard-card">
              <div className="card-icon"><RotateCcw size={22} /></div>
              <div>
                <div className="card-title">Return Requests</div>
                <div className="card-value">{returnRows.length}</div>
                <div className="card-subtitle">
                  {returnRows.filter(x => x.status === 'Confirmed').length} confirmed · {returnRows.filter(x => x.status === 'Rejected').length} rejected
                </div>
              </div>
            </div>
          </div>

          {/* Borrowing audit table */}
          {showBorrowings && (
            <div className="panel">
              <h2>Borrowing Requests — Approved &amp; Declined</h2>
              {borrowingRows.length
                ? (
                  <div className="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Borrower</th>
                          <th>Equipment</th>
                          <th>Borrowing Date</th>
                          <th>Expected Return</th>
                          <th>Quantity</th>
                          <th>Remarks</th>
                          <th>Outcome</th>
                          <th className="print-hide">Processed By</th>
                          <th className="print-hide">Date Processed</th>
                        </tr>
                      </thead>
                      <tbody>
                        {borrowingRows.map(x => (
                          <tr key={x.id}>
                            <td>#{x.id}</td>
                            <td>{fullName(x.user)}</td>
                            <td>{x.items.map(i => i.equipment?.name).join(', ')}</td>
                            <td>{fmtDate(x.borrowing_date)}</td>
                            <td>{fmtDate(x.expected_return_date)}</td>
                            <td>{x.items.map(i => i.quantity).join(', ')}</td>
                            <td className="remarks-cell">{x.remarks || '—'}</td>
                            <td><StatusBadge status={x.status} /></td>
                            <td className="print-hide">{x.processor ? fullName(x.processor) : '—'}</td>
                            <td className="print-hide">{fmtDateTime(x.updated_at)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )
                : <EmptyState icon={Inbox} title="No borrowing records" message="Approved and declined borrowing requests will be listed here after they are processed." />}
            </div>
          )}

          {/* Return audit table */}
          {showReturns && (
            <div className="panel">
              <h2>Return Requests — Confirmed &amp; Declined</h2>
              {returnRows.length
                ? (
                  <div className="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>User</th>
                          <th>Borrowing</th>
                          <th>Equipment</th>
                          <th>Condition on Return</th>
                          <th>Remarks</th>
                          <th>Outcome</th>
                          <th className="print-hide">Processed By</th>
                          <th className="print-hide">Date Processed</th>
                        </tr>
                      </thead>
                      <tbody>
                        {returnRows.map(x => (
                          <tr key={x.id}>
                            <td>#{x.id}</td>
                            <td>{fullName(x.user)}</td>
                            <td>#{x.borrowing_request_id}</td>
                            <td>{x.borrowing?.items?.map(i => i.equipment?.name).join(', ') || '—'}</td>
                            <td>{x.condition_on_return || '—'}</td>
                            <td className="remarks-cell">{x.remarks || '—'}</td>
                            <td><StatusBadge status={x.status} /></td>
                            <td className="print-hide">{x.processor ? fullName(x.processor) : '—'}</td>
                            <td className="print-hide">{fmtDateTime(x.updated_at)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )
                : <EmptyState icon={Inbox} title="No return records" message="Confirmed and declined return requests will be listed here after they are processed." />}
            </div>
          )}

          {!totalRows && !error && (
            <EmptyState icon={History} title="No transactions yet" message="Once borrowing or return requests are confirmed or declined, they will be recorded here like an audit trail." />
          )}
        </>
      )}
    </>
  );
}
