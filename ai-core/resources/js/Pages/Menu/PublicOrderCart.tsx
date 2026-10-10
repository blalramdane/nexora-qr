import { FormEvent, useEffect, useMemo, useState } from 'react';

type CartModifier = { id: number; name: string; price: number };
type CartItem = { productId: number; name: string; price: number; variantId: number | null; variantName?: string; modifiers: CartModifier[]; quantity: number };
type Props = { restaurantSlug: string; menuSlug: string; accent?: string; primary?: string; deliveryFee?: number | string; deliveryAreas?: string[]; deliveryEnabled?: boolean; pickupEnabled?: boolean };
type CartEvent = { productId: number; name: string; price: number; variantId?: number | null; variantName?: string; modifiers?: CartModifier[]; quantity?: number };

const money = (value: number) => `${value.toFixed(2)} ج.م`;

export default function PublicOrderCart({ restaurantSlug, menuSlug, accent = '#f59e0b', primary = '#111827', deliveryFee = 0, deliveryAreas = [], deliveryEnabled = true, pickupEnabled = true }: Props) {
    const [items, setItems] = useState<CartItem[]>([]);
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [submittedOrder, setSubmittedOrder] = useState<{ number: string; total: number; tracking_url: string } | null>(null);
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [notes, setNotes] = useState('');
    const [fulfillmentType, setFulfillmentType] = useState<'pickup' | 'delivery'>(deliveryEnabled ? 'delivery' : 'pickup');
    const [deliveryAddress, setDeliveryAddress] = useState('');
    const [deliveryArea, setDeliveryArea] = useState('');
    const [couponCode, setCouponCode] = useState('');
    const [appliedCoupon, setAppliedCoupon] = useState<{ code: string; discount_amount: number } | null>(null);
    const [checkingCoupon, setCheckingCoupon] = useState(false);
    const [couponMessage, setCouponMessage] = useState('');
    const total = useMemo(() => items.reduce((sum, item) => sum + item.price * item.quantity, 0), [items]);
    const effectiveDeliveryFee = fulfillmentType === 'delivery' ? Number(deliveryFee) || 0 : 0;
    const estimatedTotal = Math.max(0, total + effectiveDeliveryFee - (appliedCoupon?.discount_amount ?? 0));
    const count = items.reduce((sum, item) => sum + item.quantity, 0);

    useEffect(() => {
        const add = (event: Event) => {
            const detail = (event as CustomEvent<CartEvent>).detail;
            if (!detail?.productId) return;
            setSuccess(''); setError(''); setSubmittedOrder(null); setAppliedCoupon(null); setCouponMessage('');
            setItems(current => {
                const modifierKey = (detail.modifiers ?? []).map(modifier => modifier.id).sort((a, b) => a - b).join(',');
                const existing = current.find(item => item.productId === detail.productId && item.variantId === (detail.variantId ?? null) && item.modifiers.map(modifier => modifier.id).sort((a, b) => a - b).join(',') === modifierKey);
                if (existing) return current.map(item => item === existing ? { ...item, quantity: Math.min(30, item.quantity + (detail.quantity ?? 1)) } : item);
                return [...current, { productId: detail.productId, name: detail.name, price: detail.price, variantId: detail.variantId ?? null, variantName: detail.variantName, modifiers: detail.modifiers ?? [], quantity: Math.min(30, Math.max(1, detail.quantity ?? 1)) }];
            });
        };
        const show = () => setOpen(true);
        window.addEventListener('nexora:add-to-cart', add);
        window.addEventListener('nexora:open-cart', show);
        return () => { window.removeEventListener('nexora:add-to-cart', add); window.removeEventListener('nexora:open-cart', show); };
    }, []);

    const changeQuantity = (index: number, amount: number) => { setAppliedCoupon(null); setCouponMessage('أعد تطبيق الكوبون بعد تغيير الكميات.'); setItems(current => current.map((item, i) => i === index ? { ...item, quantity: Math.min(30, item.quantity + amount) } : item).filter(item => item.quantity > 0)); };

    const submit = async (event: FormEvent) => {
        event.preventDefault(); setError(''); setSuccess(''); setSubmittedOrder(null);
        if (!items.length) { setError('ضيف منتج واحد على الأقل للطلب.'); return; }
        setSubmitting(true);
        try {
            // Laravel's web routes validate the session CSRF token. Send the
            // token rendered with this exact page and keep the same session cookie.
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch(`/r/${encodeURIComponent(restaurantSlug)}/menu/${encodeURIComponent(menuSlug)}/orders`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ customer_name: customerName, customer_phone: customerPhone, fulfillment_type: fulfillmentType, delivery_address: fulfillmentType === 'delivery' ? deliveryAddress : null, delivery_area: fulfillmentType === 'delivery' ? deliveryArea : null, coupon_code: appliedCoupon?.code ?? null, notes, items: items.map(item => ({ product_id: item.productId, variant_id: item.variantId, modifier_ids: item.modifiers.map(modifier => modifier.id), quantity: item.quantity })) }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors ?? {}).flat().join(' ') || 'تعذر إرسال الطلب، راجع البيانات وحاول تاني.');
            setSubmittedOrder({ number: String(result.order.number), total: Number(result.order.total), tracking_url: String(result.order.tracking_url) });
            setSuccess(`تم إرسال الطلب ${result.order.number} بنجاح. إجمالي الطلب ${money(Number(result.order.total))}`);
            setItems([]); setCustomerName(''); setCustomerPhone(''); setNotes(''); setDeliveryAddress(''); setDeliveryArea(''); setCouponCode(''); setAppliedCoupon(null); setCouponMessage('');
        } catch (cause) { setError(cause instanceof Error ? cause.message : 'حصل خطأ أثناء إرسال الطلب.'); }
        finally { setSubmitting(false); }
    };

    const applyCoupon = async () => {
        if (!couponCode.trim()) { setCouponMessage('اكتب كود الخصم الأول.'); return; }
        setCheckingCoupon(true); setCouponMessage('');
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch(`/r/${encodeURIComponent(restaurantSlug)}/menu/${encodeURIComponent(menuSlug)}/coupon-check`, {
                method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ coupon_code: couponCode.trim(), subtotal: total }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'الكود غير صالح.');
            setAppliedCoupon({ code: result.code, discount_amount: Number(result.discount_amount) });
            setCouponCode(result.code); setCouponMessage(`تم تطبيق الخصم: ${money(Number(result.discount_amount))}`);
        } catch (cause) { setAppliedCoupon(null); setCouponMessage(cause instanceof Error ? cause.message : 'تعذر التحقق من الكوبون.'); }
        finally { setCheckingCoupon(false); }
    };

    return <>
        {!open && <button type="button" onClick={() => setOpen(true)} className="fixed bottom-4 left-4 right-4 z-[60] mx-auto flex max-w-lg items-center justify-between rounded-2xl px-5 py-4 text-sm font-black text-white shadow-2xl" style={{ backgroundColor: primary }}><span>🛒 السلة · {count} قطعة</span><span>{money(total)}　عرض الطلب</span></button>}
        {open && <div dir="rtl" className="fixed inset-0 z-[100] flex items-end justify-center bg-black/70 p-0 backdrop-blur-sm sm:items-center sm:p-4" onClick={() => setOpen(false)}>
            <section role="dialog" aria-modal="true" aria-label="تأكيد الطلب" className="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-5 text-slate-950 shadow-2xl sm:rounded-3xl" onClick={event => event.stopPropagation()}>
                <div className="flex items-center justify-between"><div><p className="text-xs font-black tracking-widest" style={{ color: accent }}>NEXORA ORDER</p><h2 className="mt-1 text-2xl font-black">مراجعة الطلب</h2></div><button type="button" onClick={() => setOpen(false)} className="rounded-xl border px-3 py-2" aria-label="إغلاق">✕</button></div>
                {success && <div role="status" className="mt-4 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800"><p>{success}</p>{submittedOrder && <div className="mt-3 space-y-3 border-t border-emerald-200 pt-3"><div className="flex items-center justify-between gap-2"><span>رقم الطلب: {submittedOrder.number}</span><span className="text-base">{money(submittedOrder.total)}</span></div><a href={submittedOrder.tracking_url} className="block rounded-xl bg-emerald-700 px-4 py-3 text-center font-black text-white">تابع حالة طلبك مباشرة</a><button type="button" onClick={() => navigator.clipboard.writeText(submittedOrder.tracking_url)} className="w-full rounded-xl border border-emerald-300 px-4 py-2 text-xs font-bold">نسخ رابط المتابعة</button></div>}</div>}
                {error && <div role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{error}</div>}
                <div className="mt-4 space-y-2">{items.map((item, index) => <div key={`${item.productId}-${item.variantId ?? 'base'}-${item.modifiers.map(modifier => modifier.id).sort((a,b)=>a-b).join('-')}`} className="flex items-center gap-3 rounded-xl border p-3"><div className="min-w-0 flex-1"><p className="font-bold">{item.name}</p>{item.variantName && <p className="text-xs text-slate-500">{item.variantName}</p>}{item.modifiers.length > 0 && <p className="mt-1 text-xs text-slate-500">إضافات: {item.modifiers.map(modifier => modifier.name).join('، ')}</p>}<p className="mt-1 text-sm">{money(item.price * item.quantity)}</p></div><div className="flex items-center gap-2"><button type="button" onClick={() => changeQuantity(index, -1)} className="grid h-8 w-8 place-items-center rounded-lg border" aria-label="تقليل الكمية">−</button><span className="min-w-4 text-center">{item.quantity}</span><button type="button" disabled={item.quantity >= 30} onClick={() => changeQuantity(index, 1)} className="grid h-8 w-8 place-items-center rounded-lg border disabled:cursor-not-allowed disabled:opacity-30" aria-label="زيادة الكمية">+</button></div></div>)}{!items.length && !success && <p className="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">السلة فارغة. اقفل النافذة واختار منتجات من المنيو.</p>}</div>
                {!(success && items.length === 0) && <><div className="mt-4 rounded-xl border border-dashed p-3"><label className="block text-sm font-bold">كود الخصم أو العرض</label><div className="mt-2 flex gap-2"><input value={couponCode} onChange={event => { setCouponCode(event.target.value.toUpperCase()); setAppliedCoupon(null); setCouponMessage(''); }} maxLength={40} placeholder="مثال WELCOME10" className="min-w-0 flex-1 rounded-lg border px-3 py-2.5 font-mono uppercase"/><button type="button" disabled={checkingCoupon || !items.length} onClick={applyCoupon} className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50">{checkingCoupon ? 'تحقق…' : 'تطبيق'}</button></div>{couponMessage && <p role="status" className={`mt-2 text-xs font-bold ${appliedCoupon ? 'text-emerald-700' : 'text-rose-600'}`}>{couponMessage}</p>}</div><div className="my-4 space-y-2 border-t pt-4 text-sm"><div className="flex justify-between"><span>قيمة المنتجات</span><span>{money(total)}</span></div>{fulfillmentType === 'delivery' && <div className="flex justify-between"><span>مصاريف التوصيل</span><span>{money(effectiveDeliveryFee)}</span></div>}{appliedCoupon && <div className="flex justify-between font-bold text-emerald-700"><span>خصم {appliedCoupon.code}</span><span>− {money(appliedCoupon.discount_amount)}</span></div>}<div className="flex justify-between text-lg font-black"><span>الإجمالي التقديري</span><span>{money(estimatedTotal)}</span></div><p className="text-xs text-slate-500">السعر النهائي والتحقق من صلاحية الكوبون بيتموا على السيرفر عند إرسال الطلب.</p></div></>}
                {!(success && items.length === 0) && <form onSubmit={submit} className="space-y-3">
                    <fieldset className="space-y-2"><legend className="text-sm font-bold">طريقة الاستلام</legend><div className="grid grid-cols-2 gap-2">{pickupEnabled && <button type="button" onClick={() => setFulfillmentType('pickup')} className={`rounded-xl border p-3 text-sm font-bold ${fulfillmentType === 'pickup' ? 'border-amber-500 bg-amber-50' : ''}`}>استلام من المطعم</button>}{deliveryEnabled && <button type="button" onClick={() => setFulfillmentType('delivery')} className={`rounded-xl border p-3 text-sm font-bold ${fulfillmentType === 'delivery' ? 'border-amber-500 bg-amber-50' : ''}`}>توصيل</button>}</div></fieldset>
                    {fulfillmentType === 'delivery' && <>{deliveryAreas.length > 0 && <label className="block text-sm font-bold">منطقة التوصيل<select value={deliveryArea} onChange={e => setDeliveryArea(e.target.value)} required className="mt-1 w-full rounded-xl border bg-white px-3 py-3 font-normal"><option value="">اختار المنطقة</option>{deliveryAreas.map(area => <option key={area} value={area}>{area}</option>)}</select></label>}<label className="block text-sm font-bold">عنوان التوصيل<input value={deliveryAddress} onChange={e => setDeliveryAddress(e.target.value)} required maxLength={1000} className="mt-1 w-full rounded-xl border px-3 py-3 font-normal" placeholder="الشارع، رقم العمارة والشقة، علامة مميزة" /></label></>}
                    <label className="block text-sm font-bold">اسم العميل<input value={customerName} onChange={e => setCustomerName(e.target.value)} required maxLength={120} className="mt-1 w-full rounded-xl border px-3 py-3 font-normal" placeholder="الاسم" /></label>
                    <label className="block text-sm font-bold">رقم الموبايل<input value={customerPhone} onChange={e => setCustomerPhone(e.target.value)} required maxLength={30} type="tel" className="mt-1 w-full rounded-xl border px-3 py-3 font-normal" placeholder="01xxxxxxxxx" /></label>
                    <label className="block text-sm font-bold">ملاحظات (اختياري)<textarea value={notes} onChange={e => setNotes(e.target.value)} maxLength={1000} rows={2} className="mt-1 w-full rounded-xl border px-3 py-3 font-normal" placeholder="من غير بصل، تعليمات إضافية..." /></label>
                    <button disabled={submitting || !items.length || (!deliveryEnabled && !pickupEnabled)} type="submit" className="w-full rounded-xl px-4 py-4 font-black text-white disabled:opacity-50" style={{ backgroundColor: primary }}>{submitting ? 'جاري إرسال الطلب...' : 'تأكيد وإرسال الطلب'}</button><p className="text-center text-xs text-slate-500">هيتم تسجيل الطلب في لوحة المطعم بعد التأكيد.</p>
                </form>}
            </section>
        </div>}
    </>;
}
