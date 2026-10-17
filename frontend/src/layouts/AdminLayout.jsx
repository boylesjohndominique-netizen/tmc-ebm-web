import { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import Sidebar from '../components/Sidebar';
import Navbar from '../components/Navbar';

/**
 * Floating navigation shell:
 * desktop/tablet — slim floating icon rail (Sidebar) + floating Navbar;
 * mobile — the rail becomes a slide-in floating panel, toggled by the
 * floating menu button (nav-fab) and dismissed by the backdrop, the
 * button itself, or any navigation item (Sidebar's onClose).
 */
export default function AdminLayout() {
  const [open, setOpen] = useState(false);

  return (
    <div className="app-shell">
      <div
        className={`nav-backdrop ${open ? 'open' : ''}`}
        onClick={() => setOpen(false)}
        aria-hidden="true"
      />
      {/* Brand icon — its own floating chip at the top-left, above the rail
          and separate from the navbar; taps through to the Dashboard. */}
      <NavLink to="/admin" className="nav-brand" aria-label="Dashboard">
        <div className="brand-mark"><img src="/logo.png" alt="TMCEBMS" /></div>
      </NavLink>
      <Sidebar open={open} onClose={() => setOpen(false)} />
      <button
        className={`nav-fab ${open ? 'open' : ''}`}
        onClick={() => setOpen(o => !o)}
        aria-label={open ? 'Close navigation menu' : 'Open navigation menu'}
        aria-expanded={open}
      >
        {open ? <X size={22} /> : <Menu size={22} />}
      </button>
      <div className="main-area">
        <Navbar />
        <main className="page-content"><Outlet /></main>
      </div>
    </div>
  );
}
