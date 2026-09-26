import { useEffect, useState } from 'react';

/**
 * Debounce a fast-changing value (e.g. a search input).
 * Returns a value that only updates after `delay` ms without changes,
 * so expensive re-filtering doesn't run on every keystroke.
 */
export default function useDebouncedValue(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value, delay]);

  return debounced;
}
