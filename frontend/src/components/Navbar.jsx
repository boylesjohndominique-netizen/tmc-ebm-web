import { useAuth } from '../context/AuthContext';
import ThemeToggle from './ThemeToggle';
import ConnectionBadge from './ConnectionBadge';

function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[parts.length - 1][0] || '')).toUpperCase();
}

/**
 * Floating navbar. The notification button was removed — notifications
 * live in the sidebar (with the unread badge) now.
 */
export default function Navbar() {
  const { user } = useAuth();
  const fullName = [user?.first_name, user?.last_name].filter(Boolean).join(' ');
  const role = user?.role?.name;

  return (
    <header className="navbar">
      <div className="navbar-title">Equipment Borrowing &amp; Tracking</div>
      <div className="navbar-actions">
        <ConnectionBadge />
        <ThemeToggle />
        <div className="navbar-avatar" title={role ? `${fullName} — ${role}` : fullName}>
          <div className="avatar-initials">{initials(fullName || user?.username)}</div>
          <div className="user-mini">{fullName || user?.username}</div>
        </div>
      </div>
    </header>
  );
}
