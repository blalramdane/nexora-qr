import { useForm, Head } from '@inertiajs/react';

type Order = {
  id: number; order_number: string; status: string; customer_name: string | null;
  customer_phone: string | null; table: string | null; items_count: number; total: string; created_at: string;
};

const statuses = [
  ['pending','جديد'],['confirmed','مؤكد'],['preparing','قيد التحضير'],['ready','جاهز'],['completed','مكتمل'],['cancelled','ملغي']
];

export default function Orders({ restaurant, orders }: { restaurant: { id:number; name:string }; orders: Order[] }) {
  const form = useForm({ status: '' });
  const update = (orderId:number, status:string) => form.post(`/orders/${orderId}/status`, { status, preserveScroll: true });
  return <main className="min-h-screen bg-slate-950 p-6 text-white">
    <Head title="Orders" />
    <div className="mx-auto max-w-7xl space-y-8">
      <header className="flex items-center justify-between">
        <div><p className="text-sm text-slate-400">Nexora QR</p><h1 className="text-3xl font-bold">{restaurant.name} — Orders</h1></div>
        <a href="/dashboard" className="rounded-lg border border-slate-700 px-4 py-2">Dashboard</a>
      </header>
      <div className="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900">
        <table className="w-full min-w-[900px] text-right">
          <thead className="border-b border-slate-800 text-sm text-slate-400"><tr>
            <th className="p-4">Order</th><th className="p-4">Customer</th><th className="p-4">Table</th><th className="p-4">Items</th><th className="p-4">Total</th><th className="p-4">Status</th><th className="p-4">Time</th>
          </tr></thead>
          <tbody>{orders.map(order => <tr key={order.id} className="border-b border-slate-800/70">
            <td className="p-4 font-semibold">{order.order_number}</td>
            <td className="p-4">{order.customer_name || 'Guest'}<div className="text-xs text-slate-500">{order.customer_phone}</div></td>
            <td className="p-4">{order.table || 'Takeaway'}</td><td className="p-4">{order.items_count}</td><td className="p-4">{order.total}</td>
            <td className="p-4"><select className="rounded-lg bg-slate-800 p-2" value={order.status} onChange={e => update(order.id, e.target.value)}>{statuses.map(([value,label]) => <option key={value} value={value}>{label}</option>)}</select></td>
            <td className="p-4 text-sm text-slate-400">{order.created_at}</td>
          </tr>)}</tbody>
        </table>
        {orders.length === 0 && <p className="p-8 text-center text-slate-400">No orders yet.</p>}
      </div>
    </div>
  </main>;
}