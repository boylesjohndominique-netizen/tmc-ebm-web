import { createContext, useCallback, useContext, useRef, useState } from 'react';
import Modal from '../components/Modal';

/**
 * Promise-based confirmation dialog built on the shared <Modal>.
 *
 * Usage: const confirm = useConfirm();
 *        if (await confirm({ title, message, confirmLabel, tone })) { ... }
 *
 * Resolves `true` when the user confirms, `false` on cancel / Esc / backdrop.
 * Keeps destructive actions (delete, ban, logout, …) consistent and styled
 * instead of relying on the native window.confirm().
 */
const ConfirmContext = createContext(null);

export function ConfirmProvider({ children }) {
  const [options, setOptions] = useState(null);
  const resolver = useRef(null);

  const confirm = useCallback((opts) => new Promise(resolve => {
    resolver.current = resolve;
    setOptions({
      title: 'Are you sure?',
      message: '',
      confirmLabel: 'Confirm',
      tone: 'primary',
      ...opts,
    });
  }), []);

  const settle = useCallback(result => {
    resolver.current?.(result);
    resolver.current = null;
    setOptions(null);
  }, []);

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <Modal
        open={!!options}
        title={options?.title}
        onClose={() => settle(false)}
        tone={options?.tone || 'primary'}
        icon={options?.icon}
        submitLabel={options?.confirmLabel || 'Confirm'}
        onSubmit={() => settle(true)}
      >
        {options?.message && (
          <div className="modal-body">
            {typeof options.message === 'string' ? <p>{options.message}</p> : options.message}
          </div>
        )}
      </Modal>
    </ConfirmContext.Provider>
  );
}

export const useConfirm = () => useContext(ConfirmContext);
