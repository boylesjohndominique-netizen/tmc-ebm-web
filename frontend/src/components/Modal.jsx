import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { X, AlertTriangle } from 'lucide-react';

/**
 * Centered dialog rendered through a React portal to document.body.
 *
 * The portal is important: pages animate in with a CSS transform
 * (.page-content > * { animation: fade-up ... both }), and a transform on
 * any ancestor turns position:fixed descendants into being positioned
 * relative to that ancestor instead of the viewport — which previously
 * made this dialog render down at the bottom of the panel. Mounting the
 * backdrop on <body> escapes every ancestor transform/filter/overflow.
 *
 * Focus handling: callbacks are mirrored into refs so the keydown effect
 * attaches only when `open` flips. Parent components pass fresh inline
 * closures on every render (e.g. after each keystroke in a controlled
 * textarea), and an effect that re-ran per render used to re-focus the
 * dialog and steal the caret mid-typing.
 */
export default function Modal({
  open,
  title,
  onClose,
  children,
  tone = 'primary',        // 'primary' | 'danger' — colors the icon chip & header accent
  icon: Icon,              // optional lucide icon shown in the header chip
  submitLabel,             // when set, shows a sticky confirm/cancel footer
  onSubmit,
  busy = false,
  busyLabel = 'Processing…',
  disableSubmit = false,
}) {
  const cardRef = useRef(null);

  // Latest-callback refs — updated every render, never trigger effects.
  const onCloseRef = useRef(onClose);
  const busyRef = useRef(busy);
  useEffect(() => {
    onCloseRef.current = onClose;
    busyRef.current = busy;
  });

  // Attach listeners + lock scroll + initial focus, once per open/close.
  useEffect(() => {
    if (!open) return;

    const onKey = (e) => {
      if (e.key === 'Escape' && !busyRef.current) {
        onCloseRef.current();
        return;
      }

      if (e.key === 'Tab' && cardRef.current) {
        const focusables = Array.from(
          cardRef.current.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
          )
        );
        if (!focusables.length) return;

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;

        // Focus escaped the dialog (e.g. browser chrome) — pull it back in.
        if (!cardRef.current.contains(active)) {
          e.preventDefault();
          first.focus();
        } else if (e.shiftKey && active === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && active === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';

    // Land focus on the dialog container itself (tabIndex -1) so the first
    // Tab reaches the first control — but only once, on open.
    const raf = requestAnimationFrame(() => cardRef.current?.focus({ preventScroll: true }));

    return () => {
      cancelAnimationFrame(raf);
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [open]);

  if (!open) return null;

  const isDanger = tone === 'danger';

  return createPortal(
    <div
      className="modal-backdrop"
      onMouseDown={(e) => { if (e.target === e.currentTarget && !busyRef.current) onCloseRef.current(); }}
      role="dialog"
      aria-modal="true"
      aria-label={typeof title === 'string' ? title : undefined}
    >
      <div className="modal" tabIndex={-1} ref={cardRef}>
        <div className={`modal-header ${isDanger ? 'danger' : ''}`}>
          <div className="modal-heading">
            {(Icon || isDanger) && (
              <span className={`modal-icon-chip ${isDanger ? 'danger' : ''}`}>
                {isDanger ? <AlertTriangle size={18} /> : <Icon size={18} />}
              </span>
            )}
            <h3>{title}</h3>
          </div>
          <button className="close-btn" onClick={() => !busyRef.current && onCloseRef.current()} aria-label="Close dialog" disabled={busy}>
            <X size={18} />
          </button>
        </div>

        {children}

        {submitLabel && (
          <div className="modal-actions">
            <button type="button" className="btn btn-secondary" onClick={() => !busy && onClose()} disabled={busy}>
              Cancel
            </button>
            <button
              type="button"
              className={`btn ${isDanger ? 'btn-danger' : 'btn-primary'}`}
              onClick={onSubmit}
              disabled={busy || disableSubmit}
            >
              {busy ? busyLabel : submitLabel}
            </button>
          </div>
        )}
      </div>
    </div>,
    document.body
  );
}
