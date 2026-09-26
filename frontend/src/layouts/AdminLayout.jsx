import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import Navbar from '../components/Navbar';

export default function AdminLayout() {
  const [open,setOpen]=useState(false);
  return <div className="app-shell"><Sidebar admin open={open} onClose={()=>setOpen(false)}/><div className="main-area"><Navbar onMenu={()=>setOpen(true)}/><main className="page-content"><Outlet/></main></div></div>;
}
