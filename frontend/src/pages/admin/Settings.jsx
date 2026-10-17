import { useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import api from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import Skeleton from '../../components/Skeleton';
import { fmtCurrency } from '../../utils/datetime';

export default function Settings() {
  const { can } = useAuth();
  const [fee, setFee] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');

  const editable = can('settings', 'update');

  useEffect(() => {
    api.get('/admin/settings')
      .then(r => setFee(String(r.data.overdue_fee_per_day ?? 0)))
      .catch(() => setError('Unable to load settings. Please refresh the page.'))
      .finally(() => setLoading(false));
  }, []);

  const save = async (e) => {
    e.preventDefault();
    setBusy(true); setMsg(''); setError('');
    try {
      const { data } = await api.put('/admin/settings', { overdue_fee_per_day: Number(fee) });
      setFee(String(data.overdue_fee_per_day));
      setMsg('Settings saved.');
    } catch (err) {
      setError(err.response?.data?.message
        || Object.values(err.response?.data?.errors || {}).flat().join(' ')
        || 'Unable to save settings.');
    } finally { setBusy(false); }
  };

  return (
    <div className="panel">
      <h1>System Settings</h1>
      {loading ? <Skeleton rows={3} /> : (
        <>
          {msg && <div className="alert success">{msg}</div>}
          {error && <div className="alert error">{error}</div>}
          <p className="muted small-note">
            The overdue fee is charged for every day a borrowed item is returned after
            its expected return date. The amount is computed and locked in when a
            return is confirmed; early or on-time returns owe nothing.
          </p>
          <form onSubmit={save}>
            <label>
              Fee per overdue day (₱)
              <input
                type="number"
                min="0"
                step="0.01"
                value={fee}
                onChange={e => setFee(e.target.value)}
                disabled={!editable}
                required
              />
            </label>
            <p className="muted small-note">
              Example: a return 2 days late = {fmtCurrency(Number(fee || 0) * 2)}.
            </p>
            {editable
              ? <button className="btn btn-primary" disabled={busy}><Save size={16} /> Save Settings</button>
              : <p className="muted small-note">You do not have permission to change settings.</p>}
          </form>
        </>
      )}
    </div>
  );
}
