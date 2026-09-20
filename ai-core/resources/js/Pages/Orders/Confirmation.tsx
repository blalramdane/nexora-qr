import { Head } from '@inertiajs/react';

type Item = { name:string; quantity:number; line_total:string; modifiers:string[] };
type Order = { order_number:string; status:string; total:string; table:string|null; items:Item[] };

const labels: Record<string,string> = { pending:'جاري استقبال الطلب', confirmed:'تم تأكيد الطلب', preparing:'جاري التحضير', ready:'الطلب جاهز', completed:'تم تسليم الطلب', cancelled:'تم إلغاء الطلب' };

export default function Confirmation({ order }: { order: Order }) {
  return <main className="min-h-screen bg-slate-950 p-6 text-white">
    <Head title={`Order ${order.order_number}`} />
    <div className="mx-auto max-w-xl space-y-6">
      <div className="rounded-3xl border border-slate-800 bg-slate-900 p-7 text-center">
        <p className="text-sm text-slate-400">Nexora QR</p>
        <h1 className="mt-2 text-3xl font-bold">تم استلام طلبك</h1>
        <p className="mt-3 text-cyan-400">{order.order_number}</p>
        <p className="mt-2 text-slate-300">{labels[order.status] ?? order.status}</p>
      </div>
      <section className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <div className="space-y-4">{order.items.map((item, i) => <div key={i} className="flex justify-between gap-4 border-b border-slate-800 pb-3">
          <div><div className="font-medium">{item.quantity} × {item.name}</div>{item.modifiers.length > 0 && <div className="text-xs text-slate-500">{item.modifiers.join('، ')}</div>}</div>
          <span>{item.line_total}</span>
        </div>)}</div>
        <div className="mt-5 flex justify-between text-xl font-bold"><span>الإجمالي</span><span>{order.total}</span></div>
        {order.table && <p className="mt-2 text-sm text-slate-400">الطاولة: {order.table}</p>}
      </section>
      <a href={`/order/${order.order_number}`} className="block rounded-xl bg-white px-5 py-3 text-center font-semibold text-slate-900">متابعة حالة الطلب</a>
    </div>
  </main>;
}