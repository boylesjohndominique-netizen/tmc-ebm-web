import { useCallback, useEffect, useMemo, useState } from 'react';
import { RotateCcw, Save, ShieldCheck, UsersRound } from 'lucide-react';
import api from '../../services/api';
import SearchBar from '../../components/SearchBar';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import StatusBadge from '../../components/StatusBadge';
import { useAuth } from '../../context/AuthContext';
import { useConfirm } from '../../context/ConfirmContext';
import useRealtimeResource from '../../hooks/useRealtimeResource';

/**
 * Super Admin only — per-user permission editor.
 *
 * Left: Admin/Staff/Faculty accounts (super admins excluded by the API).
 * Right: module/action matrix for the selected user. Checkboxes only list
 * actions the role has by default vs. overrides, and "view" is enforced as
 * the minimum action per module by both UI and API.
 */
export default function Permissions() {
  const { refreshUser } = useAuth();
  const confirm = useConfirm();
  const [catalog, setCatalog] = useState([]); // [{key,label,description,actions}]
  const [users, setUsers] = useState([]);
  const [selected, setSelected] = useState(null); // user object from index
  const [overrides, setOverrides] = useState({}); // {module: [actions]}
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');

  const load = useCallback(() => api.get('/admin/permissions')
    .then(r => { setCatalog(r.data.modules); setUsers(r.data.users); })
    .catch(() => setError('Unable to load permissions. Please refresh the page.'))
    .finally(() => setLoading(false)), []);

  useEffect(() => { load(); }, [load]);

  // Other super admins' edits to accounts/permissions show up instantly.
  useRealtimeResource(['users'], load);

  const openUser = async u => {
    setError(''); setNotice('');
    try {
      const { data } = await api.get(`/admin/permissions/${u.id}`);
      setSelected(data);
      setOverrides(Object.fromEntries(
        Object.entries(data.permission_map).map(([m, a]) => [m, [...a]])
      ));
    } catch {
      setError('Unable to load this user\'s permissions.');
    }
  };

  const closeUser = () => { setSelected(null); setOverrides({}); setError(''); setNotice(''); };

  const toggle = (module, action) => {
    setOverrides(prev => {
      const current = prev[module] || [];
      const has = current.includes(action);
      let next;
      if (has) {
        // Removing "view" while other actions remain is blocked by the API;
        // drop them all together instead.
        next = action === 'view' ? [] : current.filter(a => a !== action);
      } else {
        next = [...current, action];
      }
      const copy = { ...prev };
      if (next.length) copy[module] = next.sort();
      else delete copy[module];
      return copy;
    });
  };

  const save = async () => {
    setBusy(true); setError(''); setNotice('');
    try {
      const payload = {
        permissions: Object.entries(overrides).map(([module, actions]) => ({ module, actions })),
      };
      const { data } = await api.put(`/admin/permissions/${selected.id}`, payload);
      setNotice(data.message || 'Permissions updated.');
      setSelected(data.user);
      setOverrides(Object.fromEntries(
        Object.entries(data.user.permission_map).map(([m, a]) => [m, [...a]])
      ));
      load();
      refreshUser(); // own map may change if editing another admin account
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to save permissions.');
    } finally {
      setBusy(false);
    }
  };

  const resetToDefaults = async () => {
    if (!await confirm({
      title: 'Reset permissions?',
      message: `Reset all permissions for ${selected.first_name} ${selected.last_name} to their role defaults?`,
      confirmLabel: 'Reset to Defaults',
      tone: 'danger',
    })) return;
    setBusy(true); setError(''); setNotice('');
    try {
      const { data } = await api.put(`/admin/permissions/${selected.id}/reset`);
      setNotice(data.message || 'Permissions reset.');
      setSelected(data.user);
      setOverrides(Object.fromEntries(
        Object.entries(data.user.permission_map).map(([m, a]) => [m, [...a]])
      ));
      load();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to reset permissions.');
    } finally {
      setBusy(false);
    }
  };

  const query = search.trim().toLowerCase();
  const filteredUsers = useMemo(() => users.filter(u => {
    if (!query) return true;
    const fullName = [u.first_name, u.middle_name, u.last_name].filter(Boolean).join(' ').toLowerCase();
    return fullName.includes(query)
      || (u.username || '').toLowerCase().includes(query)
      || (u.email || '').toLowerCase().includes(query)
      || (u.role?.name || '').toLowerCase().includes(query);
  }), [users, query]);

  const moduleByKey = useMemo(() => Object.fromEntries(catalog.map(m => [m.key, m])), [catalog]);
  const hasChanges = selected && JSON.stringify(overrides) !== JSON.stringify(
    Object.fromEntries(Object.entries(selected.permission_map).map(([m, a]) => [m, [...a]]))
  );

  if (loading && !error) return <div className="panel"><Skeleton rows={6} /></div>;

  return (
    <>
      <div className="page-heading">
        <div>
          <h1><ShieldCheck size={24} className="heading-icon" /> User Permissions</h1>
          <p>Control which modules and actions each Admin, Staff, or Faculty account can use.</p>
        </div>
      </div>

      {notice && <div className="alert success">{notice}</div>}
      {error && !selected && <div className="alert error">{error}</div>}

      <div className="permissions-grid">
        {/* ---------- User list ---------- */}
        <div className="panel permissions-users">
          <h2>Accounts</h2>
          <div className="permissions-user-search">
            <SearchBar value={search} onChange={setSearch} placeholder="Search by name, role, email..." />
          </div>
          <div className="permissions-user-list">
            {filteredUsers.map(u => (
              <button
                key={u.id}
                className={`permissions-user ${selected?.id === u.id ? 'active' : ''}`}
                onClick={() => openUser(u)}
              >
                <span className="permissions-user-name">
                  {[u.first_name, u.last_name].filter(Boolean).join(' ')}
                </span>
                <span className="permissions-user-meta">
                  {u.role?.name}
                  {u.has_overrides && <i className="perm-dot" title="Has custom permissions" />}
                </span>
              </button>
            ))}
            {!filteredUsers.length && (
              <div className="empty">
                <UsersRound size={18} style={{ verticalAlign: '-3px', marginRight: 6 }} />
                No accounts match your search.
              </div>
            )}
          </div>
        </div>

        {/* ---------- Editor ---------- */}
        <div className="panel permissions-editor">
          {!selected ? (
            <EmptyState
              icon={ShieldCheck}
              title="Select an account"
              message="Choose an Admin, Staff, or Faculty user on the left to customize their permissions. Super Admin accounts are protected and cannot be edited."
            />
          ) : (
            <>
              <div className="panel-header">
                <div>
                  <h2>{[selected.first_name, selected.last_name].filter(Boolean).join(' ')}</h2>
                  <p className="muted" style={{ fontSize: 12.5, margin: 0 }}>
                    @{selected.username} · {selected.role?.name} · <StatusBadge status={selected.account_status} />
                  </p>
                </div>
                <div className="permissions-editor-actions">
                  {selected.has_overrides && (
                    <button className="btn btn-secondary small" disabled={busy} onClick={resetToDefaults}>
                      <RotateCcw size={14} /> Reset to defaults
                    </button>
                  )}
                  <button className="btn btn-primary small" disabled={busy || !hasChanges} onClick={save}>
                    <Save size={14} /> {busy ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              </div>

              {error && <div className="alert error">{error}</div>}

              <p className="small-note">
                Ticked rows are custom permissions for this account. Unticked rows fall back to the
                {' '}<b>{selected.role?.name}</b> role defaults. Removing "view" removes the whole module.
              </p>

              <div className="permissions-matrix">
                {catalog.map(m => {
                  const current = overrides[m.key] || [];
                  const defaulted = !current.length;
                  return (
                    <div className={`perm-module ${defaulted ? '' : 'custom'}`} key={m.key}>
                      <div className="perm-module-head">
                        <b>{m.label}</b>
                        <span className="muted">{defaulted ? 'role defaults' : 'custom'}</span>
                      </div>
                      <p className="perm-module-desc">{m.description}</p>
                      <div className="perm-actions">
                        {m.actions.map(action => (
                          <label key={action} className="perm-action">
                            <input
                              type="checkbox"
                              checked={current.includes(action)}
                              onChange={() => toggle(m.key, action)}
                            />
                            {action}
                          </label>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>
      </div>
    </>
  );
}
