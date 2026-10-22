import { useCallback,useEffect,useState} from 'react';
import { Link } from 'react-router-dom';
import { Ban, CheckCircle2, Eye, EyeOff, IdCard, Pencil, RotateCcw, ShieldBan, ShieldCheck, Trash2, UserPlus, UsersRound, XCircle } from 'lucide-react';
import api from '../../services/api';
import Modal from '../../components/Modal';
import SearchBar from '../../components/SearchBar';
import { useAuth } from '../../context/AuthContext';
import { useConfirm } from '../../context/ConfirmContext';
import useDebouncedValue from '../../hooks/useDebouncedValue';
import useRealtimeResource from '../../hooks/useRealtimeResource';
import { fmtDate, fmtDateTime, fmtDateRange } from '../../utils/datetime';
import Skeleton from '../../components/Skeleton';
import EmptyState from '../../components/EmptyState';
import StatusBadge from '../../components/StatusBadge';

const emptyForm = {first_name:'',middle_name:'',last_name:'',role:'Faculty',username:'',email:'',contact_number:'',password:''};
const STATUS_FILTERS = ['All','Pending','Active','Banned'];

/*
 * One submitted ID photo (front|back). The image is streamed from the
 * backend's private storage through the authorized admin endpoint (Bearer
 * auth), so it is never publicly reachable. Missing/failed loads show a
 * graceful fallback instead of a broken image.
 */
function IdPhoto({ userId, side }) {
  const [failed, setFailed] = useState(false);

  // Bearer tokens can't be sent via <img>, so fetch the image with the
  // auth header and hand the blob to the <img> element.
  const [blobUrl, setBlobUrl] = useState(null);
  useEffect(() => {
    let revoked = false;
    setBlobUrl(null);
    setFailed(false);
    api.get(`/admin/users/${userId}/id-photo/${side}`, { responseType: 'blob' })
      .then(r => {
        if (!revoked) setBlobUrl(URL.createObjectURL(r.data));
      })
      .catch(() => { if (!revoked) setFailed(true); });
    return () => { revoked = true; };
  }, [userId, side]);

  useEffect(() => () => { if (blobUrl) URL.revokeObjectURL(blobUrl); }, [blobUrl]);

  return (
    <figure className="id-photo">
      <figcaption>{side === 'front' ? 'Front ID' : 'Back ID'}</figcaption>
      {failed
        ? <div className="id-photo-empty"><IdCard size={22} /><span>No ID photo submitted</span></div>
        : blobUrl
          ? <img src={blobUrl} alt={`${side} ID`} onError={() => setFailed(true)} />
          : <div className="id-photo-empty"><span>Loading…</span></div>}
    </figure>
  );
}

