const LABELS = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
  confirmed: 'Confirmed',
  completed: 'Completed',
  available: 'Available',
  unavailable: 'Unavailable',
  banned: 'Banned',
};

export default function StatusBadge({ status }) {
  const key = String(status || '').toLowerCase().replace(/\s+/g, '-');
  return (
    <span className={`status-badge ${key}`}>
      <span className="status-dot" aria-hidden="true" />
      {LABELS[key] || status}
  </span>
  );
}
