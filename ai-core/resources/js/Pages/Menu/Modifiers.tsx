import { useForm, Head } from '@inertiajs/react';

type Modifier = { id:number; name:string; price_delta:string };
type Group = { id:number; name:string; min_selections:number; max_selections:number; is_required:boolean; modifiers:Modifier[] };
type Product = { id:number; name:string };

export default function Modifiers({ restaurant, groups, products }: { restaurant:{id:number;name:string}; groups:Group[]; products:Product[] }) {
  const groupForm = useForm({ name:'', min_selections:0, max_selections:1, is_required:false });
  const modifierForm = useForm({ modifier_group_id:'', name:'', price_delta:'0' });
  const attachForm = useForm({ product_id:'', modifier_group_id:'' });
  const submit = (form:any, url:string) => (e:React.FormEvent) => { e.preventDefault(); form.post(url,{preserveScroll:true,onSuccess:()=>form.reset()}); };

  return <main className="min-h-screen bg-slate-950 p-6 text-white">
    <Head title="Modifiers" />
    <div className="mx-auto max-w-6xl space-y-8">
      <header className="flex items-center justify-between"><div><p className="text-sm text-slate-400">Nexora QR</p><h1 className="text-3xl font-bold">{restaurant.name} — Modifiers</h1></div><a href="/menu" className="rounded-lg border border-slate-700 px-4 py-2">Menu</a></header>
      <div className="grid gap-5 md:grid-cols-3">
        <form onSubmit={submit(groupForm,'/menu/modifier-groups')} className="rounded-2xl bg-slate-900 p-5 space-y-3">
          <h2 className="text-xl font-semibold">Modifier group</h2>
          <input className="w-full rounded-lg bg-slate-800 p-3" placeholder="e.g. Sauces" value={groupForm.data.name} onChange={e=>groupForm.setData('name',e.target.value)} />
          <div className="grid grid-cols-2 gap-2"><input className="rounded-lg bg-slate-800 p-3" type="number" min="0" value={groupForm.data.min_selections} onChange={e=>groupForm.setData('min_selections',Number(e.target.value))}/><input className="rounded-lg bg-slate-800 p-3" type="number" min="1" value={groupForm.data.max_selections} onChange={e=>groupForm.setData('max_selections',Number(e.target.value))}/></div>
          <label className="flex gap-2"><input type="checkbox" checked={groupForm.data.is_required} onChange={e=>groupForm.setData('is_required',e.target.checked)}/> Required</label>
          <button className="w-full rounded-lg bg-white p-3 font-semibold text-slate-900">Create group</button>
        </form>
        <form onSubmit={submit(modifierForm,'/menu/modifiers')} className="rounded-2xl bg-slate-900 p-5 space-y-3">
          <h2 className="text-xl font-semibold">Modifier</h2>
          <select className="w-full rounded-lg bg-slate-800 p-3" value={modifierForm.data.modifier_group_id} onChange={e=>modifierForm.setData('modifier_group_id',e.target.value)}><option value="">Select group</option>{groups.map(g=><option key={g.id} value={g.id}>{g.name}</option>)}</select>
          <input className="w-full rounded-lg bg-slate-800 p-3" placeholder="Modifier name" value={modifierForm.data.name} onChange={e=>modifierForm.setData('name',e.target.value)}/>
          <input className="w-full rounded-lg bg-slate-800 p-3" type="number" min="0" step="0.01" placeholder="Extra price" value={modifierForm.data.price_delta} onChange={e=>modifierForm.setData('price_delta',e.target.value)}/>
          <button className="w-full rounded-lg bg-white p-3 font-semibold text-slate-900">Create modifier</button>
        </form>
        <form onSubmit={submit(attachForm,'/menu/modifier-groups/attach')} className="rounded-2xl bg-slate-900 p-5 space-y-3">
          <h2 className="text-xl font-semibold">Attach to product</h2>
          <select className="w-full rounded-lg bg-slate-800 p-3" value={attachForm.data.product_id} onChange={e=>attachForm.setData('product_id',e.target.value)}><option value="">Select product</option>{products.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select>
          <select className="w-full rounded-lg bg-slate-800 p-3" value={attachForm.data.modifier_group_id} onChange={e=>attachForm.setData('modifier_group_id',e.target.value)}><option value="">Select group</option>{groups.map(g=><option key={g.id} value={g.id}>{g.name}</option>)}</select>
          <button className="w-full rounded-lg bg-white p-3 font-semibold text-slate-900">Attach</button>
        </form>
      </div>
      <div className="grid gap-4 md:grid-cols-2">{groups.map(group=><div key={group.id} className="rounded-2xl bg-slate-900 p-5"><div className="flex justify-between"><h2 className="text-xl font-semibold">{group.name}</h2><span className="text-sm text-slate-500">{group.min_selections}-{group.max_selections}</span></div><div className="mt-4 space-y-2">{group.modifiers.map(m=><div key={m.id} className="flex justify-between rounded-lg bg-slate-800 p-3"><span>{m.name}</span><span>+{m.price_delta}</span></div>)}</div></div>)}</div>
    </div>
  </main>;
}