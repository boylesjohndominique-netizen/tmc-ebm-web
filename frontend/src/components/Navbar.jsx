import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import ThemeToggle from './ThemeToggle';
import ConnectionBadge from './ConnectionBadge';
import UserAvatar from './UserAvatar';

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
        <NavLink to="/admin/profile" className="navbar-avatar" aria-label="Open profile" title={role ? `Profile — ${fullName} — ${role}` : 'Profile'}>
          <UserAvatar path={user?.profile_photo_path} name={fullName || user?.username} />
          <div className="user-mini">{fullName || user?.username}</div>
        </NavLink>
      </div>
    </header>
  );
}
