import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type TrackOrder = {
    number: string;
    status: string;
    customerName: string;
    fulfillmentType: 'pickup' | 'delivery';
    deliveryAddress?: string | null;
    deliveryArea?: string | null;
    deliveryStatus?: string;
    deliveryRiderName?: string | null;
    deliveryPickedUpAt?: string | null;
    deliveredAt?: string | null;
    subtotal: string | number;
    discountAmount?: string | number;
    couponCode?: string | null;
    deliveryFee: string | number;
    total: string | number;
    createdAt: string;
    history: Array<{ status: string; createdAt: string }>;
    items: Array<{ name: string; variant?: string | null; modifiers?: Array<{ name: string; price?: number }>; quantity: number; total: string | number }>;
    review?: { rating: number; comment?: string | null; createdAt: string } | null;
};

const stages = [
    { key: 'new', label: 'تم استلام الطلب', description: 'وصل طلبك للمطعم وفي انتظار التأكيد.' },
    { key: 'confirmed', label: 'المطعم أكد الطلب', description: 'المطعم بدأ التعامل مع طلبك.' },
    { key: 'preparing', label: 'جاري التجهيز', description: 'طلبك بيتجهز دلوقتي.' },
    { key: 'ready', label: 'الطلب جاهز', description: 'طلبك جاهز للاستلام أو التسليم.' },
    { key: 'completed', label: 'تم إكمال الطلب', description: 'نتمنى لك وجبة سعيدة!' },
];
const money = (value: string | number) => `${Number(value).toFixed(2)} ج.م`;
const statusLabels: Record<string, string> = { new: 'تم استلام الطلب', confirmed: 'تم التأكيد', preparing: 'قيد التجهيز', ready: 'جاهز', completed: 'مكتمل', cancelled: 'ملغي' };
const deliveryLabels: Record<string, string> = { unassigned: 'المطعم بيجهز التوصيل', assigned: 'تم تعيين مندوب', picked_up: 'المندوب استلم الطلب وفي الطريق', delivered: 'تم توصيل الطلب', failed: 'المطعم بيتابع مشكلة التوصيل' };

