import { Wifi, WifiOff } from 'lucide-react';
import useRealtimeState from '../hooks/useRealtimeState';

/**
 * Small navbar chip showing the WebSocket connection state.
 * Green "Live" while connected; hidden entirely when disconnected so the
 * common case stays clean (fallback refetching still keeps data fresh).
 */
export default function ConnectionBadge() {
  const state = useRealtimeState();

  if (state === 'connected') {
    return (
      <span className="conn-badge live" title="Realtime updates connected">
        <Wifi size={13} /> Live
      </span>
    );
  }

  if (state === 'connecting') {
    return (
      <span className="conn-badge connecting" title="Connecting to realtime updates…">
        <Wifi size={13} /> Connecting…
      </span>
    );
  }

  return (
    <span className="conn-badge offline" title="Realtime updates offline — data refreshes on focus">
      <WifiOff size={13} /> Offline
    </span>
  );
}
