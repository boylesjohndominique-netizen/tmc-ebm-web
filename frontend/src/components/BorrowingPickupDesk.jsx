import { useEffect, useRef, useState } from 'react';
import { FileSearch, PackageCheck } from 'lucide-react';
import api from '../services/api';
import Modal from './Modal';
import ReceiptQrScanner from './ReceiptQrScanner';
import { ReceiptLookup, ReceiptSummary } from './ReceiptReview';
import { fmtDateTime } from '../utils/datetime';

export default function BorrowingPickupDesk({ onProcessed }) {
  const [code, setCode] = useState('');
  const [receipt, setReceipt] = useState(null);
  const [source, setSource] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [scanning, setScanning] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const processing = useRef(false);
  const lookupSequence = useRef(0);
  const [now, setNow] = useState(Date.now);

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);

  const findReceipt = async (value, method = 'manual') => {
    if (processing.current) return;
    setScanning(false);
    if (method === 'qr' && !/^TMCEBMS:[a-f0-9]{64}$/.test(value)) {
      setError('Scan a borrowing receipt QR code. Return receipts cannot be used for pickup.'); return;
    }
    const sequence = ++lookupSequence.current;
    setBusy(true); setError(''); setNotice(''); setReceipt(null); setSource(null); setRemarks('');
    try {
      const { data } = await api.get('/admin/borrowings/receipt', { params: { code: value.trim() } });
      if (sequence !== lookupSequence.current) return;
      setReceipt(data.data); setCode(data.data.transaction_code); setSource({ code: value.trim(), method });
    } catch (err) {
      if (sequence === lookupSequence.current) setError(err.response?.data?.message || 'Receipt not found. Check the 12-digit number.');
    } finally {
      if (sequence === lookupSequence.current) setBusy(false);
    }
  };

  const process = async status => {
    if (processing.current || !receipt || !source) return;
    processing.current = true;
    ++lookupSequence.current;
    setBusy(true); setError(''); setNotice('');
    try {
      const { data } = await api.post('/admin/borrowings/pickup', {
        code: source.code, status, method: source.method,
        remarks: status === 'Rejected' ? remarks.trim() : undefined,
      });
      setReceipt(data.data); setNotice(data.message); onProcessed();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to process this pickup.');
    } finally {
      processing.current = false; setBusy(false);
    }
  };

  const awaiting = receipt?.status === 'Approved' && receipt?.pickup_status === 'Awaiting';
  const inWindow = awaiting && now >= new Date(receipt.pickup_starts_at).getTime() && now <= new Date(receipt.pickup_ends_at).getTime();

  return <section className="pickup-desk" aria-label="Equipment handover">
    <div className="receipt-desk-heading"><span className="receipt-desk-icon"><PackageCheck size={23} /></span><div><h2>Equipment handover</h2><p>Find the receipt, review the student and equipment details, then confirm or reject pickup.</p></div></div>
    <ReceiptLookup code={code} onChange={value => { setCode(value); setReceipt(null); setSource(null); }} onSubmit={event => { event.preventDefault(); findReceipt(code); }} onScan={() => { setError(''); setScanning(true); }} busy={busy} />
    {error && <div className="alert error" role="alert">{error}</div>}
    {notice && <div className="alert success" role="status">{notice}</div>}
    {busy && <p className="receipt-progress" role="status">Processing receipt…</p>}
    {!receipt && !busy && <div className="receipt-empty"><FileSearch size={26} /><div><b>Find a receipt to get started</b><p>The student's details and pickup actions will appear here.</p></div></div>}
    {receipt && <div className="receipt-review-layout">
      <ReceiptSummary receipt={receipt} awaiting={awaiting} inWindow={inWindow} />
      <section className="receipt-decision" aria-label="Pickup decision">
        {awaiting ? <>
          <span className="receipt-step">Staff decision</span><h3>Complete the pickup</h3>
          <p className="receipt-help">The student's identity is shown on the receipt. Confirm when the equipment has been handed over, or give a reason to reject pickup.</p>
          {!inWindow && <p className="receipt-callout" role="status">Confirmation is available during the approved pickup window shown on the receipt.</p>}
          <label>Rejection reason<textarea value={remarks} maxLength={500} onChange={event => setRemarks(event.target.value)} disabled={busy} placeholder="Required only when rejecting pickup" rows={3} /></label>
          <div className="receipt-decision-actions">
            <button type="button" className="btn btn-primary" disabled={busy || !inWindow} onClick={() => process('Received')}>Confirm pickup</button>
            <button type="button" className="btn btn-danger" disabled={busy || !remarks.trim()} onClick={() => process('Rejected')}>Reject pickup</button>
          </div>
          <p className="receipt-help">Rejecting releases the reserved equipment and notifies the student and administrators.</p>
        </> : <>
          <span className="receipt-step">Pickup record</span><h3>{receipt.pickup_status === 'Received' ? 'Pickup confirmed' : receipt.pickup_status === 'Rejected' ? 'Pickup rejected' : 'Receipt status'}</h3>
          <dl className="receipt-notes">
            {receipt.pickup_processed_at && <div><dt>Processed at</dt><dd>{fmtDateTime(receipt.pickup_processed_at)}</dd></div>}
            {receipt.pickup_staff_name && <div><dt>Staff</dt><dd>{receipt.pickup_staff_name}</dd></div>}
            {receipt.pickup_remarks && <div><dt>Staff remarks</dt><dd>{receipt.pickup_remarks}</dd></div>}
            {receipt.verified_student_id && <div><dt>Verified student ID</dt><dd>{receipt.verified_student_id}</dd></div>}
          </dl>
          <p className="receipt-help">This receipt is no longer awaiting pickup.</p>
        </>}
      </section>
    </div>}
    <Modal open={scanning} title="Scan borrowing receipt" onClose={() => setScanning(false)}>
      {scanning && <ReceiptQrScanner message="Scan the QR code to open the borrowing receipt. Review its details before confirming or rejecting pickup." onScan={value => findReceipt(value, 'qr')} />}
    </Modal>
  </section>;
}
