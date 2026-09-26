import { useEffect, useRef } from 'react';
import { onConnectionState, onResource } from '../services/realtime';

/**
 * Refetch `loader` whenever:
 *   1. a realtime WebSocket signal arrives for one of the given resources,
 *   2. the browser tab regains focus, or
 *   3. the WebSocket reconnects after a drop.
 *
 * A short trailing throttle coalesces bursts (e.g. one admin action that
 * fires equipment + borrowings + users signals at once) into a single
 * fetch, and skips work when the tab is hidden.
 *
 * @param {string[]} resources  realtime resource names to listen to
 * @param {() => Promise|void} loader  refetch function (should setState)
 * @param {{enabled?: boolean}} [options]
 */
export default function useRealtimeResource(resources, loader, { enabled = true } = {}) {
  const loaderRef = useRef(loader);
  loaderRef.current = loader;
  const timerRef = useRef(null);
  const lastRunRef = useRef(0);

  useEffect(() => {
    if (!enabled || !resources.length) return undefined;

    const THROTTLE_MS = 1500;

    const run = () => {
      const now = Date.now();
      if (now - lastRunRef.current < THROTTLE_MS) {
        // inside the throttle window: schedule one trailing run so the
        // final update of a burst is never lost
        if (timerRef.current) return;
        timerRef.current = setTimeout(() => {
          timerRef.current = null;
          lastRunRef.current = Date.now();
          if (document.visibilityState !== 'hidden') {
            Promise.resolve(loaderRef.current()).catch(() => {});
          }
        }, THROTTLE_MS);
        return;
      }
      lastRunRef.current = now;
      if (document.visibilityState !== 'hidden') {
        Promise.resolve(loaderRef.current()).catch(() => {});
      }
    };

    const unsubs = resources.map((r) => onResource(r, run));

    const onFocus = () => {
      if (document.visibilityState === 'visible') run();
    };
    document.addEventListener('visibilitychange', onFocus);
    window.addEventListener('focus', onFocus);

    const unsubState = onConnectionState((state) => {
      if (state === 'connected') run();
    });

    return () => {
      unsubs.forEach((u) => u());
      document.removeEventListener('visibilitychange', onFocus);
      window.removeEventListener('focus', onFocus);
      unsubState();
      if (timerRef.current) clearTimeout(timerRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, resources.join('|')]);
}
