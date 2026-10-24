import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import api, { getToken } from '../services/api';
import { onResource } from '../services/realtime';

const NotificationContext = createContext(null);

export function NotificationProvider({ children }) {
  const [unread, setUnread] = useState(0);
  const [lastEventId, setLastEventId] = useState(null); // lets the inbox page react to pings
  const token = useRef(getToken());

  const fetchCount = useCallback(async () => {
    if (!getToken()) return;
    try {
      const { data } = await api.get('/notifications/unread-count');
      setUnread(data.count);
    } catch {
      // ignore transient errors
    }
  }, []);

  const markAllReadLocally = useCallback(() => setUnread(0), []);

  useEffect(() => {
    token.current = getToken();
    if (!token.current) return undefined;

    fetchCount();

    // Realtime: the backend pushes a ping whenever a notification is
    // created for this user (NotificationObserver -> private-user.{id}).
    const unsub = onResource('user:notification', () => {
      setLastEventId(Date.now());
      fetchCount();
    });

    // Fallback poll — very slow, only catches anything a dropped socket
    // might have missed while the tab was open (reconnect + focus already
    // cover the rest).
    const interval = setInterval(fetchCount, 60000);

    const onAuthChanged = () => {
      if (!getToken()) setUnread(0);
    };
    window.addEventListener('tmc:auth-changed', onAuthChanged);

    return () => {
      unsub();
      clearInterval(interval);
      window.removeEventListener('tmc:auth-changed', onAuthChanged);
    };
  }, [fetchCount]);

  const value = useMemo(() => ({
    unread,
    refresh: fetchCount,
    markAllReadLocally,
    lastEventId,
  }), [unread, fetchCount, markAllReadLocally, lastEventId]);

  return (
    <NotificationContext.Provider value={value}>
      {children}
    </NotificationContext.Provider>
  );
}

export const useNotifications = () => useContext(NotificationContext);
