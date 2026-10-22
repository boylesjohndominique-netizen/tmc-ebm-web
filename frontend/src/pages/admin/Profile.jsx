import { useEffect,useRef,useState } from 'react';
import { Eye, EyeOff, ImagePlus, KeyRound, Save, Trash2 } from 'lucide-react';
import api from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import { useNotifications } from '../../context/NotificationContext';
import { useConfirm } from '../../context/ConfirmContext';
import Skeleton from '../../components/Skeleton';
import UserAvatar from '../../components/UserAvatar';
import { fmtDate } from '../../utils/datetime';

export default function Profile(){
  const { refreshUser } = useAuth();
  const { refresh } = useNotifications(); // keeps the polling alive while token context changes
  const confirm = useConfirm();
  const [user,setUser]=useState(null);
  const [form,setForm]=useState({first_name:'',middle_name:'',last_name:'',email:'',contact_number:''});
  const [pw,setPw]=useState({current_password:'',password:'',password_confirmation:''});
  const [showPw,setShowPw]=useState(false); // show/hide the password fields
  const [msg,setMsg]=useState(''); const [error,setError]=useState('');
  const [pwMsg,setPwMsg]=useState(''); const [pwError,setPwError]=useState('');
  const [busy,setBusy]=useState(false);
  const photoInput=useRef(null);
  const [photo,setPhoto]=useState(null);
  const [photoPreview,setPhotoPreview]=useState(null);
  const [photoBusy,setPhotoBusy]=useState(false);
  const [photoMessage,setPhotoMessage]=useState('');
  const [photoError,setPhotoError]=useState('');
  const [loadError,setLoadError]=useState('');

  useEffect(()=>{ api.get('/profile').then(r=>{ setUser(r.data); setForm({
    first_name:r.data.first_name, middle_name:r.data.middle_name||'', last_name:r.data.last_name,
    email:r.data.email, contact_number:r.data.contact_number||'' }); }).catch(()=>setLoadError('Unable to load your profile. Please refresh the page.')); },[]);

  useEffect(()=>{
    if(!photo){setPhotoPreview(null);return;}
    const url=URL.createObjectURL(photo);
    setPhotoPreview(url);
    return ()=>URL.revokeObjectURL(url);
  },[photo]);

  const choosePhoto=e=>{
    const file=e.target.files?.[0];
    e.target.value='';
    if(!file)return;
    setPhotoError('');setPhotoMessage('');
    if(!['image/jpeg','image/png','image/webp','image/gif','image/bmp'].includes(file.type)){
      setPhotoError('Choose a JPG, PNG, WEBP, GIF, or BMP image.');return;
    }
    if(file.size>5*1024*1024){setPhotoError('Choose an image that is 5 MB or smaller.');return;}
    setPhoto(file);
  };

  const savePhoto=async()=>{
    if(!photo||photoBusy)return;
    setPhotoBusy(true);setPhotoError('');setPhotoMessage('');
    try{
      const payload=new FormData();payload.append('avatar',photo);
      const {data}=await api.post('/profile/avatar',payload);
      setUser(data.user);setPhoto(null);await refreshUser();
      setPhotoMessage(data.message||'Profile picture updated.');
    }catch(err){setPhotoError(err.response?.data?.errors?.avatar?.[0]||err.response?.data?.message||'Unable to upload your profile picture. Please try again.');}
    finally{setPhotoBusy(false);}
  };

  const removePhoto=async()=>{
    if(photoBusy||!await confirm({title:'Remove profile picture?',message:'Your initials will be shown in place of your profile picture.',confirmLabel:'Remove Picture',tone:'danger'}))return;
    setPhotoBusy(true);setPhotoError('');setPhotoMessage('');
    try{
      const {data}=await api.delete('/profile/avatar');
      setUser(data.user);setPhoto(null);await refreshUser();
      setPhotoMessage(data.message||'Profile picture removed.');
    }catch(err){setPhotoError(err.response?.data?.message||'Unable to remove your profile picture. Please try again.');}
    finally{setPhotoBusy(false);}
  };

  const save=async e=>{
    e.preventDefault(); setBusy(true); setMsg(''); setError('');
    try{
      const {data}=await api.put('/profile',form);
      setUser(data); refreshUser(); // navbar name updates immediately
      setMsg('Profile updated successfully.');
    }catch(err){ setError(err.response?.data?.message||'Unable to update profile.'); }
    finally{ setBusy(false); }
  };

  const changePassword=async e=>{
    e.preventDefault(); setPwMsg(''); setPwError('');
    if(pw.password!==pw.password_confirmation){ setPwError('New passwords do not match.'); return; }
    if(pw.password===pw.current_password){ setPwError('The new password must be different from the current password.'); return; }
    if(!await confirm({
      title:'Change password?',
      message:'Your password will be updated immediately. You will keep your current session.',
      confirmLabel:'Change Password',
      tone:'danger',
    })) return;
    setBusy(true);
    try{
      await api.put('/profile/password',pw);
      setPwMsg('Password changed successfully.'); setPw({current_password:'',password:'',password_confirmation:''});
    }catch(err){
      setPwError(err.response?.data?.message
        || Object.values(err.response?.data?.errors||{}).flat().join(' ')
        || 'Unable to change password.');
    } finally{ setBusy(false); }
  };

  if(!user) return <div className="profile-page"><div className="page-heading"><div><h1>My Profile</h1><p>Manage your account information and security.</p></div></div>{loadError?<div className="alert error" role="alert">{loadError}</div>:<div className="profile-grid"><div className="panel"><Skeleton variant="cards" cards={1} /></div><div className="panel"><Skeleton rows={4} /></div><div className="panel"><Skeleton rows={3} /></div></div>}</div>;

  return <div className="profile-page">
    <div className="page-heading"><div><h1>My Profile</h1><p>Manage your account information and security.</p></div></div>

    <div className="profile-grid">
      <section className="panel profile-summary">
        <UserAvatar path={user.profile_photo_path} src={photoPreview} name={[user.first_name,user.last_name].filter(Boolean).join(' ')||user.username} className="profile-avatar" />
        <div className="profile-photo-controls">
          <input ref={photoInput} type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" aria-label="Choose profile picture" onChange={choosePhoto} hidden disabled={busy||photoBusy}/>
          <button type="button" className="btn btn-secondary small" disabled={busy||photoBusy} onClick={()=>photoInput.current?.click()}><ImagePlus size={15}/>{user.profile_photo_path?'Change Picture':'Choose Picture'}</button>
          {photo&&<>
            <button type="button" className="btn btn-primary small" disabled={busy||photoBusy} onClick={savePhoto}><Save size={15}/>{photoBusy?'Uploading...':'Save Picture'}</button>
            <button type="button" className="btn btn-secondary small" disabled={busy||photoBusy} onClick={()=>{setPhoto(null);setPhotoError('');}}>Cancel</button>
          </>}
          {!photo&&user.profile_photo_path&&<button type="button" className="btn btn-danger small" disabled={busy||photoBusy} onClick={removePhoto}><Trash2 size={15}/>{photoBusy?'Removing...':'Remove Picture'}</button>}
        </div>
        <p className="small-note">JPG, PNG, WEBP, GIF, or BMP. Maximum 5 MB.</p>
        {photoMessage&&<div className="alert success" role="status">{photoMessage}</div>}
        {photoError&&<div className="alert error" role="alert">{photoError}</div>}
        <h2>{[form.first_name,form.middle_name].filter(Boolean).join(' ')} {form.last_name}</h2>
        <span className="role-chip">{user.role?.name}</span>
        <div className="detail-list">
          <div><b>Username</b>{user.username}</div>
          <div><b>Email</b>{user.email}</div>
          <div><b>Contact Number</b>{user.contact_number||'—'}</div>
          <div><b>Member Since</b>{fmtDate(user.created_at)}</div>
        </div>
      </section>

      <section className="panel">
        <h2>Account Information</h2>
        {msg&&<div className="alert success">{msg}</div>}
        {error&&<div className="alert error">{error}</div>}
        <form onSubmit={save}>
          <div className="form-grid">
            <label>First Name<input value={form.first_name} onChange={e=>setForm({...form,first_name:e.target.value})} required/></label>
            <label>Middle Name<input value={form.middle_name} onChange={e=>setForm({...form,middle_name:e.target.value})}/></label>
            <label>Last Name<input value={form.last_name} onChange={e=>setForm({...form,last_name:e.target.value})} required/></label>
            <label>Email<input type="email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} required/></label>
            <label>Contact Number<input value={form.contact_number} onChange={e=>setForm({...form,contact_number:e.target.value})}/></label>
          </div>
          <p className="muted small-note">Username and role can only be changed by an administrator.</p>
          <button className="btn btn-primary" disabled={busy||photoBusy}><Save size={16}/> Save Changes</button>
        </form>
      </section>

      <section className="panel">
        <h2>Change Password</h2>
        {pwMsg&&<div className="alert success">{pwMsg}</div>}
        {pwError&&<div className="alert error">{pwError}</div>}
        <form onSubmit={changePassword}>
          <label>Current Password
            <div className="password-field">
              <input type={showPw?'text':'password'} value={pw.current_password} onChange={e=>setPw({...pw,current_password:e.target.value})} required autoComplete="current-password"/>
              <button type="button" className="password-toggle" title={showPw?'Hide password':'Show password'} aria-label={showPw?'Hide password':'Show password'} onClick={()=>setShowPw(v=>!v)}>
                {showPw?<EyeOff size={15}/>:<Eye size={15}/>}
              </button>
            </div>
          </label>
          <label>New Password
            <div className="password-field">
              <input type={showPw?'text':'password'} value={pw.password} onChange={e=>setPw({...pw,password:e.target.value})} required minLength="8" autoComplete="new-password"/>
              <button type="button" className="password-toggle" title={showPw?'Hide password':'Show password'} aria-label={showPw?'Hide password':'Show password'} onClick={()=>setShowPw(v=>!v)}>
                {showPw?<EyeOff size={15}/>:<Eye size={15}/>}
              </button>
            </div>
          </label>
          <label>Confirm New Password
            <div className="password-field">
              <input type={showPw?'text':'password'} value={pw.password_confirmation} onChange={e=>setPw({...pw,password_confirmation:e.target.value})} required minLength="8" autoComplete="new-password"/>
              <button type="button" className="password-toggle" title={showPw?'Hide password':'Show password'} aria-label={showPw?'Hide password':'Show password'} onClick={()=>setShowPw(v=>!v)}>
                {showPw?<EyeOff size={15}/>:<Eye size={15}/>}
              </button>
            </div>
          </label>
          <button className="btn btn-primary" disabled={busy||photoBusy}><KeyRound size={16}/> Change Password</button>
        </form>
      </section>
    </div>
  </div>;
}
