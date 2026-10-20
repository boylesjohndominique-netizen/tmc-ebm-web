import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  BellRing, Send, Search, Check, CheckCheck, ClipboardList,
  CheckCircle2, Settings2, RotateCcw, Inbox, Lock, X,
  ShieldCheck, UserPlus, Ban, Archive, ArchiveRestore, Eye, EyeOff,
  Trash2, Square, CheckSquare,
} from 'lucide-react';
import api from '../../services/api';
import { useNotifications } from '../../context/NotificationContext';
import { useAuth } from '../../context/AuthContext';
import { useConfirm } from '../../context/ConfirmContext';
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

const SEND_ALL = '__all__';

export default function Notifications() {
  const { can } = useAuth();
  const confirm = useConfirm();
  const { unread, refresh } = useNotifications();
  const [users, setUsers] = useState([]);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState('inbox');   // inbox | archived | hidden
  const [tab, setTab] = useState('all');
  const [query, setQuery] = useState('');
  const [selected, setSelected] = useState([]); // notification ids
  const [form, setForm] = useState(emptyForm);
  const [sendAll, setSendAll] = useState(false);
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
    if (!await confirm({
      title: 'Mark all as read?',
      message: `Mark all ${counts.unread} unread notification${counts.unread === 1 ? '' : 's'} as read?`,
      confirmLabel: 'Mark All Read',
    })) return;
    setItems(list => list.map(x => (x.read_at ? x : { ...x, read_at: new Date().toISOString() })));
    try {
      await api.put('/notifications/read-all');
      refresh();
    } catch {
      load();
    }
  };

  /* ---- Views (inbox / archived / hidden) ---- */
  const active = useMemo(() => items.filter(x => !x.archived_at && !x.hidden_at), [items]);
  const archived = useMemo(() => items.filter(x => x.archived_at && !x.hidden_at), [items]);
  const hidden = useMemo(() => items.filter(x => x.hidden_at), [items]);

  const changeView = v => { setView(v); setTab('all'); setSelected([]); };

  /* ---- Row & bulk mutations ---- */
  const clearSelected = () => setSelected([]);

  const runBulk = async (action, ids) => {
    if (!ids.length) return;
    setBusy(true); setError('');
    try {
      await api.post('/notifications/bulk', { action, ids });
      clearSelected();
      await load();
      refresh();
    } catch {
      setError('Unable to update notifications. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  const archiveRow = id => runBulk('archive', [id]);
  const hideRow = id => runBulk('hide', [id]);
  const restoreRow = id => runBulk(view === 'archived' ? 'unarchive' : 'unhide', [id]);

  const deleteRow = async id => {
    if (!await confirm({
      title: 'Delete notification?',
      message: 'This permanently removes the notification and cannot be undone.',
      confirmLabel: 'Delete',
      tone: 'danger',
    })) return;
    runBulk('delete', [id]);
  };

  const bulkDelete = async () => {
    if (!await confirm({
      title: `Delete ${selected.length} notification${selected.length === 1 ? '' : 's'}?`,
      message: 'This permanently removes them and cannot be undone.',
      confirmLabel: 'Delete',
      tone: 'danger',
    })) return;
    runBulk('delete', selected);
  };

  const archiveAllRead = async () => {
    if (!await confirm({
      title: 'Archive all read?',
      message: 'Move every read notification out of your inbox into the archive. You can restore them later.',
      confirmLabel: 'Archive Read',
    })) return;
    setBusy(true); setError('');
    try { await api.post('/notifications/archive-read'); await load(); refresh(); }
    catch { setError('Unable to archive notifications.'); }
    finally { setBusy(false); }
  };

  const clearArchived = async () => {
    if (!await confirm({
      title: 'Delete archived notifications?',
      message: `Permanently delete all ${archived.length} archived notification${archived.length === 1 ? '' : 's'}. This cannot be undone.`,
      confirmLabel: 'Delete All',
      tone: 'danger',
    })) return;
    setBusy(true); setError('');
    try { await api.delete('/notifications/archived'); await load(); refresh(); }
    catch { setError('Unable to delete archived notifications.'); }
    finally { setBusy(false); }
  };

  const toggleSelect = id => setSelected(s => s.includes(id) ? s.filter(i => i !== id) : [...s, id]);
  const toggleSelectAll = () => (allSelected ? clearSelected() : setSelected(filtered.map(x => x.id)));

  const send = async e => {
    e.preventDefault();
    if (!sendAll && !form.user_id) return;
    const recipient = users.find(x => String(x.id) === String(form.user_id));
    const target = sendAll
      ? `all ${users.length} user${users.length === 1 ? '' : 's'}`
      : (recipient ? `${recipient.first_name} ${recipient.last_name}` : 'the selected user');
    if (!await confirm({
      title: 'Send notification?',
      message: `Send "${form.title}" to ${target}?`,
      confirmLabel: 'Send',
    })) return;
    setBusy(true);
    setMsg('');
    setError('');
    try {
      const payload = sendAll
        ? { title: form.title, message: form.message, type: form.type, all: true }
        : form;
      const { data } = await api.post('/admin/notifications', payload);
      setMsg(sendAll
        ? `Notification sent to ${data.count ?? users.length} user(s).`
        : `Notification sent to ${recipient ? `${recipient.first_name} ${recipient.last_name}` : 'user'}.`);
      setForm({ ...emptyForm });
      setSendAll(false);
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to send the notification.');
    } finally {
      setBusy(false);
    }
  };

  const resetForm = async () => {
    if (!await confirm({
      title: 'Clear form?',
      message: 'Discard the drafted notification? Any unsaved content will be lost.',
      confirmLabel: 'Clear',
      tone: 'danger',
    })) return;
    setForm({ ...emptyForm });
    setSendAll(false);
  };

  /* Derived: counts for the stat cards (inbox only) + filtered list.
     Unknown/legacy types are counted under System so totals always add up. */
  const counts = useMemo(() => {
    const byType = {};
    for (const x of active) {
      const t = TYPES[x.type] ? x.type : 'System';
      byType[t] = (byType[t] || 0) + 1;
    }
    byType.total = active.length;
    byType.unread = active.filter(x => !x.read_at).length;
    byType.archived = archived.length;
    byType.hidden = hidden.length;
    return byType;
  }, [active, archived, hidden]);

  const filtered = useMemo(() => {
    const source = view === 'inbox' ? active : view === 'archived' ? archived : hidden;
    return source.filter(x => {
      const type = TYPES[x.type] ? x.type : 'System';
      if (tab === 'unread' && x.read_at) return false;
      if (tab !== 'all' && tab !== 'unread' && type !== tab) return false;
      if (query) {
        const q = query.toLowerCase();
        return (x.title || '').toLowerCase().includes(q) || (x.message || '').toLowerCase().includes(q);
      }
      return true;
    });
  }, [view, active, archived, hidden, tab, query]);

  const allSelected = filtered.length > 0 && filtered.every(x => selected.includes(x.id));

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
                  ? `${counts.unread} unread notification${counts.unread > 1 ? 's' : ''} in your inbox.`
                  : 'You are all caught up.'}
              </p>
            </div>
            <div className="notif-head-actions">
              {view === 'inbox' && (
                <button
                  className="btn btn-secondary small"
                  onClick={archiveAllRead}
                  disabled={loading || busy || counts.total === 0}
                  title="Move every read notification to the archive"
                >
                  <Archive size={15} /> Archive Read
                </button>
              )}
              {view === 'archived' && archived.length > 0 && (
                <button
                  className="btn btn-danger small"
                  onClick={clearArchived}
                  disabled={loading || busy}
                  title="Permanently delete all archived notifications"
                >
                  <Trash2 size={15} /> Delete Archived
                </button>
              )}
              <button
                className="btn btn-secondary small"
                onClick={markAllRead}
                disabled={loading || counts.unread === 0}
                title="Mark every notification as read"
              >
                <CheckCheck size={15} /> Mark All Read
              </button>
            </div>
          </div>

          {/* View switcher: Inbox / Archived / Hidden */}
          <div className="segmented-tabs notif-views" role="tablist" aria-label="Notification view">
            {[
              ['inbox', 'Inbox', active.length],
              ['archived', 'Archived', archived.length],
              ['hidden', 'Hidden', hidden.length],
            ].map(([key, label, count]) => (
              <button
                key={key}
                role="tab"
                aria-selected={view === key}
                className={view === key ? 'active' : ''}
                onClick={() => changeView(key)}
              >
                {label}
                {count > 0 && <span className="tab-count">{count}</span>}
              </button>
            ))}
          </div>

          <div className="notif-toolbar">
            <div className="notif-tabs-wrap">
              <button
                className="row-icon-btn"
                onClick={toggleSelectAll}
                disabled={!filtered.length}
                title={allSelected ? 'Clear selection' : 'Select all'}
                aria-label={allSelected ? 'Clear selection' : 'Select all'}
              >
                {allSelected ? <CheckSquare size={15} /> : <Square size={15} />}
              </button>
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

          {/* Bulk action bar (appears when rows are selected) */}
          {selected.length > 0 && (
            <div className="notif-bulkbar">
              <span>{selected.length} selected</span>
              <div className="notif-bulkbar-actions">
                <button className="btn btn-secondary small" onClick={() => runBulk('read', selected)}>
                  <Check size={14} /> Mark read
                </button>
                {view !== 'archived' && (
                  <button className="btn btn-secondary small" onClick={() => runBulk('archive', selected)}>
                    <Archive size={14} /> Archive
                  </button>
                )}
                {view === 'archived' && (
                  <button className="btn btn-secondary small" onClick={() => runBulk('unarchive', selected)}>
                    <ArchiveRestore size={14} /> Restore
                  </button>
                )}
                {view === 'hidden' && (
                  <button className="btn btn-secondary small" onClick={() => runBulk('unhide', selected)}>
                    <Eye size={14} /> Restore
                  </button>
                )}
                <button className="btn btn-danger small" onClick={bulkDelete}>
                  <Trash2 size={14} /> Delete
                </button>
                <button className="btn btn-secondary small" onClick={clearSelected}>
                  <X size={14} /> Clear
                </button>
              </div>
            </div>
          )}

          {loading && <Skeleton rows={5} />}

          {!loading && (filtered.length ? (
            <>
              <div className="notif-list">
                {filtered.map(x => {
                  const meta = typeMeta(x.type);
                  const Icon = meta.icon;
                  const unreadRow = !x.read_at;
                  const clickable = view === 'inbox' && unreadRow;
                  const isSelected = selected.includes(x.id);
                  return (
                    <div
                      key={x.id}
                      className={`notif-row ${clickable ? 'unread' : ''} ${isSelected ? 'selected' : ''}`}
                      onClick={() => clickable && markRead(x.id)}
                      onKeyDown={e => { if (clickable && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); markRead(x.id); } }}
                      role={clickable ? 'button' : undefined}
                      tabIndex={clickable ? 0 : undefined}
                      aria-label={clickable ? 'Mark as read' : undefined}
                    >
                      <input
                        type="checkbox"
                        className="notif-check"
                        checked={isSelected}
                        onChange={() => toggleSelect(x.id)}
                        onClick={e => e.stopPropagation()}
                        aria-label="Select notification"
                      />
                      <div className={`notif-icon ${meta.cls}`}><Icon size={18} /></div>
                      <div className="notif-body">
                        <div className="notif-title-row">
                          <h3 className={unreadRow ? 'unread-title' : ''}>{x.title}</h3>
                          {clickable && <span className="new-chip">NEW</span>}
                        </div>
                        <p className="notif-msg">{x.message}</p>
                        <small className="notif-time" title={fmtDateTime(x.created_at)}>{timeAgo(x.created_at)}</small>
                      </div>
                      <div className="notif-actions">
                        {view === 'inbox' && (unreadRow
                          ? <button
                              className="row-icon-btn"
                              onClick={e => { e.stopPropagation(); markRead(x.id); }}
                              title="Mark as read"
                              aria-label="Mark as read"
                            >
                              <Check size={15} />
                            </button>
                          : <span className="row-icon-btn done" title="Read"><Check size={15} /></span>)}
                        {view === 'inbox' && (
                          <>
                            <button
                              className="row-icon-btn"
                              onClick={e => { e.stopPropagation(); archiveRow(x.id); }}
                              title="Archive"
                              aria-label="Archive"
                            >
                              <Archive size={15} />
                            </button>
                            <button
                              className="row-icon-btn"
                              onClick={e => { e.stopPropagation(); hideRow(x.id); }}
                              title="Hide"
                              aria-label="Hide"
                            >
                              <EyeOff size={15} />
                            </button>
                          </>
                        )}
                        {view === 'archived' && (
                          <button
                            className="row-icon-btn"
                            onClick={e => { e.stopPropagation(); restoreRow(x.id); }}
                            title="Restore from archive"
                            aria-label="Restore"
                          >
                            <ArchiveRestore size={15} />
                          </button>
                        )}
                        {view === 'hidden' && (
                          <button
                            className="row-icon-btn"
                            onClick={e => { e.stopPropagation(); restoreRow(x.id); }}
                            title="Unhide"
                            aria-label="Unhide"
                          >
                            <Eye size={15} />
                          </button>
                        )}
                        <button
                          className="row-icon-btn danger"
                          onClick={e => { e.stopPropagation(); deleteRow(x.id); }}
                          title="Delete"
                          aria-label="Delete"
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
              <p className="notif-footer">
                Showing {filtered.length} of {active.length + archived.length + hidden.length} notification{items.length === 1 ? '' : 's'}
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
              title={view === 'inbox' ? 'Inbox is empty' : view === 'archived' ? 'Nothing archived' : 'Nothing hidden'}
              message={
                view === 'inbox'
                  ? 'System updates and messages will appear here.'
                  : view === 'archived'
                    ? 'Notifications you archive to tidy your inbox will show up here.'
                    : 'Notifications you hide will show up here.'
              }
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
                  <p className="notif-subtitle">Message a user directly, or broadcast to everyone.</p>
                </div>
              </div>

              {msg && <div className="alert success">{msg}</div>}
              {error && <div className="alert error">{error}</div>}

              <form onSubmit={send} className="compose-form">
                <label>Recipient
                  <select
                    value={sendAll ? SEND_ALL : form.user_id}
                    onChange={e => {
                      const v = e.target.value;
                      setSendAll(v === SEND_ALL);
                      setForm({ ...form, user_id: v === SEND_ALL ? '' : v });
                    }}
                    required
                  >
                    <option value="">Select user</option>
                    <option value={SEND_ALL}>All users ({users.length})</option>
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
                  <button type="button" className="btn btn-danger" onClick={resetForm} disabled={busy || (!form.title && !form.message && !form.user_id && !sendAll)}>
                    <RotateCcw size={15} /> Reset
                  </button>
                  <button className="btn btn-primary" disabled={busy}>
                    <Send size={15} /> {busy ? 'Sending…' : sendAll ? `Send to All (${users.length})` : 'Send'}
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
