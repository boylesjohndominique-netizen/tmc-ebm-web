import {useCallback,useEffect,useState} from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import api from '../../services/api';
import Modal from '../../components/Modal';
import SearchBar from '../../components/SearchBar';
import { useAuth } from '../../context/AuthContext';
import { useConfirm } from '../../context/ConfirmContext';
import useDebouncedValue from '../../hooks/useDebouncedValue';
import useRealtimeResource from '../../hooks/useRealtimeResource';

const emptyForm={category_id:'',name:'',description:'',quantity:1,condition:'Good'};

export default function Equipment(){
  const { can } = useAuth();
  const confirm = useConfirm();
  const [items,setItems]=useState([]);
  const [cats,setCats]=useState([]);
  const [editing,setEditing]=useState(null); // null = closed, 'new' = adding, object = editing
  const [form,setForm]=useState(emptyForm);
  const [error,setError]=useState('');
  const [busy,setBusy]=useState(false);
  const [search,setSearch]=useState('');
  const [catFilter,setCatFilter]=useState('All');
  const [condFilter,setCondFilter]=useState('All');
  const [statusFilter,setStatusFilter]=useState('All');

  const load=useCallback(()=>api.get('/equipment').then(r=>{setItems(r.data.equipment);setCats(r.data.categories)}),[]);
  useEffect(()=>{load()},[load]);

  // Live stock: approvals and returns elsewhere move availability counters.
  useRealtimeResource(['equipment', 'borrowings', 'returns'], load);

  /* ---- Search & filters ---- */
  const query=useDebouncedValue(search.trim(),300).toLowerCase();
  const filtered=items.filter(x=>
    (!query||x.name.toLowerCase().includes(query)||(x.description||'').toLowerCase().includes(query)||(x.category?.name||'').toLowerCase().includes(query))&&
    (catFilter==='All'||String(x.category_id)===catFilter)&&
    (condFilter==='All'||x.condition===condFilter)&&
    (statusFilter==='All'||x.status===statusFilter)
  );
  const hasFilters=query!==''||catFilter!=='All'||condFilter!=='All'||statusFilter!=='All';
  const clearFilters=()=>{setSearch('');setCatFilter('All');setCondFilter('All');setStatusFilter('All');};

  const openNew=()=>{setError('');setForm(emptyForm);setEditing('new')};
  const openEdit=x=>{
    setError('');
    setForm({category_id:String(x.category_id),name:x.name,description:x.description||'',quantity:x.quantity,condition:x.condition});
    setEditing(x);
  };
  const close=()=>{setEditing(null);setForm(emptyForm);setError('')};

  const save=async e=>{
    e.preventDefault(); setBusy(true); setError('');
    try{
      const payload={...form,quantity:Number(form.quantity)};
      if(editing==='new') await api.post('/admin/equipment',payload);
      else await api.put(`/admin/equipment/${editing.id}`,payload);
      close(); load();
    }catch(err){ setError(err.response?.data?.message||'Unable to save equipment.'); }
    finally{ setBusy(false); }
  };

  const del=async x=>{
    if(!await confirm({
      title:'Delete equipment?',
      message:`Permanently remove "${x.name}"? This cannot be undone.`,
      confirmLabel:'Delete Equipment',
      tone:'danger',
    }))return;
    setError('');
    try{ await api.delete(`/admin/equipment/${x.id}`); load(); }
    catch(err){ setError(err.response?.data?.message||'Unable to delete equipment.'); }
  };

  return <>
    <div className="page-heading"><div><h1>Equipment Management</h1><p>Add, update, or remove college equipment.</p></div>{can('equipment','create')&&<button className="btn btn-primary" onClick={openNew}><Plus size={16}/> Add Equipment</button>}</div>
    {error&&!editing&&<div className="alert error">{error}</div>}

    {/* ---- Search & filters ---- */}
    <div className="toolbar">
      <SearchBar value={search} onChange={setSearch} placeholder="Search by name, description, or category..." />
      <select value={catFilter} onChange={e=>setCatFilter(e.target.value)} aria-label="Filter by category">
        <option value="All">All Categories</option>
        {cats.map(c=><option key={c.id} value={String(c.id)}>{c.name}</option>)}
      </select>
      <select value={condFilter} onChange={e=>setCondFilter(e.target.value)} aria-label="Filter by condition">
        <option value="All">All Conditions</option>
        <option>Good</option>
        <option>Fair</option>
        <option>Damaged</option>
      </select>
      <select value={statusFilter} onChange={e=>setStatusFilter(e.target.value)} aria-label="Filter by availability">
        <option value="All">All Availability</option>
        <option>Available</option>
        <option>Unavailable</option>
      </select>
      {hasFilters&&<button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
    </div>

    <div className="panel table-wrap"><table>
      <thead><tr><th>Name</th><th>Category</th><th>Total</th><th>Available</th><th>Condition</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>{filtered.map(x=><tr key={x.id}>
        <td>{x.name}</td>
        <td>{x.category?.name}</td>
        <td>{x.quantity}</td>
        <td>{x.available_quantity}</td>
        <td>{x.condition}</td>
        <td>{x.status}</td>
        <td className="action-cell">
          {can('equipment','edit')&&<button className="btn btn-secondary small" title="Edit" onClick={()=>openEdit(x)}><Pencil size={15}/></button>}
          {can('equipment','delete')&&<button className="btn btn-danger small" title="Delete" onClick={()=>del(x)}><Trash2 size={15}/></button>}
          {!can('equipment','edit')&&!can('equipment','delete')&&<span className="muted">View only</span>}
        </td>
      </tr>)}
      {!filtered.length&&<tr className="empty"><td colSpan="7">{items.length?'No equipment matches your filters.':'No equipment registered yet.'}</td></tr>}
    </tbody>
    </table></div>

    <Modal open={!!editing} title={editing==='new'?'Add Equipment':`Edit — ${editing?.name}`} onClose={close}>
      <form onSubmit={save}>
        <div className="modal-body">
        <label>Category
          <select value={form.category_id} onChange={e=>setForm({...form,category_id:e.target.value})} required>
            <option value="">Select</option>
            {cats.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </label>
        <label>Name<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required/></label>
        <label>Description<textarea value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label>
        <div className="form-grid">
          <label>Quantity<input type="number" min="0" value={form.quantity} onChange={e=>setForm({...form,quantity:e.target.value})} required/></label>
          <label>Condition<select value={form.condition} onChange={e=>setForm({...form,condition:e.target.value})}><option>Good</option><option>Fair</option><option>Damaged</option></select></label>
        </div>
        {editing&&editing!=='new'&&editing.available_quantity<editing.quantity&&
          <p className="muted small-note">{editing.quantity-editing.available_quantity} unit(s) are currently borrowed; total quantity cannot go below that.</p>}
        {error&&<div className="alert error">{error}</div>}
        </div>
        <div className="modal-actions">
          <button type="button" className="btn btn-secondary" onClick={close}>Cancel</button>
          <button className="btn btn-primary" disabled={busy}>{busy?'Saving...':'Save Equipment'}</button>
        </div>
      </form>
    </Modal>
  </>;
}
