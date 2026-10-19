import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';

type OrderItem = { id: number; product_name: string; quantity: number; variant_name?: string | null; modifiers: string[] };
type Order = {
  id: number;
  order_number: string;
  status: string;
  items_count: number;
  created_at: string;
  fulfillment_type: 'pickup' | 'delivery' | 'dine_in';
  customer_name: string;
  restaurant_table_name?: string | null;
  notes?: string | null;
  items: OrderItem[];
};

type Props = { ordersBaseUrl: string };

const COLUMNS = [
  { key: 'new', label: '🆕 جديدة', color: 'bg-blue-50' },
  { key: 'confirmed', label: '✅ مؤكدة', color: 'bg-indigo-50' },
  { key: 'preparing', label: '🍳 قيد التحضير', color: 'bg-amber-50' },
  { key: 'ready', label: '📦 جاهزة', color: 'bg-emerald-50' },
];
const nextStatus: Record<string, string> = { new: 'confirmed', confirmed: 'preparing', preparing: 'ready', ready: 'completed' };
const nextStatusLabels: Record<string, string> = { confirmed: 'تأكيد الطلب', preparing: 'بدء التحضير', ready: 'تجهيز الطلب', completed: 'إنهاء الطلب' };
const fulfillmentLabels: Record<Order['fulfillment_type'], string> = { pickup: 'استلام من المطعم', delivery: 'توصيل', dine_in: 'داخل المطعم' };

export default function KDS({ ordersBaseUrl }: Props) {
  const [orders, setOrders] = useState<Order[]>([]);
  const [error, setError] = useState('');
  const [busyOrderId, setBusyOrderId] = useState<number | null>(null);

  async function load() {
    try {
      const res = await fetch(`${ordersBaseUrl}/kds`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('تعذر تحميل الطلبات. حدّث الصفحة أو سجّل الدخول مرة أخرى.');
      const data: unknown = await res.json();
      if (!Array.isArray(data)) throw new Error('استجابة الطلبات غير متوقعة.');
      setOrders(data as Order[]);
      setError('');
    } catch {
      setOrders([]);
      setError('تعذر تحميل طلبات المطبخ. تحقق من الاتصال وصلاحية الدخول ثم أعد المحاولة.');
    }
  }

  useEffect(() => {
    load();
    const t = setInterval(load, 5000);
    return () => clearInterval(t);
  }, []);

  async function advance(id: number, next: string) {
    setBusyOrderId(id);
    try {
      const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
      const res = await fetch(`${ordersBaseUrl}/${id}/status`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({ status: next }),
      });
      if (!res.ok) throw new Error('تعذر تحديث حالة الطلب.');
      await load();
    } catch {
      setError('لم يتم تحديث حالة الطلب. حاول مرة أخرى وتأكد من اتصالك وصلاحياتك.');
    } finally {
      setBusyOrderId(null);
    }
  }

  return (
    <>
      <Head title="Kitchen Display System" />
      <div dir="rtl" className="min-h-screen bg-gray-900 p-4 text-white sm:p-6">
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
          <div><p className="text-xs font-bold tracking-widest text-emerald-300">NEXORA QR · OPERATIONS</p><h1 className="mt-1 text-2xl font-black sm:text-3xl">شاشة المطبخ</h1><p className="mt-1 text-sm text-slate-300">تابع الأصناف والإضافات وحدّث حالة الطلب أثناء التجهيز.</p></div>
          <a href={ordersBaseUrl} className="rounded-xl border border-white/20 px-4 py-2 text-sm font-bold transition hover:bg-white/10">العودة للطلبات</a>
        </div>
        {error && <div role="alert" className="mb-4 rounded-xl bg-red-100 p-3 text-red-800">{error}</div>}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          {COLUMNS.map((col) => (
            <div key={col.key} className={`${col.color} rounded-2xl p-3`}>
              <h2 className="font-bold text-gray-800 mb-3">{col.label}</h2>
              <div className="space-y-3">
                {orders.filter((o) => o.status === col.key).map((o) => (
                  <div key={o.id} className="bg-white rounded-xl p-3 text-gray-800 shadow">
                    <div className="flex justify-between items-center mb-1">
                      <span className="font-bold">#{o.order_number}</span>
                      <span className="text-xs text-gray-500">{o.items_count} وحدة</span>
                    </div>
                    <p className="mb-1 text-sm font-bold">{o.customer_name}</p>
                    <div className="mb-3 text-xs text-gray-500">{fulfillmentLabels[o.fulfillment_type]}{o.fulfillment_type === 'dine_in' && o.restaurant_table_name ? ` · ${o.restaurant_table_name}` : ''}</div>
                    <div className="space-y-2 border-y border-slate-100 py-3">
                      {o.items.length === 0 && <p className="text-xs text-rose-700">لا توجد تفاصيل أصناف مسجلة للطلب.</p>}
                      {o.items.map((item) => (
                        <div key={item.id} className="rounded-lg bg-slate-50 p-2">
                          <div className="flex items-start justify-between gap-2"><span className="font-bold">{item.product_name}</span><span className="shrink-0 rounded-md bg-slate-200 px-2 py-0.5 text-xs font-black">× {item.quantity}</span></div>
                          {item.variant_name && <p className="mt-1 text-xs text-slate-600">الحجم: {item.variant_name}</p>}
                          {item.modifiers.length > 0 && <p className="mt-1 text-xs text-slate-600">الإضافات: {item.modifiers.join('، ')}</p>}
                        </div>
                      ))}
                    </div>
                    {o.notes && <p className="mt-3 rounded-lg bg-amber-50 p-2 text-xs leading-5 text-amber-900"><strong>ملاحظات:</strong> {o.notes}</p>}
                    <p className="mt-2 text-[11px] text-slate-400">{new Date(o.created_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })}</p>
                    <button
                      onClick={() => advance(o.id, nextStatus[o.status])}
                      disabled={busyOrderId === o.id}
                      className="w-full py-1.5 rounded-lg bg-gray-800 text-white text-sm hover:bg-gray-700 disabled:opacity-50"
                    >
                      {busyOrderId === o.id ? 'جارٍ التحديث…' : `← ${nextStatusLabels[nextStatus[o.status]] ?? nextStatus[o.status]}`}
                    </button>
                  </div>
                ))}
                {!orders.some((order) => order.status === col.key) && <p className="rounded-lg border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">لا توجد طلبات في هذه المرحلة.</p>}
              </div>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}
