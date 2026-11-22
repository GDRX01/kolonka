import { useState } from 'react';
import { Check, Pencil, Search, Trash2, X } from 'lucide-react';
import { api } from '../lib/api.js';
import { useDebounced, useFetch } from '../lib/hooks.js';
import { Alert, Empty, ErrorBox, Modal, Spinner } from '../components/ui.jsx';
import { useConfirm } from '../components/Confirm.jsx';
import { formatNumber, plural } from '../lib/format.js';

/** Tell the admin layout to refresh the counters in the sidebar. */
const refreshBadges = () => window.dispatchEvent(new Event('admin:refresh-badges'));

/** Pencil button + a small "rename" window (used for categories and tags). */
function RenameButton({ item, onSave }) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState(item.name);

  const show = () => { setName(item.name); setOpen(true); };
  const submit = async (e) => {
    e.preventDefault();
    if (await onSave(item, name)) setOpen(false);
  };

  return (
    <>
      <button className="icon-btn" onClick={show} aria-label="Переименовать" title="Переименовать"><Pencil className="h-4 w-4" /></button>
      <Modal open={open} onClose={() => setOpen(false)} title="Переименовать">
        <form onSubmit={submit} className="space-y-4">
          <input autoFocus className="input" value={name} onChange={(e) => setName(e.target.value)} aria-label="Новое название" />
          <div className="flex justify-end gap-2">
            <button type="button" className="btn-ghost" onClick={() => setOpen(false)}>Отмена</button>
            <button className="btn-primary">Сохранить</button>
          </div>
        </form>
      </Modal>
    </>
  );
}

// ------------------------------------------------------------------ categories

