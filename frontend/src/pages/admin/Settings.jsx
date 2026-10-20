import { useEffect, useState } from 'react';
import { Plus, Save, Trash2 } from 'lucide-react';
import api from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import Skeleton from '../../components/Skeleton';
import { fmtCurrency } from '../../utils/datetime';

const newPolicy = () => ({
  id: (crypto.randomUUID?.() || String(Date.now() + Math.random())),
  title: '',
  content: '',
});

export default function Settings() {
  const { can } = useAuth();
  const [fee, setFee] = useState('');
  const [policies, setPolicies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [pBusy, setPBusy] = useState(false);
  const [pMsg, setPMsg] = useState('');
  const [pError, setPError] = useState('');

  const editable = can('settings', 'update');

  useEffect(() => {
    api.get('/admin/settings')
      .then(r => {
        setFee(String(r.data.overdue_fee_per_day ?? 0));
        setPolicies(Array.isArray(r.data.platform_policies) ? r.data.platform_policies : []);
      })
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

  const addPolicy = () => setPolicies(list => [...list, newPolicy()]);
  const updatePolicy = (id, field, value) =>
    setPolicies(list => list.map(p => (p.id === id ? { ...p, [field]: value } : p)));
  const removePolicy = (id) => setPolicies(list => list.filter(p => p.id !== id));

  const policiesValid = policies.every(p => p.title.trim() && p.content.trim());

  const savePolicies = async (e) => {
    e.preventDefault();
    if (!policiesValid) return;
    setPBusy(true); setPMsg(''); setPError('');
    try {
      const { data } = await api.put('/admin/settings/policies', {
        policies: policies.map(p => ({ id: p.id, title: p.title.trim(), content: p.content.trim() })),
      });
      setPolicies(Array.isArray(data.platform_policies) ? data.platform_policies : []);
      setPMsg(data.message || 'Platform policies saved.');
    } catch (err) {
      setPError(err.response?.data?.message
        || Object.values(err.response?.data?.errors || {}).flat().join(' ')
        || 'Unable to save platform policies.');
    } finally { setPBusy(false); }
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

          <section className="settings-section">
            <h2>Platform Policies</h2>
            <p className="muted small-note">
              Add the terms &amp; conditions, privacy policy, and any other guidelines
              users must follow. Each policy has a title and its full text.
            </p>

            {pMsg && <div className="alert success">{pMsg}</div>}
            {pError && <div className="alert error">{pError}</div>}

            {policies.length === 0 && (
              <p className="muted small-note">No policies yet. Add your first one below.</p>
            )}

            <form onSubmit={savePolicies}>
              {policies.map((p, i) => (
                <div className="policy-card" key={p.id}>
                  <div className="policy-card-head">
                    <span className="policy-index">Policy {i + 1}</span>
                    {editable && (
                      <button type="button" className="btn btn-danger small" onClick={() => removePolicy(p.id)}>
                        <Trash2 size={14} /> Remove
                      </button>
                    )}
                  </div>
                  <label>
                    Title
                    <input
                      value={p.title}
                      onChange={e => updatePolicy(p.id, 'title', e.target.value)}
                      maxLength={150}
                      placeholder="e.g. Terms and Conditions"
                      disabled={!editable}
                      required
                    />
                  </label>
                  <label>
                    Content
                    <textarea
                      value={p.content}
                      onChange={e => updatePolicy(p.id, 'content', e.target.value)}
                      rows={7}
                      maxLength={20000}
                      placeholder="Write the full policy text…"
                      disabled={!editable}
                      required
                    />
                  </label>
                </div>
              ))}

              {editable ? (
                <div className="settings-actions">
                  <button type="button" className="btn btn-secondary" onClick={addPolicy}>
                    <Plus size={16} /> Add policy
                  </button>
                  <button className="btn btn-primary" disabled={pBusy || !policiesValid}>
                    <Save size={16} /> {pBusy ? 'Saving…' : 'Save Policies'}
                  </button>
                </div>
              ) : (
                <p className="muted small-note">You do not have permission to change settings.</p>
              )}
            </form>
          </section>
        </>
      )}
    </div>
  );
}
