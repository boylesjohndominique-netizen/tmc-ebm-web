import { NavLink } from 'react-router-dom';
import { LayoutDashboard, Package, ClipboardList, RotateCcw, Bell, User, Users, BarChart3, ShieldCheck, LogOut, X } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

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
];

export default function Sidebar({ open, onClose }) {
  const { logout, can, isSuperAdmin } = useAuth();

  const visible = adminLinks.filter(([, , , module]) => can(module, 'view'));

  return (
    <aside className={`sidebar ${open ? 'open' : ''}`}>
      <div className="sidebar-brand">
        <div className="brand-mark">TMC</div>
        <div><strong>Trinidad Municipal</strong><small>College</small></div>
        <button className="close-sidebar" onClick={onClose}><X /></button>
      </div>

      <div className="sidebar-label">ADMINISTRATION</div>

      <nav>
        {visible.map(([to, label, Icon]) => (
          <NavLink key={to} to={to} end={to === '/admin'} onClick={onClose}>
            <Icon size={19} /><span>{label}</span>
          </NavLink>
        ))}

        {isSuperAdmin && (
          <NavLink to="/admin/permissions" onClick={onClose}>
            <ShieldCheck size={19} /><span>Permissions</span>
          </NavLink>
        )}

        {!visible.length && !isSuperAdmin && (
          <span className="sidebar-empty">No modules available</span>
        )}
      </nav>

      {/* Profile — always available to signed-in staff accounts, sits above Logout */}
      <NavLink to="/admin/profile" className="profile-link" onClick={onClose}>
        <User size={19} /><span>Profile</span>
      </NavLink>

      <button className="logout-link" onClick={logout}><LogOut size={19} /> Logout</button>
    </aside>
  );
}
