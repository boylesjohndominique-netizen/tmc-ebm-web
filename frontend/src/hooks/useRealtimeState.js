import { useEffect, useState } from 'react';
import { connectionState, onConnectionState } from '../services/realtime';

/** Live WebSocket connection state: 'connected' | 'connecting' | 'disconnected'. */
export default function useRealtimeState() {
  const [state, setState] = useState(connectionState);

  useEffect(() => {
    const unsub = onConnectionState(setState);
    setState(connectionState());
    return unsub;
  }, []);

  return state;
}
