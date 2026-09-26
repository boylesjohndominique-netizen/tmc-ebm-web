import { ShieldBan } from 'lucide-react';

/**
 * Shown when the signed-in user lacks permission for a module.
 * Rendered inside the normal page layout to keep the shell consistent.
 */
export default function NoAccess({ title = 'Access restricted', message }) {
  return (
    <div className="panel">
      <div className="empty-state">
        <div className="empty-state-icon"><ShieldBan size={30} /></div>
        <h3>{title}</h3>
        <p>{message || 'You do not have permission to access this module. Please contact the Super Admin if you believe this is a mistake.'}</p>
      </div>
    </div>
  );
}
