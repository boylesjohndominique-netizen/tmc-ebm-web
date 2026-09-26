import { lazy, Suspense } from "react";
import { Navigate, Route, Routes, useLocation } from "react-router-dom";

import { useAuth } from "./context/AuthContext";

// Layout
import AdminLayout from "./layouts/AdminLayout";

// Shared components (tiny — stay in the main bundle)
import Loading from "./components/Loading";
import NoAccess from "./components/NoAccess";

// =====================================================
// ROUTE-LEVEL CODE SPLITTING
// =====================================================
// Each page becomes its own chunk, downloaded on first visit. This is the
// single biggest load-time win: the login screen only needs the auth page,
// and staff only download the modules they actually open. Recharts is
// already isolated via the dashboard's lazy AnalyticsSection.
const Login = lazy(() => import("./pages/auth/Login"));
const AdminDashboard = lazy(() => import("./pages/admin/Dashboard"));
const Users = lazy(() => import("./pages/admin/Users"));
const AdminEquipment = lazy(() => import("./pages/admin/Equipment"));
const BorrowingRequests = lazy(() => import("./pages/admin/BorrowingRequests"));
const ReturnRequests = lazy(() => import("./pages/admin/ReturnRequests"));
const TransactionHistory = lazy(() => import("./pages/admin/TransactionHistory"));
const AdminNotifications = lazy(() => import("./pages/admin/Notifications"));
const AdminProfile = lazy(() => import("./pages/admin/Profile"));
const Reports = lazy(() => import("./pages/admin/Reports"));
const Permissions = lazy(() => import("./pages/admin/Permissions"));

function PageFallback() {
  return (
    <div className="page-heading" aria-busy="true">
      <Loading text="Loading page…" />
    </div>
  );
}


// =====================================================
// PROTECTED ROUTE (WEB-SYSTEM STAFF ACCOUNTS)
// =====================================================

function Protected({ children }) {
  const { user, loading } = useAuth();

  // Show loading screen while checking authentication
  if (loading) {
    return (
      <div
        style={{
          minHeight: "100vh",
          display: "grid",
          placeItems: "center",
        }}
      >
        <Loading text="Preparing your workspace…" />
      </div>
    );
  }

  // If user is not logged in, redirect to login
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // This system serves staff-side roles only (students use the mobile app)
  if (user.role?.name === "Student") {
    return <Navigate to="/login" replace />;
  }

  // Allow access
  return children;
}


// =====================================================
// PERMISSION GUARD — renders NoAccess instead of the page
// when the user lacks the module permission.
// =====================================================

function RequirePerm({ module, action = "view", children }) {
  const { can, loading } = useAuth();
  const location = useLocation();

  if (loading) return null;

  if (!can(module, action)) {
    return (
      <NoAccess
        title="Module unavailable"
        message={`Your account does not have permission to open this module (${module}). Direct URLs to restricted pages stay blocked here and on the server.`}
      />
    );
  }

  return children;
}


// =====================================================
// GUEST ONLY ROUTE
// =====================================================
// Prevent authenticated users from opening login page
// =====================================================

function GuestOnly({ children }) {
  const { user, loading } = useAuth();

  // Show loading screen while checking session
  if (loading) {
    return (
      <div
        style={{
          minHeight: "100vh",
          display: "grid",
          placeItems: "center",
        }}
      >
        <Loading text="Checking your session…" />
      </div>
    );
  }

  // If already logged in as a staff-side account, go to the dashboard
  if (user && user.role?.name !== "Student") {
    return <Navigate to="/admin" replace />;
  }

  // Allow guest to access login
  return children;
}


// =====================================================
// MAIN APP
// =====================================================

export default function App() {
  return (
    <Suspense fallback={<PageFallback />}>
      <Routes>

        {/* =========================================
            AUTHENTICATION
        ========================================= */}

        <Route
          path="/login"
          element={
            <GuestOnly>
              <Login />
            </GuestOnly>
          }
        />


        {/* =========================================
            ROOT → ADMIN DASHBOARD
        ========================================= */}

        <Route
          path="/"
          element={<Navigate to="/admin" replace />}
        />


        {/* =========================================
            ADMIN ROUTES
        ========================================= */}

        <Route
          element={
            <Protected>
              <AdminLayout />
            </Protected>
          }
        >

          {/* Admin Dashboard */}
          <Route
            path="/admin"
            element={
              <RequirePerm module="dashboard">
                <AdminDashboard />
              </RequirePerm>
            }
          />

          {/* Users Management */}
          <Route
            path="/admin/users"
            element={
              <RequirePerm module="users">
                <Users />
              </RequirePerm>
            }
          />

          {/* Equipment Management */}
          <Route
            path="/admin/equipment"
            element={
              <RequirePerm module="equipment">
                <AdminEquipment />
              </RequirePerm>
            }
          />

          {/* Borrowing Requests */}
          <Route
            path="/admin/borrowing-requests"
            element={
              <RequirePerm module="borrowings">
                <BorrowingRequests />
              </RequirePerm>
            }
          />

          {/* Return Requests */}
          <Route
            path="/admin/return-requests"
            element={
              <RequirePerm module="returns">
                <ReturnRequests />
              </RequirePerm>
            }
          />

          {/* Transaction History (processed requests audit) */}
          <Route
            path="/admin/transaction-history"
            element={
              <RequirePerm module="transactions">
                <TransactionHistory />
              </RequirePerm>
            }
          />

          {/* Admin Notifications */}
          <Route
            path="/admin/notifications"
            element={
              <RequirePerm module="notifications">
                <AdminNotifications />
              </RequirePerm>
            }
          />

          {/* Reports */}
          <Route
            path="/admin/reports"
            element={
              <RequirePerm module="reports">
                <Reports />
              </RequirePerm>
            }
          />

          {/* Permission Management (Super Admin only) */}
          <Route
            path="/admin/permissions"
            element={
              <RequirePerm module="permissions" action="manage">
                <Permissions />
              </RequirePerm>
            }
          />

          {/* Admin Profile — always available to signed-in staff accounts */}
          <Route
            path="/admin/profile"
            element={<AdminProfile />}
          />

        </Route>


        {/* =========================================
            404 / UNKNOWN ROUTES
        ========================================= */}

        <Route
          path="*"
          element={<Navigate to="/login" replace />}
        />

      </Routes>
    </Suspense>
  );
}
