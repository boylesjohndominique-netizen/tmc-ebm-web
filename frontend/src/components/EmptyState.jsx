import { PackageOpen } from 'lucide-react';

/**
 * Friendly empty state with icon + optional action button.
 */
export default function EmptyState({
  icon: Icon = PackageOpen,
  title = 'Nothing here yet',
  message = 'Items will appear here once created.',
  action = null, // { label, onClick } or { label, to }
}) {
  return (
    <div className="empty-state">
      <div className="empty-state-icon"><Icon size={30} /></div>
      <h3>{title}</h3>
      <p>{message}</p>
      {action && (
        action.to
          ? <a className="btn btn-primary" href={action.to}>{action.label}</a>
          : <button className="btn btn-primary" onClick={action.onClick}>{action.label}</button>
      )}
    </div>
  );
}
