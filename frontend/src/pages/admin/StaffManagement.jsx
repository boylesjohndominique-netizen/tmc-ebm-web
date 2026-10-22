import { lazy, Suspense } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import NoAccess from '../../components/NoAccess';
import Skeleton from '../../components/Skeleton';
import Users from './Users';

const Permissions = lazy(() => import('./Permissions'));

export default function StaffManagement() {
  const { isSuperAdmin } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const permissionsOpen = searchParams.get('tab') === 'permissions';
  const openPermissions = userId => setSearchParams({ tab: 'permissions', ...(userId ? { user: String(userId) } : {}) });

  return <>
    <div className="page-heading">
      <div>
        <h1>Staff Management</h1>
        <p>Manage Administrator, Staff, and Faculty accounts{isSuperAdmin ? ' and their permissions' : ''}.</p>
      </div>
    </div>
    {isSuperAdmin && <div className="filter-tabs" aria-label="Staff management sections">
      <button className={`filter-tab ${permissionsOpen ? '' : 'active'}`} aria-pressed={!permissionsOpen} onClick={() => setSearchParams({})}>Accounts</button>
      <button className={`filter-tab ${permissionsOpen ? 'active' : ''}`} aria-pressed={permissionsOpen} onClick={() => openPermissions()}>Permissions</button>
    </div>}
    {permissionsOpen
      ? isSuperAdmin
        ? <Suspense fallback={<div className="panel"><Skeleton rows={6} /></div>}>
            <Permissions userId={searchParams.get('user')} onSelectUser={openPermissions} />
          </Suspense>
        : <NoAccess title="Permissions unavailable" message="Only the Super Admin can manage staff permissions." />
      : <Users key="staff" audience="staff" embedded onManagePermissions={isSuperAdmin ? openPermissions : undefined} />}
  </>;
}
