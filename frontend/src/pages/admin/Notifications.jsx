import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  BellRing, Send, Search, Check, CheckCheck, ClipboardList,
  CheckCircle2, Settings2, RotateCcw, Inbox, Lock, X,
  ShieldCheck, UserPlus, Ban,
} from 'lucide-react';
import api from '../../services/api';
import { useNotifications } from '../../context/NotificationContext';
import { useAuth } from '../../context/AuthContext';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import { timeAgo, fmtDateTime } from '../../utils/datetime';
import useRealtimeResource from '../../hooks/useRealtimeResource';

const emptyForm = { user_id: '', title: '', message: '', type: 'System' };

/*
 * Notification type vocabulary — the backend tags every event with the
 * action that caused it (Borrow/Return/Verification/Registration/Account),
 * so the inbox can show the right icon and let staff filter by event kind.
 * Request/Success/System remain for older rows and manual broadcasts.
 */
const TYPES = {
  Borrow:       { icon: ClipboardList,  cls: 'request' },
  Return:       { icon: RotateCcw,      cls: 'return' },
  Verification: { icon: ShieldCheck,    cls: 'verification' },
  Registration: { icon: UserPlus,       cls: 'registration' },
  Account:      { icon: Ban,            cls: 'account' },
  Request:      { icon: ClipboardList,  cls: 'request' },
  Success:      { icon: CheckCircle2,   cls: 'success' },
  System:       { icon: Settings2,      cls: 'system' },
};
const typeMeta = t => TYPES[t] || TYPES.System;

const FILTERS = ['all', 'unread', 'Borrow', 'Return', 'Verification', 'Registration', 'Account', 'Request', 'Success', 'System'];
const filterLabel = f =>
  f === 'all' ? 'All' : f === 'unread' ? 'Unread' : f;

