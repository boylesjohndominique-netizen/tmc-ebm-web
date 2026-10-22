import { useCallback, useEffect,useState } from 'react';
import { Link } from 'react-router-dom';
import { Inbox, CheckCircle2, XCircle } from 'lucide-react';
import api from '../../services/api';
import StatusBadge from '../../components/StatusBadge';
import Modal from '../../components/Modal';
import SearchBar from '../../components/SearchBar';
import { useAuth } from '../../context/AuthContext';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import useDebouncedValue from '../../hooks/useDebouncedValue';
import useRealtimeResource from '../../hooks/useRealtimeResource';
import { fmtDate } from '../../utils/datetime';
import BorrowingPickupDesk from '../../components/BorrowingPickupDesk';

export default function BorrowingRequests(){
  const { can } = useAuth();
  const [items,setItems]=useState([]);
  const [loading,setLoading]=useState(true);
  const [selected,setSelected]=useState(null);
  const [action,setAction]=useState(null); // 'Approved' | 'Rejected'
  const [remarks,setRemarks]=useState('');
  const [error,setError]=useState('');
  const [busy,setBusy]=useState(false);
  const [pickupStart,setPickupStart]=useState('');
  const [pickupEnd,setPickupEnd]=useState('');
  const [search,setSearch]=useState('');
  const [statusFilter,setStatusFilter]=useState('All');
  const [fromDate,setFromDate]=useState('');   // borrowing_date ≥ From
  const [toDate,setToDate]=useState('');       // borrowing_date ≤ To

  const load=useCallback(()=>api.get('/admin/borrowings').then(r=>setItems(r.data)).catch(()=>setError('Unable to load borrowing requests. Please refresh the page.')).finally(()=>setLoading(false)),[]);
  useEffect(()=>{load()},[load]);

  // Live queue: student submissions and approvals from other staff appear
  // instantly, without losing the current filters.
  useRealtimeResource(['borrowings', 'equipment'], load);

  /* ---- Search & filters ---- */
  const query=useDebouncedValue(search.trim(),300).toLowerCase();
  const filtered=items.filter(x=>{
    const fullName=[x.user?.first_name,x.user?.middle_name,x.user?.last_name].filter(Boolean).join(' ').toLowerCase();
    const equipment=x.items.map(i=>i.equipment?.name).join(', ').toLowerCase();
    const textMatch=!query||fullName.includes(query)||equipment.includes(query)||`#${x.id}`.includes(query)||x.transaction_code?.includes(query);
    const statusMatch=statusFilter==='All'||x.status===statusFilter;
    const dateMatch=(!fromDate||x.borrowing_date>=fromDate)&&(!toDate||x.borrowing_date<=toDate);
    return textMatch&&statusMatch&&dateMatch;
  });
  const hasFilters=query!==''||statusFilter!=='All'||fromDate||toDate;
  const clearFilters=()=>{setSearch('');setStatusFilter('All');setFromDate('');setToDate('');};
  const statuses=[...new Set(items.map(x=>x.status))]; // statuses actually present
  const countByStatus=s=>items.filter(x=>x.status===s).length;

  const open=(x,mode)=>{setSelected(x);setAction(mode);setRemarks('');setError('');setPickupStart('');setPickupEnd('')};
  const close=()=>{setSelected(null);setAction(null);setRemarks('');setError('')};

  const submit=async()=>{
    setBusy(true); setError('');
    try{
      await api.put(`/admin/borrowings/${selected.id}/process`,{
        status:action,remarks:remarks||undefined,
        ...(action==='Approved'?{pickup_starts_at:new Date(pickupStart).toISOString(),pickup_ends_at:new Date(pickupEnd).toISOString()}:{}),
      });
      close(); load();
    }catch(err){
      setError(err.response?.data?.message||'Unable to process the request.');
    }finally{ setBusy(false); }
  };

  return <div className="panel">
    <h1>Borrowing Requests</h1>
    {can('borrowings','handover')&&<BorrowingPickupDesk onProcessed={load} />}
    {error&&<div className="alert error">{error}</div>}
    {loading&&!items.length&&<Skeleton rows={5} />}

    {/* ---- Search & filters ---- */}
    <div className="toolbar">
      <SearchBar value={search} onChange={setSearch} placeholder="Search by borrower, equipment, receipt number, or #id..." />
      <select value={statusFilter} onChange={e=>setStatusFilter(e.target.value)} aria-label="Filter by status">
        <option value="All">All Statuses</option>
        {statuses.map(s=><option key={s} value={s}>{s}{s==='Pending'?` (${countByStatus('Pending')})`:''}</option>)}
      </select>
      <label className="inline-label">From <input type="date" value={fromDate} onChange={e=>setFromDate(e.target.value)} /></label>
      <label className="inline-label">To <input type="date" value={toDate} onChange={e=>setToDate(e.target.value)} /></label>
      {hasFilters&&<button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
    </div>

    <div className="table-wrap"><table>
      <thead><tr><th>ID</th><th>Borrower</th><th>Equipment</th><th>Borrowing Date</th><th>Expected Return</th><th>Quantity</th><th>Remarks</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        {filtered.map(x=><tr key={x.id}>
          <td>#{x.id}<br/><small>{x.transaction_code}</small></td>
          <td>{[x.user?.first_name,x.user?.middle_name].filter(Boolean).join(' ')} {x.user?.last_name}</td>
          <td>{x.items.map(i=>i.equipment?.name).join(', ')}</td>
          <td>{fmtDate(x.borrowing_date)}</td>
          <td>{fmtDate(x.expected_return_date)}</td>
          <td>{x.items.map(i=>i.quantity).join(', ')}</td>
          <td className="remarks-cell">{x.remarks||'—'}</td>
          <td><StatusBadge status={x.status}/>{x.pickup_status&&<div className="muted">Pickup: {x.pickup_status}</div>}</td>
          <td>{x.status==='Pending'
            ? (can('borrowings','process')
              ? <><button className="btn btn-primary small" onClick={()=>open(x,'Approved')}>Approve</button> <button className="btn btn-danger small" onClick={()=>open(x,'Rejected')}>Reject</button></>
              : <span className="muted">Awaiting review</span>)
            : <span className="muted">Processed</span>}</td>
        </tr>)}
        {!filtered.length&&!loading&&<tr className="empty"><td colSpan="9">{hasFilters?'No borrowing requests match your filters.':'No borrowing requests yet.'}</td></tr>}
      </tbody>
    </table></div>
    {!items.length&&!loading&&<EmptyState icon={Inbox} title="No borrowing requests yet" message="Student borrowing submissions awaiting review will appear here."/>}

    <Modal
      open={!!selected}
      title={`Request #${selected?.id} — ${action==='Approved'?'Approve':'Reject'}`}
      onClose={close}
      tone={action==='Approved'?'primary':'danger'}
      icon={action==='Approved'?CheckCircle2:XCircle}
    >
      {selected&&<>
        <div className="modal-body">
          <p><b>Borrower:</b> {[selected.user?.first_name,selected.user?.middle_name].filter(Boolean).join(' ')} {selected.user?.last_name}</p>
          <p><b>Borrowing date:</b> {fmtDate(selected.borrowing_date)} &nbsp; <b>Expected return:</b> {fmtDate(selected.expected_return_date)}</p>
          <p><b>Equipment:</b></p>
          <ul className="equipment-list">
            {selected.items.map(i=>{
              const enough=(i.equipment?.available_quantity??0)>=i.quantity;
              return <li key={i.id}>
                {i.equipment?.name} — requested <b>{i.quantity}</b>, available <b style={{color:enough?'var(--success)':'var(--danger)'}}>{i.equipment?.available_quantity??'?'}</b>
                {!enough&&<span className="stock-warning"> — insufficient stock for approval</span>}
                <p><b>School pickup location:</b> {i.equipment?.pickup_location || 'Not set'}</p>
                {i.equipment?.min_borrowing_days != null && <p><b>Allowed loan duration:</b> {i.equipment.min_borrowing_days}–{i.equipment.max_borrowing_days} days</p>}
              </li>;
            })}
          </ul>
          {selected.remarks&&<p><b>Borrower remarks:</b> {selected.remarks}</p>}
          {action==='Approved'&&<>
            <p>Approval reserves stock and issues a receipt. Staff will confirm the actual handover. Select the pickup window in your local time.</p>
            <label>Pickup starts <input type="datetime-local" value={pickupStart} onChange={e=>setPickupStart(e.target.value)} required /></label>
            <label>Pickup ends <input type="datetime-local" value={pickupEnd} min={pickupStart} onChange={e=>setPickupEnd(e.target.value)} required /></label>
          </>}
          <label>Remarks {action==='Approved'?'(optional)':'(optional, shown to the borrower)'}
            <textarea value={remarks} onChange={e=>setRemarks(e.target.value)} placeholder={action==='Approved'?'e.g. pick up at the equipment office':'e.g. equipment reserved for another event'}/>
          </label>
          {error&&<div className="alert error">{error}</div>}
          {action==='Approved'&&selected.items.some(i=>(i.equipment?.available_quantity??0)<i.quantity)&&
            <div className="alert error">One or more items no longer have enough stock. The request cannot be approved.</div>}
        </div>
        <div className="modal-actions">
          <button className="btn btn-secondary" onClick={close}>Cancel</button>
          <button
            className={`btn ${action==='Approved'?'btn-primary':'btn-danger'}`}
            disabled={busy||(action==='Approved'&&(!pickupStart||!pickupEnd||new Date(pickupEnd)<=new Date(pickupStart)||selected.items.some(i=>(i.equipment?.available_quantity??0)<i.quantity)))}
            onClick={submit}>
            {busy?'Processing...':action==='Approved'?'Approve Request':'Reject Request'}
          </button>
        </div>
      </>}
    </Modal>
  </div>;
}
