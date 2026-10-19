import { Head } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type DemoItem = { id: string; name: string; category: string; description?: string | null; price: number | string; is_available: boolean; is_example?: boolean };
type DemoOrder = { id: number; order_number: string; customer_name: string; customer_phone: string; items: Array<{ product_id: string; name: string; quantity: number; unit_price: number; line_total: number }>; total: number; status: string; created_at: string };
type Props = {
    workspace: { id: number; name: string; city: string; category: string; menu: { name: string; style: string; items: DemoItem[]; all_items_are_examples?: boolean }; expires_at: string; orders: DemoOrder[] };
    accessMode: 'preview' | 'owner';
    endpoint: string;
};

const money = (amount: number) => new Intl.NumberFormat('ar-EG', { style: 'currency', currency: 'EGP', maximumFractionDigits: 2 }).format(amount);
const orderStatus: Record<string, string> = { new: 'جديد', confirmed: 'تم التأكيد', preparing: 'قيد التحضير', ready: 'جاهز', completed: 'مكتمل', cancelled: 'ملغي' };
const statusOptions = Object.entries(orderStatus);

export default function Experience({ workspace, accessMode, endpoint }: Props) {
    const [items, setItems] = useState<DemoItem[]>(workspace.menu.items ?? []);
    const [cart, setCart] = useState<Record<string, number>>({});
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [orders, setOrders] = useState<DemoOrder[]>(workspace.orders ?? []);
    const isOwner = accessMode === 'owner';
    const cartLines = useMemo(() => items.filter(item => cart[item.id] && cart[item.id] > 0).map(item => ({ ...item, quantity: cart[item.id], lineTotal: Number(item.price) * cart[item.id] })), [items, cart]);
    const cartTotal = cartLines.reduce((sum, item) => sum + item.lineTotal, 0);
    const groups = useMemo(() => [...new Set(items.map(item => item.category || 'الأصناف'))], [items]);

    const add = (item: DemoItem) => setCart(current => ({ ...current, [item.id]: Math.min(30, (current[item.id] ?? 0) + 1) }));
    const remove = (id: string) => setCart(current => { const next = { ...current }; if ((next[id] ?? 0) <= 1) delete next[id]; else next[id] -= 1; return next; });
    const csrfHeaders = () => {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        return { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}), 'X-Requested-With': 'XMLHttpRequest' };
    };

    const submitOrder = async (event: FormEvent) => {
        event.preventDefault(); setBusy(true); setError(''); setNotice('');
        try {
            const response = await fetch(`${endpoint}/orders`, { method: 'POST', credentials: 'same-origin', headers: csrfHeaders(), body: JSON.stringify({ customer_name: customerName, customer_phone: customerPhone, items: cartLines.map(line => ({ product_id: line.id, quantity: line.quantity })) }) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors ?? {}).flat().join(' ') || 'تعذر إنشاء الطلب التجريبي.');
            setNotice(`${result.notice} رقم الطلب: ${result.order.number} — الإجمالي ${money(Number(result.order.total))}`);
            setCart({}); setCustomerName(''); setCustomerPhone('');
        } catch (cause) { setError(cause instanceof Error ? cause.message : 'حصل خطأ أثناء الطلب.'); }
        finally { setBusy(false); }
    };

    const saveMenu = async () => {
        setBusy(true); setError(''); setNotice('');
        try {
            const response = await fetch(`${endpoint}/menu`, { method: 'PATCH', credentials: 'same-origin', headers: csrfHeaders(), body: JSON.stringify({ items: items.map(item => ({ id: item.id, name: item.name, category: item.category, description: item.description ?? '', price: item.price, is_available: Boolean(item.is_available) })) }) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || Object.values(result.errors ?? {}).flat().join(' ') || 'تعذر حفظ التعديلات.');
            setItems(result.menu.items); setNotice(result.message || 'اتحفظت التعديلات في المعاينة.');
        } catch (cause) { setError(cause instanceof Error ? cause.message : 'حصل خطأ أثناء الحفظ.'); }
        finally { setBusy(false); }
    };

    const updateStatus = async (orderId: number, status: string) => {
        setError(''); setNotice('');
        try {
            const response = await fetch(`${endpoint}/orders/${orderId}/status`, { method: 'PATCH', credentials: 'same-origin', headers: csrfHeaders(), body: JSON.stringify({ status }) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'تعذر تحديث حالة الطلب.');
            setOrders(current => current.map(order => order.id === orderId ? { ...order, status: result.status } : order));
            setNotice(result.message || 'تم تحديث الحالة التجريبية.');
        } catch (cause) { setError(cause instanceof Error ? cause.message : 'حصل خطأ.'); }
    };

    const requestLaunch = async () => {
        const text = `طلب مراجعة تشغيل NEXORA لـ${workspace.name}\nالمدينة: ${workspace.city}\nالتصنيف: ${workspace.category}\nالمنيو الحالي: ${items.length} أصناف\n\nالمطلوب مراجعة الأسعار والأصناف والصور وربط الطلبات قبل أي نشر. هذه المعاينة لا تنشئ طلب تشغيل ولا تنشر الموقع.`;
        try { await navigator.clipboard.writeText(text); setNotice('اتنسخ ملخص طلب مراجعة التشغيل. ابعته لفريق NEXORA بعد مراجعته؛ مفيش أي رسالة اتبعتت تلقائيًا.'); }
        catch { setNotice(text); }
    };

    return <main dir="rtl" className="min-h-screen bg-[#f5f3ee] text-slate-950">
        <Head title={`${isOwner ? 'لوحة صاحب المطعم' : 'معاينة المنيو'} — ${workspace.name}`}>
            <meta name="robots" content="noindex,nofollow" />
            <meta name="description" content="معاينة خاصة من NEXORA. طلبات التجربة محفوظة في Sandbox منفصل ولا تدخل التشغيل الفعلي." />
        </Head>
        <header className="relative overflow-hidden bg-[#111614] text-white">
            <div className="pointer-events-none absolute -left-20 -top-36 h-96 w-96 rounded-full bg-emerald-400/20 blur-3xl" />
            <div className="relative mx-auto max-w-7xl px-4 py-5 sm:px-7">
                <div className="flex items-center justify-between gap-4"><div className="flex items-center gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl border border-emerald-300/40 bg-emerald-300/10 text-lg font-black text-emerald-200">N</div><div><p className="text-xs font-black tracking-[.22em] text-emerald-200">NEXORA / PRIVATE DEMO</p><p className="mt-1 text-sm text-white/60">معاينة تفاعلية مخصصة</p></div></div><span className="rounded-full border border-amber-200/30 bg-amber-100/10 px-3 py-2 text-xs font-bold text-amber-100">Sandbox · لا يوجد تشغيل فعلي</span></div>
                <div className="grid gap-7 py-10 md:grid-cols-[1fr_.75fr] md:items-end md:py-14"><div><p className="mb-3 text-sm font-bold text-emerald-200">{workspace.city} · {workspace.category}</p><h1 className="max-w-3xl text-4xl font-black leading-tight sm:text-6xl">{workspace.name}<span className="block text-emerald-200">تجربة المنيو كما يراها عميلك.</span></h1><p className="mt-5 max-w-2xl text-base leading-8 text-white/65">مش مجرد صورة. جرّب رحلة العميل، أو افتح لوحة صاحب المطعم وشوف إزاي تعديل الأصناف ومتابعة الطلبات ممكن يكونوا أبسط.</p></div><div className="rounded-3xl border border-white/10 bg-white/[.06] p-5 backdrop-blur-sm"><p className="text-xs font-black tracking-widest text-white/50">DEMO STATUS</p><div className="mt-4 flex items-center justify-between gap-3"><div><p className="text-lg font-black">معاينة خاصة</p><p className="mt-1 text-sm text-white/60">صالحة حتى {new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(workspace.expires_at))}</p></div><span className="h-3 w-3 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(110,231,183,.8)]" /></div><p className="mt-4 border-t border-white/10 pt-4 text-xs leading-6 text-white/50">المحتوى التوضيحي والأسعار تحتاج تأكيد المطعم. الطلبات والتعديلات لا تؤثر على الحسابات الحقيقية.</p></div></div>
            </div>
        </header>

        <div className="mx-auto max-w-7xl px-4 py-7 sm:px-7 sm:py-10">
            {(error || notice) && <div role={error ? 'alert' : 'status'} className={`mb-5 whitespace-pre-wrap rounded-2xl border p-4 text-sm leading-7 ${error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`}>{error || notice}</div>}

            {isOwner ? <>
                <div className="mb-4 flex justify-end"><button type="button" onClick={() => window.location.reload()} className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">تحديث الطلبات وحالة المنيو</button></div>
                <section className="mb-7 grid gap-3 sm:grid-cols-3"><div className="rounded-3xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">أصناف المعاينة</p><p className="mt-2 text-3xl font-black">{items.length}</p></div><div className="rounded-3xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">طلبات تجريبية</p><p className="mt-2 text-3xl font-black">{orders.length}</p></div><div className="rounded-3xl border border-slate-200 bg-white p-5"><p className="text-sm text-slate-500">إجمالي قيمة الطلبات التجريبية</p><p className="mt-2 text-3xl font-black">{money(orders.filter(order => order.status !== 'cancelled').reduce((sum, order) => sum + Number(order.total), 0))}</p></div></section>
                <section className="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7"><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-xs font-black tracking-widest text-emerald-800">OWNER WORKSPACE</p><h2 className="mt-2 text-2xl font-black sm:text-3xl">إدارة المنيو التجريبي</h2><p className="mt-2 text-sm leading-7 text-slate-500">غيّر الاسم أو الفئة أو السعر وجرب إخفاء صنف. التعديلات محفوظة في الـSandbox فقط.</p></div><button disabled={busy} onClick={() => setItems(current => [...current, { id: `new-${crypto.randomUUID()}`, name: 'صنف جديد', category: 'أصناف جديدة', description: 'صنف مضاف في وضع المعاينة', price: 50, is_available: true, is_example: true }])} className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-black disabled:opacity-50">+ إضافة صنف</button></div>
                    <div className="mt-6 space-y-3">{items.map((item, index) => <div key={item.id} className="grid gap-3 rounded-2xl border border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-[1.2fr_1fr_140px_140px_auto] xl:items-end"><label className="text-xs font-bold text-slate-500">اسم الصنف<input value={item.name} onChange={event => setItems(current => current.map((row, i) => i === index ? { ...row, name: event.target.value } : row))} maxLength={120} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold text-slate-950" /></label><label className="text-xs font-bold text-slate-500">الفئة<input value={item.category} onChange={event => setItems(current => current.map((row, i) => i === index ? { ...row, category: event.target.value } : row))} maxLength={80} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold text-slate-950" /></label><label className="text-xs font-bold text-slate-500">السعر بالجنيه<input value={item.price} type="number" min="0" max="100000" step="0.01" onChange={event => setItems(current => current.map((row, i) => i === index ? { ...row, price: event.target.value } : row))} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold text-slate-950" /></label><label className="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-3 text-sm font-bold"><input type="checkbox" checked={item.is_available} onChange={event => setItems(current => current.map((row, i) => i === index ? { ...row, is_available: event.target.checked } : row))} /> متاح للعرض</label><button onClick={() => setItems(current => current.filter((_, i) => i !== index))} disabled={items.length <= 1} className="rounded-xl px-3 py-3 text-sm font-bold text-rose-700 disabled:opacity-30">حذف</button></div>)}</div>
                    <div className="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between"><p className="text-xs leading-6 text-slate-500">حفظ المنيو لا ينشر التعديلات ولا يغيّر المنيو الإنتاجي.</p><button disabled={busy || items.length === 0} onClick={saveMenu} className="rounded-xl bg-[#111614] px-6 py-4 font-black text-white disabled:opacity-40">{busy ? 'جاري الحفظ...' : 'حفظ تعديلات المعاينة'}</button></div>
                </section>
                <section className="mt-7 rounded-3xl border border-slate-200 bg-white p-5 sm:p-7"><div><p className="text-xs font-black tracking-widest text-emerald-800">SANDBOX ORDERS</p><h2 className="mt-2 text-2xl font-black sm:text-3xl">الطلبات التجريبية</h2><p className="mt-2 text-sm leading-7 text-slate-500">غيّر حالة الطلب وشوف مسار المتابعة. مفيش أي دفع أو إشعار واتساب أو حركة مخزون حقيقية.</p></div>{orders.length === 0 ? <div className="mt-5 rounded-2xl bg-slate-50 p-8 text-center"><p className="font-bold">لسه مفيش طلبات تجريبية.</p><p className="mt-2 text-sm text-slate-500">افتح رابط معاينة العميل في نافذة أخرى وأكمل طلبًا تجريبيًا.</p></div> : <div className="mt-5 space-y-3">{orders.map(order => <article key={order.id} className="rounded-2xl border border-slate-200 p-4"><div className="flex flex-col justify-between gap-3 md:flex-row md:items-start"><div><div className="flex flex-wrap items-center gap-2"><h3 className="font-black">{order.order_number}</h3><span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">تجريبي فقط</span></div><p className="mt-2 text-sm text-slate-600">{order.customer_name} · {order.customer_phone}</p><p className="mt-2 text-sm font-black">{money(Number(order.total))}</p><div className="mt-3 space-y-1 text-xs text-slate-500">{order.items.map((line, i) => <p key={`${line.product_id}-${i}`}>{line.name} × {line.quantity} — {money(Number(line.line_total))}</p>)}</div></div><div className="min-w-48"><label className="text-xs font-bold text-slate-500">حالة الطلب التجريبي<select value={order.status} onChange={event => updateStatus(order.id, event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-950">{statusOptions.map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label><p className="mt-2 text-xs text-slate-400">{new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.created_at))}</p></div></div></article>)}</div>}</section>
                <div className="mt-7 rounded-3xl bg-[#17221c] p-6 text-white sm:flex sm:items-center sm:justify-between"><div><h2 className="text-xl font-black">جاهز لمراجعة النسخة الفعلية؟</h2><p className="mt-2 max-w-2xl text-sm leading-7 text-white/60">انسخ ملخص مراجعة التشغيل لفريق NEXORA. مش هيتم نشر الموقع أو إرسال أي بيانات تلقائيًا.</p></div><button onClick={requestLaunch} className="mt-4 rounded-xl bg-emerald-200 px-5 py-3 font-black text-emerald-950 sm:mt-0">طلب مراجعة التشغيل</button></div>
            </> : <>
                <div className="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><p className="text-xs font-black tracking-widest text-emerald-800">CUSTOMER EXPERIENCE</p><h2 className="mt-2 text-2xl font-black sm:text-3xl">{workspace.menu.name}</h2><p className="mt-2 text-sm text-slate-500">اختار صنف واضفه للسلة وجرب تأكيد طلب وهمي.</p></div>{workspace.menu.all_items_are_examples && <span className="h-fit rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-900">أمثلة توضيحية · ليست قائمة المطعم المعتمدة</span>}</div>
                <div className="grid gap-7 lg:grid-cols-[1fr_340px]"><div className="space-y-8">{groups.map((group, groupIndex) => <section key={group}><div className="mb-4 flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-2xl bg-[#18231d] text-sm font-black text-emerald-200">{String(groupIndex + 1).padStart(2, '0')}</span><div><h3 className="text-xl font-black">{group}</h3><p className="mt-1 text-xs text-slate-500">اختيارات مصممة لتجربة سريعة وواضحة</p></div></div><div className="grid gap-3 sm:grid-cols-2">{items.filter(item => item.category === group).map((item, index) => <article key={item.id} className={`relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-lg ${!item.is_available ? 'opacity-55' : ''}`}><div className="absolute -left-8 -top-10 h-28 w-28 rounded-full bg-emerald-50" /><div className="relative"><div className="flex items-start justify-between gap-3"><div className="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-emerald-100 to-amber-50 text-xl">{['✦', '◈', '✳', '◇'][index % 4]}</div><p className="rounded-full bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500">{item.is_example ? 'مثال' : 'معتمد'}</p></div><h4 className="mt-5 text-lg font-black">{item.name}</h4><p className="mt-2 min-h-10 text-sm leading-6 text-slate-500">{item.description || 'تفاصيل الصنف تظهر هنا بعد اعتمادها من المطعم.'}</p><div className="mt-5 flex items-center justify-between gap-3"><p className="text-lg font-black text-emerald-900">{money(Number(item.price))}</p><button type="button" disabled={!item.is_available} onClick={() => add(item)} className="rounded-xl bg-[#18231d] px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-30">{(cart[item.id] ?? 0) ? `+ إضافة أخرى (${cart[item.id]})` : '+ أضف للسلة'}</button></div></div></article>)}</div></section>)}</div>
                    <aside className="h-fit rounded-3xl border border-slate-200 bg-white p-5 lg:sticky lg:top-5"><p className="text-xs font-black tracking-widest text-emerald-800">YOUR CART</p><h3 className="mt-2 text-xl font-black">سلة التجربة</h3><p className="mt-1 text-xs leading-6 text-slate-500">الطلب ده محاكاة داخل المعاينة فقط.</p><div className="mt-4 space-y-3">{cartLines.length === 0 ? <p className="rounded-2xl bg-slate-50 p-5 text-center text-sm text-slate-500">السلة فاضية — اختار صنف عشان تبدأ.</p> : cartLines.map(line => <div key={line.id} className="flex items-start justify-between gap-3 border-b border-slate-100 pb-3"><div className="min-w-0"><p className="font-bold">{line.name}</p><p className="mt-1 text-xs text-slate-500">{money(Number(line.price))} × {line.quantity}</p></div><div className="flex items-center gap-2"><button aria-label="تقليل الكمية" onClick={() => remove(line.id)} className="grid h-7 w-7 place-items-center rounded-lg border">−</button><button aria-label="زيادة الكمية" onClick={() => add(line)} className="grid h-7 w-7 place-items-center rounded-lg border">+</button></div></div>)}</div><div className="mt-5 flex justify-between border-t border-slate-100 pt-4 text-lg font-black"><span>الإجمالي</span><span>{money(cartTotal)}</span></div><form onSubmit={submitOrder} className="mt-5 space-y-3"><label className="block text-sm font-bold">اسم للتجربة<input required value={customerName} onChange={event => setCustomerName(event.target.value)} maxLength={120} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal" placeholder="اسم تجريبي" /></label><label className="block text-sm font-bold">رقم موبايل للتجربة<input required value={customerPhone} onChange={event => setCustomerPhone(event.target.value)} maxLength={30} type="tel" className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-normal" placeholder="01xxxxxxxxx" /></label><button disabled={busy || cartLines.length === 0} type="submit" className="w-full rounded-xl bg-emerald-800 px-4 py-4 font-black text-white disabled:opacity-40">{busy ? 'جاري تجهيز الطلب...' : 'تأكيد طلب تجريبي'}</button><p className="text-center text-[11px] leading-5 text-slate-400">لا يتم إرسال الطلب للمطعم أو واتساب. لا توجد دفعة مالية.</p></form></aside></div>
            </>}
            <footer className="mt-12 border-t border-slate-200 py-5 text-center text-xs leading-6 text-slate-500">NEXORA QR · بيئة تجربة معزولة · البيانات لا تُنقل إلى النظام الفعلي تلقائيًا.</footer>
        </div>
    </main>;
}