export default function Notifications() {
  const { can } = useAuth();
  const { unread, refresh } = useNotifications();
  const [users, setUsers] = useState([]);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('all');
  const [query, setQuery] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    return api.get('/notifications')
      .then(r => setItems(r.data))
      .then(() => api.get('/admin/notification-users'))
      .then(r => setUsers(r.data))
      .catch(() => setError('Unable to load notifications. Please refresh the page.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  // New notifications ping the private user channel; refetch the inbox.
  useRealtimeResource(['user:notification'], load);

  /* Optimistic read updates — the row reacts instantly, then the
     unread-count badge syncs via the polling context. */
  const markRead = async id => {
    setItems(list => list.map(x => (x.id === id && !x.read_at ? { ...x, read_at: new Date().toISOString() } : x)));
    try {
      await api.put(`/notifications/${id}/read`);
      refresh();
    } catch {
      load(); // revert to server state on failure
    }
  };

  const markAllRead = async () => {
    setItems(list => list.map(x => (x.read_at ? x : { ...x, read_at: new Date().toISOString() })));
    try {
      await api.put('/notifications/read-all');
      refresh();
    } catch {
      load();
    }
  };

  const send = async e => {
    e.preventDefault();
    setBusy(true);
    setMsg('');
    setError('');
    try {
      await api.post('/admin/notifications', form);
      const u = users.find(x => String(x.id) === String(form.user_id));
      setMsg(`Notification sent to ${u ? `${u.first_name} ${u.last_name}` : 'user'}.`);
      setForm({ ...emptyForm });
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to send the notification.');
    } finally {
      setBusy(false);
    }
  };

  /* Derived: counts for the stat cards + filtered list.
     Unknown/legacy types are counted under System so totals always add up. */
  const counts = useMemo(() => {
    const byType = {};
    for (const x of items) {
      const t = TYPES[x.type] ? x.type : 'System';
      byType[t] = (byType[t] || 0) + 1;
    }
    byType.total = items.length;
    byType.unread = items.filter(x => !x.read_at).length;
    return byType;
  }, [items]);

  const filtered = useMemo(() => items.filter(x => {
    const type = TYPES[x.type] ? x.type : 'System';
    if (tab === 'unread' && x.read_at) return false;
    if (tab !== 'all' && tab !== 'unread' && type !== tab) return false;
    if (query) {
      const q = query.toLowerCase();
      return (x.title || '').toLowerCase().includes(q) || (x.message || '').toLowerCase().includes(q);
    }
    return true;
  }), [items, tab, query]);

  const hasFilters = tab !== 'all' || query.trim() !== '';
  const canSend = can('notifications', 'send');

  return (
    <>
      {/* ── Stat cards ─────────────────────────────────────── */}
      <div className="notif-stats">
        <div className="notif-stat">
          <div className="notif-stat-icon all"><Inbox size={20} /></div>
          <div><b className="notif-stat-value">{counts.total}</b><span className="notif-stat-label">Total</span></div>
        </div>
        <div className={`notif-stat ${counts.unread ? 'pulse' : ''}`}>
          <div className="notif-stat-icon unread"><BellRing size={20} /></div>
          <div><b className="notif-stat-value">{counts.unread}</b><span className="notif-stat-label">Unread</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon request"><ClipboardList size={20} /></div>
          <div><b className="notif-stat-value">{counts.Borrow || 0}</b><span className="notif-stat-label">Borrow</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon return"><RotateCcw size={20} /></div>
          <div><b className="notif-stat-value">{counts.Return || 0}</b><span className="notif-stat-label">Returns</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon verification"><ShieldCheck size={20} /></div>
          <div><b className="notif-stat-value">{counts.Verification || 0}</b><span className="notif-stat-label">Verification</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon registration"><UserPlus size={20} /></div>
          <div><b className="notif-stat-value">{counts.Registration || 0}</b><span className="notif-stat-label">Registrations</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon account"><Ban size={20} /></div>
          <div><b className="notif-stat-value">{counts.Account || 0}</b><span className="notif-stat-label">Account</span></div>
        </div>
        <div className="notif-stat">
          <div className="notif-stat-icon success"><CheckCircle2 size={20} /></div>
          <div><b className="notif-stat-value">{counts.Success || 0}</b><span className="notif-stat-label">Success</span></div>
        </div>
      </div>

      <div className="notif-layout">
        {/* ── Inbox ──────────────────────────────────────────── */}
        <section className="panel notif-inbox">
          <div className="panel-header">
            <div>
              <h2>Notifications</h2>
              <p className="notif-subtitle">
                {counts.unread > 0
                  ? `${counts.unread} unread notification${counts.unread > 1 ? 's' : ''}.`
                  : 'You are all caught up.'}
              </p>
            </div>
            <button
              className="btn btn-secondary small"
              onClick={markAllRead}
              disabled={loading || counts.unread === 0}
              title="Mark every notification as read"
            >
              <CheckCheck size={15} /> Mark All Read
            </button>
          </div>

          <div className="notif-toolbar">
            <div className="segmented-tabs" role="tablist" aria-label="Filter notifications">
              {FILTERS.map(f => (
                <button
                  key={f}
                  role="tab"
                  aria-selected={tab === f}
                  className={tab === f ? 'active' : ''}
                  onClick={() => setTab(f)}
                >
                  {filterLabel(f)}
                  {f === 'unread' && counts.unread > 0 && <span className="tab-count">{counts.unread}</span>}
                </button>
              ))}
            </div>
            <div className="notif-search">
              <Search size={15} />
              <input
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Search notifications…"
                aria-label="Search notifications"
              />
              {query && (
                <button className="notif-search-clear" onClick={() => setQuery('')} aria-label="Clear search">
                  <X size={13} />
                </button>
              )}
            </div>
          </div>

          {loading && <Skeleton rows={5} />}

          {!loading && (filtered.length ? (
            <>
              <div className="notif-list">
                {filtered.map(x => {
                  const meta = typeMeta(x.type);
                  const Icon = meta.icon;
                  const unreadRow = !x.read_at;
                  return (
                    <div
                      key={x.id}
                      className={`notif-row ${unreadRow ? 'unread' : ''}`}
                      onClick={() => unreadRow && markRead(x.id)}
                      onKeyDown={e => { if (unreadRow && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); markRead(x.id); } }}
                      role={unreadRow ? 'button' : undefined}
                      tabIndex={unreadRow ? 0 : undefined}
                      aria-label={unreadRow ? 'Mark as read' : undefined}
                    >
                      <div className={`notif-icon ${meta.cls}`}><Icon size={18} /></div>
                      <div className="notif-body">
                        <div className="notif-title-row">
                          <h3 className={unreadRow ? 'unread-title' : ''}>{x.title}</h3>
                          {unreadRow && <span className="new-chip">NEW</span>}
                        </div>
                        <p className="notif-msg">{x.message}</p>
                        <small className="notif-time" title={fmtDateTime(x.created_at)}>{timeAgo(x.created_at)}</small>
                      </div>
                      <div className="notif-actions">
                        {unreadRow
                          ? <button
                              className="row-icon-btn"
                              onClick={e => { e.stopPropagation(); markRead(x.id); }}
                              title="Mark as read"
                              aria-label="Mark as read"
                            >
                              <Check size={15} />
                            </button>
                          : <span className="row-icon-btn done" title="Read"><Check size={15} /></span>}
                      </div>
                    </div>
                  );
                })}
              </div>
              <p className="notif-footer">
                Showing {filtered.length} of {items.length} notification{items.length === 1 ? '' : 's'}
              </p>
            </>
          ) : hasFilters ? (
            <EmptyState
              icon={Search}
              title="No matching notifications"
              message="Try a different filter or search term."
              action={{ label: 'Clear filters', onClick: () => { setTab('all'); setQuery(''); } }}
            />
          ) : (
            <EmptyState
              icon={BellRing}
              title="No notifications"
              message="System updates and messages will appear here."
            />
          ))}
        </section>

        {/* ── Compose ────────────────────────────────────────── */}
        <section className="panel compose-panel">
          {canSend ? (
            <>
              <div className="compose-head">
                <div className="compose-icon"><Send size={18} /></div>
                <div>
                  <h2>Send Notification</h2>
                  <p className="notif-subtitle">Message a staff member directly.</p>
                </div>
              </div>

              {msg && <div className="alert success">{msg}</div>}
              {error && <div className="alert error">{error}</div>}

              <form onSubmit={send} className="compose-form">
                <label>Recipient
                  <select
                    value={form.user_id}
                    onChange={e => setForm({ ...form, user_id: e.target.value })}
                    required
                  >
                    <option value="">Select user</option>
                    {users.map(u => (
                      <option key={u.id} value={u.id}>{u.first_name} {u.last_name} — {u.username}</option>
                    ))}
                  </select>
                </label>

                <label>Type
                  <select
                    value={form.type}
                    onChange={e => setForm({ ...form, type: e.target.value })}
                  >
                    <option value="Request">Request</option>
                    <option value="Success">Success</option>
                    <option value="System">System</option>
                  </select>
                </label>

                <label>
                  <span className="label-row">Title <span className="char-counter">{form.title.length}/150</span></span>
                  <input
                    value={form.title}
                    onChange={e => setForm({ ...form, title: e.target.value })}
                    maxLength={150}
                    placeholder="e.g. Equipment return reminder"
                    required
                  />
                </label>

                <label>
                  <span className="label-row">Message</span>
                  <textarea
                    value={form.message}
                    onChange={e => setForm({ ...form, message: e.target.value })}
                    placeholder="Write a clear, friendly message…"
                    required
                  />
                </label>

                <div className="compose-actions">
                  <button type="button" className="btn btn-danger" onClick={() => setForm({ ...emptyForm })} disabled={busy || (!form.title && !form.message && !form.user_id)}>
                    <RotateCcw size={15} /> Reset
                  </button>
                  <button className="btn btn-primary" disabled={busy}>
                    <Send size={15} /> {busy ? 'Sending…' : 'Send'}
                  </button>
                </div>
              </form>
            </>
          ) : (
            <div className="compose-locked">
              <div className="compose-icon system"><Lock size={18} /></div>
              <h2>Send Notification</h2>
              <p className="muted">You do not have permission to send notifications. Contact a super admin if you need access.</p>
            </div>
          )}
        </section>
      </div>
    </>
  );
}
