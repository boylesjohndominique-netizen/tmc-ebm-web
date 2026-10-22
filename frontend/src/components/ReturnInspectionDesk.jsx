import { useEffect, useRef, useState } from 'react';
import api from '../services/api';
import Modal from './Modal';
import ReceiptQrScanner from './ReceiptQrScanner';
import { fmtCurrency, fmtDate, fmtDateTime } from '../utils/datetime';

export default function ReturnInspectionDesk({ onProcessed }) {
  const [code, setCode] = useState('');
  const [receipt, setReceipt] = useState(null);
  const [condition, setCondition] = useState('');
  const [conditionNotes, setConditionNotes] = useState('');
  const [paid, setPaid] = useState('');
  const [studentId, setStudentId] = useState('');
  const [checked, setChecked] = useState(false);
  const [reason, setReason] = useState('');
  const [method, setMethod] = useState('manual');
  const [scanning, setScanning] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const processing = useRef(false);
  const [now, setNow] = useState(Date.now);
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);

  const findReceipt = async (value, source = 'manual') => {
    if (processing.current) return;
    setScanning(false);
    if (source === 'qr' && !/^TMCEBMS:RETURN:[a-f0-9]{64}$/.test(value)) {
      setError('Scan a return receipt QR code. Borrowing receipts cannot be used for returns.'); return;
    }
    processing.current = true;
    setBusy(true); setError(''); setNotice(''); setReceipt(null);
    setCondition(''); setConditionNotes(''); setPaid(''); setStudentId(''); setChecked(false); setReason('');
    try {
      const { data } = await api.get('/admin/returns/receipt', { params: { code: value.trim() } });
      setReceipt(data.data); setCode(data.data.transaction_code); setMethod(source);
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to find the return receipt.');
    } finally {
      processing.current = false; setBusy(false);
    }
  };

  const awaiting = receipt?.status === 'Approved' && receipt?.handover_status === 'Awaiting';
  const fee = Number(receipt?.fee_quote?.overdue_fee ?? receipt?.overdue_fee ?? 0);
  const days = Number(receipt?.fee_quote?.overdue_days ?? receipt?.overdue_days ?? 0);
  const findingsReady = condition !== '' && (condition === 'Good' || conditionNotes.trim() !== '') && (fee <= 0 || paid !== '');
  const rejectionReason = (reason.trim() || [condition !== 'Good' && conditionNotes.trim(), fee > 0 && paid === 'no' && 'Overdue fee not paid'].filter(Boolean).join('; ')).slice(0, 500);
  const inWindow = awaiting && now >= new Date(receipt.handover_starts_at).getTime() && now <= new Date(receipt.handover_ends_at).getTime();
  const canAccept = findingsReady && condition === 'Good' && (fee <= 0 || paid === 'yes') && checked && studentId.trim() && inWindow;

  const submit = async status => {
    if (processing.current) return;
    processing.current = true; setBusy(true); setError(''); setNotice('');
    try {
      const { data } = await api.post('/admin/returns/handover', {
        code: method === 'qr' ? receipt.qr_payload : receipt.transaction_code,
        status, method, condition_on_return: condition, condition_notes: condition === 'Good' ? undefined : conditionNotes.trim(),
        overdue_fee_paid: paid === 'yes', quoted_overdue_fee: fee,
        student_id_number: studentId.trim() || undefined, equipment_checked: checked,
        remarks: status === 'Rejected' ? rejectionReason : reason.trim() || undefined,
      });
      setReceipt(data.data); setNotice(data.message); onProcessed();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to submit the inspection.');
    } finally {
      processing.current = false; setBusy(false);
    }
  };

  return <section className="pickup-desk" aria-label="Equipment return inspection">
    <h2>Equipment return inspection</h2>
    <p>Find or scan the student's receipt, inspect the equipment and record any overdue-fee payment. Your decision goes to the admin for final verification.</p>
    <form className="toolbar" onSubmit={event => { event.preventDefault(); findReceipt(code); }}>
      <label>Return receipt number <input value={code} onChange={event => { setCode(event.target.value); setReceipt(null); }} inputMode="numeric" pattern="[0-9]{12}" maxLength={12} required disabled={busy} placeholder="12-digit return receipt number" /></label>
      <button className="btn btn-secondary" disabled={busy}>Find return receipt</button>
      <button type="button" className="btn btn-primary" disabled={busy} onClick={() => { setError(''); setScanning(true); }}>Scan return QR</button>
    </form>
    {error && <div className="alert error" role="alert">{error}</div>}
    {notice && <p role="status">{notice}</p>}
    {busy && <p role="status">Processing return receipt…</p>}
    {receipt && <div className="pickup-receipt">
      <h3>Return receipt {receipt.transaction_code}</h3>
      <p><b>Student:</b> {receipt.borrower?.name} · {receipt.borrower?.id_number || 'No student ID recorded'}</p>
      <p><b>Expected return:</b> {fmtDate(receipt.expected_return_date)}</p>
      <p><b>Return window:</b> {fmtDateTime(receipt.handover_starts_at)} – {fmtDateTime(receipt.handover_ends_at)}</p>
      <ul>{receipt.items?.map(item => <li key={item.equipment_id}><b>{item.name} × {item.quantity}</b> · Equipment #{item.equipment_id}{item.description && <p>{item.description}</p>}{item.pickup_location && <p><b>School pickup location:</b> {item.pickup_location}</p>}</li>)}</ul>
      <p><b>Status:</b> {receipt.status} · <b>Staff decision:</b> {receipt.handover_status}</p>
      {awaiting ? <>
        <label>Student ID presented <input value={studentId} maxLength={20} onChange={event => setStudentId(event.target.value)} disabled={busy} placeholder="ID on the physical student card" /></label>
        <label className="handover-check"><input type="checkbox" checked={checked} onChange={event => setChecked(event.target.checked)} disabled={busy} />I verified the student's identity and inspected all equipment, quantities and accessories.</label>
        <fieldset className="return-question"><legend>Is the equipment in good condition?</legend>
          <label><input type="radio" name="return-condition" checked={condition === 'Good'} onChange={() => { setCondition('Good'); setConditionNotes(''); }} disabled={busy} />Yes</label>
          <label><input type="radio" name="return-condition" checked={condition !== '' && condition !== 'Good'} onChange={() => setCondition('Damaged')} disabled={busy} />No</label>
        </fieldset>
        {condition !== '' && condition !== 'Good' && <>
          <label>Problem type <select value={condition} onChange={event => setCondition(event.target.value)} disabled={busy}>
            <option value="Damaged">Damaged equipment</option><option value="Incomplete">Missing equipment or accessories</option>
            <option value="NotPresented">Equipment not presented</option><option value="Other">Other problem</option>
          </select></label>
          <label>Describe the problem (required)<textarea value={conditionNotes} onChange={event => setConditionNotes(event.target.value)} maxLength={1000} disabled={busy} /></label>
        </>}
        <p><b>Overdue fee:</b> {fmtCurrency(fee)} {days > 0 && `(${days} day(s) past the expected return date)`}</p>
        {fee > 0 ? <fieldset className="return-question"><legend>Has the student paid {fmtCurrency(fee)}?</legend>
          <label><input type="radio" name="return-payment" checked={paid === 'yes'} onChange={() => setPaid('yes')} disabled={busy} />Yes, paid</label>
          <label><input type="radio" name="return-payment" checked={paid === 'no'} onChange={() => setPaid('no')} disabled={busy} />No, not paid</label>
        </fieldset> : <p>No overdue payment is required.</p>}
        <button type="button" className="btn btn-secondary small" disabled={busy} onClick={() => findReceipt(receipt.transaction_code)}>Refresh fee and inspection form</button>
        <label>Decision notes / rejection reason<textarea value={reason} onChange={event => setReason(event.target.value)} maxLength={500} disabled={busy} placeholder="For example: broken lens, or overdue fee not paid" /></label>
        {condition !== '' && condition !== 'Good' && <p role="status">Equipment problems must be resolved before the return can be accepted.</p>}
        {fee > 0 && paid === 'no' && <p role="status">An unpaid overdue fee prevents acceptance. Submit a rejection for the admin to review.</p>}
        {!inWindow && <p role="status">Acceptance is available during the approved return window.</p>}
        <div className="toolbar">
          <button type="button" className="btn btn-primary" disabled={busy || !canAccept} onClick={() => submit('Accepted')}>Accept return for admin verification</button>
          <button type="button" className="btn btn-danger" disabled={busy || !findingsReady || !rejectionReason} onClick={() => submit('Rejected')}>Reject return for admin verification</button>
        </div>
      </> : <>
        <p><b>Condition:</b> {receipt.condition_on_return || 'Not assessed'} {receipt.condition_notes && `— ${receipt.condition_notes}`}</p>
        <p><b>Overdue fee:</b> {fmtCurrency(fee)} · {receipt.payment_status}</p>
        <p><b>Staff reason:</b> {receipt.handover_remarks || '—'}</p>
        {receipt.admin_verified_at && <p><b>Admin verified:</b> {fmtDateTime(receipt.admin_verified_at)} · {receipt.admin_remarks || '—'}</p>}
      </>}
    </div>}
    <Modal open={scanning} title="Scan return receipt" onClose={() => setScanning(false)}>
      {scanning && <ReceiptQrScanner message="Scanning opens the return receipt. Inspect the equipment and record payment before submitting your decision." onScan={value => findReceipt(value, 'qr')} />}
    </Modal>
  </section>;
}