function CategoriesPanel({ items, reload, setMsg }) {
  const [name, setName] = useState('');
  const confirm = useConfirm();

  const add = async () => {
    if (!name.trim()) return;
    try { await api('/admin/categories', { method: 'POST', body: { name } }); setName(''); setMsg({ type: 'success', text: 'Категория добавлена.' }); reload(); }
    catch (e) { setMsg({ type: 'error', text: e.first?.('name') || e.message }); }
  };
  const save = async (item, newName) => {
    try { await api(`/admin/categories/${item.id}`, { method: 'PUT', body: { name: newName } }); setMsg({ type: 'success', text: 'Сохранено.' }); reload(); return true; }
    catch (e) { setMsg({ type: 'error', text: e.first?.('name') || e.message }); return false; }
  };
  const del = async (item) => {
    if (!(await confirm({ title: `Удалить категорию «${item.name}»?`, text: 'Действие нельзя отменить.' }))) return;
    try { await api(`/admin/categories/${item.id}`, { method: 'DELETE' }); setMsg({ type: 'success', text: 'Удалено.' }); reload(); }
    catch (e) { setMsg({ type: 'error', text: e.message }); }
  };

  return (
    <section className="card overflow-hidden">
      <div className="space-y-4 p-5">
        <h2 className="text-xl font-medium">Категории <span className="ml-1 text-xs font-normal text-subtle">{items.length} {plural(items.length, ['категория', 'категории', 'категорий'])}</span></h2>
        <p className="text-xs text-subtle">Категории добавляет только администратор.</p>
        <div className="flex gap-3">
          <input className="input" placeholder="Название новой категории" value={name} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && add()} />
          <button className="btn-primary" onClick={add}>Добавить</button>
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[420px] text-left text-sm">
          <thead className="table-head"><tr>
            <th className="px-5 py-3 font-semibold">Название</th><th className="px-5 py-3 font-semibold">Slug</th><th className="px-5 py-3 font-semibold">Публикаций</th><th />
          </tr></thead>
          <tbody>
            {items.map((it) => (
              <tr key={it.id} className="border-t border-line">
                <td className="px-5 py-3">{it.name}</td>
                <td className="px-5 py-3 text-muted">{it.slug}</td>
                <td className="px-5 py-3">{formatNumber(it.posts_count)}</td>
                <td className="px-5 py-3">
                  <div className="flex justify-end gap-2">
                    <RenameButton item={it} onSave={save} />
                    <button className="icon-btn-danger" onClick={() => del(it)} aria-label="Удалить"><Trash2 className="h-4 w-4" /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ tags

function TagRow({ tag, onSave, onApprove, onDelete }) {
  const pending = tag.status === 'pending';
  return (
    <tr className={`border-t border-line ${pending ? 'bg-warning-soft/40' : ''}`}>
      <td className="px-5 py-3">
        <span className="flex flex-wrap items-center gap-2">
          <span className="chip">#{tag.name}</span>
          {pending && <span className="badge bg-warning-soft text-warning">На модерации</span>}
          {pending && tag.created_by && <span className="text-xs text-subtle">предложил(а): {tag.created_by.name}</span>}
        </span>
      </td>
      <td className="px-5 py-3">{formatNumber(tag.posts_count)}</td>
      <td className="px-5 py-3">
        <div className="flex justify-end gap-2">
          {pending && (
            <button className="icon-btn text-success hover:bg-success-soft hover:text-success" onClick={() => onApprove(tag)} aria-label="Одобрить" title="Одобрить тег">
              <Check className="h-4 w-4" />
            </button>
          )}
          <RenameButton item={tag} onSave={onSave} />
          <button className="icon-btn-danger" onClick={() => onDelete(tag)} aria-label={pending ? 'Отклонить' : 'Удалить'} title={pending ? 'Отклонить и удалить тег' : 'Удалить тег'}>
            {pending ? <X className="h-4 w-4" /> : <Trash2 className="h-4 w-4" />}
          </button>
        </div>
      </td>
    </tr>
  );
}

function TagsPanel({ setMsg }) {
  const [text, setText] = useState('');
  const [status, setStatus] = useState('');   // '' = all tags, 'pending' = waiting for moderation
  const [all, setAll] = useState(false);
  const [name, setName] = useState('');
  const confirm = useConfirm();
  const q = useDebounced(text, 300);

  const limit = q ? 100 : all ? 500 : 8;
  const res = useFetch(() => api('/admin/tags', { params: { q, status, limit } }), [q, status, limit]);

  const changed = () => { res.reload(); refreshBadges(); };

  const add = async () => {
    if (!name.trim()) return;
    try { await api('/admin/tags', { method: 'POST', body: { name } }); setName(''); setMsg({ type: 'success', text: 'Тег добавлен.' }); changed(); }
    catch (e) { setMsg({ type: 'error', text: e.first?.('name') || e.message }); }
  };
  const save = async (tag, newName) => {
    try { await api(`/admin/tags/${tag.id}`, { method: 'PUT', body: { name: newName } }); setMsg({ type: 'success', text: 'Сохранено.' }); changed(); return true; }
    catch (e) { setMsg({ type: 'error', text: e.first?.('name') || e.message }); return false; }
  };
  const approve = async (tag) => {
    try { await api(`/admin/tags/${tag.id}/approve`, { method: 'PATCH' }); setMsg({ type: 'success', text: `Тег «#${tag.name}» одобрен и теперь виден всем.` }); changed(); }
    catch (e) { setMsg({ type: 'error', text: e.message }); }
  };
  const del = async (tag) => {
    const pending = tag.status === 'pending';
    const n = tag.posts_count || 0;
    const ok = await confirm({
      title: `${pending ? 'Отклонить' : 'Удалить'} тег «#${tag.name}»?`,
      text: n ? `Тег снимется с ${formatNumber(n)} ${plural(n, ['публикации', 'публикаций', 'публикаций'])}. Действие нельзя отменить.` : 'Действие нельзя отменить.',
      confirmLabel: pending ? 'Отклонить' : 'Удалить',
    });
    if (!ok) return;
    try { await api(`/admin/tags/${tag.id}`, { method: 'DELETE' }); setMsg({ type: 'success', text: pending ? 'Тег отклонён.' : 'Тег удалён.' }); changed(); }
    catch (e) { setMsg({ type: 'error', text: e.message }); }
  };

  const data = res.data;
  const pendingTotal = data?.pending_total ?? 0;
  const tab = (key, label, badge = 0) => (
    <button onClick={() => { setStatus(key); setAll(false); }} aria-pressed={status === key}
      className={`rounded-full px-3 py-1.5 text-sm font-medium ${status === key ? 'bg-ink text-white' : 'bg-alt text-muted hover:text-ink'}`}>
      {label}
      {badge > 0 && <span className={`ml-1.5 rounded-full px-1.5 py-0.5 text-[11px] ${status === key ? 'bg-white/20' : 'bg-warning-soft text-warning'}`}>{badge}</span>}
    </button>
  );

  return (
    <section className="card overflow-hidden">
      <div className="space-y-4 p-5">
        <h2 className="text-xl font-medium">Теги <span className="ml-1 text-xs font-normal text-subtle">{data ? `${data.total} ${plural(data.total, ['тег', 'тега', 'тегов'])}` : ''}</span></h2>
        <p className="text-xs text-subtle">Новые теги от пользователей попадают в «На модерации» и видны всем только после вашего одобрения.</p>

        <div className="flex gap-3">
          <input className="input" placeholder="Новый тег, например #docker" value={name} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && add()} />
          <button className="btn-primary" onClick={add}>Добавить</button>
        </div>

        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
          <input className="input pl-9 pr-9" placeholder="Поиск по тегам" aria-label="Поиск по тегам" value={text} onChange={(e) => setText(e.target.value)} />
          {text && <button className="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink" onClick={() => setText('')} aria-label="Очистить поиск"><X className="h-4 w-4" /></button>}
        </div>

        <div className="flex flex-wrap gap-2">
          {tab('', 'Все')}
          {tab('pending', 'На модерации', pendingTotal)}
        </div>
      </div>

      {res.error ? <div className="p-5"><ErrorBox error={res.error} onRetry={res.reload} /></div>
        : !data ? <Spinner />
        : data.data.length === 0 ? <Empty title={q ? 'Ничего не найдено' : status === 'pending' ? 'Нет тегов на модерации' : 'Тегов пока нет'}>{q ? `По запросу «${q}» тегов нет.` : ''}</Empty>
        : (
          <div className={`overflow-x-auto ${res.loading ? 'opacity-60' : ''}`}>
            <table className="w-full min-w-[420px] text-left text-sm">
              <thead className="table-head"><tr><th className="px-5 py-3 font-semibold">Тег</th><th className="px-5 py-3 font-semibold">Публикаций</th><th /></tr></thead>
              <tbody>{data.data.map((t) => <TagRow key={t.id} tag={t} onSave={save} onApprove={approve} onDelete={del} />)}</tbody>
            </table>
          </div>
        )}

      {data && !q && data.matched > 8 && (
        <button className="w-full border-t border-line py-3 text-sm font-medium text-accent hover:bg-alt" onClick={() => setAll(!all)}>
          {all ? 'Свернуть' : `Показать все ${data.matched} ${plural(data.matched, ['тег', 'тега', 'тегов'])}`}
        </button>
      )}
      {data && q && <p className="border-t border-line px-5 py-3 text-xs text-subtle">Найдено: {data.matched}</p>}
    </section>
  );
}

export default function Taxonomy() {
  const [msg, setMsg] = useState({ type: 'success', text: '' });
  const cats = useFetch(() => api('/admin/categories'), []);

  if (cats.error) return <ErrorBox error={cats.error} onRetry={cats.reload} />;
  if (!cats.data) return <Spinner />;

  return (
    <div className="space-y-5">
      <Alert type={msg.type}>{msg.text}</Alert>
      <div className="grid items-start gap-6 xl:grid-cols-2">
        <CategoriesPanel items={cats.data.data} reload={cats.reload} setMsg={setMsg} />
        <TagsPanel setMsg={setMsg} />
      </div>
    </div>
  );
}
