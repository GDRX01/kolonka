import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { CircleAlert, TriangleAlert } from 'lucide-react';

const ConfirmContext = createContext(async () => false);
const AlertContext = createContext(async () => { });

/**
 * Замена window.confirm() и window.alert() в стиле системы.
 *   const confirm = useConfirm();
 *   if (!(await confirm({ title: 'Удалить?', text: 'Действие нельзя отменить.' }))) return;
 */
export const useConfirm = () => useContext(ConfirmContext);

/**
 * Замена window.alert(): окно с одной кнопкой.
 *   const showAlert = useAlert();
 *   await showAlert({ title: 'Не удалось поставить лайк', text: error.message });
 */
export const useAlert = () => useContext(AlertContext);

export function ConfirmProvider({ children }) {
  const [dialog, setDialog] = useState(null); // { kind: 'confirm' | 'alert', title, text, confirmLabel, cancelLabel, resolve }
  const cancelRef = useRef(null);

  const confirm = useCallback((options) => new Promise((resolve) => {
    setDialog((prev) => {
      prev?.resolve(false); // если окно уже открыто - предыдущий вызов считается отменой
      return { kind: 'confirm', confirmLabel: 'Удалить', cancelLabel: 'Отмена', ...options, resolve };
    });
  }), []);

  // one-button window instead of window.alert()
  const showAlert = useCallback((options) => new Promise((resolve) => {
    setDialog((prev) => {
      prev?.resolve(false);
      return { kind: 'alert', title: 'Что-то пошло не так', confirmLabel: 'Понятно', ...options, resolve };
    });
  }), []);

  const close = useCallback((result) => {
    setDialog((d) => { d?.resolve(result); return null; });
  }, []);

  useEffect(() => {
    if (!dialog) return;
    const onKey = (e) => e.key === 'Escape' && close(false);
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    cancelRef.current?.focus(); // для деструктивного действия фокус на безопасной кнопке
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [dialog, close]);

  return (
    <ConfirmContext.Provider value={confirm}>
      <AlertContext.Provider value={showAlert}>
        {children}
        {dialog && (
          <div className="fixed inset-0 z-[70] flex items-center justify-center bg-ink/40 p-4" onMouseDown={() => close(false)}>
            <div
              role="alertdialog"
              aria-modal="true"
              aria-labelledby="confirm-title"
              aria-describedby={dialog.text ? 'confirm-text' : undefined}
              className="flex w-full max-w-[460px] flex-col gap-4 rounded-xl border border-line bg-surface p-7 shadow-[0_16px_48px_rgba(26,20,13,0.16)]"
              onMouseDown={(e) => e.stopPropagation()}
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-full bg-danger-soft text-danger">
                {dialog.kind === 'alert'
                  ? <CircleAlert className="h-[22px] w-[22px]" strokeWidth={1.6} />
                  : <TriangleAlert className="h-[22px] w-[22px]" strokeWidth={1.6} />}
              </span>
              <h2 id="confirm-title" className="text-xl font-semibold leading-7">{dialog.title}</h2>
              {dialog.text && <p id="confirm-text" className="text-sm leading-5 text-muted">{dialog.text}</p>}
              <div className="flex justify-end gap-3">
                {dialog.kind === 'alert' ? (
                  <button ref={cancelRef} className="btn-primary" onClick={() => close(true)}>{dialog.confirmLabel}</button>
                ) : (
                  <>
                    <button ref={cancelRef} className="btn-ghost" onClick={() => close(false)}>{dialog.cancelLabel}</button>
                    <button className="btn-danger" onClick={() => close(true)}>{dialog.confirmLabel}</button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </AlertContext.Provider>
    </ConfirmContext.Provider>
  );
}
