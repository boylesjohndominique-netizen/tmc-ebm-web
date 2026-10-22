import { useEffect, useRef, useState } from 'react';
import api from '../services/api';
import Modal from './Modal';
import ReceiptQrScanner from './ReceiptQrScanner';
import { fmtDate, fmtDateTime } from '../utils/datetime';

export default function BorrowingPickupDesk({ onProcessed, isReturn = false }) {
  const [code, setCode] = useState('');
  const [receipt, setReceipt] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [scanning, setScanning] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const processing = useRef(false);
  const lookupSequence = useRef(0);
  const [now, setNow] = useState(Date.now);
  const [studentId, setStudentId] = useState('');
  const [equipmentChecked, setEquipmentChecked] = useState(false);
  const [condition, setCondition] = useState('Damaged');
  const resource = isReturn ? 'returns' : 'borrowings';
  const label = isReturn ? 'Return' : 'Pickup';
  const confirmation = isReturn ? 'Confirmed' : 'Received';
  const ready = studentId.trim() !== '' && equipmentChecked;

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);

  const lookup = async event => {
    event.preventDefault();
    if (processing.current) return;
    const sequence = ++lookupSequence.current;
    setBusy(true); setError(''); setNotice(''); setReceipt(null); setRemarks('');
    try {
      const { data } = await api.get(`/admin/${resource}/receipt`, { params: { code: code.trim() } });
      if (sequence === lookupSequence.current) setReceipt(data.data);
    } catch (err) {
      if (sequence === lookupSequence.current) setError(err.response?.data?.message || 'Receipt not found. Check the 12-digit number.');
    } finally {
      if (sequence === lookupSequence.current) setBusy(false);
    }
  };

  const process = async (value, status, method) => {
    if (processing.current) return;
    if (method === 'qr' && !(isReturn ? /^TMCEBMS:RETURN:[a-f0-9]{64}$/ : /^TMCEBMS:[a-f0-9]{64}$/).test(value)) {
      setScanning(false); setError('This is not a TMC equipment receipt QR code.'); return;
    }
    if (status === confirmation && !ready) {
      setError('Match the student ID and inspect all equipment before confirming.'); return;
    }
    processing.current = true;
    ++lookupSequence.current;
    setScanning(false); setBusy(true); setError(''); setNotice('');
    if (method === 'qr') { setReceipt(null); setRemarks(''); }
    try {
      const { data } = await api.post(`/admin/${resource}/${isReturn ? 'handover' : 'pickup'}`, {
        code: value, status, method, remarks: status === 'Rejected' ? remarks.trim() : undefined,
        ...(status === confirmation ? { student_id_number: studentId.trim(), equipment_checked: equipmentChecked } : {}),
        ...(isReturn ? { condition_on_return: status === confirmation ? 'Good' : condition } : {}),
      });
      setReceipt(data.data); setCode(data.data.transaction_code); setNotice(data.message);
      setStudentId(''); setEquipmentChecked(false);
      onProcessed();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to process this pickup.');
    } finally {
      processing.current = false;
      setBusy(false);
    }
  };

  const handoverStatus = isReturn ? receipt?.handover_status : receipt?.pickup_status;
  const startsAt = isReturn ? receipt?.handover_starts_at : receipt?.pickup_starts_at;
  const endsAt = isReturn ? receipt?.handover_ends_at : receipt?.pickup_ends_at;
  const processedAt = isReturn ? receipt?.handover_processed_at : receipt?.pickup_processed_at;
  const staffName = isReturn ? receipt?.handover_staff_name : receipt?.pickup_staff_name;
  const handoverRemarks = isReturn ? receipt?.handover_remarks : receipt?.pickup_remarks;
  const awaiting = receipt?.status === 'Approved' && handoverStatus === 'Awaiting';
  const inWindow = awaiting && now >= new Date(startsAt).getTime() && now <= new Date(endsAt).getTime();

  return <section className="pickup-desk" aria-label={isReturn ? 'Equipment return inspection' : 'Equipment handover'}>
    <h2>{isReturn ? 'Equipment return inspection' : 'Equipment handover'}</h2>
    <p>Only staff can confirm {isReturn ? 'a return' : 'pickup'}. Match the physical student ID to the receipt and inspect all equipment and accessories.</p>
    <label>Student ID presented
      <input value={studentId} maxLength={20} onChange={event => setStudentId(event.target.value)} disabled={busy} placeholder="Enter the ID on the student's physical card" />
    </label>
    <label className="handover-check">
      <input type="checkbox" checked={equipmentChecked} onChange={event => setEquipmentChecked(event.target.checked)} disabled={busy} />
      I checked the student identity, equipment, quantities and accessories. All equipment is complete and undamaged.
    </label>
    <form className="toolbar" onSubmit={lookup}>
      <label>Receipt number
        <input value={code} onChange={event => { setCode(event.target.value); setReceipt(null); setEquipmentChecked(false); }} inputMode="numeric" pattern="[0-9]{12}" maxLength={12} required placeholder="12-digit receipt number" disabled={busy} />
      </label>
      <button type="submit" className="btn btn-secondary" disabled={busy}>Find receipt</button>
      <button type="button" className="btn btn-primary" disabled={busy || !ready} onClick={() => { setError(''); setScanning(true); }}>Scan QR &amp; confirm {isReturn ? 'return' : 'pickup'}</button>
    </form>
    {error && <div className="alert error" role="alert">{error}</div>}
    {notice && <p role="status">{notice}</p>}
    {busy && <p role="status">Processing receipt…</p>}
    {receipt && <div className="pickup-receipt">
      <h3>Receipt {receipt.transaction_code}</h3>
      <p><b>Student:</b> {receipt.borrower?.name} · {receipt.borrower?.id_number || 'No student ID recorded'}</p>
      <p><b>Contact:</b> {receipt.borrower?.email} {receipt.borrower?.contact_number}</p>
      <p><b>Status:</b> {receipt.status} · {label}: {handoverStatus}</p>
      <p><b>{label} window:</b> {fmtDateTime(startsAt)} – {fmtDateTime(endsAt)}</p>
      <p><b>Borrowing date:</b> {fmtDate(receipt.borrowing_date)} · <b>Return by:</b> {fmtDate(receipt.expected_return_date)}</p>
      <ul>{receipt.items?.map(item => <li key={item.equipment_id}>
        <b>{item.name} × {item.quantity}</b> · Equipment #{item.equipment_id} · {item.category} · {item.condition}
        {item.description && <p>{item.description}</p>}
      </li>)}</ul>
      <p><b>Purpose / student notes:</b> {receipt.borrower_remarks || '—'}</p>
      <p><b>Approved by:</b> {receipt.approved_by} · {fmtDateTime(receipt.approved_at)}</p>
      {receipt.approval_remarks && <p><b>Admin instructions:</b> {receipt.approval_remarks}</p>}
      {processedAt && <p><b>{label} processed:</b> {fmtDateTime(processedAt)}</p>}
      {staffName && <p><b>Staff:</b> {staffName}</p>}
      {receipt.verified_student_id && <p><b>Verified student ID:</b> {receipt.verified_student_id}</p>}
      {receipt.condition_on_return && <p><b>Inspected condition:</b> {receipt.condition_on_return}</p>}
      {handoverRemarks && <p><b>Staff remarks:</b> {handoverRemarks}</p>}
      {awaiting && <>
        {!inWindow && <p role="status">Confirmation is available only during the approved {label.toLowerCase()} window.</p>}
        {isReturn && <label>Reason for refusing the return
          <select value={condition} onChange={event => setCondition(event.target.value)} disabled={busy}>
            <option value="Damaged">Damaged equipment</option><option value="Incomplete">Missing equipment or accessories</option>
            <option value="NotPresented">Student or equipment not presented</option><option value="Other">Other issue</option>
          </select>
        </label>}
        <label>Rejection reason
          <textarea value={remarks} maxLength={500} onChange={event => setRemarks(event.target.value)} disabled={busy} placeholder="For example: Student did not arrive during the pickup window." />
        </label>
        <div className="toolbar">
          <button className="btn btn-primary" disabled={busy || !inWindow || !ready} onClick={() => process(receipt.transaction_code, confirmation, 'manual')}>{isReturn ? 'Confirm inspected equipment returned' : 'Confirm student received equipment'}</button>
          <button className="btn btn-danger" disabled={busy || !remarks.trim()} onClick={() => process(receipt.transaction_code, 'Rejected', 'manual')}>{isReturn ? 'Reject return & notify admin' : 'Reject pickup & release stock'}</button>
        </div>
      </>}
    </div>}
    <Modal open={scanning} title="Scan approval receipt QR code" onClose={() => setScanning(false)}>
      {scanning && <ReceiptQrScanner onScan={value => process(value, confirmation, 'qr')} />}
    </Modal>
  </section>;
}
