import React, { lazy, Suspense, useCallback, useEffect, useState } from 'react';
import { Package, Users, ClipboardList, AlertTriangle } from 'lucide-react';
import api from '../../services/api';
import { fmtDate } from '../../utils/datetime';
import DashboardCard from '../../components/DashboardCard';
import StatusBadge from '../../components/StatusBadge';
import Skeleton from '../../components/Skeleton';
import useRealtimeResource from '../../hooks/useRealtimeResource';

// Recharts is heavy — load the analytics section (and its charts) as a
// separate chunk so the initial dashboard bundle stays lean.
const AnalyticsSection = lazy(() => import('../../components/AnalyticsSection'));

function AnalyticsFallback() {
  return (
    <div className="panel analytics-panel" aria-hidden="true">
      <h2 className="analytics-title">Analytics</h2>
      <div className="analytics-grid">
        {[0, 1].map(i => (
          <div className="analytics-card" key={i}>
            <div className="skeleton" style={{ height: 18, width: '40%', marginBottom: 18 }} />
            <div className="skeleton" style={{ height: 240, borderRadius: 10 }} />
          </div>
        ))}
      </div>
    </div>
  );
}

// If the analytics chunk fails to download, the dashboard must still render.
class SilentErrorBoundary extends React.Component {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  render() { return this.state.failed ? null : this.props.children; }
}

export default function Dashboard() {
  const [d, setD] = useState(null);
  const [error, setError] = useState('');
  const load = useCallback(() => {
    return api.get('/dashboard')
      .then(r => setD(r.data))
      .catch(() => setError('Unable to load the dashboard. Please refresh the page.'));
  }, []);
  useEffect(() => { load(); }, [load]);

  // Live updates: equipment stock, requests, users, returns all move the
  // dashboard numbers — refetch whenever any of them changes server-side.
  useRealtimeResource(['equipment', 'borrowings', 'returns', 'users'], load);

  if (error) return <div className="panel"><h1>Administrator Dashboard</h1><div className="alert error">{error}</div></div>;
  if (!d) return <Skeleton variant="stats" />;

  const s = d.statistics;
  return (
    <>
      <div className="page-heading"><div><h1>Administrator Dashboard</h1><p>System-wide equipment and borrowing overview.</p></div></div>
      <div className="dashboard-grid">
        <DashboardCard icon={Package} title="Equipment" value={s.total_equipment} subtitle={`${s.available_equipment} units available`} />
        <DashboardCard icon={Users} title="Users" value={s.total_users} />
        <DashboardCard icon={ClipboardList} title="Pending Requests" value={s.pending_requests} />
        <DashboardCard icon={AlertTriangle} title="Overdue" value={s.overdue} />
      </div>
      <SilentErrorBoundary>
        <Suspense fallback={<AnalyticsFallback />}>
          <AnalyticsSection />
        </Suspense>
      </SilentErrorBoundary>
      <div className="panel">
        <h2>Recent Requests</h2>
        <div className="table-wrap"><table>
          <thead><tr><th>ID</th><th>User</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            {d.recent_requests.map(x => (
              <tr key={x.id}>
                <td>#{x.id}</td>
                <td>{x.user?.first_name} {x.user?.last_name}</td>
                <td>{fmtDate(x.borrowing_date)}</td>
                <td><StatusBadge status={x.status} /></td>
              </tr>
            ))}
            {!d.recent_requests.length && <tr><td colSpan="4" className="empty">No requests yet.</td></tr>}
          </tbody>
        </table></div>
      </div>
    </>
  );
}
