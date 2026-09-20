import { useForm, Head } from '@inertiajs/react';

type Table = {
  id: number; name: string; token: string; capacity: number | null;
  branch: string | null; is_active: boolean; active_orders_count: number; menu_url: string;
};

export default function Tables({ restaurant, tables }: { restaurant: { id: number; name: string }; tables: Table[] }) {
  const form = useForm({ name: '', capacity: '' });
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/tables', { preserveScroll: true, onSuccess: () => form.reset() });
  };

  return <main className="min-h-screen bg-slate-950 p-6 text-white">
    <Head title="Tables" />
    <div className="mx-auto max-w-6xl space-y-8">
      <header className="flex items-center justify-between">
        <div><p className="text-sm text-slate-400">Nexora QR</p><h1 className="text-3xl font-bold">{restaurant.name} — Tables</h1></div>
        <a href="/dashboard" className="rounded-lg border border-slate-700 px-4 py-2">Dashboard</a>
      </header>
      <form onSubmit={submit} className="grid gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 md:grid-cols-[1fr_180px_auto]">
        <input className="rounded-lg bg-slate-800 p-3" placeholder="Table name e.g. Table 1" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
        <input className="rounded-lg bg-slate-800 p-3" placeholder="Capacity" type="number" min="1" value={form.data.capacity} onChange={e => form.setData('capacity', e.target.value)} />
        <button className="rounded-lg bg-white px-5 py-3 font-semibold text-slate-900">Add table</button>
      </form>
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {tables.map(table => <article key={table.id} className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
          <div className="flex items-start justify-between"><h2 className="text-xl font-semibold">{table.name}</h2><span className="text-sm text-slate-400">{table.active_orders_count} active</span></div>
          <p className="mt-2 text-sm text-slate-400">Capacity: {table.capacity ?? '—'} · {table.branch ?? 'Main'}</p>
          <div className="mt-5 flex gap-2">
            <a className="rounded-lg bg-slate-800 px-3 py-2 text-sm" href={table.menu_url} target="_blank">Open menu</a>
            <button className="rounded-lg border border-slate-700 px-3 py-2 text-sm" onClick={() => form.post(`/tables/${table.id}/toggle`, { preserveScroll: true })}>{table.is_active ? 'Disable' : 'Enable'}</button>
          </div>
          <p className="mt-3 break-all text-xs text-slate-500">{table.menu_url}</p>
        </article>)}
      </div>
    </div>
  </main>;
}