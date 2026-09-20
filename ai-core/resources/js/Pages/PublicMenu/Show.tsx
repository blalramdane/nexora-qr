import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Modifier = { id:number; name:string; price_delta:string };
type ModifierGroup = { id:number; name:string; min_selections:number; max_selections:number; is_required:boolean; modifiers:Modifier[] };
type Product = { id:number; name:string; description:string|null; price:string; image_path:string|null; modifier_groups:ModifierGroup[] };
type Category = { id:number; name:string; products:Product[] };
type CartItem = { product:Product; quantity:number; modifier_ids:number[]; notes:string };

export default function Show({
  restaurant, categories, table,
}: {
  restaurant:{name:string;slug:string;phone:string|null};
  categories:Category[];
  table:{name:string;token:string}|null;
}) {
  const [cart, setCart] = useState<CartItem[]>([]);
  const [selectedProduct, setSelectedProduct] = useState<Product|null>(null);
  const [selectedModifiers, setSelectedModifiers] = useState<number[]>([]);
  const [notes, setNotes] = useState('');
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const form = useForm({ items: [] as any[], customer_name:'', customer_phone:'', notes:'', table_token:table?.token ?? '', idempotency_key: crypto.randomUUID() });

  const total = useMemo(() => cart.reduce((sum,item) => {
    const modifierTotal = item.product.modifier_groups.flatMap(g => g.modifiers).filter(m => item.modifier_ids.includes(m.id)).reduce((s,m) => s + Number(m.price_delta), 0);
    return sum + (Number(item.product.price) + modifierTotal) * item.quantity;
  }, 0), [cart]);

  const addProduct = (product:Product) => {
    setSelectedProduct(product);
    setSelectedModifiers([]);
    setNotes('');
  };

  const confirmAdd = () => {
    if (!selectedProduct) return;
    for (const group of selectedProduct.modifier_groups) {
      const count = group.modifiers.filter(m => selectedModifiers.includes(m.id)).length;
      if (group.is_required && count < Math.max(1, group.min_selections)) return alert(`اختر اختيارًا من: ${group.name}`);
      if (count < group.min_selections || count > group.max_selections) return alert(`اختيارات ${group.name} يجب أن تكون من ${group.min_selections} إلى ${group.max_selections}`);
    }
    setCart(items => [...items, { product:selectedProduct, quantity:1, modifier_ids:[...selectedModifiers], notes }]);
    setSelectedProduct(null);
  };

  const submitOrder = () => {
    form.setData('items', cart.map(item => ({ product_id:item.product.id, quantity:item.quantity, modifier_ids:item.modifier_ids, notes:item.notes })));
    form.post('/orders', { preserveScroll:true });
  };

  return <>
    <Head title={restaurant.name} />
    <main className="min-h-screen bg-slate-950 text-white">
      <header className="sticky top-0 z-20 border-b border-slate-800 bg-slate-950/95 px-5 py-6 backdrop-blur">
        <div className="mx-auto flex max-w-4xl items-center justify-between gap-4">
          <div><p className="text-sm text-cyan-400">Nexora QR</p><h1 className="mt-1 text-2xl font-bold">{restaurant.name}</h1>{table && <p className="mt-1 text-sm text-slate-400">طاولة: {table.name}</p>}</div>
          <button onClick={() => setCheckoutOpen(true)} disabled={!cart.length} className="rounded-xl bg-white px-4 py-3 font-semibold text-slate-900 disabled:opacity-40">السلة ({cart.reduce((s,i)=>s+i.quantity,0)}) · {total.toFixed(2)}</button>
        </div>
      </header>

      <div className="mx-auto max-w-4xl space-y-10 px-5 py-8">
        {categories.map(category => <section key={category.id}>
          <h2 className="mb-4 text-2xl font-bold">{category.name}</h2>
          <div className="grid gap-4 md:grid-cols-2">
            {category.products.map(product => <article key={product.id} className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
              <div className="flex gap-4">
                {product.image_path && <img src={product.image_path} alt="" className="h-20 w-20 rounded-xl object-cover" />}
                <div className="min-w-0 flex-1"><h3 className="font-semibold">{product.name}</h3>{product.description && <p className="mt-1 text-sm text-slate-400">{product.description}</p>}<p className="mt-3 font-bold text-cyan-400">{product.price}</p></div>
              </div>
              <button onClick={() => addProduct(product)} className="mt-4 w-full rounded-xl bg-slate-800 px-4 py-3 font-semibold hover:bg-slate-700">إضافة للطلب</button>
            </article>)}
          </div>
        </section>)}
        {!categories.length && <p className="py-20 text-center text-slate-400">القائمة قيد التجهيز.</p>}
      </div>
    </main>

    {selectedProduct && <div className="fixed inset-0 z-30 overflow-y-auto bg-black/70 p-4">
      <div className="mx-auto mt-10 max-w-xl rounded-3xl border border-slate-700 bg-slate-900 p-6">
        <div className="flex items-center justify-between"><h2 className="text-2xl font-bold">{selectedProduct.name}</h2><button onClick={()=>setSelectedProduct(null)}>✕</button></div>
        <div className="mt-6 space-y-6">{selectedProduct.modifier_groups.map(group => <div key={group.id}>
          <h3 className="font-semibold">{group.name} {group.is_required && <span className="text-red-400">*</span>}<span className="mr-2 text-xs text-slate-500">({group.min_selections}-{group.max_selections})</span></h3>
          <div className="mt-3 space-y-2">{group.modifiers.map(mod => <label key={mod.id} className="flex cursor-pointer items-center justify-between rounded-xl bg-slate-800 p-3">
            <span><input type="checkbox" className="ml-3" checked={selectedModifiers.includes(mod.id)} onChange={() => setSelectedModifiers(ids => ids.includes(mod.id) ? ids.filter(id=>id!==mod.id) : [...ids,mod.id])}/>{mod.name}</span><span>+{mod.price_delta}</span>
          </label>)}</div>
        </div>)}</div>
        <textarea className="mt-6 w-full rounded-xl bg-slate-800 p-3" placeholder="ملاحظات على المنتج (اختياري)" value={notes} onChange={e=>setNotes(e.target.value)} />
        <button onClick={confirmAdd} className="mt-4 w-full rounded-xl bg-white px-5 py-3 font-semibold text-slate-900">إضافة للسلة</button>
      </div>
    </div>}

    {checkoutOpen && <div className="fixed inset-0 z-40 overflow-y-auto bg-black/70 p-4">
      <div className="mx-auto mt-8 max-w-xl rounded-3xl border border-slate-700 bg-slate-900 p-6">
        <div className="flex items-center justify-between"><h2 className="text-2xl font-bold">تأكيد الطلب</h2><button onClick={()=>setCheckoutOpen(false)}>✕</button></div>
        <div className="mt-5 space-y-3">{cart.map((item,i)=><div key={i} className="flex justify-between rounded-xl bg-slate-800 p-3"><span>{item.quantity} × {item.product.name}</span><span>{(Number(item.product.price) + item.product.modifier_groups.flatMap(g=>g.modifiers).filter(m=>item.modifier_ids.includes(m.id)).reduce((s,m)=>s+Number(m.price_delta),0)).toFixed(2)}</span></div>)}</div>
        <input className="mt-5 w-full rounded-xl bg-slate-800 p-3" placeholder="الاسم (اختياري)" value={form.data.customer_name} onChange={e=>form.setData('customer_name',e.target.value)} />
        <input className="mt-3 w-full rounded-xl bg-slate-800 p-3" placeholder="رقم الهاتف (اختياري)" value={form.data.customer_phone} onChange={e=>form.setData('customer_phone',e.target.value)} />
        <textarea className="mt-3 w-full rounded-xl bg-slate-800 p-3" placeholder="ملاحظات على الطلب" value={form.data.notes} onChange={e=>form.setData('notes',e.target.value)} />
        <div className="mt-5 flex items-center justify-between text-xl font-bold"><span>الإجمالي</span><span>{total.toFixed(2)}</span></div>
        <button onClick={submitOrder} disabled={form.processing} className="mt-5 w-full rounded-xl bg-white px-5 py-3 font-semibold text-slate-900 disabled:opacity-50">إرسال الطلب</button>
      </div>
    </div>}
  </>;
}