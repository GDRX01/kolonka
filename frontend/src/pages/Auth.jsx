import { useState } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, MailCheck } from 'lucide-react';
import { api } from '../lib/api.js';
import { useAuth } from '../lib/auth.jsx';
import { PASSWORD_HINT, validateEmail, validateNewPassword } from '../lib/validation.js';
import { Alert, Field, Logo } from '../components/ui.jsx';
import { dashboardFor } from '../components/Guards.jsx';
import { useToast } from '../components/Toast.jsx';

function Shell({ title, subtitle, children, footer }) {
  return (
    <>
      <Link to="/" className="mb-4 inline-block"><Logo /></Link>
      <h1 className="text-4xl font-medium">{title}</h1>
      <p className="mb-6 mt-2 text-muted">{subtitle}</p>
      {children}
      <p className="mt-6 text-center text-sm text-muted">{footer}</p>
    </>
  );
}

export function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const notify = useToast();
  const from = useLocation().state?.from;
  const [f, setF] = useState({ email: '', password: '' });
  const [remember, setRemember] = useState(true);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  /** Client-side checks (the server validates again - never trust the browser alone). */
  const validate = () => {
    const e = {};
    const emailError = validateEmail(f.email);
    if (emailError) e.email = emailError;
    if (!f.password) e.password = 'Введите пароль.';
    else if (f.password.length < 8) e.password = 'Пароль должен содержать не менее 8 символов.';
    return e;
  };

  const submit = async (ev) => {
    ev.preventDefault();
    setError('');
    const local = validate();
    setErrors(local);
    if (Object.keys(local).length) return;

    setBusy(true);
    try {
      const user = await login(f.email.trim(), f.password, remember);
      notify(`Добро пожаловать, ${user.name}!`);
      nav(dashboardFor(user, from), { replace: true });
    } catch (err) {
      setError(err.first?.('email') || err.message); // e.g. "Неверный email или пароль" / "учётная запись заблокирована"
    } finally {
      setBusy(false);
    }
  };

  const cls = (k) => `input ${errors[k] ? 'input-error' : ''}`;
  return (
    <Shell title="Вход в аккаунт" subtitle="Рады видеть вас снова" footer={<>Нет аккаунта? <Link to="/register" state={{ from }} className="font-medium text-accent">Зарегистрироваться</Link></>}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Alert>{error}</Alert>
        <Field label="Электронная почта" error={errors.email}>
          <input type="email" className={cls('email')} autoComplete="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
        </Field>
        <Field label="Пароль" error={errors.password}>
          <input type="password" className={cls('password')} autoComplete="current-password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} />
        </Field>
        <div className="flex items-center justify-between gap-3">
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="h-4 w-4 accent-[#b4532a]" checked={remember} onChange={(e) => setRemember(e.target.checked)} /> Запомнить меня
          </label>
          <Link to="/password/forgot" className="text-sm font-medium text-accent hover:text-accent-hover">Забыли пароль?</Link>
        </div>
        <button className="btn-primary w-full" disabled={busy}>{busy ? 'Входим…' : 'Войти'}</button>
      </form>
    </Shell>
  );
}

