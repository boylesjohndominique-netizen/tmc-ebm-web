import { NavLink } from 'react-router-dom';
import { LayoutDashboard, Package, ClipboardList, RotateCcw, Bell, Users, BarChart3, ShieldCheck, Settings, LogOut, X } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useNotifications } from '../context/NotificationContext';
import { useConfirm } from '../context/ConfirmContext';

/**
 * Sidebar directory: [route, label, icon, module].
 * The module key is checked against the signed-in user's permissions;
 * links to modules they cannot view are hidden entirely.
 *
 * Transaction History deliberately has no entry here — it lives inside
 * the Reports page ("Transaction Audit" report) and a standalone page
 * would duplicate it.
 */
const adminLinks = [
  ['/admin', 'Dashboard', LayoutDashboard, 'dashboard'],
  ['/admin/users', 'Users', Users, 'users'],
  ['/admin/equipment', 'Equipment', Package, 'equipment'],
  ['/admin/borrowing-requests', 'Borrowing Requests', ClipboardList, 'borrowings'],
  ['/admin/return-requests', 'Return Requests', RotateCcw, 'returns'],
  ['/admin/notifications', 'Notifications', Bell, 'notifications'],
  ['/admin/reports', 'Reports', BarChart3, 'reports'],
  ['/admin/settings', 'Settings', Settings, 'settings'],
];

function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[parts.length - 1][0] || '')).toUpperCase();
}

/**
 * Floating navigation — a slim rail of circular icon buttons on desktop
 * (page names appear as hover tooltips via data-label) and, on mobile,
 * the same markup becomes the floating panel with names shown next to
 * the icons (CSS decides; this component only carries both variants).
 */
export default function Sidebar({ open, onClose }) {
  const { logout, can, isSuperAdmin, user } = useAuth();
  const { unread } = useNotifications();
  const confirm = useConfirm();

  const visible = adminLinks.filter(([, , , module]) => can(module, 'view'));
  const fullName = [user?.first_name, user?.last_name].filter(Boolean).join(' ') || user?.username || '?';

  const handleLogout = async () => {
    if (await confirm({
      title: 'Log out?',
      message: 'You will need to sign in again to access the admin panel.',
      confirmLabel: 'Log Out',
      tone: 'danger',
    })) logout();
  };

  return (
    <aside className={`sidebar ${open ? 'open' : ''}`} aria-label="Main navigation">
      {/* Mobile panel header — text brand + close button. The TMC mark lives
          in its own floating chip outside the rail (AdminLayout). */}
      <div className="sidebar-head">
        <div className="sidebar-brand-text"><strong>Trinidad Municipal</strong><small>College</small></div>
        <button className="close-sidebar" onClick={onClose} aria-label="Close menu"><X size={20} /></button>
      </div>

      <div className="sidebar-label">ADMINISTRATION</div>

      <nav>
        {visible.map(([to, label, Icon]) => (
          <NavLink key={to} to={to} end={to === '/admin'} className="nav-tip" data-label={label} onClick={onClose}>
            <span className="nav-icon">
              <Icon size={19} />
              {to === '/admin/notifications' && unread > 0 && <span className="notification-count">{unread}</span>}
            </span>
            <span className="nav-name">{label}</span>
          </NavLink>
        ))}

        {isSuperAdmin && (
          <NavLink to="/admin/permissions" className="nav-tip" data-label="Permissions" onClick={onClose}>
            <span className="nav-icon"><ShieldCheck size={19} /></span>
            <span className="nav-name">Permissions</span>
          </NavLink>
        )}

        {!visible.length && !isSuperAdmin && (
          <span className="sidebar-empty">No modules available</span>
        )}
      </nav>

      {/* Profile — circular avatar button, navigates to the Profile page */}
      <NavLink to="/admin/profile" className="profile-link nav-tip" data-label="Profile" onClick={onClose}>
        <span className="avatar-initials">{initials(fullName)}</span>
        <span className="nav-name">Profile</span>
      </NavLink>

      <button className="logout-link nav-tip" data-label="Logout" onClick={handleLogout}>
        <LogOut size={19} />
        <span className="nav-name">Logout</span>
      </button>
    </aside>
  );
}
