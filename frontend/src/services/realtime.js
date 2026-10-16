import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getToken } from './api';

/**
 * Laravel Echo + Reverb (WebSockets) singleton.
 *
 * Channels (see backend/routes/channels.php):
 *   private-staff        — data-change signals for every staff account
 *   private-user.{id}    — the signed-in user's notification feed
 *
 * Events are lightweight "something changed" pings (the wire payload is
 * only the resource name / ids). Clients refetch the affected endpoints,
 * so data always flows through the normal authorized API — no duplicated
 * permission logic, no stale payloads, tiny bandwidth.
 */

let echo = null;
let started = false;

const listeners = new Map(); // resource name -> Set<handler>
const stateListeners = new Set();

export const REALTIME_EVENTS = Object.freeze({
  EQUIPMENT: 'equipment',
  BORROWINGS: 'borrowings',
  RETURNS: 'returns',
  USERS: 'users',
  NOTIFICATION: 'notification',
});

const isSecureContext = window.location.protocol === 'https:';
const wsScheme = import.meta.env.VITE_REVERB_SCHEME || (isSecureContext ? 'https' : 'http');
const wsPort = Number(import.meta.env.VITE_REVERB_PORT || (isSecureContext ? 443 : 8080));

function notifyState(status) {
  stateListeners.forEach((fn) => {
    try {
      fn(status);
    } catch (err) {
      console.error('[realtime] state listener error', err);
    }
  });
}

function dispatch(resource, payload) {
  const set = listeners.get(resource);
  if (!set) return;
  set.forEach((fn) => {
    try {
      fn(payload);
    } catch (err) {
      console.error('[realtime] listener error', err);
    }
  });
}

function broadcastAuthEndpoint() {
  const base = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';
  return `${base.replace(/\/$/, '')}/broadcasting/auth`;
}

function createEcho() {
  window.Pusher = Pusher;

  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'tmc-equipment-local',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname || '127.0.0.1',
    wsPort,
    wssPort: wsPort,
    forceTLS: wsScheme === 'https',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: broadcastAuthEndpoint(),
    auth: {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    },
  });
}

function attachChannels(instance) {
  // --- staff channel: data-change signals ------------------------------
  instance.private('staff').listen('.changed', (payload) => {
    const resource = payload?.resource;
    if (resource) dispatch(resource, payload);
  });

  // --- user channel: notifications -------------------------------------
  const userId = JSON.parse(localStorage.getItem('tmc_user') || 'null')?.id;
  if (userId) {
    instance.private(`user.${userId}`).listen('.notification.created', (payload) => {
      dispatch('user:notification', payload);
    });
  }
}

/**
 * Open the WebSocket connection. Safe to call multiple times; only the
 * first call connects. No-ops when there is no auth token (login page).
 */
export function startRealtime() {
  if (started || !getToken()) return;
  started = true;
  notifyState('connecting');

  echo = createEcho();

  echo.connector.pusher.connection.bind('state_change', (states) => {
    notifyState(states.current); // connected | connecting | disconnected | ...
  });

  attachChannels(echo);
}

/**
 * Close the socket (used on logout). Page subscriptions survive so a
 * login → logout → login flow on the same mounted tree keeps working;
 * components unsubscribe themselves on unmount.
 */
export function stopRealtime() {
  if (echo) {
    try {
      echo.disconnect();
    } catch {
      /* ignore */
    }
  }
  echo = null;
  started = false;
  notifyState('disconnected');
}

/**
 * Subscribe to a resource signal ("equipment", "borrowings", "returns",
 * "users") or to personal notifications ("user:notification").
 * Returns an unsubscribe function.
 */
export function onResource(resource, handler) {
  if (!listeners.has(resource)) listeners.set(resource, new Set());
  listeners.get(resource).add(handler);
  return () => listeners.get(resource)?.delete(handler);
}

/** Subscribe to connection-state changes ("connected", "connecting", "disconnected"). */
export function onConnectionState(handler) {
  stateListeners.add(handler);
  return () => stateListeners.delete(handler);
}

/** Current connection state string (for initial render of indicators). */
export function connectionState() {
  return echo?.connector?.pusher?.connection?.state || 'disconnected';
}