export function Register() {
  const { register } = useAuth();
  const nav = useNavigate();
  const notify = useToast();
  const from = useLocation().state?.from;
  const [f, setF] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const validate = () => {
    const e = {};
    if (f.name.trim().length < 2) e.name = 'Введите имя (не менее 2 символов).';
    const emailError = validateEmail(f.email);
    if (emailError) e.email = emailError;
    const passwordError = validateNewPassword(f.password);
    if (passwordError) e.password = passwordError;
    if (f.password !== f.password_confirmation) e.password_confirmation = 'Пароли не совпадают.';
    return e;
  };

  const submit = async (ev) => {
    ev.preventDefault();
    setError('');
    const local = validate();
    setErrors(local);
    if (Object.keys(local).length) return;

    setBusy(true);
    try {
      const user = await register({ ...f, name: f.name.trim(), email: f.email.trim() });
      notify('Аккаунт создан. Добро пожаловать в Колонку!');
      nav(dashboardFor(user, from), { replace: true });
    } catch (err) {
      const server = {};
      Object.keys(err.errors || {}).forEach((k) => (server[k] = err.first(k)));
      setErrors(server);
      if (!Object.keys(server).length) setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const cls = (k) => `input ${errors[k] ? 'input-error' : ''}`;
  return (
    <Shell title="Создание аккаунта" subtitle="Займёт меньше минуты" footer={<>Уже есть аккаунт? <Link to="/login" state={{ from }} className="font-medium text-accent">Войти</Link></>}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Alert>{error}</Alert>
        <Field label="Имя" error={errors.name}><input className={cls('name')} autoComplete="name" value={f.name} onChange={set('name')} /></Field>
        <Field label="Электронная почта" error={errors.email}><input type="email" className={cls('email')} autoComplete="email" value={f.email} onChange={set('email')} /></Field>
        <Field label="Пароль" error={errors.password} hint={PASSWORD_HINT}><input type="password" className={cls('password')} autoComplete="new-password" value={f.password} onChange={set('password')} /></Field>
        <Field label="Подтверждение пароля" error={errors.password_confirmation}><input type="password" className={cls('password_confirmation')} autoComplete="new-password" value={f.password_confirmation} onChange={set('password_confirmation')} /></Field>
        <button className="btn-primary w-full" disabled={busy}>{busy ? 'Создаём…' : 'Зарегистрироваться'}</button>
        <p className="text-center text-xs text-subtle">Нажимая кнопку, вы соглашаетесь с правилами публикаций и обработкой персональных данных.</p>
      </form>
    </Shell>
  );
}

/** The "← Back" link under the password pages (a real icon, not a text arrow). */
function BackToLogin() {
  return (
    <Link to="/login" className="inline-flex items-center gap-1.5 font-medium text-accent hover:text-accent-hover">
      <ArrowLeft className="h-4 w-4" /> Назад ко входу
    </Link>
  );
}

/**
 * Step 1 of "I forgot my password": the user types the e-mail and gets a one-time link by e-mail.
 * (The link proves the mailbox is theirs; that is what makes it safe to skip the old password.)
 */
export function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [sent, setSent] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (ev) => {
    ev.preventDefault();
    const emailError = validateEmail(email);
    setError(emailError);
    if (emailError) return;

    setBusy(true);
    try {
      const r = await api('/password/forgot', { method: 'POST', body: { email: email.trim() } });
      setSent(r.message);
    } catch (err) {
      setError(err.first?.('email') || err.message);
    } finally {
      setBusy(false);
    }
  };

  if (sent) {
    return (
      <Shell title="Проверьте почту" subtitle="Осталось перейти по ссылке из письма" footer={<BackToLogin />}>
        <div className="flex items-start gap-3 rounded-lg bg-success-soft px-4 py-4 text-sm text-success">
          <MailCheck className="mt-0.5 h-5 w-5 shrink-0" />
          <div>
            <p>{sent}</p>
            <p className="mt-2 text-success/80">Ссылка действует 60 минут. Если письма нет, проверьте папку «Спам».</p>
          </div>
        </div>
      </Shell>
    );
  }

  return (
    <Shell title="Восстановление пароля" subtitle="Укажите email - мы пришлём ссылку для установки нового пароля" footer={<BackToLogin />}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Field label="Электронная почта" error={error}>
          <input type="email" className={`input ${error ? 'input-error' : ''}`} autoComplete="email" autoFocus value={email} onChange={(e) => setEmail(e.target.value)} />
        </Field>
        <button className="btn-primary w-full" disabled={busy}>{busy ? 'Отправляем…' : 'Отправить ссылку'}</button>
      </form>
    </Shell>
  );
}

/** Step 2: the page the e-mailed link opens: /password/reset?token=...&email=... */
export function ResetPassword() {
  const [params] = useSearchParams();
  const token = params.get('token') || '';
  const email = params.get('email') || '';
  const nav = useNavigate();
  const notify = useToast();
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (!token || !email) {
    return (
      <Shell title="Ссылка неполная" subtitle="Откройте ссылку из письма целиком или запросите новую" footer={<BackToLogin />}>
        <Link to="/password/forgot" className="btn-primary w-full">Запросить новую ссылку</Link>
      </Shell>
    );
  }

  const submit = async (ev) => {
    ev.preventDefault();
    const passwordError = validateNewPassword(password);
    setError(passwordError);
    if (passwordError) return;

    setBusy(true);
    try {
      await api('/password/reset', { method: 'POST', body: { email, token, password } });
      notify('Пароль изменён. Войдите с новым паролем.');
      nav('/login', { replace: true });
    } catch (err) {
      // an expired / used link comes back as an error on the "email" field
      setError(err.first?.('password') || err.first?.('email') || err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Shell title="Новый пароль" subtitle="Придумайте новый пароль для входа" footer={<BackToLogin />}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Field label="Электронная почта">
          <input type="email" className="input bg-alt text-muted" value={email} readOnly />
        </Field>
        <Field label="Новый пароль" error={error} hint={PASSWORD_HINT}>
          <input type={show ? 'text' : 'password'} className={`input ${error ? 'input-error' : ''}`} autoComplete="new-password" autoFocus value={password} onChange={(e) => setPassword(e.target.value)} />
        </Field>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="h-4 w-4 accent-[#b4532a]" checked={show} onChange={(e) => setShow(e.target.checked)} /> Показать пароль
        </label>
        <button className="btn-primary w-full" disabled={busy}>{busy ? 'Сохраняем…' : 'Сохранить пароль'}</button>
      </form>
    </Shell>
  );
}
