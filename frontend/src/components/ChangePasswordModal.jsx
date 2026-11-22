import { useState } from 'react';
import { api } from '../lib/api.js';
import { PASSWORD_HINT, validateNewPassword } from '../lib/validation.js';
import { Field, Modal } from './ui.jsx';
import { useToast } from './Toast.jsx';

/** Logged-in user: current password + new password. Opened from the "Edit profile" window. */
export default function ChangePasswordModal({ open, onClose }) {
  const notify = useToast();
  const [f, setF] = useState({ current_password: '', password: '' });
  const [show, setShow] = useState(false);
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev) => {
    ev.preventDefault();
    const local = {};
    if (!f.current_password) local.current_password = 'Введите текущий пароль.';
    const passwordError = validateNewPassword(f.password);
    if (passwordError) local.password = passwordError;
    else if (f.password === f.current_password) local.password = 'Новый пароль должен отличаться от текущего.';
    setErrors(local);
    if (Object.keys(local).length) return;

    setBusy(true);
    try {
      await api('/me/password', { method: 'PUT', body: f });
      notify('Пароль изменён. На других устройствах потребуется войти заново.');
      onClose();
    } catch (err) {
      const server = {};
      Object.keys(err.errors || {}).forEach((k) => (server[k] = err.first(k)));
      setErrors(Object.keys(server).length ? server : { current_password: err.message });
    } finally {
      setBusy(false);
    }
  };

  const type = show ? 'text' : 'password';
  return (
    <Modal open={open} onClose={onClose} title="Смена пароля">
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Field label="Текущий пароль" error={errors.current_password}>
          <input type={type} className={`input ${errors.current_password ? 'input-error' : ''}`} autoComplete="current-password" autoFocus value={f.current_password} onChange={set('current_password')} />
        </Field>
        <Field label="Новый пароль" error={errors.password} hint={PASSWORD_HINT}>
          <input type={type} className={`input ${errors.password ? 'input-error' : ''}`} autoComplete="new-password" value={f.password} onChange={set('password')} />
        </Field>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="h-4 w-4 accent-[#b4532a]" checked={show} onChange={(e) => setShow(e.target.checked)} /> Показать пароли
        </label>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn-ghost" onClick={onClose}>Отмена</button>
          <button className="btn-primary" disabled={busy}>{busy ? 'Сохраняем…' : 'Сменить пароль'}</button>
        </div>
      </form>
    </Modal>
  );
}