export default function Users({audience='students',embedded=false,onManagePermissions}){
  const isStudentPage=audience==='students';
  const { can } = useAuth();
  const confirm = useConfirm();
  const [items,setItems]=useState([]);
  const [statusFilter,setStatusFilter]=useState('All');
  const [search,setSearch]=useState('');      // input value — updates instantly as you type
  const [roleFilter,setRoleFilter]=useState('All');
  const query=useDebouncedValue(search.trim(),300).toLowerCase(); // debounced — drives the actual filtering
  const [detail,setDetail]=useState(null);      // full info object from /admin/users/{id}
  const [editing,setEditing]=useState(null);    // user object being edited
  const [adding,setAdding]=useState(false);
  const [showPassword,setShowPassword]=useState(false); // show/hide password in the add/edit modal
  const [form,setForm]=useState(emptyForm);
  const [banning,setBanning]=useState(null);    // user being banned
  const [banReason,setBanReason]=useState('');
  const [rejecting,setRejecting]=useState(null);
  const [rejectionReason,setRejectionReason]=useState('');
  const [error,setError]=useState('');
  const [notice,setNotice]=useState('');
  const [busy,setBusy]=useState(false);

  const [loading,setLoading]=useState(true);
  const [loadError,setLoadError]=useState('');
  const load=useCallback(()=>api.get('/admin/users')
    .then(r=>{setItems(r.data.filter(x=>isStudentPage?x.role?.name==='Student':['Administrator','Staff','Faculty'].includes(x.role?.name)));setLoadError('');})
    .catch(()=>setLoadError(`Unable to load ${isStudentPage?'student':'staff'} accounts.`))
    .finally(()=>setLoading(false)),[isStudentPage]);
  useEffect(()=>{load()},[load]);

  // Live roster: new registrations, verifications and bans pushed by staff
  // elsewhere appear here without refreshing.
  useRealtimeResource(['users'], load);

  const openDetail=async id=>{
    setError(''); setNotice('');
    try{ setDetail(await api.get(`/admin/users/${id}`).then(r=>r.data)); }
    catch{ setError('Unable to load user details.'); }
  };

  const openEdit=(x,fromDetail=false)=>{ setError(''); setForm({first_name:x.first_name,middle_name:x.middle_name||'',last_name:x.last_name,role:x.role?.name||'Faculty',username:x.username,email:x.email,contact_number:x.contact_number||'',password:''}); setEditing(x); if(fromDetail) setDetail(null); // avoid stacking the view + edit modals
  };
  const closeAll=()=>{ setDetail(null); setEditing(null); setAdding(false); setBanning(null); setBanReason(''); setRejecting(null); setRejectionReason(''); setForm(emptyForm); setError(''); setShowPassword(false); };

  const save=async e=>{
    e.preventDefault(); setBusy(true); setError('');
    try{
      if(editing) await api.put(`/admin/users/${editing.id}`,form);
      else await api.post('/admin/users',form);
      setNotice(editing?'User updated.':'User created.'); closeAll(); load();
    }catch(err){ setError(err.response?.data?.message||'Unable to save user.'); }
    finally{ setBusy(false); }
  };

  /* ---------- Account status actions ---------- */

  const verify=async x=>{
    if(!await confirm({
      title:'Verify account?',
      message:`Approve ${x.first_name} ${x.last_name}'s ID verification and allow them to borrow equipment? They will be notified in the mobile app.`,
      confirmLabel:'Verify Account',
    })) return;
    setBusy(true); setError('');
    try{ const r=await api.put(`/admin/users/${x.id}/verify`); setNotice(r.data.message||'Account verified.'); setDetail(null); load(); }
    catch(err){ setError(err.response?.data?.message||'Unable to verify account.'); }
    finally{ setBusy(false); }
  };

  const openBan=x=>{ setError(''); setBanReason(''); setBanning(x); };
  const openReject=x=>{ setError(''); setDetail(null); setRejectionReason(''); setRejecting(x); };
  const confirmReject=async e=>{
    e.preventDefault();
    if(busy)return;
    const reason=rejectionReason.trim();
    if(!reason){setError('Enter a reason for rejecting this verification.');return;}
    setBusy(true); setError('');
    try{
      const r=await api.put(`/admin/users/${rejecting.id}/reject-verification`,{reason});
      setNotice(r.data.message); closeAll(); load();
    }catch(err){setError(err.response?.data?.message||'Unable to reject verification.');}
    finally{setBusy(false);}
  };
  const confirmBan=async e=>{
    e.preventDefault(); setBusy(true); setError('');
    try{ const r=await api.put(`/admin/users/${banning.id}/ban`,{reason:banReason}); setNotice(r.data.message||'Account banned.'); closeAll(); load(); }
    catch(err){ setError(err.response?.data?.message||'Unable to ban account.'); }
    finally{ setBusy(false); }
  };

  const unban=async x=>{
    if(!await confirm({
      title:'Lift ban?',
      message:`Restore ${x.first_name} ${x.last_name}'s access? They will be able to sign in again.`,
      confirmLabel:'Lift Ban',
    })) return;
    setBusy(true); setError('');
    try{ const r=await api.put(`/admin/users/${x.id}/unban`); setNotice(r.data.message||'Account unbanned.'); setDetail(null); load(); }
    catch(err){ setError(err.response?.data?.message||'Unable to unban account.'); }
    finally{ setBusy(false); }
  };

  const del=async x=>{
    if(!await confirm({
      title:'Delete user?',
      message:`Permanently delete ${x.first_name} ${x.last_name}? This cannot be undone.`,
      confirmLabel:'Delete User',
      tone:'danger',
    }))return;
    try{ await api.delete(`/admin/users/${x.id}`); setNotice('User deleted.'); load(); }
    catch(err){
      if(err.response?.data?.requires_force){
        if(await confirm({
          title:'Delete user and records?',
          message:'This user has borrowing/return history. Deleting them permanently removes those records too. Continue?',
          confirmLabel:'Delete Permanently',
          tone:'danger',
        })){
          try{ await api.delete(`/admin/users/${x.id}?force=1`); setNotice('User and their records deleted.'); load(); }
          catch(e2){ setError(e2.response?.data?.message||'Unable to delete user.'); }
        }
      } else setError(err.response?.data?.message||'Unable to delete user.');
    }
  };

  const stat=d=>detail?.statistics?.[d];

  // Students cannot be edited (they self-register); admins cannot be banned or deleted.
  // Permission checks come first: the Super Admin decides who can do what.
  const canEdit=x=>can('users','edit')&&x.role?.name!=='Student';
  const canBan=x=>can('users','ban')&&x.role?.name!=='Administrator'&&x.account_status!=='Banned';
  const canVerify=x=>can('users','verify')&&x.role?.name==='Student'&&x.account_status==='Pending'&&!x.verified_at&&x.id_submitted_at&&x.id_front_path&&x.id_back_path&&!x.id_rejection_reason;
  const canUnban=x=>can('users','ban')&&x.account_status==='Banned';
  const canDelete=x=>can('users','delete');
  const canManagePermissions=x=>onManagePermissions&&!x.is_super_admin;

  /* ---- Search & role filter (combined with the status tabs) ---- */

  // Roles present in the system, derived from the loaded users (sorted A→Z).
  const roles=[...new Set(items.map(x=>x.role?.name).filter(Boolean))].sort();

  const matchesSearch=x=>!query
    ||[x.first_name,x.middle_name,x.last_name].filter(Boolean).join(' ').toLowerCase().includes(query)
    ||(x.email||'').toLowerCase().includes(query)
    ||(x.username||'').toLowerCase().includes(query);

  const filtered=items.filter(x=>
    (statusFilter==='All'||x.account_status===statusFilter)&&
    (roleFilter==='All'||x.role?.name===roleFilter)&&
    matchesSearch(x)
  );
  const countBy=s=>items.filter(x=>x.account_status===s).length;
  const hasSearchFilters=query!==''||roleFilter!=='All';
  const clearSearchFilters=()=>{setSearch('');setRoleFilter('All');};

  return <>
    {!embedded&&<div className="page-heading"><div><h1>{isStudentPage?'Student Management':'Staff Management'}</h1><p>{isStudentPage?'Review student ID submissions, verify accounts, and manage student access.':'Manage Administrator, Staff, and Faculty accounts.'}</p></div></div>}
    {notice&&<div className="alert success">{notice}</div>}
    {loadError&&<div className="alert error" role="alert">{loadError} <button className="btn btn-secondary small" disabled={loading} onClick={()=>{setLoading(true);load();}}>Try Again</button></div>}
    {error&&!detail&&!editing&&!adding&&!banning&&!rejecting&&<div className="alert error">{error}</div>}

    {/* ---- Search & role filter ---- */}
    <div className="toolbar">
      <SearchBar value={search} onChange={setSearch} placeholder="Search by name, email, or username..." />
      {!isStudentPage&&<select value={roleFilter} onChange={e=>setRoleFilter(e.target.value)} aria-label="Filter by role">
        <option value="All">All Roles</option>
        {roles.map(r=><option key={r} value={r}>{r}</option>)}
      </select>}
      {hasSearchFilters&&<button className="btn btn-secondary small" onClick={clearSearchFilters}>Clear</button>}
      {!isStudentPage&&can('users','create')&&<button className="btn btn-primary" onClick={()=>{setError('');setForm(emptyForm);setShowPassword(false);setAdding(true)}}><UserPlus size={16}/> Add Staff Account</button>}
    </div>

    {/* ---- Status filter tabs ---- */}
    <div className="filter-tabs" role="tablist">
      {STATUS_FILTERS.filter(s=>isStudentPage||s!=='Pending').map(s=>
        <button key={s} className={`filter-tab ${statusFilter===s?'active':''}`} onClick={()=>setStatusFilter(s)}>
          {s}{s!=='All'&&` (${s==='Pending'?countBy('Pending'):s==='Active'?countBy('Active'):countBy('Banned')})`}
        </button>
      )}
    </div>

    <div className="panel table-wrap"><table>
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>{isStudentPage?'Student ID':'Role'}</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>{filtered.map(x=><tr key={x.id}>
        <td>{[x.first_name,x.middle_name].filter(Boolean).join(' ')} {x.last_name}</td>
        <td>{x.username}</td>
        <td>{x.email}</td>
        <td>{isStudentPage?(x.id_number||'—'):x.role?.name}</td>
        <td><StatusBadge status={x.account_status}/>{x.id_rejection_reason&&<span title={`ID verification rejected: ${x.id_rejection_reason}`}><StatusBadge status="Rejected"/></span>}{x.ban_reason?<span title={`Reason: ${x.ban_reason}`} className="ban-reason-hint"><Ban size={12}/></span>:null}</td>
        <td className="action-cell">
          <button className="btn btn-secondary small" title="View info" onClick={()=>openDetail(x.id)}><Eye size={15}/></button>
          {canVerify(x)&&<button className="btn btn-primary small" title="Verify account" disabled={busy} onClick={()=>verify(x)}><CheckCircle2 size={15}/></button>}
          {canVerify(x)&&<button className="btn btn-danger small" title="Reject ID verification" aria-label={`Reject ID verification for ${x.first_name} ${x.last_name}`} disabled={busy} onClick={()=>openReject(x)}><XCircle size={15}/></button>}
          {canEdit(x)&&<button className="btn btn-secondary small" title="Edit" onClick={()=>openEdit(x)}><Pencil size={15}/></button>}
          {!isStudentPage&&canManagePermissions(x)&&<button className="btn btn-secondary small" title="Manage permissions" aria-label={`Manage permissions for ${x.first_name} ${x.last_name}`} onClick={()=>onManagePermissions(x.id)}><ShieldCheck size={15}/></button>}
          {canBan(x)&&<button className="btn btn-danger small" title="Ban account" onClick={()=>openBan(x)}><ShieldBan size={15}/></button>}
          {canUnban(x)&&<button className="btn btn-secondary small" title="Lift ban" disabled={busy} onClick={()=>unban(x)}><RotateCcw size={15}/></button>}
          {canDelete(x)&&x.role?.name!=='Administrator'&&<button className="btn btn-danger small" title="Delete" onClick={()=>del(x)}><Trash2 size={15}/></button>}
        </td>
      </tr>)}</tbody>
    </table></div>
    {loading&&!error&&<div className="panel"><Skeleton rows={5} /></div>}
    {!loading&&!filtered.length&&!error&&!loadError&&(hasSearchFilters
      ?<EmptyState icon={UsersRound} title="No matching users" message="No users match your search or role filter. Try different keywords or clear the filters." action={{label:'Clear Filters',onClick:clearSearchFilters}}/>
      :<EmptyState icon={UsersRound} title={`No ${statusFilter==='All'?'':`${statusFilter.toLowerCase()} `}${isStudentPage?'students':'staff accounts'} found`} message={isStudentPage?'Students who register in the mobile app will appear here.':'Administrator, Staff, and Faculty accounts will appear here.'} action={!isStudentPage&&statusFilter==='All'&&can('users','create')?{label:'Add Staff Account',onClick:()=>{setForm(emptyForm);setShowPassword(false);setAdding(true)}}:null}/>)}

    {/* ---- View user info ---- */}
    <Modal open={!!detail} title={`User — ${detail?.user?.first_name} ${detail?.user?.last_name}`} onClose={closeAll}>
      {detail&&<>
        <div className="modal-body">
          {error&&<div className="alert error" role="alert">{error}</div>}
          <div className="detail-list">
            <div><b>Role</b>{detail.user.role?.name}</div>
            <div><b>Status</b><StatusBadge status={detail.user.account_status}/></div>
            {/* Verification is shown independently of ban status — banning
                never revokes it and unbanning never grants it. */}
            {isStudentPage&&<div><b>ID Verified</b>{detail.user.is_id_verified ? 'Yes' : 'No'}</div>}
            {isStudentPage&&detail.user.id_rejection_reason&&<div><b>Verification Rejection Reason</b>{detail.user.id_rejection_reason}</div>}
            <div><b>Username</b>{detail.user.username}</div>
            <div><b>Email</b>{detail.user.email}</div>
            <div><b>Contact Number</b>{detail.user.contact_number||'—'}</div>
            <div><b>Registered</b>{fmtDate(detail.user.created_at)}</div>
            {isStudentPage&&detail.user.verified_at&&<div><b>Verified At</b>{fmtDateTime(detail.user.verified_at)}</div>}
            {isStudentPage&&detail.user.id_submitted_at&&<div><b>ID Submitted</b>{fmtDateTime(detail.user.id_submitted_at)}</div>}
            {detail.user.banned_at&&<div><b>Banned</b>{fmtDateTime(detail.user.banned_at)}</div>}
            {detail.user.ban_reason&&<div><b>Ban Reason</b>{detail.user.ban_reason}</div>}
          </div>

          {/* Submitted verification ID photos (front + back), streamed from
              private storage through the authorized admin endpoint. */}
          {isStudentPage&&<><h3>Verification ID</h3>
          {detail.user.id_submitted_at
            ? <div className="id-photo-grid">
                <IdPhoto userId={detail.user.id} side="front" />
                <IdPhoto userId={detail.user.id} side="back" />
              </div>
            : <div className="id-photo-grid"><div className="id-photo-empty"><IdCard size={22} /><span>No ID submitted</span></div></div>}
          <div className="detail-list">
            <div><b>Total Requests</b>{stat('total_requests')??0}</div>
            <div><b>Pending</b>{stat('pending_requests')??0}</div>
            <div><b>Active Borrowings</b>{stat('active_borrowings')??0}</div>
            <div><b>Borrowed Units</b>{stat('currently_borrowed_units')??0}</div>
            <div><b>Completed</b>{stat('completed')??0}</div>
            <div><b>Overdue</b>{stat('overdue')??0}</div>
          </div>

          <h3>Recent Borrowings</h3>
          <div className="table-wrap"><table>
            <thead><tr><th>ID</th><th>Equipment</th><th>Dates</th><th>Status</th></tr></thead>
            <tbody>{detail.borrowings.map(b=><tr key={b.id}>
              <td><Link to="/admin/borrowing-requests" onClick={closeAll}>#{b.id}</Link></td>
              <td>{b.items.map(i=>i.equipment?.name).join(', ')}</td>
              <td>{fmtDateRange(b.borrowing_date, b.expected_return_date)}</td>
              <td>{b.status}</td>
            </tr>)}</tbody>
          </table></div>
          {!detail.borrowings.length&&<div className="empty">No borrowing history.</div>}</>}
        </div>
        <div className="modal-actions">
          {canVerify(detail.user)&&<button className="btn btn-primary" disabled={busy} onClick={()=>verify(detail.user)}><CheckCircle2 size={16}/> Verify Account</button>}
          {canVerify(detail.user)&&<button className="btn btn-danger" disabled={busy} onClick={()=>openReject(detail.user)}><XCircle size={16}/> Reject Verification</button>}
          {canBan(detail.user)&&<button className="btn btn-danger" onClick={()=>openBan(detail.user)}><ShieldBan size={16}/> Ban Account</button>}
          {canUnban(detail.user)&&<button className="btn btn-secondary" disabled={busy} onClick={()=>unban(detail.user)}><RotateCcw size={16}/> Lift Ban</button>}
          {canEdit(detail.user)&&<button className="btn btn-secondary" onClick={()=>openEdit(detail.user,true)}>Edit User</button>}
          {!isStudentPage&&canManagePermissions(detail.user)&&<button className="btn btn-secondary" onClick={()=>onManagePermissions(detail.user.id)}><ShieldCheck size={16}/> Manage Permissions</button>}
          <button className="btn btn-primary" onClick={closeAll}>Close</button>
        </div>
      </>}
    </Modal>

    <Modal open={!!rejecting} title="Reject ID verification?" onClose={closeAll} busy={busy} tone="danger" icon={XCircle}>
      {rejecting&&<form onSubmit={confirmReject}>
        <div className="modal-body">
          <p>Reject {rejecting.first_name} {rejecting.last_name}'s ID verification? The student will receive a notification in the mobile app with your reason and can submit new ID photos.</p>
          <label>Rejection reason (shown to the student)
            <textarea value={rejectionReason} onChange={e=>setRejectionReason(e.target.value)} placeholder="e.g. The back of the ID is blurry. Please upload a clearer photo." required maxLength={500} disabled={busy}/>
          </label>
          {error&&<div className="alert error" role="alert">{error}</div>}
        </div>
        <div className="modal-actions">
          <button type="button" className="btn btn-secondary" onClick={closeAll} disabled={busy}>Cancel</button>
          <button className="btn btn-danger" disabled={busy||!rejectionReason.trim()}><XCircle size={16}/>{busy?'Rejecting...':'Reject and Notify Student'}</button>
        </div>
      </form>}
    </Modal>

    {/* ---- Ban user (requires reason) ---- */}
    <Modal open={!!banning} title={`Ban — ${banning?.first_name} ${banning?.last_name}`} onClose={closeAll}>
      {banning&&<form onSubmit={confirmBan}>
        <div className="modal-body">
          <p className="ban-warning">Banning this account immediately revokes their access and prevents any further login — including the mobile app. You can lift the ban later.</p>
          <label>Reason (shown to the user and kept on record)
            <textarea value={banReason} onChange={e=>setBanReason(e.target.value)} placeholder="e.g. repeated late returns" required maxLength={500}/>
          </label>
        </div>
        {error&&<div className="alert error">{error}</div>}
        <div className="modal-actions">
          <button type="button" className="btn btn-secondary" onClick={closeAll}>Cancel</button>
          <button className="btn btn-danger" disabled={busy}><ShieldBan size={16}/>{busy?'Banning...':'Ban Account'}</button>
        </div>
      </form>}
    </Modal>

    {/* ---- Add / Edit user (Admin, Faculty, Staff only) ---- */}
    <Modal open={adding||!!editing} title={editing?`Edit — ${editing.first_name} ${editing.last_name}`:'Add Staff Account'} onClose={closeAll}>
      <form onSubmit={save}>
        <div className="modal-body">
          {!editing&&<p className="ban-warning">Students are not created here — they register themselves in the mobile app and appear as Pending for you to verify.</p>}
          <div className="form-grid">
            <label>First Name<input value={form.first_name} onChange={e=>setForm({...form,first_name:e.target.value})} required/></label>
            <label>Middle Name<input value={form.middle_name} onChange={e=>setForm({...form,middle_name:e.target.value})}/></label>
            <label>Last Name<input value={form.last_name} onChange={e=>setForm({...form,last_name:e.target.value})} required/></label>
            <label>Role<select value={form.role} onChange={e=>setForm({...form,role:e.target.value})}><option>Administrator</option><option>Faculty</option><option>Staff</option></select></label>
            <label>Username<input value={form.username} onChange={e=>setForm({...form,username:e.target.value})} required/></label>
            <label>Email<input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} required/></label>
            <label>Contact Number<input value={form.contact_number} onChange={e=>setForm({...form,contact_number:e.target.value})}/></label>
            <label>{editing?'New Password (optional)':'Password'}
              <div className="password-field">
                <input type={showPassword?'text':'password'} value={form.password} onChange={e=>setForm({...form,password:e.target.value})} minLength="8" required={!editing} autoComplete="new-password" placeholder={editing?'Leave blank to keep current':''}/>
                <button type="button" className="password-toggle" title={showPassword?'Hide password':'Show password'} aria-label={showPassword?'Hide password':'Show password'} onClick={()=>setShowPassword(v=>!v)}>
                  {showPassword?<EyeOff size={15}/>:<Eye size={15}/>}
                </button>
              </div>
            </label>
          </div>
        </div>
        {error&&<div className="alert error">{error}</div>}
        <div className="modal-actions">
          <button type="button" className="btn btn-secondary" onClick={closeAll}>Cancel</button>
          <button className="btn btn-primary" disabled={busy}>{busy?'Saving...':'Save User'}</button>
        </div>
      </form>
    </Modal>
  </>;
}