export default function Track({ restaurant, order }: { restaurant: { name: string; slug: string }; order: TrackOrder }) {
    const [lastUpdated, setLastUpdated] = useState(() => new Date());
    const { url } = usePage();
    const reviewForm = useForm({ rating: 5, comment: '' });
    const tokenMatch = url.match(/\/track\/([^/?]+)/);
    const token = tokenMatch?.[1] ?? '';
    const submitReview = (event: React.FormEvent) => {
        event.preventDefault();
        reviewForm.post(`/r/${encodeURIComponent(restaurant.slug)}/orders/${encodeURIComponent(order.number)}/track/${encodeURIComponent(token)}/review`, { preserveScroll: true, onSuccess: () => router.reload({ only: ['order'] }) });
    };
    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState !== 'visible' || ['completed', 'cancelled'].includes(order.status)) return;
            router.reload({ only: ['order'], onSuccess: () => setLastUpdated(new Date()) });
        };
        const timer = window.setInterval(refresh, 15000);
        return () => window.clearInterval(timer);
    }, [order.status]);

    const cancelled = order.status === 'cancelled';
    const activeIndex = stages.findIndex(stage => stage.key === order.status);

    return <main dir="rtl" className="min-h-screen bg-[#f5f5f3] px-4 py-8 text-slate-950 sm:py-12">
        <Head title={`متابعة الطلب ${order.number}`} />
        <div className="mx-auto max-w-2xl">
            <header className="mb-5 rounded-[2rem] bg-[#111827] p-6 text-white shadow-xl sm:p-8">
                <p className="text-xs font-black tracking-[.2em] text-amber-300">NEXORA ORDER TRACKING</p>
                <p className="mt-4 text-sm text-white/65">{restaurant.name}</p>
                <h1 className="mt-1 text-3xl font-black sm:text-4xl">متابعة طلبك</h1>
                <div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div><p className="text-xs text-white/60">رقم الطلب</p><p className="mt-1 text-xl font-black">{order.number}</p></div>
                    <span className="rounded-full bg-amber-300 px-4 py-2 text-sm font-black text-slate-950">{statusLabels[order.status] ?? order.status}</span>
                </div>
            </header>

            {cancelled ? <section role="status" className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><h2 className="text-xl font-black">تم إلغاء الطلب</h2><p className="mt-2 text-sm">تواصل مع المطعم لو محتاج تعرف تفاصيل إضافية.</p></section> : <section className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 className="text-lg font-black">حالة الطلب</h2>
                <div className="mt-6 space-y-5">
                    {stages.map((stage, index) => {
                        const done = order.status === 'completed' || (activeIndex >= 0 && index <= activeIndex);
                        const current = stage.key === order.status;
                        return <div key={stage.key} className="flex gap-3">
                            <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-black ${done ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400'}`}>{done ? '✓' : index + 1}</div>
                            <div className="flex-1 border-b border-slate-100 pb-4"><div className="flex flex-wrap items-center gap-2"><h3 className={`font-black ${current ? 'text-emerald-700' : ''}`}>{stage.label}</h3>{current && <span className="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black text-emerald-700">الحالة الحالية</span>}</div><p className="mt-1 text-sm text-slate-500">{stage.description}</p>{order.history.find(event => event.status === stage.key) && <p className="mt-2 text-xs font-bold text-slate-400">{new Date(order.history.find(event => event.status === stage.key)!.createdAt).toLocaleString('ar-EG')}</p>}</div>
                        </div>;
                    })}
                </div>
            </section>}

            <section className="mt-5 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div className="flex items-center justify-between gap-3"><h2 className="text-lg font-black">تفاصيل الطلب</h2><span className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold">{order.fulfillmentType === 'delivery' ? 'توصيل' : 'استلام من المطعم'}</span></div>
                {order.fulfillmentType === 'delivery' && <div className="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4"><p className="font-black text-emerald-900">{deliveryLabels[order.deliveryStatus ?? 'unassigned'] ?? 'جارٍ تحديث التوصيل'}</p>{order.deliveryRiderName && <p className="mt-1 text-sm text-emerald-800">مندوب التوصيل: {order.deliveryRiderName}</p>}{order.deliveryPickedUpAt && <p className="mt-1 text-xs text-emerald-800">وقت الاستلام: {new Date(order.deliveryPickedUpAt).toLocaleString('ar-EG')}</p>}{order.deliveredAt && <p className="mt-1 text-xs text-emerald-800">وقت التسليم: {new Date(order.deliveredAt).toLocaleString('ar-EG')}</p>}</div>}
                <div className="mt-4 divide-y divide-slate-100">{order.items.map((item, index) => <div key={`${item.name}-${index}`} className="flex justify-between gap-3 py-3 text-sm"><div><p className="font-bold">{item.name}{item.variant ? ` — ${item.variant}` : ''} × {item.quantity}</p>{item.modifiers?.length ? <p className="mt-1 text-xs text-slate-500">{item.modifiers.map(modifier => modifier.name).join('، ')}</p> : null}</div><strong>{money(item.total)}</strong></div>)}</div>
                {order.fulfillmentType === 'delivery' && order.deliveryAddress && <p className="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><strong>عنوان التوصيل:</strong> {order.deliveryArea ? `${order.deliveryArea} — ` : ''}{order.deliveryAddress}</p>}
                <div className="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm"><div className="flex justify-between"><span className="text-slate-500">المنتجات</span><span>{money(order.subtotal)}</span></div>{Number(order.discountAmount ?? 0) > 0 && <div className="flex justify-between font-bold text-emerald-700"><span>خصم {order.couponCode}</span><span>− {money(order.discountAmount ?? 0)}</span></div>}{Number(order.deliveryFee) > 0 && <div className="flex justify-between"><span className="text-slate-500">التوصيل</span><span>{money(order.deliveryFee)}</span></div>}<div className="flex justify-between text-lg font-black"><span>الإجمالي</span><span>{money(order.total)}</span></div></div>
            </section>
            {order.status === 'completed' && <section className="mt-5 rounded-[2rem] border border-amber-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 className="text-lg font-black">تقييم تجربتك</h2>
                {order.review ? <div role="status" className="mt-4 rounded-xl bg-emerald-50 p-4"><p className="font-black text-emerald-800">شكراً لتقييمك · {'★'.repeat(order.review.rating)}{'☆'.repeat(5 - order.review.rating)}</p>{order.review.comment && <p className="mt-2 text-sm text-emerald-900">{order.review.comment}</p>}</div> : <form onSubmit={submitReview} className="mt-4 space-y-4">
                    <p className="text-sm text-slate-500">رأيك بيساعد المطعم يحسّن الخدمة. التقييم متاح مرة واحدة للطلب المكتمل.</p>
                    <fieldset><legend className="mb-2 text-sm font-bold">تقييمك من 1 إلى 5</legend><div className="flex gap-2" dir="ltr">{[1,2,3,4,5].map(value => <button key={value} type="button" aria-label={`${value} من 5`} aria-pressed={reviewForm.data.rating === value} onClick={() => reviewForm.setData('rating', value)} className={`rounded-xl border px-3 py-2 text-xl ${reviewForm.data.rating >= value ? 'border-amber-300 bg-amber-50 text-amber-500' : 'border-slate-200 text-slate-300'}`}>★</button>)}</div></fieldset>
                    <label className="block text-sm font-bold">تعليق (اختياري)<textarea value={reviewForm.data.comment} onChange={event => reviewForm.setData('comment', event.target.value)} maxLength={1000} rows={3} className="mt-2 w-full rounded-xl border border-slate-200 p-3 font-normal" placeholder="إيه أكتر حاجة عجبتك أو محتاجة تتحسن؟" /></label>
                    {reviewForm.errors.rating && <p role="alert" className="text-sm text-rose-600">{reviewForm.errors.rating}</p>}{reviewForm.errors.comment && <p role="alert" className="text-sm text-rose-600">{reviewForm.errors.comment}</p>}
                    <button disabled={reviewForm.processing} type="submit" className="w-full rounded-xl bg-slate-900 px-4 py-3 font-black text-white disabled:opacity-50">{reviewForm.processing ? 'جارٍ إرسال التقييم…' : 'إرسال التقييم'}</button>
                </form>}
            </section>}
            <p className="mt-4 text-center text-xs text-slate-500">آخر تحديث {lastUpdated.toLocaleTimeString('ar-EG')} · يتم تحديث الحالة تلقائيًا كل 15 ثانية أثناء فتح الصفحة.</p>
        </div>
    </main>;
}
