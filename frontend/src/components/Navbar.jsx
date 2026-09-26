import { Menu } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import NotificationBell from './NotificationBell';
import ThemeToggle from './ThemeToggle';
import ConnectionBadge from './ConnectionBadge';

function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[parts.length - 1][0] || '')).toUpperCase();
}

export default function Navbar({ onMenu }) {
  const { user } = useAuth();
  const fullName = [user?.first_name, user?.last_name].filter(Boolean).join(' ');
  const role = user?.role?.name;

  return (
    <header className="navbar">
      <button className="mobile-menu" onClick={onMenu} aria-label="Open menu"><Menu /></button>
      <div className="navbar-title">Equipment Borrowing &amp; Tracking</div>
      <div className="navbar-actions">
        <ConnectionBadge />
        <ThemeToggle />
        <NotificationBell />
        <div className="navbar-avatar" title={role ? `${fullName} — ${role}` : fullName}>
          <div className="avatar-initials">{initials(fullName || user?.username)}</div>
          <div className="user-mini">{fullName || user?.username}</div>
        </div>
      </div>
    </header>
  );
}
