import { useEffect, useRef, useState } from 'react';
import { FileSearch, RotateCcw } from 'lucide-react';
import api from '../services/api';
import Modal from './Modal';
import ReceiptQrScanner from './ReceiptQrScanner';
import { ReceiptLookup, ReceiptSummary } from './ReceiptReview';
import { fmtCurrency, fmtDateTime } from '../utils/datetime';

export default function ReturnInspectionDesk({ onProcessed }) {
  const [code, setCode] = useState('');
  const [receipt, setReceipt] = useState(null);
  const [condition, setCondition] = useState('');
  const [conditionNotes, setConditionNotes] = useState('');
  const [paid, setPaid] = useState('');
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
    setCondition(''); setConditionNotes(''); setPaid(''); setChecked(false); setReason('');
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
  const canAccept = findingsReady && condition === 'Good' && (fee <= 0 || paid === 'yes') && checked && inWindow;

  const submit = async status => {
    if (processing.current) return;
    processing.current = true; setBusy(true); setError(''); setNotice('');
    try {
      const { data } = await api.post('/admin/returns/handover', {
        code: method === 'qr' ? receipt.qr_payload : receipt.transaction_code,
        status, method, condition_on_return: condition, condition_notes: condition === 'Good' ? undefined : conditionNotes.trim(),
        overdue_fee_paid: paid === 'yes', quoted_overdue_fee: fee,
        equipment_checked: checked,
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
    <div className="receipt-desk-heading"><span className="receipt-desk-icon"><RotateCcw size={23} /></span><div><h2>Equipment return inspection</h2><p>Find the return receipt, record your inspection and payment findings, then send your decision for admin verification.</p></div></div>
    <ReceiptLookup isReturn code={code} onChange={value => { setCode(value); setReceipt(null); }} onSubmit={event => { event.preventDefault(); findReceipt(code); }} onScan={() => { setError(''); setScanning(true); }} busy={busy} />
    {error && <div className="alert error" role="alert">{error}</div>}
    {notice && <div className="alert success" role="status">{notice}</div>}
    {busy && <p className="receipt-progress" role="status">Processing return receipt…</p>}
    {!receipt && !busy && <div className="receipt-empty"><FileSearch size={26} /><div><b>Find a return receipt to get started</b><p>Inspection and payment questions will appear after you open a receipt.</p></div></div>}
    {receipt && <div className="receipt-review-layout">
      <ReceiptSummary receipt={receipt} isReturn awaiting={awaiting} inWindow={inWindow} />
      <section className="receipt-decision" aria-label="Return inspection findings">
      {awaiting ? <>
        <span className="receipt-step">Inspection findings</span><h3>Review the return</h3>
        <p className="receipt-help">Complete each section below. An administrator will verify your decision before the loan is closed.</p>
        <div className="receipt-form-section">
        <h4>Equipment check</h4>
        <label className="handover-check"><input type="checkbox" checked={checked} onChange={event => setChecked(event.target.checked)} disabled={busy} /><span>I checked the student against the ID shown on the receipt and inspected the equipment, quantities and accessories.</span></label>
        </div>
        <div className="receipt-form-section">
        <fieldset className="return-question"><legend>Is the equipment in good condition?</legend>
          <label className={condition === 'Good' ? 'is-selected' : ''}><input type="radio" name="return-condition" checked={condition === 'Good'} onChange={() => { setCondition('Good'); setConditionNotes(''); }} disabled={busy} />Yes, good condition</label>
          <label className={condition !== '' && condition !== 'Good' ? 'is-selected' : ''}><input type="radio" name="return-condition" checked={condition !== '' && condition !== 'Good'} onChange={() => setCondition('Damaged')} disabled={busy} />No, there is a problem</label>
        </fieldset>
        {condition !== '' && condition !== 'Good' && <>
          <label>Problem type <select value={condition} onChange={event => setCondition(event.target.value)} disabled={busy}>
            <option value="Damaged">Damaged equipment</option><option value="Incomplete">Missing equipment or accessories</option>
            <option value="NotPresented">Equipment not presented</option><option value="Other">Other problem</option>
          </select></label>
          <label>Describe the problem (required)<textarea value={conditionNotes} onChange={event => setConditionNotes(event.target.value)} maxLength={1000} disabled={busy} rows={3} placeholder="Describe the damage, missing accessories, or other issue." /></label>
        </>}
        </div>
        <div className="receipt-form-section">
        <div className={`receipt-fee ${fee > 0 ? 'has-fee' : ''}`}><div><b>Overdue fee</b><span>{days > 0 ? `${days} day(s) overdue` : 'No overdue payment required'}</span></div><strong>{fmtCurrency(fee)}</strong></div>
        {fee > 0 ? <fieldset className="return-question"><legend>Has the student paid {fmtCurrency(fee)}?</legend>
          <label className={paid === 'yes' ? 'is-selected' : ''}><input type="radio" name="return-payment" checked={paid === 'yes'} onChange={() => setPaid('yes')} disabled={busy} />Yes, paid</label>
          <label className={paid === 'no' ? 'is-selected' : ''}><input type="radio" name="return-payment" checked={paid === 'no'} onChange={() => setPaid('no')} disabled={busy} />No, not paid</label>
        </fieldset> : null}
        <button type="button" className="btn btn-secondary small" disabled={busy} onClick={() => findReceipt(method === 'qr' ? receipt.qr_payload : receipt.transaction_code, method)}><RotateCcw size={14} /> Refresh fee</button>
        <p className="receipt-help">Refreshing also resets the inspection answers.</p>
        </div>
        <div className="receipt-form-section">
        <label>Decision notes / rejection reason<textarea value={reason} onChange={event => setReason(event.target.value)} maxLength={500} disabled={busy} rows={3} placeholder="Add instructions or explain why the return cannot be accepted." /></label>
        {condition !== '' && condition !== 'Good' && <p className="receipt-callout" role="status">Equipment problems must be resolved before the return can be accepted.</p>}
        {fee > 0 && paid === 'no' && <p className="receipt-callout" role="status">The overdue fee is unpaid. Submit a rejection for admin review.</p>}
        {!inWindow && <p className="receipt-callout" role="status">Acceptance is available during the approved return window.</p>}
        <div className="receipt-decision-actions">
          <button type="button" className="btn btn-primary" disabled={busy || !canAccept} onClick={() => submit('Accepted')}>Accept return</button>
          <button type="button" className="btn btn-danger" disabled={busy || !findingsReady || !rejectionReason} onClick={() => submit('Rejected')}>Reject return</button>
        </div>
        <p className="receipt-help">Both decisions are sent to an administrator for final verification.</p>
        </div>
      </> : <>
        <span className="receipt-step">Inspection record</span><h3>{receipt.status === 'Awaiting Verification' ? 'Sent for admin verification' : 'Return reviewed'}</h3>
        <dl className="receipt-notes">
          <div><dt>Staff decision</dt><dd>{receipt.handover_status}</dd></div>
          <div><dt>Condition</dt><dd>{receipt.condition_on_return || 'Not assessed'}{receipt.condition_notes && ` — ${receipt.condition_notes}`}</dd></div>
          <div><dt>Overdue fee</dt><dd>{fmtCurrency(fee)} · {receipt.payment_status || 'Not recorded'}</dd></div>
          <div><dt>Staff reason</dt><dd>{receipt.handover_remarks || '—'}</dd></div>
          {receipt.admin_verified_at && <div><dt>Admin verified</dt><dd>{fmtDateTime(receipt.admin_verified_at)} · {receipt.admin_remarks || '—'}</dd></div>}
        </dl>
      </>}
      </section>
    </div>}
    <Modal open={scanning} title="Scan return receipt" onClose={() => setScanning(false)}>
      {scanning && <ReceiptQrScanner message="Scanning opens the return receipt. Inspect the equipment and record payment before submitting your decision." onScan={value => findReceipt(value, 'qr')} />}
    </Modal>
  </section>;
}
