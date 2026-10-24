import { memo } from 'react';

function DashboardCard({ icon: Icon, title, value, subtitle }) {
  return (
    <div className="dashboard-card">
      <div className="card-icon"><Icon size={22} /></div>
      <div>
        <div className="card-title">{title}</div>
        <div className="card-value">{value}</div>
        {subtitle && <div className="card-subtitle">{subtitle}</div>}
      </div>
    </div>
  );
}

export default memo(DashboardCard);
