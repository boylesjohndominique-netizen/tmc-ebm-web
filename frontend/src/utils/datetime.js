/**
 * Central date/time formatting — one readable style everywhere.
 *
 *   fmtDate(v)       → "Sep 22, 2026"
 *   fmtDateTime(v)   → "Sep 22, 2026, 3:45 PM"
 *   fmtDateRange(a,b)→ "Sep 22 – Sep 30, 2026"
 *
 * All helpers accept anything Date can parse (ISO strings, "YYYY-MM-DD"),
 * return "—" for empty/invalid input, and render in the viewer's local time.
 */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function parse(value) {
  if (value == null || value === '') return null;
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? null : d;
}

/** "2026-09-22" → "Sep 22, 2026" */
export function fmtDate(value) {
  const d = parse(value);
  if (!d) return '—';
  return `${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
}

/** "2026-04-01" → "Apr 2026" — chart axis labels. */
export function fmtMonth(value) {
  const d = parse(value);
  if (!d) return '—';
  return `${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

/** "2026-09-22T15:45:00" → "Sep 22, 2026, 3:45 PM" */
export function fmtDateTime(value) {
  const d = parse(value);
  if (!d) return '—';
  let h = d.getHours();
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${fmtDate(d)}, ${h}:${m} ${ampm}`;
}

/** "Sep 22, 2026" or "Sep 22 – Sep 30, 2026" (same month collapses the month) */
export function fmtDateRange(from, to) {
  const a = parse(from);
  const b = parse(to);
  if (!a && !b) return '—';
  if (a && !b) return fmtDate(a);
  if (!a && b) return fmtDate(b);
  const sameMonth = a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear();
  const left = sameMonth
    ? `${MONTHS[a.getMonth()]} ${a.getDate()}`
    : `${MONTHS[a.getMonth()]} ${a.getDate()}, ${a.getFullYear()}`;
  return `${left} – ${MONTHS[b.getMonth()]} ${b.getDate()}, ${b.getFullYear()}`;
}

/** Relative fallback for timestamps, e.g. "2 hours ago" — used for notifications. */
export function timeAgo(value) {
  const d = parse(value);
  if (!d) return '—';
  const secs = Math.floor((Date.now() - d.getTime()) / 1000);
  if (secs < 60) return 'just now';
  const mins = Math.floor(secs / 60);
  if (mins < 60) return `${mins} minute${mins === 1 ? '' : 's'} ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;
  return fmtDate(d);
}

/**
 * True when an expected-return date has passed while the request is still
 * out (status Approved). Matches the backend's overdue definition:
 * expected_return_date < today — a due date of today is not yet overdue.
 * If a status is given and it isn't "Approved", it's never overdue
 * (completed/rejected requests are no longer out).
 */
export function isOverdue(expectedReturn, status) {
  if (status && status !== 'Approved') return false;
  const d = parse(expectedReturn);
  if (!d) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return d.getTime() < today.getTime();
}

/** 12.5 → "₱12.50" — peso amounts (overdue fees, etc.). */
export function fmtCurrency(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '₱0.00';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(n);
}
