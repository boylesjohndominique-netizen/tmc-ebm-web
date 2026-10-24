import { useEffect, useMemo, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { LayoutDashboard, Boxes, Package, ClipboardList, RotateCcw, Bell, Users, GraduationCap, UserCog, BarChart3, Settings, LogOut, X, ChevronDown } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useNotifications } from '../context/NotificationContext';
import { useConfirm } from '../context/ConfirmContext';

/**
 * Sidebar directory. Each entry is either a plain link or a collapsible
 * group (type: 'group') whose children drop down on click.
 * The module key is checked against the signed-in user's permissions;
 * links to modules they cannot view are hidden entirely.
 *
 * Transaction History deliberately has no entry here — it lives inside
 * the Reports page ("Transaction Audit" report) and a standalone page
 * would duplicate it.
 */
const adminNav = [
  { type: 'link', to: '/admin', label: 'Dashboard', icon: LayoutDashboard, module: 'dashboard' },
  {
    type: 'group',
    id: 'users',
    label: 'User Management',
    icon: Users,
    children: [
      { to: '/admin/students', label: 'Student Management', icon: GraduationCap, module: 'users' },
      { to: '/admin/staff', label: 'Staff Management', icon: UserCog, module: 'users' },
    ],
  },
  {
    type: 'group',
    id: 'equipment',
    label: 'Equipment Management',
    icon: Boxes,
    children: [
      { to: '/admin/equipment', label: 'Equipment', icon: Package, module: 'equipment' },
      { to: '/admin/borrowing-requests', label: 'Borrowing Requests', icon: ClipboardList, module: 'borrowings' },
      { to: '/admin/return-requests', label: 'Return Requests', icon: RotateCcw, module: 'returns' },
    ],
  },
  { type: 'link', to: '/admin/notifications', label: 'Notifications', icon: Bell, module: 'notifications' },
  { type: 'link', to: '/admin/reports', label: 'Reports', icon: BarChart3, module: 'reports' },
  { type: 'link', to: '/admin/settings', label: 'Settings', icon: Settings, module: 'settings' },
];

/**
 * Floating navigation — a slim rail of circular icon buttons on desktop
 * and, on mobile, the same markup becomes the floating panel with names
 * shown next to the icons (CSS decides; this component only carries both
 * variants). On pointer devices every button reveals its name in a
 * tooltip on hover/focus, positioned in JS so it is not clipped by the
 * rail's scroll container.
 */
export default function Sidebar({ open, onClose }) {
  const { logout, can } = useAuth();
  const { unread } = useNotifications();
  const confirm = useConfirm();
  const location = useLocation();
  const [openGroup, setOpenGroup] = useState(null);
  const [tip, setTip] = useState(null);

  const nav = useMemo(() => {
    return adminNav
      .map(item => item.type === 'group'
        ? { ...item, children: item.children.filter(c => can(c.module, 'view')) }
        : item)
      .filter(item => item.type === 'group' ? item.children.length > 0 : can(item.module, 'view'));
  }, [can]);

  // Keep the group expanded while the admin is inside one of its pages.
  useEffect(() => {
    const group = nav.find(item => item.type === 'group' && item.children.some(c => c.to === location.pathname));
    if (group) setOpenGroup(group.id);
  }, [location.pathname]);

  const showTip = label => e => {
    const r = e.currentTarget.getBoundingClientRect();
    setTip({ label, top: r.top + r.height / 2 });
  };
  const hideTip = () => setTip(null);

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
        {nav.map(item => {
          if (item.type === 'link') {
            const { to, label, icon: Icon } = item;
            return (
              <NavLink
                key={to}
                to={to}
                end={to === '/admin'}
                onClick={onClose}
                onMouseEnter={showTip(label)}
                onMouseLeave={hideTip}
                onFocus={showTip(label)}
                onBlur={hideTip}
              >
                <span className="nav-icon">
                  <Icon size={19} />
                  {to === '/admin/notifications' && unread > 0 && <span className="notification-count">{unread}</span>}
                </span>
                <span className="nav-name">{label}</span>
              </NavLink>
            );
          }

          const { id, label, icon: Icon, children } = item;
          const isOpen = openGroup === id;
          const sectionActive = children.some(c => c.to === location.pathname);
          return (
            <div className="nav-group-wrap" key={id}>
              <button
                type="button"
                className={`nav-group ${isOpen ? 'open' : ''} ${sectionActive ? 'active' : ''}`}
                aria-expanded={isOpen}
                aria-label={label}
                onClick={() => setOpenGroup(isOpen ? null : id)}
                onMouseEnter={showTip(label)}
                onMouseLeave={hideTip}
                onFocus={showTip(label)}
                onBlur={hideTip}
              >
                <span className="nav-icon"><Icon size={19} /></span>
                <span className="nav-name">{label}</span>
                <ChevronDown size={16} className="nav-caret" />
              </button>
              {isOpen && (
                <div className="nav-sub">
                  {children.map(({ to, label: childLabel, icon: ChildIcon }) => (
                    <NavLink
                      key={to}
                      to={to}
                      onClick={onClose}
                      onMouseEnter={showTip(childLabel)}
                      onMouseLeave={hideTip}
                      onFocus={showTip(childLabel)}
                      onBlur={hideTip}
                    >
                      <span className="nav-icon"><ChildIcon size={18} /></span>
                      <span className="nav-name">{childLabel}</span>
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          );
        })}

        {!nav.length && (
          <span className="sidebar-empty">No modules available</span>
        )}
      </nav>

      <button
        className="logout-link"
        onClick={handleLogout}
        onMouseEnter={showTip('Logout')}
        onMouseLeave={hideTip}
        onFocus={showTip('Logout')}
        onBlur={hideTip}
      >
        <LogOut size={19} />
        <span className="nav-name">Logout</span>
      </button>

      {tip && (
        <span className="nav-tooltip" style={{ top: tip.top }} role="tooltip">{tip.label}</span>
      )}
    </aside>
  );
}
