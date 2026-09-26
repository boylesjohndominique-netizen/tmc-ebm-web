import { createContext, useContext, useEffect, useState } from 'react';
import api, { getToken, setToken, clearToken } from '../services/api';
import { startRealtime, stopRealtime } from '../services/realtime';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const loadUser = async () => {
    const token = getToken();
    if (!token) { setLoading(false); return; }
    try {
      const { data } = await api.get('/me');
      setUser(data);
      // Mirrored for the realtime service (private channel name) — api.js
      // clears it together with the token on logout/401.
      localStorage.setItem('tmc_user', JSON.stringify(data));
      startRealtime();
    } catch {
      clearToken();
      setUser(null);
      stopRealtime();
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadUser(); }, []);

  const login = async (login, password) => {
    const { data } = await api.post('/login', { login, password });
    setToken(data.token);
    setUser(data.user);
    localStorage.setItem('tmc_user', JSON.stringify(data.user));
    startRealtime();
    return data;
  };

  // Public self-registration (mobile app flow). The account is created as
  // Pending and requires admin verification, so it does not log the user in.
  const register = async (form) => {
    const { data } = await api.post('/register', form);
    return data;
  };

  const logout = async () => {
    try { await api.post('/logout'); } catch {}
    stopRealtime();
    clearToken();
    setUser(null);
  };

  /**
   * Frontend permission check — mirrors the backend's User::canAccess().
   * Map shape: { "*": ["*"] } (super admin / full admin) or
   * { module: ["view", "edit", ...] }. This gates menus and buttons so
   * users don't see actions they can't perform; the backend enforces the
   * same rules on every endpoint.
   */
  const can = (module, action = 'view') => {
    const map = user?.permission_map;
    if (!map) return false;
    if (map['*']?.includes('*')) return true;
    const actions = map[module];
    if (!actions) return false;
    return actions.includes('*') || actions.includes(action);
  };

  const isSuperAdmin = !!user?.is_super_admin;

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, refreshUser: loadUser, can, isSuperAdmin }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
