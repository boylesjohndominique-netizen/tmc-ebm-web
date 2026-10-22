import { CalendarClock, Package, QrCode, Search } from 'lucide-react';
import StatusBadge from './StatusBadge';
import { fmtDate, fmtDateTime } from '../utils/datetime';

export function ReceiptLookup({ code, onChange, onSubmit, onScan, busy, isReturn = false }) {
  const inputId = isReturn ? 'return-receipt-number' : 'pickup-receipt-number';

  return <form className="receipt-lookup" onSubmit={onSubmit} aria-label="Find receipt">
    <div className="receipt-lookup-field">
      <label htmlFor={inputId}>{isReturn ? 'Return receipt number' : 'Borrowing receipt number'}</label>
      <input id={inputId} value={code} onChange={event => onChange(event.target.value)} inputMode="numeric" pattern="[0-9]{12}" maxLength={12} required disabled={busy} placeholder="Enter the 12-digit number" aria-describedby={`${inputId}-hint`} />
      <small id={`${inputId}-hint`}>Enter the receipt number or scan its QR code.</small>
    </div>
    <button type="submit" className="btn btn-secondary" disabled={busy}><Search size={16} /> Find receipt</button>
    <button type="button" className="btn btn-primary" disabled={busy} onClick={onScan}><QrCode size={16} /> Scan QR</button>
  </form>;
}

export function ReceiptSummary({ receipt, isReturn = false, awaiting, inWindow }) {
  const startsAt = isReturn ? receipt.handover_starts_at : receipt.pickup_starts_at;
  const endsAt = isReturn ? receipt.handover_ends_at : receipt.pickup_ends_at;
  const status = isReturn ? receipt.handover_status : receipt.pickup_status;

  return <section className="receipt-summary" aria-label="Receipt details">
    <div className="receipt-section-heading">
      <div><span className="receipt-step">Receipt details</span><h3>{isReturn ? 'Return' : 'Borrowing'} receipt <span className="receipt-number">{receipt.transaction_code}</span></h3></div>
      <StatusBadge status={receipt.status} />
    </div>
    <dl className="receipt-facts">
      <div><dt>Student</dt><dd>{receipt.borrower?.name || 'Not recorded'}</dd></div>
      <div><dt>Student ID</dt><dd>{receipt.borrower?.id_number || 'Not recorded'}</dd></div>
      {receipt.borrower?.email && <div><dt>Email</dt><dd>{receipt.borrower.email}</dd></div>}
      {receipt.borrower?.contact_number && <div><dt>Contact number</dt><dd>{receipt.borrower.contact_number}</dd></div>}
      {!isReturn && <div><dt>Borrowing date</dt><dd>{fmtDate(receipt.borrowing_date)}</dd></div>}
      <div><dt>Expected return</dt><dd>{fmtDate(receipt.expected_return_date)}</dd></div>
    </dl>
    <div className={`receipt-window ${awaiting && inWindow ? 'is-open' : ''}`}>
      <CalendarClock size={19} aria-hidden="true" />
      <div><b>{isReturn ? 'Return' : 'Pickup'} window</b><p>{fmtDateTime(startsAt)} <span aria-hidden="true">–</span> {fmtDateTime(endsAt)}</p><span>{awaiting ? (inWindow ? 'Open now' : 'Outside the approved window') : status}</span></div>
    </div>
    <h4 className="receipt-items-heading"><Package size={16} aria-hidden="true" /> Equipment to {isReturn ? 'return' : 'release'}</h4>
    <ul className="receipt-items">
      {receipt.items?.map(item => <li key={item.equipment_id}>
        <div className="receipt-item-heading"><b>{item.name}</b><span className="receipt-quantity">Qty {item.quantity}</span></div>
        <p className="receipt-item-meta">Equipment #{item.equipment_id}{item.category && ` · ${item.category}`}{item.condition && ` · ${item.condition}`}</p>
        {item.description && <p>{item.description}</p>}
        {item.pickup_location && <p><b>Pickup location:</b> {item.pickup_location}</p>}
      </li>)}
    </ul>
    {(receipt.borrower_remarks || receipt.approval_remarks || receipt.approved_by) && <dl className="receipt-notes">
      {receipt.borrower_remarks && <div><dt>Purpose / student notes</dt><dd>{receipt.borrower_remarks}</dd></div>}
      {receipt.approval_remarks && <div><dt>Admin instructions</dt><dd>{receipt.approval_remarks}</dd></div>}
      {receipt.approved_by && <div><dt>Approved by</dt><dd>{receipt.approved_by}{receipt.approved_at && ` · ${fmtDateTime(receipt.approved_at)}`}</dd></div>}
    </dl>}
  </section>;
}
